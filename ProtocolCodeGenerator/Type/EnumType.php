<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\CustomType;
use ProtocolCodeGenerator\Type\HasUnderlyingType;

class EnumType extends CustomType implements HasUnderlyingType
{
    private $name;
    private $sourcePath;
    private $underlyingType;
    private $values;

    public function __construct($name, $sourcePath, $underlyingType, $values)
    {
        $this->name = $name;
        $this->sourcePath = $sourcePath;
        $this->underlyingType = $underlyingType;
        $this->values = $values;
    }

    public function name()
    {
        return $this->name;
    }

    public function sourcePath()
    {
        return namespaceToPascalCase($this->sourcePath);
    }

    public function fixedSize()
    {
        return $this->underlyingType->fixedSize();
    }

    public function bounded()
    {
        return $this->underlyingType->bounded();
    }

    public function underlyingType()
    {
        return $this->underlyingType;
    }

    public function values()
    {
        return $this->values;
    }

    public function getEnumValueByOrdinal($ordinalValue)
    {
        foreach ($this->values as $value) {
            if ($value->ordinalValue() === $ordinalValue) {
                return $value;
            }
        }
        return null;
    }

    public function getEnumValueByName($name)
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
