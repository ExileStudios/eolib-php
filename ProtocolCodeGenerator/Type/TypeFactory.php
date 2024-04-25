<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\BlobType;
use ProtocolCodeGenerator\Type\BoolType;
use ProtocolCodeGenerator\Type\EnumType;
use ProtocolCodeGenerator\Type\EnumValue;
use ProtocolCodeGenerator\Type\HasUnderlyingType;
use ProtocolCodeGenerator\Type\IntegerType;
use ProtocolCodeGenerator\Type\Length;
use ProtocolCodeGenerator\Type\StringType;
use ProtocolCodeGenerator\Type\StructType;

class TypeFactory
{
    private $unresolvedTypes = [];
    private $types = [];

    public function getType(string $name, $length = null)
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

    public function defineCustomType($protocolType, $sourcePath)
    {
        $name = (string)$protocolType['name'];
        if (isset($this->unresolvedTypes[$name])) {
            return false;
        }
        $this->unresolvedTypes[$name] = new UnresolvedCustomType($protocolType, $sourcePath);
        return true;
    }

    public function clear()
    {
        $this->unresolvedTypes = [];
        $this->types = [];
    }

    private function createType($name, $length)
    {
        $underlyingType = $this->readUnderlyingType($name);
        if ($underlyingType !== null) {
            $name = substr($name, 0, strpos($name, ":"));
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
            $result = $this->createCustomType($name, $underlyingType);
        }

        if ($underlyingType !== null && !($result instanceof HasUnderlyingType)) {
            throw new \RuntimeException(
                "{$result->name} has no underlying type, so {$underlyingType->name} is not allowed "
                . "as an underlying type override."
            );
        }

        return $result;
    }

    private function readUnderlyingType($name)
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
                    "{$underlyingType->name} is not a numeric type, so it cannot be specified as "
                    . "an underlying type."
                );
            }
            return $underlyingType;
        } else {
            throw new \RuntimeException("\"{$name}\" type syntax is invalid. (Only one colon is allowed)");
        }
    }

    private function createCustomType($name, $underlyingTypeOverride)
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

    private function createEnumType($protocolEnum, $underlyingTypeOverride, $relativePath)
    {
        $underlyingType = $underlyingTypeOverride;
        $enumName = (string)$protocolEnum['name'];

        if ($underlyingType === null) {
            $underlyingTypeName = (string)$protocolEnum['type'];
            if ($enumName === $underlyingTypeName) {
                throw new \RuntimeException("{$enumName} type cannot specify itself as an underlying type.");
            }

            $defaultUnderlyingType = $this->getType($underlyingTypeName);
            if (!($defaultUnderlyingType instanceof IntegerType)) {
                throw new \RuntimeException(
                    "{$defaultUnderlyingType->name} is not a numeric type, so it cannot be "
                    . "specified as an underlying type."
                );
            }

            $underlyingType = $defaultUnderlyingType;
        }
        $protocolValues = $protocolEnum->value;

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

    private function createStructType($protocolStruct, $relativePath)
    {
        return new StructType(
            (string)$protocolStruct['name'],
            $this->calculateFixedStructSize($protocolStruct),
            $this->isBounded($protocolStruct),
            $relativePath
        );
    }

    private function calculateFixedStructSize($protocolStruct)
    {
        $size = 0;
        foreach (self::flattenInstructions($protocolStruct) as $instruction) {
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

        return $size;
    }

    private function calculateFixedStructFieldSize($protocolField)
    {
        $typeName = (string)$protocolField['type'];
        $typeLength = self::createTypeLengthForField($protocolField);
        $typeInstance = $this->getType($typeName, $typeLength);
        $fieldSize = $typeInstance->fixedSize();
        $fieldSizeInt = is_numeric($fieldSize) ? intval($fieldSize) : null;

        if ($fieldSize === null) {
            // All fields in a fixed-size struct must also be fixed-size
            return null;
        }

        if ($protocolField['optional']) {
            // Nothing can be optional in a fixed-size struct
            return null;
        }

        return $fieldSize;
    }

    private function calculateFixedStructArraySize($protocolArray)
    {
        $lengthString = (string)$protocolArray['length'];
        $length = is_numeric($lengthString) ? intval($lengthString) : null;

        if ($length === null) {
            // An array cannot be fixed-size unless a numeric length attribute is provided
            return null;
        }

        $typeName = (string)$protocolArray['type'];
        $typeInstance = $this->getType($typeName);

        $elementSize = $typeInstance->fixedSize();

        if ($elementSize === null || !is_numeric($elementSize)) {
            // An array cannot be fixed-size unless its elements are also fixed-size
            // All arrays in a fixed-size struct must also be fixed-size
            return null;
        }

        if ($protocolArray['optional']) {
            // Nothing can be optional in a fixed-size struct
            return null;
        }

        if ($protocolArray['delimited']) {
            // It's possible to omit data or insert garbage data at the end of each chunk
            return null;
        }

        return $length * $elementSize;
    }

    private function calculateFixedStructDummySize($protocolDummy)
    {
        $typeName = (string)$protocolDummy['type'];
        $typeInstance = $this->getType($typeName);
        $dummySize = $typeInstance->fixedSize;

        if ($dummySize === null) {
            // All dummy fields in a fixed-size struct must also be fixed-size
            return null;
        }

        return $dummySize;
    }

    private function isBounded($protocolStruct)
    {
        $result = true;

        foreach (self::flattenInstructions($protocolStruct) as $instruction) {
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

    private static function flattenInstructions($element, $result = null)
    {
        if ($result === null) {
            $result = [];
        }

        foreach ($element->children() as $instruction) {
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

    private static function createTypeLengthForField($protocolField)
    {
        $lengthString = (string)$protocolField['length'];
        if ($lengthString !== null) {
            return Length::fromString($lengthString);
        } else {
            return Length::unspecified();
        }
    }

    private static function createTypeWithSpecifiedLength($name, $length)
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

class UnresolvedCustomType
{
    private $typeXml;
    private $relativePath;

    public function __construct($typeXml, $relativePath)
    {
        $this->typeXml = $typeXml;
        $this->relativePath = $relativePath;
    }

    public function getTypeXml()
    {
        return $this->typeXml;
    }

    public function getRelativePath()
    {
        return $this->relativePath;
    }
}
