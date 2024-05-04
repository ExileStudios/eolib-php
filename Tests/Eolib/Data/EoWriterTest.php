<?php

use PHPUnit\Framework\TestCase;
use Eolib\Data\EoWriter;
use Eolib\Data\EoNumericLimits;

class EoWriterTest extends TestCase
{
    public function testAddByte()
    {
        $writer = new EoWriter();
        $writer->addByte(0x00);
        $this->assertEquals(chr(0x00), $writer->toByteString());
    }

    public function testAddBytes()
    {
        $writer = new EoWriter();
        $writer->addBytes([0x00, 0xFF]);
        $this->assertEquals(chr(0x00) . chr(0xFF), $writer->toByteString());
    }

    public function testAddChar()
    {
        $writer = new EoWriter();
        $writer->addChar(123);
        $this->assertEquals(chr(0x7C), $writer->toByteString());
    }

    public function testAddShort()
    {
        $writer = new EoWriter();
        $writer->addShort(12345);
        $this->assertEquals(chr(0xCA) . chr(0x31), $writer->toByteString());
    }

    public function testAddThree()
    {
        $writer = new EoWriter();
        $writer->addThree(10000000);
        $this->assertEquals(chr(0xB0) . chr(0x3A) . chr(0x9D), $writer->toByteString());
    }

    public function testAddInt()
    {
        $writer = new EoWriter();
        $writer->addInt(2048576040);
        $this->assertEquals(
            chr(0x7F) . chr(0x7F) . chr(0x7F) . chr(0x7F),
            $writer->toByteString()
        );
    }

    public function testAddString()
    {
        $writer = new EoWriter();
        $writer->addString("foo");
        $this->assertEquals("foo", $writer->toByteString());
    }

    public function testAddFixedString()
    {
        $writer = new EoWriter();
        $writer->addFixedString("bar", 3);
        $this->assertEquals("bar", $writer->toByteString());
    }

    public function testAddPaddedFixedString()
    {
        $writer = new EoWriter();
        $writer->addFixedString("bar", 6, true);
        $this->assertEquals("bar" . chr(0xFF) . chr(0xFF) . chr(0xFF), $writer->toByteString());
    }

    public function testAddPaddedWithPerfectFitFixedString()
    {
        $writer = new EoWriter();
        $writer->addFixedString("bar", 3, true);
        $this->assertEquals("bar", $writer->toByteString());
    }

    public function testAddEncodedString()
    {
        $writer = new EoWriter();
        $writer->addEncodedString("foo");
        $this->assertEquals("^0g", $writer->toByteString());
    }

    public function testAddFixedEncodedString()
    {
        $writer = new EoWriter();
        $writer->addFixedEncodedString("bar", 3);
        $this->assertEquals("[>k", $writer->toByteString());
    }

    public function testAddPaddedFixedEncodedString()
    {
        $writer = new EoWriter();
        $writer->addFixedEncodedString("bar", 6, true);
        $this->assertEquals(
            chr(0xFF) . chr(0xFF) . chr(0xFF) . "-l=",
            $writer->toByteString()
        );
    }

    public function testAddPaddedWithPerfectFitFixedEncodedString()
    {
        $writer = new EoWriter();
        $writer->addFixedEncodedString("bar", 3, true);
        $this->assertEquals("[>k", $writer->toByteString());
    }

    public function testAddSanitizedString()
    {
        $writer = new EoWriter();
        $writer->setStringSanitizationMode(true);
        $writer->addString("aÿz");
        $this->assertEquals("ayz", $writer->toByteString());
    }

    public function testAddSanitizedFixedString()
    {
        $writer = new EoWriter();
        $writer->setStringSanitizationMode(true);
        $writer->addFixedString("aÿz", 3);
        $this->assertEquals("ayz", $writer->toByteString());
    }

    public function testAddSanitizedPaddedFixedString()
    {
        $writer = new EoWriter();
        $writer->setStringSanitizationMode(true);
        $writer->addFixedString("aÿz", 6, true);
        $this->assertEquals(
            "ayz" . chr(0xFF) . chr(0xFF) . chr(0xFF),
            $writer->toByteString()
        );
    }

    public function testAddSanitizedEncodedString()
    {
        $writer = new EoWriter();
        $writer->setStringSanitizationMode(true);
        $writer->addEncodedString("aÿz");
        $this->assertEquals("S&l", $writer->toByteString());
    }

    public function testAddSanitizedFixedEncodedString()
    {
        $writer = new EoWriter();
        $writer->setStringSanitizationMode(true);
        $writer->addFixedEncodedString("aÿz", 3);
        $this->assertEquals("S&l", $writer->toByteString());
    }

    public function testAddSanitizedPaddedFixedEncodedString()
    {
        $writer = new EoWriter();
        $writer->setStringSanitizationMode(true);
        $writer->addFixedEncodedString("aÿz", 6, true);
        $this->assertEquals(
            chr(0xFF) . chr(0xFF) . chr(0xFF) . "%T>",
            $writer->toByteString()
        );
    }

    public function testAddNumbersOnBoundary()
    {
        $writer = new EoWriter();
        $writer->addByte(0xFF);
        $writer->addChar(EoNumericLimits::EO_CHAR_MAX - 1);
        $writer->addShort(EoNumericLimits::EO_SHORT_MAX - 1);
        $writer->addThree(EoNumericLimits::EO_THREE_MAX - 1);
        $writer->addInt(EoNumericLimits::EO_INT_MAX - 1);
        $this->assertTrue(true);
    }

    public function testAddNumbersExceedingLimit()
    {
        $writer = new EoWriter();

        $this->expectException(\ValueError::class);
        $writer->addByte(256);

        $this->expectException(\ValueError::class);
        $writer->addChar(256);

        $this->expectException(\ValueError::class);
        $writer->addShort(65536);

        $this->expectException(\ValueError::class);
        $writer->addThree(16777216);

        $this->expectException(\ValueError::class);
        $writer->addInt(4294967296);
    }

    public function testAddFixedStringWithIncorrectLength()
    {
        $writer = new EoWriter();

        $this->expectException(\ValueError::class);
        $writer->addFixedString("foo", 2);

        $this->expectException(\ValueError::class);
        $writer->addFixedString("foo", 2, true);

        $this->expectException(\ValueError::class);
        $writer->addFixedString("foo", 4);

        $this->expectException(\ValueError::class);
        $writer->addFixedEncodedString("foo", 2);

        $this->expectException(\ValueError::class);
        $writer->addFixedEncodedString("foo", 2, true);

        $this->expectException(\ValueError::class);
        $writer->addFixedEncodedString("foo", 4);
    }

    public function testGetStringSanitizationMode()
    {
        $writer = new EoWriter();

        $this->assertFalse($writer->getStringSanitizationMode());

        $writer->setStringSanitizationMode(true);
        $this->assertTrue($writer->getStringSanitizationMode());
    }

    public function testLen()
    {
        $writer = new EoWriter();

        $this->assertEquals(0, $writer->length());

        $writer->addString("Lorem ipsum dolor sit amet");
        $this->assertEquals(26, $writer->length());

        for ($i = 27; $i < 101; $i++) {
            $writer->addByte(0xFF);
        }
        $this->assertEquals(100, $writer->length());
    }
}