<?php

use PHPUnit\Framework\TestCase;
use Eolib\Data\EoReader;
use Eolib\Data\StringEncodingUtils;

class EoReaderTest extends TestCase
{
    public function testSlice()
    {
        $reader = $this->createReader([0x01, 0x02, 0x03, 0x04, 0x05, 0x06]);
        $reader->getByte();
        $reader->setChunkedReadingMode(true);

        $reader2 = $reader->slice();
        $this->assertEquals(0, $reader2->getPosition());
        $this->assertEquals(5, $reader2->getRemaining());
        $this->assertFalse($reader2->isChunkedReadingMode());

        $reader3 = $reader2->slice(1);
        $this->assertEquals(0, $reader3->getPosition());
        $this->assertEquals(4, $reader3->getRemaining());
        $this->assertFalse($reader3->isChunkedReadingMode());

        $reader4 = $reader3->slice(1, 2);
        $this->assertEquals(0, $reader4->getPosition());
        $this->assertEquals(2, $reader4->getRemaining());
        $this->assertFalse($reader4->isChunkedReadingMode());

        $this->assertEquals(1, $reader->getPosition());
        $this->assertEquals(5, $reader->getRemaining());
        $this->assertTrue($reader->isChunkedReadingMode());
    }

    public function testSliceOverRead()
    {
        $reader = $this->createReader([0x01, 0x02, 0x03]);
        $this->assertEquals(1, $reader->slice(2, 5)->getRemaining());
        $this->assertEquals(0, $reader->slice(3)->getRemaining());
        $this->assertEquals(0, $reader->slice(4)->getRemaining());
        $this->assertEquals(0, $reader->slice(4, 12345)->getRemaining());
    }

    public function testSliceNegativeIndex()
    {
        $reader = $this->createReader([0x01, 0x02, 0x03]);
        $this->expectException(\ValueError::class);
        $reader->slice(-1);
    }

    public function testSliceNegativeLength()
    {
        $reader = $this->createReader([0x01, 0x02, 0x03]);
        $this->expectException(\ValueError::class);
        $reader->slice(0, -1);
    }

    /**
     * @dataProvider byteValueProvider
     */
    public function testGetByte($byteValue)
    {
        $reader = $this->createReader([$byteValue]);
        $this->assertEquals($byteValue, $reader->getByte());
    }

    public static function byteValueProvider()
    {
        return [
            [0x00],
            [0x01],
            [0x02],
            [0x80],
            [0xFD],
            [0xFE],
            [0xFF],
        ];
    }

    public function testOverReadByte()
    {
        $reader = $this->createReader([]);
        $this->assertEquals(0x00, $reader->getByte());
    }

    public function testGetBytes()
    {
        $reader = $this->createReader([0x01, 0x02, 0x03, 0x04, 0x05]);
        $this->assertEquals([0x01, 0x02, 0x03], $reader->getBytes(3));
        $this->assertEquals([0x04, 0x05], $reader->getBytes(10));
        $this->assertEquals([], $reader->getBytes(1));
    }

    public function testGetChar()
    {
        $reader = $this->createReader([0x01, 0x02, 0x80, 0x81, 0xFD, 0xFE, 0xFF]);
        $this->assertEquals(0, $reader->getChar());
        $this->assertEquals(1, $reader->getChar());
        $this->assertEquals(127, $reader->getChar());
        $this->assertEquals(128, $reader->getChar());
        $this->assertEquals(252, $reader->getChar());
        $this->assertEquals(0, $reader->getChar());
        $this->assertEquals(254, $reader->getChar());
    }

    public function testGetShort()
    {
        $reader = $this->createReader(
            [0x01, 0xFE, 0x02, 0xFE, 0x80, 0xFE, 0xFD, 0xFE, 0xFE, 0xFE, 0xFE, 0x80, 0x7F, 0x7F, 0xFD, 0xFD]
        );
        $this->assertEquals(0, $reader->getShort());
        $this->assertEquals(1, $reader->getShort());
        $this->assertEquals(127, $reader->getShort());
        $this->assertEquals(252, $reader->getShort());
        $this->assertEquals(0, $reader->getShort());
        $this->assertEquals(0, $reader->getShort());
        $this->assertEquals(32004, $reader->getShort());
        $this->assertEquals(64008, $reader->getShort());
    }

