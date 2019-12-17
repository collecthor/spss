<?php
declare(strict_types=1);

namespace SPSS;

/**
 * Class ByteCodeWriter
 * Abstracts bytecode (de)compression
 * @package SPSS
 */
class ByteCodeWriter
{
    private const BYTE_SYS_MISSING = 255;
    private const BYTE_SPACES = 254;
    private const BYTE_LITERAL = 253;
    private const BYTE_NUL = 0;

    /** @var Buffer  */
    private $buffer;

    private $commandBuffer = [];
    private $dataBuffer = '';
    private $data = '';

    public function __construct(Buffer $buffer)
    {
        $this->buffer = $buffer;
    }

    /**
     * Appends data, data might not actually be written until flush is called
     */
    public function append(string $data): void
    {
        $data = $this->dataBuffer . $data;
        $this->dataBuffer = '';
        while(strlen($data) >= 8) {
            $block = substr($data, 0, 8);
            $data = substr($data, 8);
            switch ($block) {
                case pack('d', -PHP_FLOAT_MAX):
                    $this->commandBuffer[] = self::BYTE_SYS_MISSING;
                    break;
                case '        ':
                    $this->commandBuffer[] = self::BYTE_SPACES;
                    break;
                default:
                    $this->commandBuffer[] = self::BYTE_LITERAL;
                    $this->data .= $block;
            }
            $this->writeBlock();
        }
        $this->dataBuffer .= $data;
    }

    private function writeBlock(): void
    {
        if (count($this->commandBuffer) === 8) {
            $commandBlock = '';
            foreach ($this->commandBuffer as $cmd) {
                $commandBlock .= chr($cmd);
            }
            $this->buffer->write($commandBlock . $this->data);
            $this->data = '';
            $this->commandBuffer = [];
        }
    }

    /**
     * Fills the command buffer with nuls and writes the remaining data.
     */
    public function flush(): void
    {
        // Write remaining data by padding with spaces.
        if (!empty($this->dataBuffer)) {
            $this->append(str_repeat(' ', 8 - strlen($this->dataBuffer)));
        }
        if (!empty($this->commandBuffer)) {
            $this->commandBuffer = array_pad($this->commandBuffer, 8, self::BYTE_NUL);
            $this->writeBlock();
        }
        if (!empty($this->dataBuffer) || !empty($this->commandBuffer)) {
            throw new \Exception('Failed to flush one of the buffers');
        }
    }

}