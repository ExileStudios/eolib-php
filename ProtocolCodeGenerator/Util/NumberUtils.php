<?php

/**
 * Casts a numeric string to an integer if possible.
 *
 * @param mixed $value The value to cast.
 * @return int|mixed The value as an integer or the original value.
 */
function tryCastInt($value)
{
    if (is_string($value) && is_numeric($value) && (string)(int)$value === $value) {
        return (int)$value;
    }
    return $value;
}