<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\BasicType;

class StringType extends BasicType
{
    private $name;
    private $length;

    public function __construct($name, $length)
    {
        $this->name = $name;
        $this->length = $length;
    }

    public function name()
    {
        return $this->name;
    }

    public function fixedSize()
    {
        return $this->length->asInteger();
    }

    public function bounded()
    {
        return $this->length->isSpecified();
    }
}