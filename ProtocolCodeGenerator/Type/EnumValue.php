<?php

namespace ProtocolCodeGenerator\Type;

class EnumValue
{
    private int $ordinalValue;
    private string $name;

    public function __construct(int $ordinalValue, string $name)
    {
        $this->ordinalValue = $ordinalValue;
        $this->name = $name;
    }

    public function ordinalValue(): int
    {
        return $this->ordinalValue;
    }

    public function name(): string
    {
        return $this->name;
    }
}