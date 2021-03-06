<?php

namespace SPSS\Sav;

use SPSS\Buffer;
use SPSS\Exception;
use SPSS\Sav\Record\Info;
use SPSS\Utils;

class Writer
{
    /**
     * @var Record\Header
     */
    public $header;

    /**
     * @var Record\Variable[]
     */
    public $variables = [];

    /**
     * @var Record\ValueLabel[]
     */
    public $valueLabels = [];

    /**
     * @var Record\Document
     */
    public $document;

    /**
     * @var InfoRecordSet
     */
    private $info = [];

    /**
     * @var Record\Data
     */
    public $data;

    /**
     * @var Buffer
     */
    protected $buffer;

    /**
     * Writer constructor.
     *
     * @param array $data
     * @throws \Exception
     */
    public function __construct($data = [])
    {
        $this->buffer = Buffer::factory();
        $this->buffer->context = $this;

        $this->info = new InfoRecordSet();
        if (! empty($data)) {
            $this->write($data);
        }
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    public function write($data)
    {
        $this->header = new Record\Header($data['header']);
        $this->header->nominalCaseSize = 0;
        $this->header->casesCount = 0;

        $this->info->set($this->prepareInfoRecord(
            Record\Info\MachineInteger::class,
            $data
        ));

        $this->info->set($this->prepareInfoRecord(
            Record\Info\MachineFloatingPoint::class,
            $data
        ));

        $this->info->set(new Record\Info\VariableDisplayParam());
        $this->info->set(new Record\Info\LongVariableNames());
        $this->info->set(new Record\Info\VeryLongString());
        $this->info->set($this->prepareInfoRecord(
            Record\Info\ExtendedNumberOfCases::class,
            $data
        ));
        $this->info->set(new Record\Info\VariableAttributes());
        $this->info->set(new Record\Info\LongStringValueLabels());
        $this->info->set(new Record\Info\LongStringMissingValues());
        $this->info->set(new Record\Info\CharacterEncoding('UTF-8'));

        $this->data = new Record\Data();

        $nominalIdx = 0;

        /**
         * @var bool[string] The variable names used in this SPSS file
         */
        $variableNames = [];
        /** @var Variable $var */
        foreach (array_values($data['variables']) as $idx => $var) {
            if (!$var instanceof Variable) {
                throw new \InvalidArgumentException('Variables must be instance of ' . Variable::class);
            }

            $variable = new Record\Variable();

            /**
             * @see \SPSS\Sav\Record\Variable::getSegmentName()
             *
             * Variable names in the SPSS file should be unique. If they are not, SPSS will rename them.
             * If SPSS renames them and it happens to be a long string then the segments will no longer share
             * the required prefix in the name.
             */
            $name = strtoupper(substr($var->getName(), 0, 8));

            $counter = 0;
            /**
             * Using base convert we can encode 36^3 = 46656 variables with a common 5 character prefix in an 8
             * character variable name. This should suffice since the current variable limit of SPSS is 32767
             * variables.
             */
            while (isset($variableNames[$name])) {
                $name = strtoupper(substr($var->getName(), 0, 5) . base_convert($counter, 10, 36));
                $counter++;
            }

            $variableNames[$name] = true;
            $variable->name = $name;

            if ($var->format == Variable::FORMAT_TYPE_A) {
                $variable->width = $var->getWidth();
            } else {
                $variable->width = 0;
            }

            $variable->label = $var->label;
            $variable->print = [
                0,
                $var->format,
                min($var->getWidth(), 255),
                $var->decimals,
            ];
            $variable->write = [
                0,
                $var->format,
                min($var->getWidth(), 255),
                $var->decimals,
            ];

            // TODO: refactory
            $shortName = $variable->name;
            $longName = $var->getName();

            if ($var->attributes) {
                $this->info[Record\Info\VariableAttributes::SUBTYPE][$longName] = $var->attributes;
            }

            if ($var->missing) {
                if ($var->getWidth() <= 8) {
                    if (count($var->missing) >= 3) {
                        $variable->missingValuesFormat = 3;
                    } elseif (count($var->missing) == 2) {
                        $variable->missingValuesFormat = -2;
                    } else {
                        $variable->missingValuesFormat = 1;
                    }
                    $variable->missingValues = $var->missing;
                } else {
                    $this->info[Record\Info\LongStringMissingValues::SUBTYPE][$shortName] = $var->missing;
                }
            }

            $this->variables[$idx] = $variable;

            if ($var->values) {
                if ($variable->width > 8) {
                    $this->info[Record\Info\LongStringValueLabels::SUBTYPE][$longName] = [
                        'width' => $var->getWidth(),
                        'values' => $var->values,
                    ];
                } else {
                    $valueLabel = new Record\ValueLabel([
                        'variables' => $this->variables,
                    ]);
                    foreach ($var->values as $key => $value) {
                        $valueLabel->labels[] = [
                            'value' => $key,
                            'label' => $value,
                        ];
                        $valueLabel->indexes = [$nominalIdx + 1];
                    }
                    $this->valueLabels[] = $valueLabel;
                }
            }

            $this->info[Record\Info\LongVariableNames::SUBTYPE][$shortName] = $var->getName();

            if (Record\Variable::isVeryLong($var->getWidth())) {
                $this->info[Record\Info\VeryLongString::SUBTYPE][$shortName] = $var->getWidth();
            }

            $segmentCount = Utils::widthToSegments($var->getWidth());
            for ($i = 0; $i < $segmentCount; $i++) {
                $this->info[Record\Info\VariableDisplayParam::SUBTYPE][] = [
                    $var->getMeasure(),
                    $var->getColumns(),
                    $var->getAlignment(),
                ];
            }

            // TODO: refactory
            $dataCount = count($var->data);
            if ($dataCount > $this->header->casesCount) {
                $this->header->casesCount = $dataCount;
            }

            foreach ($var->data as $case => $value) {
                $this->data->matrix[$case][$idx] = $value;
            }

            $nominalIdx += $var->getOcts();
        }

        $this->header->nominalCaseSize = $nominalIdx;

        // write header
        $this->header->write($this->buffer);

        // write variables
        foreach ($this->variables as $variable) {
            $variable->write($this->buffer);
        }

        // write valueLabels
        foreach ($this->valueLabels as $valueLabel) {
            $valueLabel->write($this->buffer);
        }

        // write documents
        if (! empty($data['documents'])) {
            $this->document = new Record\Document([
                    'lines' => $data['documents'],
                ]
            );
            $this->document->write($this->buffer);
        }

        foreach ($this->info as $info) {
            $info->write($this->buffer);
        }

        $this->data->write($this->buffer);
    }

    /**
     * @param $file
     * @return false|int
     */
    public function save($file)
    {
        return $this->buffer->saveToFile($file);
    }

    /**
     * @return \SPSS\Buffer
     */
    public function getBuffer()
    {
        return $this->buffer;
    }

    /**
     * @param string $className
     * @param array $data
     * @param string $group
     * @return array
     * @throws Exception
     */
    private function prepareInfoRecord($className, $data, $group = 'info'): Info
    {
        if (! class_exists($className)) {
            throw new Exception('Unknown class');
        }
        $key = lcfirst(substr($className, strrpos($className, '\\') + 1));

        return new $className(
            isset($data[$group]) && isset($data[$group][$key]) ?
                $data[$group][$key] :
                []
        );
    }
}