    public function testGetThree()
    {
        $reader = $this->createReader(
            [0x01, 0xFE, 0xFE, 0x02, 0xFE, 0xFE, 0x80, 0xFE, 0xFE, 0xFD, 0xFE, 0xFE, 0xFE, 0xFE, 0xFE, 0xFE, 0x80, 0x81, 0x7F, 0x7F, 0xFE, 0xFD, 0xFD, 0xFE, 0xFD, 0xFD, 0xFD]
        );
        $this->assertEquals(0, $reader->getThree());
        $this->assertEquals(1, $reader->getThree());
        $this->assertEquals(127, $reader->getThree());
        $this->assertEquals(252, $reader->getThree());
        $this->assertEquals(0, $reader->getThree());
        $this->assertEquals(0, $reader->getThree());
        $this->assertEquals(32004, $reader->getThree());
        $this->assertEquals(64008, $reader->getThree());
        $this->assertEquals(16194276, $reader->getThree());
    }

    public function testGetInt()
    {
        $reader = $this->createReader(
            [0x01, 0xFE, 0xFE, 0xFE, 0x02, 0xFE, 0xFE, 0xFE, 0x80, 0xFE, 0xFE, 0xFE, 0xFD, 0xFE, 0xFE, 0xFE, 0xFE, 0xFE, 0xFE, 0xFE, 0xFE, 0x80, 0x81, 0x82, 0x7F, 0x7F, 0xFE, 0xFE, 0xFD, 0xFD, 0xFE, 0xFE, 0xFD, 0xFD, 0xFD, 0xFE, 0x7F, 0x7F, 0x7F, 0x7F, 0xFD, 0xFD, 0xFD, 0xFD]
        );
        $this->assertEquals(0, $reader->getInt());
        $this->assertEquals(1, $reader->getInt());
        $this->assertEquals(127, $reader->getInt());
        $this->assertEquals(252, $reader->getInt());
        $this->assertEquals(0, $reader->getInt());
        $this->assertEquals(0, $reader->getInt());
        $this->assertEquals(32004, $reader->getInt());
        $this->assertEquals(64008, $reader->getInt());
        $this->assertEquals(16194276, $reader->getInt());
        $this->assertEquals(2048576040, $reader->getInt());
        $this->assertEquals(4097152080, $reader->getInt());
    }

    public function testGetString()
    {
        $reader = $this->createReaderFromString("Hello, World!");
        $this->assertEquals("Hello, World!", $reader->getString());
    }

    public function testGetFixedString()
    {
        $reader = $this->createReaderFromString("foobar");
        $this->assertEquals("foo", $reader->getFixedString(3));
        $this->assertEquals("bar", $reader->getFixedString(3));
    }

    public function testPaddedGetFixedString()
    {
        $reader = $this->createReaderFromString("fooÿbarÿÿÿ");
        $this->assertEquals("foo", $reader->getFixedString(4, true));
        $this->assertEquals("bar", $reader->getFixedString(6, true));
    }

    public function testChunkedGetString()
    {
        $reader = $this->createReaderFromString("Hello,ÿWorld!");
        $reader->setChunkedReadingMode(true);
        $string = $reader->getString();
        $this->assertEquals("Hello,", $string);

        $reader->nextChunk();
        $this->assertEquals("World!", $reader->getString());
    }

    public function testGetNegativeLengthString()
    {
        $reader = $this->createReaderFromString("foo");
        $this->expectException(\ValueError::class);
        $reader->getFixedString(-1);
    }

    public function testGetEncodedString()
    {
        $reader = $this->createReaderFromString("!;a-^H s^3a:)");
        $this->assertEquals("Hello, World!", $reader->getEncodedString());
    }

    public function testFixedGetEncodedString()
    {
        $reader = $this->createReaderFromString("^0g[>k");
        $this->assertEquals("foo", $reader->getFixedEncodedString(3));
        $this->assertEquals("bar", $reader->getFixedEncodedString(3));
    }

    public function testPaddedGetFixedEncodedString()
    {
        $reader = $this->createReaderFromString("ÿ0^9ÿÿÿ-l=S>k");
        $this->assertEquals("foo", $reader->getFixedEncodedString(4, true));
        $this->assertEquals("bar", $reader->getFixedEncodedString(6, true));
        $this->assertEquals("baz", $reader->getFixedEncodedString(3, true));
    }

    public function testChunkedGetEncodedString()
    {
        $reader = $this->createReaderFromString("E0a3hWÿ!;a-^H");
        $reader->setChunkedReadingMode(true);

        $this->assertEquals("Hello,", $reader->getEncodedString());

        $reader->nextChunk();
        $this->assertEquals("World!", $reader->getEncodedString());
    }

    public function testGetNegativeLengthEncodedString()
    {
        $reader = $this->createReaderFromString("^0g");
        $this->expectException(\ValueError::class);
        $reader->getFixedEncodedString(-1);
    }

    public function testChunkedReadingMode()
    {
        $reader = $this->createReader([]);
        $this->assertFalse($reader->isChunkedReadingMode());
        $reader->setChunkedReadingMode(true);
        $this->assertTrue($reader->isChunkedReadingMode());
    }

