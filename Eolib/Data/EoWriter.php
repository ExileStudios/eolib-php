<?php

namespace Eolib\Data;

use Eolib\Data\StringEncodingUtils;
use Eolib\Data\EoNumericLimits;

/**
 * A class for writing EO data to a sequence of bytes.
 * EoWriter enables serialization of different data types into a binary format,
 * essential for networking applications or data storage.
 */
class EoWriter
{
    /**
     * @var \SplFixedArray<int> The internal data structure for storing bytes.
     */
    private \SplFixedArray $data;

    /**
     * @var bool Indicates whether string sanitization is enabled.
     */
    private $stringSanitizationMode = false;

    /**
     * Constructor initializes the internal data structure for storing bytes.
     */
    public function __construct()
    {
        $this->data = new \SplFixedArray(0);
    }

    /**
     * Adds a single byte to the data array.
     *
     * @param int $value The byte value to add (must be between 0 and 255).
     * @throws \ValueError If the value is not within the byte range.
     */
    public function addByte(int $value): void
    {
        $this->checkNumberSize($value, 0xFF);
        $this->data->setSize($this->data->getSize() + 1);
        $this->data[$this->data->getSize() - 1] = $value;
    }

    /**
     * Adds an array of bytes to the data array.
     *
     * @param int[] $bytes The array of bytes to add.
     */
    public function addBytes(array $bytes): void
    {
        $this->data->setSize($this->data->getSize() + count($bytes));
        for ($i = 0; $i < count($bytes); $i++) {
            $this->data[$this->data->getSize() - count($bytes) + $i] = $bytes[$i];
        }
    }

    /**
     * Adds an encoded character to the data array as a 1-byte integer.
     *
     * @param ?int $number The character code to add (must be within EO_CHAR_MAX - 1).
     * @throws \ValueError If the number is out of the allowable range.
     */
    public function addChar(?int $number): void
    {
        $this->checkNumberSize($number ?? 0, EoNumericLimits::EO_CHAR_MAX - 1);
        $numberBytes = NumberEncodingUtils::encodeNumber($number ?? 0);
        $this->addBytesWithLength($numberBytes, 1);
    }

    /**
     * Adds an encoded short integer to the data array as a 2-byte integer.
     *
     * @param ?int $number The short to add (must be within EO_SHORT_MAX - 1).
     * @throws \ValueError If the number is out of the allowable range.
     */
    public function addShort(?int $number): void
    {
        $this->checkNumberSize($number ?? 0, EoNumericLimits::EO_SHORT_MAX - 1);
        $numberBytes = NumberEncodingUtils::encodeNumber($number ?? 0);
        $this->addBytesWithLength($numberBytes, 2);
    }

    /**
     * Adds an encoded integer to the data array as a 3-byte integer.
     *
     * @param ?int $number The integer to add (must be within EO_THREE_MAX - 1).
     * @throws \ValueError If the number is out of the allowable range.
     */
    public function addThree(?int $number): void
    {
        $this->checkNumberSize($number ?? 0, EoNumericLimits::EO_THREE_MAX - 1);
        $numberBytes = NumberEncodingUtils::encodeNumber($number ?? 0);
        $this->addBytesWithLength($numberBytes, 3);
    }

    /**
     * Adds an encoded integer to the data array as a 4-byte integer.
     *
     * @param ?int $number The integer to add (must be within EO_INT_MAX - 1).
     * @throws \ValueError If the number is out of the allowable range.
     */
    public function addInt(?int $number): void
    {
        $this->checkNumberSize($number ?? 0, EoNumericLimits::EO_INT_MAX - 1);
        $numberBytes = NumberEncodingUtils::encodeNumber($number ?? 0);
        $this->addBytesWithLength($numberBytes, 4);
    }

    /**
     * Adds a string to the data array, encoded in ANSI format.
     *
     * @param string $string The string to encode and add.
     */
    public function addString(string $string): void
    {
        $stringBytes = $this->encodeAnsi($string);
        $this->sanitizeString($stringBytes);
        $this->addBytes($stringBytes);
    }

    /**
     * Adds a fixed-length string to the data array, optionally padded to length with `0xFF`.
     *
     * @param string $string The string to encode and add.
     * @param int $length The fixed length of the string.
     * @param bool $padded Whether to pad the string with `0xFF` to the fixed length.
     * @throws \ValueError If the string length does not match the specified length when not padded.
     */
    public function addFixedString(string $string, int $length, bool $padded = false): void
    {
        $this->checkStringLength($string, $length, $padded);
        $stringBytes = $this->encodeAnsi($string);
        $this->sanitizeString($stringBytes);
        if ($padded) {
            $stringBytes = $this->addPadding($stringBytes, $length);
        }
        $this->addBytes($stringBytes);
    }
    
    /**
     * Adds a string to the data array, encoded in ANSI format and then encoded with the EO encryption scheme.
     *
     * @param string $string The string to encode and add.
     */
    public function addEncodedString(string $string): void
    {
        $stringBytes = $this->encodeAnsi($string);
        $this->sanitizeString($stringBytes);
        $sanitizedString = StringEncodingUtils::bytesToString($stringBytes);
        $stringBytes = StringEncodingUtils::encodeString($sanitizedString);
        $this->addBytes($stringBytes);
    }

