<?php

namespace Eolib\Data;

use Eolib\Data\EoNumericLimits;

/**
 * A class for encoding and decoding numeric values according to the EO protocol.
 *
 * This class provides functions for encoding and decoding numeric values into
 * a format that is compatible with the EO protocol requirements. The encoding
 * format is a variable-length encoding that uses a sequence of bytes to represent
 * integers up to the size of an unsigned four-byte integer.
 */
class NumberEncodingUtils {
    /**
     * Encodes a numeric value into a sequence of bytes, ensuring compatibility
     * with the EO data size specifications.
     *
     * This function is used to convert a numeric integer into a specific format
     * that uses variable length encoding based on the EO protocol requirements.
     * It supports encoding integers up to the size of an unsigned four-byte integer.
     *
     * @param int $number The number to encode.
     * @return int[] The encoded number as a string of bytes.
     */
    public static function encodeNumber(int $number): array {
        $value = $number;
        $d = 0xFE;
        if ($number >= EoNumericLimits::EO_THREE_MAX) {
            $d = intdiv($value, EoNumericLimits::EO_THREE_MAX) + 1;
            $value %= EoNumericLimits::EO_THREE_MAX;
        }
        $c = 0xFE;
        if ($number >= EoNumericLimits::EO_SHORT_MAX) {
            $c = intdiv($value, EoNumericLimits::EO_SHORT_MAX) + 1;
            $value %= EoNumericLimits::EO_SHORT_MAX;
        }
        $b = 0xFE;
        if ($number >= EoNumericLimits::EO_CHAR_MAX) {
            $b = intdiv($value, EoNumericLimits::EO_CHAR_MAX) + 1;
            $value = $value % EoNumericLimits::EO_CHAR_MAX;
        }
        $a = $value + 1;
        return [$a, $b, $c, $d];
    }

    /**
     * Decodes a string of bytes back into an integer, according to the EO protocol
     * encoding specification.
     *
     * This function reverses the operation performed by encodeNumber, turning
     * a sequence of bytes back into the original integer value. It supports
     * decoding from sequences up to four bytes long.
     *
     * @param int[] $encodedNumber The encoded number as a string of bytes.
     * @return int The decoded integer.
     */
    public static function decodeNumber(array $encodedNumber): int {
        $result = 0;
        $length = min(count($encodedNumber), 4);

        for ($i = 0; $i < $length; $i++) {
            $value = $encodedNumber[$i];

            if ($value === 0xFE) {
                break;
            }

            $value -= 1;

            switch ($i) {
                case 0:
                    $result += $value;
                    break;
                case 1:
                    $result += EoNumericLimits::EO_CHAR_MAX * $value;
                    break;
                case 2:
                    $result += EoNumericLimits::EO_SHORT_MAX * $value;
                    break;
                case 3:
                    $result += EoNumericLimits::EO_THREE_MAX * $value;
                    break;
            }
        }

        return $result;
    }
}