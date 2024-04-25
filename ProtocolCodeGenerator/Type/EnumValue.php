<?php

namespace ProtocolCodeGenerator\Type;

class EnumValue
{
    private $ordinalValue;
    private $name;

    public function __construct($ordinalValue, $name)
    {
        $this->ordinalValue = $ordinalValue;
        $this->name = $name;
    }

    public function ordinalValue()
    {
        return $this->ordinalValue;
    }

    public function name()
    {
        return $this->name;
    }
}