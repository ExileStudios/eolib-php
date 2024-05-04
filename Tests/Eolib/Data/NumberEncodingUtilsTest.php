<?php

namespace Eolib\Tests\Data;

use PHPUnit\Framework\TestCase;
use Eolib\Data\NumberEncodingUtils;

class NumberEncodingUtilsTest extends TestCase
{
    /**
     * @return array<int, array<int, array<int, int>|int>>
     */
    public static function numberProvider(): array
    {
        return [
            [0, [0x01, 0xFE, 0xFE, 0xFE]],
            [1, [0x02, 0xFE, 0xFE, 0xFE]],
            [28, [0x1D, 0xFE, 0xFE, 0xFE]],
            [100, [0x65, 0xFE, 0xFE, 0xFE]],
            [128, [0x81, 0xFE, 0xFE, 0xFE]],
            [252, [0xFD, 0xFE, 0xFE, 0xFE]],
            [253, [0x01, 0x02, 0xFE, 0xFE]],
            [254, [0x02, 0x02, 0xFE, 0xFE]],
            [255, [0x03, 0x02, 0xFE, 0xFE]],
            [32003, [0x7E, 0x7F, 0xFE, 0xFE]],
            [32004, [0x7F, 0x7F, 0xFE, 0xFE]],
            [32005, [0x80, 0x7F, 0xFE, 0xFE]],
            [64008, [0xFD, 0xFD, 0xFE, 0xFE]],
            [64009, [0x01, 0x01, 0x02, 0xFE]],
            [64010, [0x02, 0x01, 0x02, 0xFE]],
            [10000000, [0xB0, 0x3A, 0x9D, 0xFE]],
            [16194276, [0xFD, 0xFD, 0xFD, 0xFE]],
            [16194277, [0x01, 0x01, 0x01, 0x02]],
            [16194278, [0x02, 0x01, 0x01, 0x02]],
            [2048576039, [0x7E, 0x7F, 0x7F, 0x7F]],
            [2048576040, [0x7F, 0x7F, 0x7F, 0x7F]],
            [2048576041, [0x80, 0x7F, 0x7F, 0x7F]],
            [4097152079, [0xFC, 0xFD, 0xFD, 0xFD]],
            [4097152080, [0xFD, 0xFD, 0xFD, 0xFD]]
        ];
    }

    /**
     * @dataProvider numberProvider
     * 
     * @param int $decoded
     * @param array<int, int> $encoded
     * 
     * @return void
     */
    public function testEncodeNumber(int $decoded, array $encoded): void
    {
        $this->assertEquals($encoded, NumberEncodingUtils::encodeNumber($decoded));
    }

    /**
     * @dataProvider numberProvider
     * 
     * @param int $decoded
     * @param array<int, int> $encoded
     * 
     * @return void
     */
    public function testDecodeNumber(int $decoded, array $encoded): void
    {
        $this->assertEquals($decoded, NumberEncodingUtils::decodeNumber($encoded));
    }
}
