<?php

/**
 * Converts a PascalCase string to snake_case.
 *
 * @param string $name The PascalCase string to convert.
 * @return string The string converted to snake_case.
 */
function pascalCaseToSnakeCase($name)
{
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
}

/**
 * Converts a snake_case string to PascalCase.
 *
 * @param string $name The snake_case string to convert.
 * @return string The string converted to PascalCase.
 */
function snakeCaseToPascalCase($name)
{
    return str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($name))));
}

/**
 * Converts a camelCase string to snake_case.
 *
 * @param string $name The camelCase string to convert.
 * @return string The string converted to snake_case.
 */
function camelCaseToSnakeCase($name)
{
    return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
}

/**
 * Converts a camelCase string to PascalCase.
 *
 * @param string $name The camelCase string to convert.
 * @return string The string converted to PascalCase.
 */
function camelCaseToPascalCase($name)
{
    return ucfirst($name);
}

/**
 * Converts a snake_case string to camelCase.
 *
 * @param string|null $name The snake_case string to convert.
 * @return string|null The string converted to camelCase or null if input is null.
 */
function snakeCaseToCamelCase($name)
{
    if ($name === null) {
        return null;
    }
    return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
}

/**
 * Converts a string with \ as namespace separator to PascalCase.
 *
 * @param string $name The string with \ as namespace separator.
 * @return string The string converted to PascalCase.
 */
function namespaceToPascalCase($name)
{
    return str_replace(' ', '\\', ucwords(str_replace('\\', ' ', strtolower($name))));
}