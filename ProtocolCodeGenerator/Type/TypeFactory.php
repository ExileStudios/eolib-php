<?php

namespace ProtocolCodeGenerator\Type;

use SimpleXMLElement;
use ProtocolCodeGenerator\Type\Type;
use ProtocolCodeGenerator\Type\Length;
use ProtocolCodeGenerator\Type\BlobType;
use ProtocolCodeGenerator\Type\BoolType;
use ProtocolCodeGenerator\Type\EnumType;
use ProtocolCodeGenerator\Type\EnumValue;
use ProtocolCodeGenerator\Type\StringType;
use ProtocolCodeGenerator\Type\StructType;
use ProtocolCodeGenerator\Type\IntegerType;
use ProtocolCodeGenerator\Type\HasUnderlyingType;

/**
 * A factory for creating types.
 */
class TypeFactory
{
    /**
     * @var UnresolvedCustomType[]
     */
    private $unresolvedTypes = [];
    /**
     * @var Type[]|null[]
     */
    private $types = [];

    /**
     * Returns the type with the specified name and length.
     *
     * @param string $name The name of the type.
     * @param Length|null $length The length of the type.
     * @return Type The type with the specified name and length.
     */
    public function getType(string $name, ?Length $length = null): Type
    {
        if (!$length) {
            $length = Length::unspecified();
        }
        if ($length->isSpecified()) {
            return self::createTypeWithSpecifiedLength($name, $length);
        }
        if (!isset($this->types[$name])) {
            $this->types[$name] = $this->createType($name, $length);
        }
        return $this->types[$name];
    }

    /**
     * Defines a custom type from the specified XML element.
     *
     * @param SimpleXMLElement $xmlElement The XML element to define the custom type from.
     * @param string $sourcePath The path to the source file containing the XML element.
     * @return bool Whether the custom type was defined.
     */
    public function defineCustomType(SimpleXMLElement $xmlElement, string $sourcePath): bool
    {
        $name = (string)$xmlElement['name'];
        if (isset($this->unresolvedTypes[$name])) {
            return false;
        }
        $this->unresolvedTypes[$name] = new UnresolvedCustomType($xmlElement, $sourcePath);
        return true;
    }

    /**
     * Clears the type factory.
     */
    public function clear(): void
    {
        $this->unresolvedTypes = [];
        $this->types = [];
    }

    /**
     * Returns the names of the unresolved custom types.
     *
     * @return Type The names of the unresolved custom types.
     */
    private function createType(string $name, Length $length): Type
    {
        $underlyingType = $this->readUnderlyingType($name);
        if ($underlyingType !== null) {
            $name = substr($name, 0, (int)strpos($name, ":"));
        }

        $result = null;
        if (in_array($name, ["byte", "char"])) {
            $result = new IntegerType($name, 1);
        } elseif ($name === "short") {
            $result = new IntegerType($name, 2);
        } elseif ($name === "three") {
            $result = new IntegerType($name, 3);
        } elseif ($name === "int") {
            $result = new IntegerType($name, 4);
        } elseif ($name === "bool") {
            if ($underlyingType === null) {
                $underlyingType = $this->getType("char");
            }
            $result = new BoolType($underlyingType);
        } elseif (in_array($name, ["string", "encoded_string"])) {
            $result = new StringType($name, $length);
        } elseif ($name === "blob") {
            $result = new BlobType();
        } else {
            echo "Creating custom type: {$name}\n";
            $result = $this->createCustomType($name, $underlyingType);
        }

        if ($underlyingType !== null && !($result instanceof HasUnderlyingType)) {
            throw new \RuntimeException(
                "{$result->name()} has no underlying type, so {$underlyingType->name()} is not allowed "
                . "as an underlying type override."
            );
        }

        return $result;
    }

