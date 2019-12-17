<?php
declare(strict_types=1);

namespace SPSS;

/**
 * Class ByteCodeCompressor
 * Abstracts bytecode (de)compression
 * @package SPSS
 */
class ByteCodeReader
{
    private const BYTE_SYS_MISSING = 255;
    private const BYTE_SPACES = 254;
    private const BYTE_LITERAL = 253;
    private const BYTE_NUL = 0;
    /** @var Buffer  */
    private $buffer;

    private $commandBuffer = [];
    private $dataBuffer = '';

    public function __construct(Buffer $buffer)
    {
        $this->buffer = $buffer;
    }

    private function readBlock(): string
    {
        $this->refreshCommand();
        if (!is_array($this->commandBuffer)) {
            return '';
        }
        switch($cmd = array_shift($this->commandBuffer)) {
            case self::BYTE_SYS_MISSING:
                return pack('d', -PHP_FLOAT_MAX);
            case self::BYTE_SPACES:
                return '        ';
            case self::BYTE_LITERAL:
                $result = $this->buffer->read(8);
                if ($result === false) {
                     throw new \Exception('Stream read error');
                }
                return $result;
            case self::BYTE_NUL:
                return $this->readBlock();
                throw new \Exception('0 byte command not supported');
            default:
                // Bias
                throw new \Exception('bias encoding not supported: ' . $cmd);
        }
    }

    private function refreshCommand()
    {
        if (empty($this->commandBuffer)) {
            $this->commandBuffer = $this->buffer->readBytes(8);
        }
    }

    public function read(int $length): string
    {
        $result = substr($this->dataBuffer, 0, $length);
        $length -= strlen($result);
        while ($length > 0 && ($data = $this->readBlock()) !== '')
        {
            if ($length >= strlen($data)) {
                $result .= $data;
                $length -= strlen($data);
            } else {
                $this->dataBuffer .= substr($data, $length);
                return $result . substr($data, 0, $length);
            }
        }
        return $result;
    }

}