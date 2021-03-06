<?php

namespace SPSS\Sav\Record;

use SPSS\Buffer;
use SPSS\Sav\Record;
use SPSS\Utils;

class Variable extends Record
{
    const TYPE = 2;

    /**
     * Number of bytes really stored in each segment of a very long string variable.
     */
    const REAL_VLS_CHUNK = 255;

    /**
     * Number of bytes per segment by which the amount of space for very long string variables is allocated.
     */
    const EFFECTIVE_VLS_CHUNK = 252;

    /**
     * Set to 0 for a numeric variable.
     * For a short string variable or the first part of a long string variable, this is set to the width of the string.
     * For the second and subsequent parts of a long string variable, set to -1, and the remaining fields in the structure are ignored.
     *
     * @var int Variable width.
     */
    public $width;

    /**
     * If the variable has no missing values, set to 0.
     * If the variable has one, two, or three discrete missing values, set to 1, 2, or 3, respectively.
     * If the variable has a range for missing variables, set to -2;
     * if the variable has a range for missing variables plus a single discrete value, set to -3.
     * A long string variable always has the value 0 here.
     * A separate record indicates missing values for long string variables
     *
     * @var int
     * @see \SPSS\Sav\Record\Info\LongStringMissingValues
     */
    public $missingValuesFormat = 0;

    /**
     * Print format for this variable.
     * [decimals, width, format, 0]
     *
     * @var array
     */
    public $print = [0, 0, 0, 0];

    /**
     * Write format for this variable.
     * [decimals, width, format, 0]
     *
     * @var array
     */
    public $write = [0, 0, 0, 0];

    /**
     * The variable name must begin with a capital letter or the at-sign (‘@’).
     * Subsequent characters may also be digits, octothorpes (‘#’), dollar signs (‘$’), underscores (‘_’), or full stops (‘.’).
     * The variable name is padded on the right with spaces.
     *
     * @var string Variable name.
     */
    public $name;

    /**
     * It has length label_len, rounded up to the nearest multiple of 32 bits.
     * The first label_len characters are the variable’s variable label.
     *
     * @var string
     */
    public $label;

    /**
     * It has the same number of 8-byte elements as the absolute value of $missingValuesFormat.
     * Each element is interpreted as a number for numeric variables (with HIGHEST and LOWEST indicated as described in the chapter introduction).
     * For string variables of width less than 8 bytes, elements are right-padded with spaces;
     * for string variables wider than 8 bytes,
     * only the first 8 bytes of each missing value are specified, with the remainder implicitly all spaces.
     * For discrete missing values, each element represents one missing value.
     * When a range is present, the first element denotes the minimum value in the range,
     * and the second element denotes the maximum value in the range.
     * When a range plus a value are present, the third element denotes the additional discrete missing value.
     *
     * @var array
     */
    public $missingValues = [];

    /**
     * Returns true if WIDTH is a very long string width, false otherwise.
     *
     */
    public static function isVeryLong(int $width): bool
    {
        return $width > self::REAL_VLS_CHUNK;
    }

    /**
     * @param Buffer $buffer
     */
    public function read(Buffer $buffer)
    {
        $this->width = $buffer->readInt();
        $hasLabel = $buffer->readInt();
        $this->missingValuesFormat = $buffer->readInt();
        $this->print = Utils::intToBytes($buffer->readInt());
        $this->write = Utils::intToBytes($buffer->readInt());
        $this->name = rtrim($buffer->readString(8));
        if ($hasLabel) {
            $labelLength = $buffer->readInt();
            $this->label = $buffer->readString($labelLength, 4);
        }
        if ($this->missingValuesFormat != 0) {
            for ($i = 0; $i < abs($this->missingValuesFormat); $i++) {
                $this->missingValues[] = $buffer->readDouble();
            }
        }
    }

    /**
     * @param Buffer $buffer
     */
    public function write(Buffer $buffer)
    {
        $hasLabel = ! empty($this->label);

        $buffer->writeInt(self::TYPE);
        $buffer->writeInt(min(255, $this->width));
        $buffer->writeInt($hasLabel ? 1 : 0);
        $buffer->writeInt($this->missingValuesFormat);
        $buffer->writeInt(Utils::bytesToInt($this->print));
        $buffer->writeInt(Utils::bytesToInt($this->write));
        $buffer->writeString($this->name, 8);

        if ($hasLabel) {
            // Maxlength is 255 bytes, since we write utf8 a char can be multiple bytes
            $labelLength = min(mb_strlen($this->label), 255);
            $label = mb_substr($this->label, 0, $labelLength);
            $labelLengthBytes = mb_strlen($label, '8bit');
            while ($labelLengthBytes > 255) {
                // Strip one char, can be multiple bytes
                $label = mb_substr($label, 0, -1);
                $labelLengthBytes = mb_strlen($label, '8bit');
            }
            $buffer->writeInt($labelLengthBytes);
            $buffer->writeString($label, Utils::roundUp($labelLengthBytes, 4));
        }

        // TODO: test
        if ($this->missingValuesFormat) {
            foreach ($this->missingValues as $val) {
                if ($this->width == 0) {
                    $buffer->writeDouble($val);
                } else {
                    $buffer->writeString($val, 8);
                }
            }
        }

        // Write additional segments for very long string variables.
        if (self::isVeryLong($this->width)) {
            $format = Utils::bytesToInt([0, 1, 1, 0]);

            foreach(Utils::getSegments($this->width) as $i => $segmentWidth) {
                if ($i == 0) {
                    continue;
                }
                $buffer->writeInt(self::TYPE);
                $buffer->writeInt($segmentWidth);
                $buffer->writeInt(1); // No variable label
                $buffer->writeInt(0); // No missing values
                $buffer->writeInt($format); // Print format
                $buffer->writeInt($format); // Write format
                $buffer->writeString($this->getSegmentName($i - 1), 8);

                /**
                 * If the long string variable is interpreted correctly SPSS does not show these segments.
                 * If something goes wrong they will be visible, so we provide a label to explain the situation.
                 */
                $segmentLabel = "Segment $i of variable {$this->name}, you should not see this";
                $length = mb_strlen($segmentLabel, '8BIT');
                $buffer->writeInt($length);
                $buffer->writeString($segmentLabel, Utils::roundUp($length, 4));
            }
        }
    }

    /**
     * Constructs the name for a segment.
     * A long string variable consists of multiple segment variables.
     * The names for these segment variables need not be unique across the whole SPSS file.
     * The names for these segment must have share a prefix of at least length 5 with the base variable name.
     * @param int $seg Index of the segment
     * @return string Name of the segment
     */
    private function getSegmentName($seg = 0)
    {
        $name = $this->name;
        $name = mb_substr($name, 0, 5);
        $name .=  $seg;

        return mb_strtoupper($name);
    }


}