    /**
     * Reads the underlying type from the specified type name.
     *
     * @param string $name The name of the type.
     * @return Type|null The underlying type, or null if the type has no underlying type.
     */
    private function readUnderlyingType(string $name): ?Type
    {
        $parts = explode(":", $name);

        if (count($parts) === 1) {
            return null;
        } elseif (count($parts) === 2) {
            [$typeName, $underlyingTypeName] = $parts;
            if ($typeName === $underlyingTypeName) {
                throw new \RuntimeException("{$typeName} type cannot specify itself as an underlying type.");
            }
            $underlyingType = $this->getType($underlyingTypeName);
            if (!($underlyingType instanceof IntegerType)) {
                throw new \RuntimeException(
                    "{$underlyingType->name()} is not a numeric type, so it cannot be specified as "
                    . "an underlying type."
                );
            }
            return $underlyingType;
        } else {
            throw new \RuntimeException("\"{$name}\" type syntax is invalid. (Only one colon is allowed)");
        }
    }

    /**
     * Creates a custom type from the specified XML element.
     *
     * @param string $name The name of the custom type.
     * @param Type|null $underlyingTypeOverride The underlying type override, if any.
     * @return Type The custom type.
     */
    private function createCustomType(string $name, ?Type $underlyingTypeOverride): Type
    {
        $unresolvedType = $this->unresolvedTypes[$name] ?? null;
        if (!$unresolvedType) {
            throw new \RuntimeException("{$name} type is not defined.");
        }

        if ($unresolvedType->getTypeXml()->getName() === "enum") {
            return $this->createEnumType(
                $unresolvedType->getTypeXml(),
                $underlyingTypeOverride,
                $unresolvedType->getRelativePath()
            );
        } elseif ($unresolvedType->getTypeXml()->getName() === "struct") {
            return $this->createStructType($unresolvedType->getTypeXml(), $unresolvedType->getRelativePath());
        } else {
            throw new \RuntimeException('Unhandled CustomType xml element: <' . $unresolvedType->getTypeXml()->getName() . '>');
        }
    }

    /**
     * Creates an enum type from the specified XML element.
     *
     * @param SimpleXMLElement $xmlElement The XML element to create the enum type from.
     * @param Type|null $underlyingTypeOverride The underlying type override, if any.
     * @param string $relativePath The relative path to the source file containing the XML element.
     * @return EnumType The enum type.
     */
    private function createEnumType(SimpleXMLElement $xmlElement, ?Type $underlyingTypeOverride, string $relativePath): EnumType
    {
        $underlyingType = $underlyingTypeOverride;
        $enumName = (string)$xmlElement['name'];

        if ($underlyingType === null) {
            $underlyingTypeName = (string)$xmlElement['type'];
            if ($enumName === $underlyingTypeName) {
                throw new \RuntimeException("{$enumName} type cannot specify itself as an underlying type.");
            }

            $defaultUnderlyingType = $this->getType($underlyingTypeName);
            if (!($defaultUnderlyingType instanceof IntegerType)) {
                throw new \RuntimeException(
                    "{$defaultUnderlyingType->name()} is not a numeric type, so it cannot be "
                    . "specified as an underlying type."
                );
            }

            $underlyingType = $defaultUnderlyingType;
        }
        $protocolValues = $xmlElement->value;

        $values = [];
        $ordinals = [];
        $names = [];

        foreach ($protocolValues as $protocolValue) {
            $text = (string)$protocolValue;
            $ordinal = $text !== "" ? intval($text) : null;
            $valueName = (string)$protocolValue['name'];

            if ($ordinal === null) {
                throw new \RuntimeException("{$enumName}.{$valueName} has invalid ordinal value \"{$text}\"");
            }

            if (!in_array($ordinal, $ordinals)) {
                $ordinals[] = $ordinal;
            } else {
                throw new \RuntimeException("{$enumName}.{$valueName} cannot redefine ordinal value {$ordinal}.");
            }

            $values[] = new EnumValue($ordinal, $valueName);
        }

        return new EnumType($enumName, $relativePath, $underlyingType, $values);
    }

    /**
     * Creates a struct type from the specified XML element.
     *
     * @param SimpleXMLElement $xmlElement The XML element to create the struct type from.
     * @param string $relativePath The relative path to the source file containing the XML element.
     * @return StructType The struct type.
     */
    private function createStructType(SimpleXMLElement $xmlElement, string $relativePath): StructType
    {
        echo "Creating struct type: {$xmlElement['name']}\n";
        return new StructType(
            (string)$xmlElement['name'],
            $this->calculateFixedStructSize($xmlElement),
            $this->isBounded($xmlElement),
            $relativePath
        );
    }