    /**
     * Adds a fixed-length string to the data array, optionally padded to length with `0xFF` and then encoded with the EO encryption scheme.
     *
     * @param string $string The string to encode and add.
     * @param int $length The fixed length of the string.
     * @param bool $padded Whether to pad the string with `0xFF` to the fixed length.
     * @throws \ValueError If the string length does not match the specified length when not padded.
     */
    public function addFixedEncodedString(string $string, int $length, bool $padded = false): void
    {
        $this->checkStringLength($string, $length, $padded);
        $stringBytes = $this->encodeAnsi($string);
        $this->sanitizeString($stringBytes);
        if ($padded) {
            $stringBytes = $this->addPadding($stringBytes, $length);
        }
        $sanitizedString = StringEncodingUtils::bytesToString($stringBytes);
        $stringBytes = StringEncodingUtils::encodeString($sanitizedString);
        $this->addBytes($stringBytes);
    }

    /**
     * Enables or disables string sanitization mode.
     *
     * @param bool $mode True to enable sanitization, false to disable.
     */
    public function setStringSanitizationMode(bool $mode): void
    {
        $this->stringSanitizationMode = $mode;
    }

    /**
     * Returns the current state of string sanitization mode.
     *
     * @return bool True if sanitization is enabled, otherwise false.
     */
    public function getStringSanitizationMode(): bool
    {
        return $this->stringSanitizationMode;
    }

    /**
     * Converts the internal data array to a standard PHP array of bytes.
     *
     * @return int[] An array of byte values.
     */
    public function toByteArray(): array
    {
        return array_filter($this->data->toArray(), function ($value) {
            return $value !== null;
        });
    }

    /**
     * Converts the internal data array to a string of bytes.
     *
     * @return string A string of bytes.
     */
    public function toByteString(): string
    {
        return implode('', array_map(function ($value) {
            return $value !== null ? chr($value) : '';
        }, $this->data->toArray()));
    }

    /**
     * Returns the current length of the internal data array.
     *
     * @return int The number of bytes in the internal data array.
     */
    public function getLength(): int
    {
        return count($this->data);
    }

    /**
     * Adds an array of bytes to the data array with a specified length.
     *
     * @param int[] $bytes The bytes to add.
     * @param int $bytesLength The length of bytes to add.
     */
    private function addBytesWithLength(array $bytes, int $bytesLength): void
    {
        $this->data->setSize($this->data->getSize() + $bytesLength);
        for ($i = 0; $i < $bytesLength; $i++) {
            $this->data[$this->data->getSize() - $bytesLength + $i] = $bytes[$i];
        }
    }

    /**
     * Sanitizes a string by replacing `0xFF` bytes with `0x79` ('y').
     *
     * @param int[] &$bytes The array of bytes representing the string to sanitize.
     */
    private function sanitizeString(&$bytes): void
    {
        if ($this->stringSanitizationMode) {
            foreach ($bytes as $i => $byte) {
                if ($byte == 0xFF) {
                    $bytes[$i] = 0x79; // 'y'
                }
            }
        }
    }

    /**
     * Checks that a number is within the specified maximum value.
     *
     * @param int $number The number to check.
     * @param int $maxValue The maximum value the number can have.
     * @throws \ValueError If the number exceeds the maximum value.
     */
    private static function checkNumberSize(int $number, int $maxValue): void
    {
        if ($number > $maxValue) {
            throw new \ValueError("Value {$number} exceeds maximum of {$maxValue}.");
        }
    }

    /**
     * Adds padding to a byte array up to a specified length.
     *
     * @param int[] $bytes The byte array to pad.
     * @param int $length The length to pad to.
     * @return int[] The padded byte array.
     */
    private static function addPadding(array $bytes, int $length): array
    {
        if (count($bytes) == $length) {
            return $bytes;
        }

        $result = array_fill(0, $length, 0xFF);
        foreach ($bytes as $i => $byte) {
            $result[$i] = $byte;
        }
        return $result;
    }

    /**
     * Checks the length of a string against a specified length for fixed-length strings.
     *
     * @param string $string The string whose length is to be checked.
     * @param int $length The expected length of the string.
     * @param bool $padded Whether the string should be padded.
     * @throws \ValueError If the string does not meet the expected length conditions.
     */
    private static function checkStringLength(string $string, int $length, bool $padded): void
    {
        if ($padded && mb_strlen($string) <= $length) {
            return;
        }

        if (!$padded && mb_strlen($string) != $length) {
            throw new \ValueError("String '{$string}' does not have the expected length of {$length}, actual length is " . mb_strlen($string) . ".");
        }
    }

    /**
     * Encodes a string from UTF-8 to ANSI.
     *
     * @param string $string The UTF-8 string to encode.
     * @return int[] The ANSI-encoded string as an array of bytes.
     */
    private static function encodeAnsi(string $string): array
    {
        $ansiString = iconv('UTF-8', 'Windows-1252//IGNORE', $string);
        if ($ansiString === false) {
            throw new \RuntimeException('Failed to convert string from UTF-8 to ANSI');
        }
        return array_map('ord', str_split($ansiString));
    }
}
