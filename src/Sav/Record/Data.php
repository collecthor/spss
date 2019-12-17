<?php

namespace SPSS\Sav\Record;

use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use SebastianBergmann\CodeCoverage\Util;
use SPSS\Buffer;
use SPSS\ByteCodeReader;
use SPSS\ByteCodeWriter;
use SPSS\Exception;
use SPSS\Sav\Record;
use SPSS\Utils;

class Data extends Record
{
    const TYPE = 999;
    /**
     * @var array [case_index][var_index]
     */
    public $matrix = [];

    /**
     * @param Buffer $buffer
     * @throws Exception
     */
    public function read(Buffer $buffer)
    {

        if ($buffer->readInt() != 0) {
            throw new \InvalidArgumentException('Error reading data record. Non-zero value found.');
        }
        if (! isset($buffer->context->variables)) {
            throw new \InvalidArgumentException('Variables required');
        }
        if (! isset($buffer->context->header)) {
            throw new \InvalidArgumentException('Header required');
        }
        if (! isset($buffer->context->info)) {
            throw new \InvalidArgumentException('Info required');
        }

        $compressed = $buffer->context->header->compression;
        $bias = $buffer->context->header->bias;
        $casesCount = $buffer->context->header->casesCount;

        /** @var Record\Info[] $info */
        $info = $buffer->context->info;


        if (isset($info[Record\Info\VeryLongString::SUBTYPE])) {
            $veryLongStrings = $info[Record\Info\VeryLongString::SUBTYPE]->toArray();
        } else {
            $veryLongStrings = [];
        }

        /** @var Variable[] $variables */
        $variables = $buffer->context->variables;

        if (isset($info[Record\Info\MachineFloatingPoint::SUBTYPE])) {
            $sysmis = $info[Record\Info\MachineFloatingPoint::SUBTYPE]->sysmis;
        } else {
            $sysmis = NAN;
        }

        $reader = new ByteCodeReader($buffer);
        for ($case = 0; $case < $casesCount; $case++) {
            $varNum = 0;

            for($index = 0; $index < count($variables); $index++) {
                $var = $variables[$index];
                $isNumeric = \SPSS\Sav\Variable::isNumberFormat($var->write[1]);
                if ($isNumeric) {
                    $data = $reader->read(8);
                    $this->matrix[$case][$varNum] = unpack('d', $data)[1];
                } else {
                    $value = '';
                    foreach(Utils::getSegments($veryLongStrings[$var->name] ?? $var->width) as $segmentWidth) {
                        $segment = $reader->read($segmentWidth + 8 - ($segmentWidth % 8));
                        $value .= rtrim($segment);
                        $index++;
                    }
                    $index--;
                    $this->matrix[$case][$varNum] = $value;
                }
                $varNum++;
            }

        }
    }

    /**
     * @param Buffer $buffer
     */
    public function write(Buffer $buffer)
    {
        $buffer->writeInt(self::TYPE);
        $buffer->writeInt(0);


        if (! isset($buffer->context->variables)) {
            throw new \InvalidArgumentException('Variables required');
        }
        if (! isset($buffer->context->header)) {
            throw new \InvalidArgumentException('Header required');
        }
        if (! isset($buffer->context->info)) {
            throw new \InvalidArgumentException('Info required');
        }

        $casesCount = $buffer->context->header->casesCount;

        /** @var Variable[] $variables */
        $variables = $buffer->context->variables;

        /** @var Record\Info[] $info */
        $info = $buffer->context->info;

        if (isset($info[Record\Info\MachineFloatingPoint::SUBTYPE])) {
            $sysmis = $info[Record\Info\MachineFloatingPoint::SUBTYPE]->sysmis;
        } else {
            $sysmis = NAN;
        }

        /** @var Record\Info\VeryLongString $vls */
        $veryLongStrings = $info[Record\Info\VeryLongString::SUBTYPE];

        $dataBuffer = Buffer::factory('', ['memory' => true]);

        $writer = new ByteCodeWriter($dataBuffer);
        for ($case = 0; $case < $casesCount; $case++) {
            /**
             * @var  $index
             * @var Variable $variable
             */
            foreach ($variables as $index => $variable) {
                $width = $veryLongStrings[$variable->name] ?? $variable->width;
                $value = $this->matrix[$case][$index];
                $this->writeVariable($writer, $variable, $width, $value);
            }
        }

        $dataBuffer->rewind();
        $buffer->writeStream($dataBuffer->getStream());
    }

    private function writeVariable(
        ByteCodeWriter $writer,
        Variable $variable,
        int $width,
        $value
    ) {
        $isNumeric = \SPSS\Sav\Variable::isNumberFormat($variable->write[1]);
        if ($isNumeric) {
            $writer->append(pack('d', $value));
            $writer->flush();
        } else {
            foreach(Utils::getSegments($width) as $segmentWidth) {
                $segment = str_pad(substr($value, 0, $segmentWidth), $segmentWidth);
                $padding = str_repeat(' ', 8 - (strlen($segment) % 8));
                $writer->append($segment);
                $writer->append($padding);
                $writer->flush();
                $value = substr($value, $segmentWidth);
            }
        }
    }


    /**
     * @return array
     */
    public function toArray()
    {
        return $this->matrix;
    }
}
