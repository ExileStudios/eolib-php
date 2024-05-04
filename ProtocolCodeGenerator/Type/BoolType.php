<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\BasicType;
use ProtocolCodeGenerator\Type\HasUnderlyingType;

class BoolType extends BasicType implements HasUnderlyingType
{
    private Type $underlyingType;

    public function __construct($underlyingType)
    {
        parent::__construct("bool", $underlyingType->fixedSize(), true);
        $this->underlyingType = $underlyingType;
    }


    public function fixedSize(): ?int
    {
        return $this->underlyingType->fixedSize();
    }

    public function underlyingType(): Type
    {
        return $this->underlyingType;
    }
}