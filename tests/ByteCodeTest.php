<?php
declare(strict_types=1);

namespace SPSS\Tests;


use SPSS\Buffer;
use SPSS\ByteCodeReader;
use SPSS\ByteCodeWriter;

class ByteCodeTest extends TestCase
{

    public function provider()
    {
        return [
            ['test'],
            [str_repeat('abcdf', 1000)],
            ['ᚠᛇᚻ᛫ᛒᛦᚦ᛫ᚠᚱᚩᚠᚢᚱ᛫ᚠᛁᚱᚪ᛫ᚷᛖᚻᚹᛦᛚᚳᚢᛗ']

        ];
    }

    /**
     * @param $data
     * @throws \Exception
     * @dataProvider provider
     */
    public function testWrite($data)
    {
        $stream = fopen('php://memory', 'r+');
        $buffer = Buffer::factory($stream);
        $writer = new ByteCodeWriter($buffer);
        $writer->append($data);
        $writer->flush();
        $buffer->rewind();
        $reader = new ByteCodeReader($buffer);
        $this->assertSame($data, trim($reader->read(strlen($data))));
    }
}