    /**
     * Calculates the fixed size of a struct from the specified XML element.
     *
     * @param SimpleXMLElement $xmlElement The XML element to calculate the fixed size from.
     * @return int|null The fixed size of the struct, or null if the struct is not fixed-size.
     */
    private function calculateFixedStructSize(SimpleXMLElement $xmlElement): ?int
    {
        $size = 0;
        foreach (self::flattenInstructions($xmlElement) as $instruction) {
            $instructionSize = 0;
            if ($instruction->getName() === "field") {
                $instructionSize = $this->calculateFixedStructFieldSize($instruction);
            } elseif ($instruction->getName() === "array") {
                $instructionSize = $this->calculateFixedStructArraySize($instruction);
            } elseif ($instruction->getName() === "dummy") {
                $instructionSize = $this->calculateFixedStructDummySize($instruction);
            } elseif ($instruction->getName() === "chunked") {
                // Chunked reading is not allowed in fixed-size structs
                return null;
            } elseif ($instruction->getName() === "switch") {
                // Switch sections are not allowed in fixed-sized structs
                return null;
            }

            if ($instructionSize === null || !is_numeric($instructionSize)) {
                return null;
            }

            $size += $instructionSize;
        }

        return (int)$size;
    }

    /**
     * Calculates the fixed size of a field from the specified XML element.
     *
     * @param SimpleXMLElement $xmlElement The XML element to calculate the fixed size from.
     * @return int|null The fixed size of the field, or null if the field is not fixed-size.
     */
    private function calculateFixedStructFieldSize(SimpleXMLElement $xmlElement): ?int
    {
        $typeName = (string)$xmlElement['type'];
        $typeLength = self::createTypeLengthForField($xmlElement);
        $typeInstance = $this->getType($typeName, $typeLength);
        $fieldSize = $typeInstance->fixedSize();
        $fieldSizeInt = is_numeric($fieldSize) ? intval($fieldSize) : null;

        if ($fieldSize === null) {
            // All fields in a fixed-size struct must also be fixed-size
            return null;
        }

        if ($xmlElement['optional']) {
            // Nothing can be optional in a fixed-size struct
            return null;
        }

        return $fieldSize;
    }

    /**
     * Calculates the fixed size of an array from the specified XML element.
     *
     * @param SimpleXMLElement $xmlElement The XML element to calculate the fixed size from.
     * @return int|null The fixed size of the array, or null if the array is not fixed-size.
     */
    private function calculateFixedStructArraySize(SimpleXMLElement $xmlElement): ?int
    {
        $lengthString = (string)$xmlElement['length'];
        $length = is_numeric($lengthString) ? intval($lengthString) : null;

        if ($length === null) {
            // An array cannot be fixed-size unless a numeric length attribute is provided
            return null;
        }

        $typeName = (string)$xmlElement['type'];
        $typeInstance = $this->getType($typeName);

        $elementSize = $typeInstance->fixedSize();

        if ($elementSize === null || !is_numeric($elementSize)) {
            // An array cannot be fixed-size unless its elements are also fixed-size
            // All arrays in a fixed-size struct must also be fixed-size
            return null;
        }

        if ($xmlElement['optional']) {
            // Nothing can be optional in a fixed-size struct
            return null;
        }

        if ($xmlElement['delimited']) {
            // It's possible to omit data or insert garbage data at the end of each chunk
            return null;
        }

        return (int)($length * $elementSize);
    }

    /**
     * Calculates the fixed size of a dummy field from the specified XML element.
     *
     * @param SimpleXMLElement $xmlElement The XML element to calculate the fixed size from.
     * @return int|null The fixed size of the dummy field, or null if the dummy field is not fixed-size.
     */
    private function calculateFixedStructDummySize(SimpleXMLElement $xmlElement): ?int
    {
        $typeName = (string)$xmlElement['type'];
        $typeInstance = $this->getType($typeName);
        $dummySize = $typeInstance->fixedSize();

        if ($dummySize === null) {
            // All dummy fields in a fixed-size struct must also be fixed-size
            return null;
        }

        return $dummySize;
    }

