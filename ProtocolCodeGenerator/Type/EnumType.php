<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\Type;
use ProtocolCodeGenerator\Type\CustomType;
use ProtocolCodeGenerator\Type\HasUnderlyingType;

class EnumType extends CustomType implements HasUnderlyingType
{
    private $underlyingType;
    private $values;

    public function __construct($name, $sourcePath, $underlyingType, $values)
    {
        parent::__construct($name, null, false, $sourcePath);
        $this->underlyingType = $underlyingType;
        $this->values = $values;
    }

    public function fixedSize(): ?int
    {
        return $this->underlyingType->fixedSize();
    }

    public function bounded(): bool
    {
        return $this->underlyingType->bounded();
    }

    public function underlyingType(): Type
    {
        return $this->underlyingType;
    }

    public function values(): array
    {
        return $this->values;
    }

    public function getEnumValueByOrdinal($ordinalValue): ?EnumValue
    {
        foreach ($this->values as $value) {
            if ($value->ordinalValue() === $ordinalValue) {
                return $value;
            }
        }
        return null;
    }

    public function getEnumValueByName($name): ?EnumValue
    {
        foreach ($this->values as $value) {
            $valueName = trim($value->name());
            $name = trim($name);
            if ($valueName === $name) {
                return $value;
            }
        }
        return null;
    }
}
