<?php

namespace Eolib\Data;

/**
 * A class for reading EO data from a sequence of bytes.
 *
 * EoReader features a chunked reading mode, which is important for accurate emulation of
 * the official game client.
 *
 * See documentation for chunked reading:
 * https://github.com/Cirras/eo-protocol/blob/master/docs/chunks.md
 */
class EoReader {
    /** @var int[] */
    private array $data;
    private int $position = 0;
    private bool $chunkedReadingMode = false;
    private int $chunkStart = 0;
    private int $nextBreak = -1;

    /**
     * Creates a new EoReader instance for the specified data.
     *
     * @param int[] $data The byte string containing the input data.
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Creates a new EoReader whose input data is a shared subsequence of this reader's data.
     * The input data of the new reader will start at position `index` in this reader and contain
     * up to `length` bytes. The two readers' positions and chunked reading modes will be independent.
     * The new reader's position will be zero, and its chunked reading mode will be false.
     *
     * @param int|null $index The position in this reader at which the data of the new reader will start.
     *                        Defaults to the current reader position.
     * @param int|null $length The length of the shared subsequence of data to supply to the new reader.
     *                         Defaults to the length of the remaining data starting from `index`.
     * @return EoReader The new reader.
     * @throws \ValueError If `index` or `length` is negative.
     */
    public function slice($index = null, $length = null): EoReader {
        if ($index === null) {
            $index = $this->position;
        }
        if ($length === null) {
            $length = max(0, count($this->data) - $index);
        }

        if ($index < 0 || $length < 0) {
            throw new \ValueError("Index or length cannot be negative: index $index, length $length");
        }
        $sliceData = array_slice($this->data, $index, $length);
        $newReader = new self($sliceData);

        return $newReader;
    }

    /**
     * Reads a raw byte from the input data.
     *
     * @return int A raw byte.
     */
    public function getByte(): int {
        return $this->readByte();
    }

    /**
     * Internal method to read a raw byte from the input data.
     *
     * @return int A raw byte.
     */
    private function readByte(): int {
        if ($this->getRemaining() > 0) {
            $byte = $this->data[$this->position++];
            return $byte;
        }
        return 0; // Indicate end of data
    }
    

    /**
     * Reads an array of raw bytes from the input data.
     *
     * @param int $length The number of bytes to read.
     * @return int[] An array of raw bytes.
     */
    public function getBytes($length): array {
        return $this->readBytes($length);
    }

    /**
     * Internal method to read an array of raw bytes from the input data.
     *
     * @param int $length The number of bytes to read.
     * @return int[] An array of raw bytes.
     */
    private function readBytes(int $length): array {
        $actualLength = min($length, $this->getRemaining());
        $bytes = array_slice($this->data, $this->position, $actualLength);
        $this->position += $actualLength;
        return $bytes;
    }

    /**
     * Reads an encoded 1-byte integer from the input data.
     *
     * @return int A decoded 1-byte integer.
     */
    public function getChar(): int {
        return NumberEncodingUtils::decodeNumber($this->readBytes(1));
    }

    /**
     * Reads an encoded 2-byte integer from the input data.
     *
     * @return int A decoded 2-byte integer.
     */
    public function getShort(): int {
        return NumberEncodingUtils::decodeNumber($this->readBytes(2));
    }

    /**
     * Reads an encoded 3-byte integer from the input data.
     *
     * @return int A decoded 3-byte integer.
     */
    public function getThree(): int {
        return NumberEncodingUtils::decodeNumber($this->readBytes(3));
    }

    /**
     * Reads an encoded 4-byte integer from the input data.
     *
     * @return int A decoded 4-byte integer.
     */
    public function getInt(): int {
        return NumberEncodingUtils::decodeNumber($this->readBytes(4));
    }

    /**
     * Reads a string from the input data by reading until the end.
     *
     * @return string A decoded string.
     */
    public function getString(): string {
        $bytes = $this->readBytes($this->getRemaining());
        return $this->decodeAnsi($bytes);
    }

    /**
     * Reads a string with a fixed length from the input data.
     *
     * @param int $length The length of the string.
     * @param bool $padded True if the string is padded with trailing `0xFF` bytes.
     * @return string A decoded string.
     * @throws \ValueError If the length is negative.
     */
    public function getFixedString($length, $padded = false): string {
        if ($length < 0) {
            throw new \ValueError("Negative length");
        }
        $bytes = $this->readBytes($length);
        if ($padded) {
            $bytes = $this->removePadding($bytes);
        }
        return $this->decodeAnsi($bytes);
    }

