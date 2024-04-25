<?php

/**
 * Retrieves instructions from the given XML element.
 *
 * @param \SimpleXMLElement $element The XML element to retrieve instructions from.
 * @return array An array of instruction elements.
 */
function getInstructions($element)
{
    $instructions = [];
    foreach ($element->children() as $child) {
        if ($child instanceof \SimpleXMLElement && in_array($child->getName(), ["field", "array", "length", "dummy", "switch", "chunked", "break"])) {
            $instructions[] = $child;
        }
    }
    return $instructions;
}

/**
 * Retrieves a string attribute from the given XML element.
 *
 * @param \SimpleXMLElement $element The XML element to retrieve the attribute from.
 * @param string $name The name of the attribute.
 * @param string|null $defaultValue The default value to return if the attribute is not found.
 * @return string The attribute value or the default value if the attribute is not found.
 */
function getStringAttribute($element, $name, $defaultValue = null)
{
    $attributeText = (string)$element[$name];
    return $attributeText !== "" ? $attributeText : $defaultValue;
}

/**
 * Retrieves an integer attribute from the given XML element.
 *
 * @param \SimpleXMLElement $element The XML element to retrieve the attribute from.
 * @param string $name The name of the attribute.
 * @param int $defaultValue The default value to return if the attribute is not found.
 * @return int The attribute value as an integer or the default value if the attribute is not found.
 * @throws ValueError If the attribute value is not a valid integer.
 */
function getIntAttribute($element, $name, $defaultValue = 0)
{
    $attributeText = (string)$element[$name];
    if ($attributeText === "") {
        return $defaultValue;
    }
    if (!ctype_digit($attributeText)) {
        throw new ValueError("{$name} attribute has an invalid integer value: {$attributeText}");
    }
    return (int)$attributeText;
}

/**
 * Retrieves a boolean attribute from the given XML element.
 *
 * @param \SimpleXMLElement $element The XML element to retrieve the attribute from.
 * @param string $name The name of the attribute.
 * @param bool $defaultValue The default value to return if the attribute is not found.
 * @return bool The attribute value as a boolean or the default value if the attribute is not found.
 */
function getBooleanAttribute($element, $name, $defaultValue = false)
{
    $attributeText = (string)$element[$name];
    return $attributeText !== "" ? strtolower($attributeText) === "true" : $defaultValue;
}

/**
 * Retrieves a required string attribute from the given XML element.
 *
 * @param \SimpleXMLElement $element The XML element to retrieve the attribute from.
 * @param string $name The name of the attribute.
 * @return string The attribute value.
 * @throws ValueError If the required attribute is missing.
 */
function getRequiredStringAttribute($element, $name)
{
    $attributeValue = (string)$element[$name];
    if ($attributeValue === "") {
        throw new ValueError("Required attribute \"{$name}\" is missing.");
    }
    return $attributeValue;
}

/**
 * Retrieves a required integer attribute from the given XML element.
 *
 * @param \SimpleXMLElement $element The XML element to retrieve the attribute from.
 * @param string $name The name of the attribute.
 * @return int The attribute value as an integer.
 * @throws ValueError If the required attribute is missing or has an invalid integer value.
 */
function getRequiredIntAttribute($element, $name)
{
    $attributeValue = (string)$element[$name];
    if ($attributeValue === "") {
        throw new ValueError("Required attribute \"{$name}\" is missing.");
    }
    if (!ctype_digit($attributeValue)) {
        throw new ValueError("{$name} attribute has an invalid integer value: {$attributeValue}");
    }
    return (int)$attributeValue;
}

/**
 * Retrieves a required boolean attribute from the given XML element.
 *
 * @param \SimpleXMLElement $element The XML element to retrieve the attribute from.
 * @param string $name The name of the attribute.
 * @return bool The attribute value as a boolean.
 * @throws ValueError If the required attribute is missing.
 */
function getRequiredBooleanAttribute($element, $name)
{
    $attributeValue = (string)$element[$name];
    if ($attributeValue === "") {
        throw new ValueError("Required attribute \"{$name}\" is missing.");
    }
    return strtolower($attributeValue) === "true";
}