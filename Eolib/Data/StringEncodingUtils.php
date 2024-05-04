<?php

namespace Eolib\Data;

/**
 * Provides utility functions for encoding and decoding strings.
 *
 * This class provides static utility functions for encoding and decoding strings
 * in a reversible manner. The encoding process inverts the characters of a string
 * within a specific ASCII range and then reverses the entire string. The decoding
 * process reverses the string and then inverts the characters back to their original
 * values. This method is used to obfuscate strings in a reversible way.
 */
class StringEncodingUtils {
    /**
     * Encodes a string by inverting its characters and reversing the entire string.
     *
     * This function applies a transformation that inverts each character's ASCII value
     * within a specific range and then reverses the order of the entire string. This method
     * is used to obfuscate strings in a reversible way.
     *
     * @param string $string The string to encode
     * @return int[] The encoded string as an array of bytes.
     */
    public static function encodeString(string $string) : array {
        $bytes = self::stringToBytes($string);
        self::invertCharacters($bytes);
        $bytes = array_reverse($bytes);
        return $bytes;
    }

    /**
     * Decodes a previously encoded string by reversing its order and inverting its characters.
     *
     * This function reverses the operations performed by encodeString, restoring the original
     * text from an encoded string by first reversing its order and then inverting its characters.
     *
     * @param int[] $bytes The encoded string to decode.
     * @return string The decoded string.
     */
    public static function decodeString(array $bytes) : string {
        $bytes = array_reverse($bytes);
        self::invertCharacters($bytes);
        return self::bytesToString($bytes);
    }

    /**
     * Converts a string to an array of bytes.
     *
     * This function converts a string to an array of bytes, where each byte represents
     * a character in the string. The bytes are stored in an array in the order they appear
     * in the string.
     *
     * @param string $string The string to convert.
     * @return int[] An array of bytes representing the string.
     */
    public static function stringToBytes(string $string) : array {
        $bytes = [];
        for ($i = 0; $i < mb_strlen($string, "UTF-8"); $i++) {
            $bytes[] = mb_ord(mb_substr($string, $i, 1, "UTF-8"), "UTF-8");
        }
        return $bytes;
    }

    /**
     * Converts an array of bytes to a string.
     *
     * This function converts an array of bytes to a string, where each byte in the array
     * represents a character in the string. The bytes are concatenated in the order they
     * appear in the array.
     *
     * @param int[] $bytes The array of bytes to convert.
     * @return string The string represented by the bytes.
     */
    public static function bytesToString($bytes) : string {
        $string = "";
        foreach ($bytes as $byte) {
            $string .= mb_chr($byte, "UTF-8");
        }
        return $string;
    }

    /**
     * Inverts characters within an ASCII range to transform a string.
     *
     * This internal function inverts characters within the printable ASCII range (0x22 to 0x7E)
     * by applying a transformation that reflects each character around a central point in the range.
     * The inversion can be adjusted slightly based on whether the character's index is odd or even,
     * which adds a level of obfuscation.
     *
     * @param int[] $bytes The string whose characters are to be inverted, passed by reference.
     */
    private static function invertCharacters(&$bytes): void {
        $flippy = count($bytes) % 2 === 1;
    
        for ($i = 0; $i < count($bytes); $i++) {
            $c = $bytes[$i];
            $f = 0;
    
            if ($flippy) {
                $f = 0x2E;
                if ($c >= 0x50) {
                    $f *= -1;
                }
            }
    
            if ($c >= 0x22 && $c <= 0x7E) {
                $bytes[$i] = 0x9F - $c - $f;
            }
    
            $flippy = !$flippy;
        }
    }
}
