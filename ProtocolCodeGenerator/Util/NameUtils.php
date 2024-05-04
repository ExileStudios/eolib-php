<?php

/**
 * Converts a PascalCase string to snake_case.
 *
 * @param string $name The PascalCase string to convert.
 * @return string The string converted to snake_case.
 */
function pascalCaseToSnakeCase(string $name): string
{
    $name = preg_replace('/(?<!^)[A-Z]/', '_$0', $name);
    if ($name === null) {
        return '';
    }
    return strtolower($name);
}

/**
 * Converts a snake_case string to PascalCase.
 *
 * @param string $name The snake_case string to convert.
 * @return string The string converted to PascalCase.
 */
function snakeCaseToPascalCase(string $name): string
{
    return str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($name))));
}

/**
 * Converts a camelCase string to snake_case.
 *
 * @param string $name The camelCase string to convert.
 * @return string The string converted to snake_case.
 */
function camelCaseToSnakeCase(string $name): string
{
    $name = preg_replace('/[A-Z]/', '_$0', lcfirst($name));
    if ($name === null) {
        return '';
    }
    return strtolower($name);
}

/**
 * Converts a camelCase string to PascalCase.
 *
 * @param string $name The camelCase string to convert.
 * @return string The string converted to PascalCase.
 */
function camelCaseToPascalCase(string $name): string
{
    return ucfirst($name);
}

/**
 * Converts a snake_case string to camelCase.
 *
 * @param string $name The snake_case string to convert.
 * @return string The string converted to camelCase.
 */
function snakeCaseToCamelCase(string $name): string
{
    return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
}

/**
 * Converts a string with / or \ as namespace separators to PascalCase.
 * Each segment of the namespace is capitalized independently, ensuring that 
 * even incorrectly cased inputs are handled correctly.
 *
 * @param string $name The string with namespace separators.
 * @return string The string converted to PascalCase.
 */
function namespaceToPascalCase(string $name): string {
    $name = str_replace('/', '\\', $name);
    $segments = explode('\\', trim($name, '\\'));

    // Transform each segment to PascalCase
    $transformed = array_map(function($segment) {
        return ucfirst(strtolower($segment));
    }, $segments);

    $finalNamespace = implode('\\', $transformed);
    return $finalNamespace;
}


