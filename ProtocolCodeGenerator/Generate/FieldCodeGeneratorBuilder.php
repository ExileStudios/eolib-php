<?php

namespace ProtocolCodeGenerator\Generate;

use SimpleXMLElement;
use ProtocolCodeGenerator\Generate\FieldCodeGenerator;

/**
 * Constructs FieldCodeGenerator instances, allowing detailed configuration of field properties for code generation.
 * This builder provides a fluent interface for setting field attributes used in serializing and deserializing operations.
 */
class FieldCodeGeneratorBuilder
{
    private $typeFactory;
    private $context;
    private $data;
    private $name;
    private $type;
    private $length;
    private $offset = 0;
    private $padded = false;
    private $optional = false;
    private $hardcodedValue;
    private $comment;
    private $arrayField = false;
    private $lengthField = false;
    private $delimited = false;
    private $trailingDelimiter = false;

    /**
     * Constructs a new FieldCodeGeneratorBuilder instance with the provided type factory, context, and data.
     *
     * @param mixed $typeFactory The type factory for the field.
     * @param mixed $context The context of the field.
     * @param mixed $data The data associated with the field.
     */
    public function __construct($typeFactory, $context, $data)
    {
        $this->typeFactory = $typeFactory;
        $this->context = $context;
        $this->data = $data;
    }

    /**
     * Extracts the value from a SimpleXMLElement if it is one, otherwise returns the original value.
     *
     * @param mixed $value The value to be processed.
     * @return mixed The extracted value if it is a SimpleXMLElement, otherwise the original value.
     */
    private function getValueIfSimpleXMLElement($value)
    {
        if ($value instanceof SimpleXMLElement) {
            return (string)$value[0];
        }
        return $value;
    }

    /**
     * Specifies the name of the field.
     *
     * @param string $name The name to be assigned to the field.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Defines the data type of the field.
     *
     * @param string $type The type of the field.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function type($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Sets the length of the field, which can be a fixed value or variable based on another field.
     *
     * @param mixed $length The length attribute of the field, supporting both integers and references to other fields.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function length($length)
    {
        $this->length = $length;
        return $this;
    }

    /**
     * Configures whether the field should be padded to meet its defined length.
     *
     * @param bool $padded Indicates if the field should be padded.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function padded($padded)
    {
        $this->padded = $padded;
        return $this;
    }

    /**
     * Marks the field as optional, allowing it to be omitted in some contexts.
     *
     * @param bool $optional Specifies if the field is optional.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function optional($optional)
    {
        $this->optional = $optional;
        return $this;
    }

    /**
     * Assigns a hardcoded value to the field, used when the field value is constant.
     *
     * @param string $hardcodedValue The value that will always be used for this field.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function hardcodedValue($hardcodedValue)
    {
        $this->hardcodedValue = $hardcodedValue;
        return $this;
    }

    /**
     * Adds a description or note to the field, typically used for explaining the purpose or usage of the field in generated documentation.
     *
     * @param string $comment A descriptive comment about the field.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function comment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Indicates whether the field represents a collection of elements.
     *
     * @param bool $arrayField Specifies if the field is an array of elements.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function arrayField($arrayField)
    {
        $this->arrayField = $arrayField;
        return $this;
    }

    /**
     * Determines if elements of an array field should be separated by a delimiter.
     *
     * @param bool $delimited Indicates whether a delimiter is used between array elements.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function delimited($delimited)
    {
        $this->delimited = $delimited;
        return $this;
    }

    /**
     * Sets whether a trailing delimiter should be included after the last element in a delimited array field.
     *
     * @param bool $trailingDelimiter Specifies if a trailing delimiter is needed.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function trailingDelimiter($trailingDelimiter)
    {
        $this->trailingDelimiter = $trailingDelimiter;
        return $this;
    }

    /**
     * Configures the field to act as a reference for determining the length of another field.
     *
     * @param bool $lengthField Specifies if this field serves as a length field.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function lengthField($lengthField)
    {
        $this->lengthField = $lengthField;
        return $this;
    }

    /**
     * Adjusts the offset applied during length calculation of a field.
     *
     * @param int $offset The offset value to be applied.
     * @return FieldCodeGeneratorBuilder This builder instance to allow method chaining.
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Completes the configuration and constructs a FieldCodeGenerator object.
     *
     * @return FieldCodeGenerator The fully configured FieldCodeGenerator instance.
     * @throws InvalidArgumentException If essential attributes are not provided or improperly configured.
     */
    public function build()
    {
        // Validate necessary conditions first
        if ($this->type === null) {
            throw new \InvalidArgumentException("Type must be provided");
        }

        // Pre-process all fields that could be SimpleXMLElements
        $processedTypeFactory = $this->getValueIfSimpleXMLElement($this->typeFactory);
        $processedContext = $this->getValueIfSimpleXMLElement($this->context);
        $processedData = $this->getValueIfSimpleXMLElement($this->data);
        $name = $this->getValueIfSimpleXMLElement($this->name);
        $processedName = $name !== null ? snakeCaseToCamelCase($name) : null;
        $processedType = $this->getValueIfSimpleXMLElement($this->type);
        $length = $this->getValueIfSimpleXMLElement($this->length);
        $processedLength = $length !== null ? snakeCaseToCamelCase($length) : null;
        $processedPadded = $this->getValueIfSimpleXMLElement($this->padded);
        $processedOptional = $this->getValueIfSimpleXMLElement($this->optional);
        $processedHardcodedValue = $this->getValueIfSimpleXMLElement($this->hardcodedValue);
        $processedComment = $this->getValueIfSimpleXMLElement($this->comment);
        $processedArrayField = $this->getValueIfSimpleXMLElement($this->arrayField);
        $processedDelimited = $this->getValueIfSimpleXMLElement($this->delimited);
        $processedTrailingDelimiter = $this->getValueIfSimpleXMLElement($this->trailingDelimiter);
        $processedLengthField = snakeCaseToCamelCase($this->getValueIfSimpleXMLElement($this->lengthField));
        $processedOffset = $this->getValueIfSimpleXMLElement($this->offset);

        // Create and return the FieldCodeGenerator object
        return new FieldCodeGenerator(
            $processedTypeFactory,
            $processedContext,
            $processedData,
            $processedName,
            $processedType,
            $processedLength,
            $processedPadded,
            $processedOptional,
            $processedHardcodedValue,
            $processedComment,
            $processedArrayField,
            $processedDelimited,
            $processedTrailingDelimiter,
            $processedLengthField,
            $processedOffset
        );
    }
}