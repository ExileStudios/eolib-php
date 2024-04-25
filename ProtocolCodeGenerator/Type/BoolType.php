<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\BasicType;
use ProtocolCodeGenerator\Type\HasUnderlyingType;

class BoolType extends BasicType implements HasUnderlyingType
{
    private $underlyingType;

    public function __construct($underlyingType)
    {
        $this->underlyingType = $underlyingType;
    }

    public function name()
    {
        return "bool";
    }

    public function fixedSize()
    {
        return $this->underlyingType->fixedSize();
    }

    public function bounded()
    {
        return true;
    }

    public function underlyingType()
    {
        return $this->underlyingType;
    }
}