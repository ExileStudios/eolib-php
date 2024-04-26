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
    private $data;
    private $position = 0;
    private $chunkedReadingMode = false;
    private $chunkStart = 0;
    private $nextBreak = -1;

    /**
     * Creates a new EoReader instance for the specified data.
     *
     * @param string $data The byte string containing the input data.
     */
    public function __construct($data) {
        $this->data = array_values(unpack('C*', $data));
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
    public function slice($index = null, $length = null) {
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
        $newReader = new self(pack('C*', ...$sliceData));
        $newReader->resetChunkedReading();

        return $newReader;
    }

    /**
     * Reads a raw byte from the input data.
     *
     * @return int A raw byte.
     */
    public function getByte() {
        return $this->readByte();
    }

    /**
     * Internal method to read a raw byte from the input data.
     *
     * @return int A raw byte.
     */
    private function readByte() {
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
     * @return string An array of raw bytes.
     */
    public function getBytes($length) {
        return $this->readBytes($length);
    }

    /**
     * Internal method to read an array of raw bytes from the input data.
     *
     * @param int $length The number of bytes to read.
     * @return string An array of raw bytes.
     */
    private function readBytes($length) {
        $length = min($length, $this->getRemaining());
        $bytes = array_slice($this->data, $this->position, $length);
        $this->position += $length;
        return pack('C*', ...$bytes);
    }

    /**
     * Reads an encoded 1-byte integer from the input data.
     *
     * @return int A decoded 1-byte integer.
     */
    public function getChar() {
        return NumberEncodingUtils::decodeNumber($this->readBytes(1));
    }

    /**
     * Reads an encoded 2-byte integer from the input data.
     *
     * @return int A decoded 2-byte integer.
     */
    public function getShort() {
        return NumberEncodingUtils::decodeNumber($this->readBytes(2));
    }

    /**
     * Reads an encoded 3-byte integer from the input data.
     *
     * @return int A decoded 3-byte integer.
     */
    public function getThree() {
        return NumberEncodingUtils::decodeNumber($this->readBytes(3));
    }

    /**
     * Reads an encoded 4-byte integer from the input data.
     *
     * @return int A decoded 4-byte integer.
     */
    public function getInt() {
        return NumberEncodingUtils::decodeNumber($this->readBytes(4));
    }

    /**
     * Reads a string from the input data by reading until the end.
     *
     * @return string A decoded string.
     */
    public function getString() {
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
    public function getFixedString($length, $padded = false) {
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
    public function getEncodedString() {
        $bytes = $this->readBytes($this->getRemaining());
        decodeString($bytes);
        return $this->decodeAnsi($bytes);
    }

    /**
     * Reads an encoded string with a fixed length from the input data.
     *
     * @param int $length The length of the string.
     * @param bool $padded True if the string is padded with trailing `0xFF` bytes.
     * @return string A decoded string.
     * @throws \ValueError If the length is negative.
     */
    public function getFixedEncodedString($length, $padded = false) {
        if ($length < 0) {
            throw new \ValueError("Negative length");
        }
        $bytes = $this->readBytes($length);
        decodeString($bytes);
        if ($padded) {
            $bytes = $this->removePadding($bytes);
        }
        return $this->decodeAnsi($bytes);
    }

    /**
     * Decodes windows-1252 bytes to a string.
     *
     * @param string $bytes The sequence of bytes to decode.
     * @return string The decoded string.
     */
    private function decodeAnsi($bytes) {
        return iconv('Windows-1252', 'UTF-8//IGNORE', $bytes);
    }

    /**
     * Removes padding (trailing 0xFF bytes) from a sequence of bytes.
     *
     * @param string $bytes The sequence of bytes.
     * @return string The bytes without padding.
     */
    private function removePadding($bytes) {
        $position = strpos($bytes, "\xFF");
        return $position === false ? $bytes : substr($bytes, 0, $position);
    }

    /**
     * Gets the number of bytes remaining in the current chunk or in the input data.
     *
     * @return int The number of bytes remaining.
     */
    public function getRemaining() {
        if ($this->chunkedReadingMode) {
            return $this->nextBreak - $this->position;
        }
        return count($this->data) - $this->position;
    }

    /**
     * Moves the reader position to the start of the next chunk in the input data.
     *
     * @throws \RuntimeException If not in chunked reading mode.
     */
    public function nextChunk() {
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
    private function findNextBreakIndex() {
        for ($i = $this->chunkStart; $i < count($this->data); $i++) {
            if ($this->data[$i] === 0xFF) {
                $this->nextBreak = $i;
                return;
            }
        }
        $this->nextBreak = count($this->data);
    }
}