    public function testRemaining()
    {
        $reader = $this->createReader([0x01, 0x03, 0x04, 0xFE, 0x05, 0xFE, 0xFE, 0x06, 0xFE, 0xFE, 0xFE]);

        $this->assertEquals(11, $reader->getRemaining());
        $reader->getByte();
        $this->assertEquals(10, $reader->getRemaining());
        $reader->getChar();
        $this->assertEquals(9, $reader->getRemaining());
        $reader->getShort();
        $this->assertEquals(7, $reader->getRemaining());
        $reader->getThree();
        $this->assertEquals(4, $reader->getRemaining());
        $reader->getInt();
        $this->assertEquals(0, $reader->getRemaining());

        $reader->getChar();
        $this->assertEquals(0, $reader->getRemaining());
    }

    public function testChunkedRemaining()
    {
        $reader = $this->createReader([0x01, 0x03, 0x04, 0xFF, 0x05, 0xFE, 0xFE, 0x06, 0xFE, 0xFE, 0xFE]);
        $reader->setChunkedReadingMode(true);

        $this->assertEquals(3, $reader->getRemaining());
        $reader->getChar();
        $reader->getShort();
        $this->assertEquals(0, $reader->getRemaining());

        $reader->getChar();
        $this->assertEquals(0, $reader->getRemaining());

        $reader->nextChunk();
        $this->assertEquals(7, $reader->getRemaining());
    }

    public function testNextChunk()
    {
        $reader = $this->createReader([0x01, 0x02, 0xFF, 0x03, 0x04, 0x05, 0xFF, 0x06]);
        $reader->setChunkedReadingMode(true);

        $this->assertEquals(0, $reader->getPosition());

        $reader->nextChunk();
        $this->assertEquals(3, $reader->getPosition());

        $reader->nextChunk();
        $this->assertEquals(7, $reader->getPosition());

        $reader->nextChunk();
        $this->assertEquals(8, $reader->getPosition());

        $reader->nextChunk();
        $this->assertEquals(8, $reader->getPosition());
    }

    public function testNextChunkNotInChunkedReadingMode()
    {
        $reader = $this->createReader([0x01, 0x02, 0xFF, 0x03, 0x04, 0x05, 0xFF, 0x06]);
        $this->expectException(\RuntimeException::class);
        $reader->nextChunk();
    }

    public function testNextChunkWithChunkedReadingToggledInBetween()
    {
        $reader = $this->createReader([0x01, 0x02, 0xFF, 0x03, 0x04, 0x05, 0xFF, 0x06]);
        $this->assertEquals(0, $reader->getPosition());

        $reader->setChunkedReadingMode(true);
        $reader->nextChunk();
        $reader->setChunkedReadingMode(false);
        $this->assertEquals(3, $reader->getPosition());

        $reader->setChunkedReadingMode(true);
        $reader->nextChunk();
        $reader->setChunkedReadingMode(false);
        $this->assertEquals(7, $reader->getPosition());

        $reader->setChunkedReadingMode(true);
        $reader->nextChunk();
        $reader->setChunkedReadingMode(false);
        $this->assertEquals(8, $reader->getPosition());

        $reader->setChunkedReadingMode(true);
        $reader->nextChunk();
        $reader->setChunkedReadingMode(false);
        $this->assertEquals(8, $reader->getPosition());
    }

    public function testUnderRead()
    {
        $reader = $this->createReader([0x7C, 0x67, 0x61, 0x72, 0x62, 0x61, 0x67, 0x65, 0xFF, 0xCA, 0x31]);
        $reader->setChunkedReadingMode(true);

        $this->assertEquals(123, $reader->getChar());
        $reader->nextChunk();
        $this->assertEquals(12345, $reader->getShort());
    }

    public function testOverRead()
    {
        $reader = $this->createReader([0xFF, 0x7C]);
        $reader->setChunkedReadingMode(true);

        $this->assertEquals(0, $reader->getInt());
        $reader->nextChunk();
        $this->assertEquals(123, $reader->getShort());
    }

    public function testDoubleRead()
    {
        $reader = $this->createReader([0xFF, 0x7C, 0xCA, 0x31]);

        $this->assertEquals(790222478, $reader->getInt());

        $reader->setChunkedReadingMode(true);
        $reader->nextChunk();
        $this->assertEquals(123, $reader->getChar());
        $this->assertEquals(12345, $reader->getShort());
    }

    private function createReaderFromString(string $inputData)
    {
        return $this->createReader(StringEncodingUtils::stringToBytes($inputData));
    }

    private function createReader(array $inputData) 
    {
        $data = array_fill(0, 10, 0);
        $data = array_merge($data, $inputData);
        $data = array_merge($data, array_fill(0, 10, 0));
        return (new EoReader($data))->slice(10, count($inputData));
    }
}