    /**
     * Determines whether the specified XML element is bounded.
     *
     * @param SimpleXMLElement $xmlElement The XML element to determine whether it is bounded.
     * @return bool Whether the XML element is bounded.
     */
    private function isBounded(SimpleXMLElement $xmlElement): bool
    {
        $result = true;

        foreach (self::flattenInstructions($xmlElement) as $instruction) {
            if (!$result) {
                $result = $instruction->getName() === "break";
                continue;
            }

            if ($instruction->getName() === "field") {
                $fieldType = $this->getType(
                    (string)$instruction['type'],
                    self::createTypeLengthForField($instruction)
                );
                $result = $fieldType->bounded();
            } elseif ($instruction->getName() === "array") {
                $elementType = $this->getType((string)$instruction['type']);
                $length = (string)$instruction['length'];
                $result = $elementType->bounded() && $length !== null;
            } elseif ($instruction->getName() === "dummy") {
                $dummyType = $this->getType((string)$instruction['type']);
                $result = $dummyType->bounded();
            }
        }

        return $result;
    }

    /**
    * Flattens the instructions from a SimpleXMLElement into a single array.
    *
    * @param SimpleXMLElement $xmlElement The XML element to process.
    * @param SimpleXMLElement[]|null $result The array to which the results are added.
    * @return SimpleXMLElement[] The flattened list of instructions.
    */
    private static function flattenInstructions(SimpleXMLElement $xmlElement, ?array $result = null): array
    {
        if ($result === null) {
            $result = [];
        }

        foreach ($xmlElement->children() as $instruction) {
            $result[] = $instruction;

            if ($instruction->getName() === "chunked") {
                foreach ($instruction->children() as $chunkedInstruction) {
                    self::flattenInstructions($chunkedInstruction, $result);
                }
            } elseif ($instruction->getName() === "switch") {
                $protocolCases = $instruction->case;
                foreach ($protocolCases as $protocolCase) {
                    foreach ($protocolCase->children() as $caseInstruction) {
                        self::flattenInstructions($caseInstruction, $result);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Creates a Length object for a field from the specified XML element.
     *
     * @param SimpleXMLElement $xmlElement The XML element to create the Length object from.
     * @return Length The Length object.
     */
    private static function createTypeLengthForField(SimpleXMLElement $xmlElement): Length
    {
        $lengthString = (string)$xmlElement['length'];
        if ($lengthString != null) {
            return Length::fromString($lengthString);
        } else {
            return Length::unspecified();
        }
    }

    /**
     * Creates a type with the specified name and length.
     *
     * @param string $name The name of the type.
     * @param Length $length The length of the type.
     * @return Type The type with the specified name and length.
     */
    private static function createTypeWithSpecifiedLength(string $name, Length $length): Type
    {
        if (in_array($name, ["string", "encoded_string"])) {
            return new StringType($name, $length);
        } else {
            throw new \RuntimeException(
                "{$name} type with length {$length} is invalid. "
                . '(Only string types may specify a length)'
            );
        }
    }
}

/**
 * Represents an unresolved custom type.
 */
class UnresolvedCustomType
{
    private SimpleXMLElement $typeXml;
    private string $relativePath;

    /**
     * Creates a new UnresolvedCustomType instance.
     *
     * @param SimpleXMLElement $typeXml The XML element representing the unresolved custom type.
     * @param string $relativePath The relative path to the source file containing the XML element.
     */
    public function __construct(SimpleXMLElement $typeXml, string $relativePath)
    {
        $this->typeXml = $typeXml;
        $this->relativePath = $relativePath;
    }

    /**
     * Returns the XML element representing the unresolved custom type.
     *
     * @return SimpleXMLElement The XML element representing the unresolved custom type.
     */
    public function getTypeXml(): SimpleXMLElement
    {
        return $this->typeXml;
    }

    /**
     * Returns the relative path to the source file containing the XML element.
     *
     * @return string The relative path to the source file containing the XML element.
     */
    public function getRelativePath(): string
    {
        return $this->relativePath;
    }
}
