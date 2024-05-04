<?php

use PHPUnit\Framework\TestCase;
use Eolib\Data\StringEncodingUtils;

class StringEncodingUtilsTest extends TestCase
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function caseProvider(): array
    {
        return [
            [
                "decoded" => "Hello, World!",
                "encoded" => "!;a-^H s^3a:)",
            ],
            [
                "decoded" => "We're ¼ of the way there, so ¾ is remaining.",
                "encoded" => "C8_6_6l2h- ,d ¾ ^, sh-h7Y T>V h7Y g0 ¼ :[xhH",
            ],
            [
                "decoded" => "64² = 4096",
                "encoded" => ";fAk b ²=i",
            ],
            [
                "decoded" => "© FÒÖ BÃR BÅZ 2014",
                "encoded" => "=nAm EÅ] MÃ] ÖÒY ©",
            ],
            [
                "decoded" => 'Öxxö Xööx "Lëïth Säë" - "Ÿ"',
                "encoded" => "OŸO D OëäL 7YïëSO UööG öU'Ö",
            ],
            [
                "decoded" => "Padded with 0xFFÿÿÿÿÿÿÿÿ",
                "encoded" => "ÿÿÿÿÿÿÿÿ+YUo 7Y6V i:i;lO",
            ],
        ];
    }

    /**
     * @dataProvider caseProvider
     * 
     * @param string $decoded
     * @param string $encoded
     * 
     * @return void
     */
    public function testEncodeString(string $decoded, string $encoded): void
    {
        $bytes = StringEncodingUtils::encodeString($decoded);
        $byteString = StringEncodingUtils::bytesToString($bytes);
        $this->assertEquals($encoded, $byteString);
    }

    /**
     * @dataProvider caseProvider
     * 
     * @param string $decoded
     * @param string $encoded
     * 
     * @return void
     */
    public function testDecodeString(string $decoded, string $encoded): void
    {
        $byteArray = StringEncodingUtils::stringToBytes($encoded);
        $byteString = StringEncodingUtils::decodeString($byteArray);
        $this->assertEquals($decoded, $byteString);
    }
}