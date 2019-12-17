<?php

namespace SPSS\Tests;

use SPSS\Buffer;
use SPSS\ByteCodeReader;
use SPSS\Sav\Reader;
use SPSS\Sav\Variable;
use SPSS\Sav\Writer;

class LongStringTest extends TestCase
{

    public function testLongString()
    {
//        $reader = new ByteCodeReader(Buffer::factory(fopen('/tmp/test.bin', 'r')));
//        var_dump($reader->read(104));
//        var_dump($reader->read(152));
//        var_dump($reader->read(256));
//        var_dump($reader->read(264));
//        var_dump($reader->read(104));
//        var_dump($reader->read(152));
//        var_dump($reader->read(256));
//        var_dump($reader->read(256));
//        var_dump($reader->read(8));
//        die();
        $firstLong = str_repeat('1234567890', 3000);
        $secondLong = str_repeat('abcdefghij', 3000);
        $data   = [
            'header'    => [
                'prodName'     => '@(#) IBM SPSS STATISTICS',
                'layoutCode'   => 2,
                'creationDate' => '08 May 19',
                'creationTime' => '12:22:16',
            ],
            'variables' => [
                [
                    'name' => 'SHORT',
                    'label' => 'short label',
                    'width' => 100,
                    'format' => Variable::FORMAT_TYPE_A,
                    'attributes' => [
                        '$@Role' => Variable::ROLE_INPUT,
                    ],
                    'data' => [
                        '20202020202020202020',
                        '303030303030303030303030303030',
                    ]
                ],
                [
                    'name' => 'MEDIUM',
                    'label' => 'medium label',
                    'width' => 150,
                    'format' => Variable::FORMAT_TYPE_A,
                    'attributes' => [
                        '$@Role' => Variable::ROLE_INPUT,
                    ],
                    'data' => [
                        '210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210',
                        '02600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260',
                    ]
                ],
                [
                    'name' => 'MEDIUMER',
                    'label' => 'mediumer label',
                    'width' => 250,
                    'format' => Variable::FORMAT_TYPE_A,
                    'attributes' => [
                        '$@Role' => Variable::ROLE_INPUT,
                    ],
                    'data' => [
                        '210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210',
                        '02600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260',
                    ]
                ],
                [
                    'name' => 'SHORTLONG',
                    'label' => 'short long label',
                    'width' => 265,
                    'format' => Variable::FORMAT_TYPE_A,
                    'attributes' => [
                        '$@Role' => Variable::ROLE_INPUT,
                    ],
                    'data' => [
                        '210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210210',
                        '0260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026002600260026012345',
                    ]
                ],
                [
                    'name'   => 'LONGER1',
                    'label'  => 'long label1',
                    'width'  => 30000,
                    'format' => Variable::FORMAT_TYPE_A,
                    'attributes' => [
                        '$@Role' => Variable::ROLE_INPUT,
                    ],
                    'data' => [
                        $firstLong,
                        $secondLong
                    ]
                ],
                [
                    'name'   => 'LONGER2',
                    'label'  => 'long label2',
                    'width'  => 30000,
                    'format' => Variable::FORMAT_TYPE_A,
                    'attributes' => [
                        '$@Role' => Variable::ROLE_INPUT,
                    ],
                    'data' => [
                        $firstLong,
                        $secondLong
                    ]
                ],
                [
                    'name'   => 'short',
                    'label'  => 'short label',
                    'format' => Variable::FORMAT_TYPE_F,
                    'width'  => 8,
                    'attributes' => [
                        '$@Role' => Variable::ROLE_INPUT,
                    ],
                    'data' => [
                        12135.12,
                        123
                    ],
                ],
                [
                    'name'   => 'short',
                    'label'  => 'short label',
                    'format' => Variable::FORMAT_TYPE_A,
                    'width'  => 8,
                    'attributes' => [
                        '$@Role' => Variable::ROLE_INPUT,
                    ],
                    'data' => [
                        '12345678',
                        'abcdefgh'
                    ],
                ],
            ],
        ];
        $writer = new Writer($data);

        // Uncomment if you want to really save and check the resulting file in SPSS
        $writer->save('/tmp/longString.sav');

        $buffer = $writer->getBuffer();
        $buffer->rewind();
        
        $reader = Reader::fromString($buffer->getStream())->read();

        foreach ($data['variables'] as $i => $variable) {
            foreach($variable['data'] as $case => $value) {
                if (is_string($value)) {
                    $this->assertSame(substr($value, 0, $variable['width']), $reader->data[$case][$i],
                        "Position ($case, $i)");
                } elseif (is_numeric($value)) {
                    $this->assertEqualsWithDelta($value, $reader->data[$case][$i], 0.00001);
                } else {
                    var_dump($value); die();
                }
            }
        }

    }

}
