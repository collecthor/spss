<?php

namespace SPSS\Sav\Record\Info;

use SPSS\Buffer;
use SPSS\Sav\Record\Info;

class VeryLongString extends Info
{
    const SUBTYPE = 14;
    const DELIMITER = "\t";

    /**
     * @param Buffer $buffer
     */
    public function read(Buffer $buffer)
    {
        parent::read($buffer);
        $data = rtrim($buffer->readString($this->dataSize * $this->dataCount));
        foreach (explode(self::DELIMITER, $data) as $item) {
            list($key, $value) = explode('=', $item);
            $this->data[$key] = intval($value);
        }
    }

    /**
     * @param Buffer $buffer
     */
    public function write(Buffer $buffer)
    {
        if ($this->data) {
            $data = "";
            foreach ($this->data as $key => $value) {
                $data .= "$key=" . str_pad((string)$value, 5, "0", STR_PAD_LEFT) . "\0\t";
            }
            $this->dataCount = strlen($data);
            parent::write($buffer);
            $buffer->writeString($data);
        }
    }
}
