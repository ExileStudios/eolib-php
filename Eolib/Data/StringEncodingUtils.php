<?php

/**
 * Encodes a string by inverting its characters and reversing the entire string.
 *
 * This function applies a transformation that inverts each character's ASCII value
 * within a specific range and then reverses the order of the entire string. This method
 * is used to obfuscate strings in a reversible way.
 *
 * @param string $bytes The string to encode, passed by reference.
 */
function encodeString(&$bytes) {
    invertCharacters($bytes);
    $bytes = strrev($bytes);
}

/**
 * Decodes a previously encoded string by reversing its order and inverting its characters.
 *
 * This function reverses the operations performed by encodeString, restoring the original
 * text from an encoded string by first reversing its order and then inverting its characters.
 *
 * @param string $bytes The encoded string to decode, passed by reference.
 */
function decodeString(&$bytes) {
    $bytes = strrev($bytes);
    invertCharacters($bytes);
}

/**
 * Inverts characters within an ASCII range to transform a string.
 *
 * This internal function inverts characters within the printable ASCII range (0x22 to 0x7E)
 * by applying a transformation that reflects each character around a central point in the range.
 * The inversion can be adjusted slightly based on whether the character's index is odd or even,
 * which adds a level of obfuscation.
 *
 * @param string $bytes The string whose characters are to be inverted, passed by reference.
 */
function invertCharacters(&$bytes) {
    $flippy = strlen($bytes) % 2 === 1;

    for ($i = 0; $i < strlen($bytes); $i++) {
        $c = ord($bytes[$i]);
        $f = 0;

        if ($flippy) {
            $f = 0x2E;
            if ($c >= 0x50) {
                $f *= -1;
            }
        }

        if ($c >= 0x22 && $c <= 0x7E) {
            $bytes[$i] = chr(0x9F - $c - $f);
        }

        $flippy = !$flippy;
    }
}