    /**
     * Reads an encoded string from the input data.
     *
     * @return string A decoded string.
     */
    public function getEncodedString(): string {
        $bytes = $this->readBytes($this->getRemaining());
        $bytes = StringEncodingUtils::decodeString($bytes);
        return $this->decodeAnsiString($bytes);
    }

    /**
     * Reads an encoded string with a fixed length from the input data.
     *
     * @param int $length The length of the string.
     * @param bool $padded True if the string is padded with trailing `0xFF` bytes.
     * @return string A decoded string.
     * @throws \ValueError If the length is negative.
     */
    public function getFixedEncodedString($length, $padded = false): string {
        if ($length < 0) {
            throw new \ValueError("Negative length");
        }
        $bytes = $this->readBytes($length);
        $bytesString = StringEncodingUtils::decodeString($bytes);
        $bytes = StringEncodingUtils::stringToBytes($bytesString);
        if ($padded) {
            $bytes = $this->removePadding($bytes);
        }
        return $this->decodeAnsi($bytes);
    }

    /**
     * Decodes windows-1252 bytes to a string.
     *
     * @param int[] $bytes The sequence of bytes to decode.
     * @return string The decoded string.
     */
    private function decodeAnsi(array $bytes): string {
        $byteString = pack('C*', ...$bytes);
        $result = iconv('Windows-1252', 'UTF-8//IGNORE', $byteString);
        return $result !== false ? $result : '';
    }

    /**
     * Decodes a windows-1252 string to UTF-8.
     *
     * @param string $string The string to decode.
     * @return string The decoded string.
     */
    private function decodeAnsiString(string $string): string {
        $result = iconv('Windows-1252', 'UTF-8//IGNORE', $string);
        return $result !== false ? $result : '';
    }

    /**
     * Removes padding (trailing 0xFF or 0x00 bytes) from a sequence of bytes.
     *
     * @param int[] $bytes The sequence of bytes.
     * @return int[] The bytes without padding.
     */
    private function removePadding(array $bytes): array {
        $length = count($bytes);
        for ($i = 0; $i < $length; $i++) {
            if ($bytes[$i] === 0xFF) { //|| $bytes[$i] === 0x00
                return array_slice($bytes, 0, $i);
            }
        }
        return $bytes;  // Return the original array if no padding was found.
    }

    /**
     * Gets the value of the chunked reading mode.
     * 
     * @return bool True if in chunked reading mode, false otherwise.
     */
    public function isChunkedReadingMode(): bool {
        return $this->chunkedReadingMode;
    }

    /**
     * Sets the chunked reading mode.
     * 
     * @param bool $value True to enable chunked reading mode, false to disable it.
     */
    public function setChunkedReadingMode($value): void {
        $this->chunkedReadingMode = $value;
        if ($this->nextBreak === -1) {
            $this->findNextBreakIndex();
        }
    }

    /**
     * Gets the current position of the reader in the input data.
     * 
     * @return int The current position.
     */
    public function getPosition(): int {
        return $this->position;
    }

    /**
     * Gets the number of bytes remaining in the current chunk or in the input data.
     *
     * @return int The number of bytes remaining.
     */
    public function getRemaining(): int {
        if ($this->chunkedReadingMode) {
            return $this->nextBreak - min($this->position, $this->nextBreak);
        }
        return count($this->data) - $this->position;
    }

    /**
     * Moves the reader position to the start of the next chunk in the input data.
     *
     * @throws \RuntimeException If not in chunked reading mode.
     */
    public function nextChunk(): void {
        if (!$this->chunkedReadingMode) {
            throw new \RuntimeException("Not in chunked reading mode");
        }

        $this->position = $this->nextBreak;
        if ($this->position < count($this->data)) {
            $this->position++; // Skip the break byte
        }
        $this->chunkStart = $this->position;
        $this->findNextBreakIndex();
    }

    /**
     * Finds the index of the next break byte (0xFF) in the input data.
     */
    private function findNextBreakIndex(): void {
        for ($i = $this->chunkStart; $i < count($this->data); $i++) {
            if ($this->data[$i] === 0xFF) {
                $this->nextBreak = $i;
                return;
            }
        }
        $this->nextBreak = count($this->data);
    }
}

