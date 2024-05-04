<?php

/**
 * Interleaves a sequence of bytes. When encrypting EO data, bytes are "woven" into each other.
 * This is an in-place operation.
 *
 * @param int[] $data Reference to the data to interleave.
 */
function interleave(array &$data): void
{
    $buffer = array_fill(0, count($data), 0);
    $i = 0;
    $ii = 0;

    while ($i < count($data)) {
        $buffer[$i] = $data[$ii];
        $i += 2;
        $ii += 1;
    }

    $i -= 1;

    if (count($data) % 2 != 0) {
        $i -= 2;
    }

    while ($i >= 0) {
        $buffer[$i] = $data[$ii];
        $i -= 2;
        $ii += 1;
    }

    $data = $buffer;
}

/**
 * Deinterleaves a sequence of bytes. This is the reverse of interleave.
 * This is an in-place operation.
 *
 * @param int[] $data Reference to the data to deinterleave.
 */
function deinterleave(array &$data): void
{
    $buffer = array_fill(0, count($data), 0);
    $i = 0;
    $ii = 0;

    while ($i < count($data)) {
        $buffer[$ii] = $data[$i];
        $i += 2;
        $ii += 1;
    }

    $i -= 1;

    if (count($data) % 2 != 0) {
        $i -= 2;
    }

    while ($i >= 0) {
        $buffer[$ii] = $data[$i];
        $i -= 2;
        $ii += 1;
    }

    $data = $buffer;
}

/**
 * Flips the most significant bits of each byte in a sequence of bytes.
 * Values 0 and 128 are not flipped.
 *
 * @param int[] $data Reference to the data to flip MSB on.
 */
function flipMsb(array &$data): void
{
    foreach ($data as &$byte) {
        if ($byte !== 0 && $byte !== 128) {
            $byte ^= 0x80;
        }
    }
}

/**
 * Swaps the order of contiguous bytes in a sequence that are divisible by a given multiple value.
 *
 * @param int[] $data Reference to the data to swap bytes in.
 * @param int $multiple The multiple value.
 */
function swapMultiples(array &$data, int $multiple): void
{
    if ($multiple < 1) {
        throw new \InvalidArgumentException("multiple must be a positive number");
    }

    $sequenceLength = 0;

    for ($i = 0; $i <= count($data); $i++) {
        if ($i != count($data) && $data[$i] % $multiple == 0) {
            $sequenceLength++;
        } else {
            if ($sequenceLength > 1) {
                for ($ii = 0; $ii < intdiv($sequenceLength, 2); $ii++) {
                    $tmp = $data[$i - $sequenceLength + $ii];
                    $data[$i - $sequenceLength + $ii] = $data[$i - $ii - 1];
                    $data[$i - $ii - 1] = $tmp;
                }
            }
            $sequenceLength = 0;
        }
    }
}