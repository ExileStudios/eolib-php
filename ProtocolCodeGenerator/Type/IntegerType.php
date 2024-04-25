<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\BasicType;

class IntegerType extends BasicType
{
    private $name;
    private $size;

    public function __construct($name, $size)
    {
        $this->name = $name;
        $this->size = $size;
    }

    public function name()
    {
        return $this->name;
    }

    public function fixedSize()
    {
        return $this->size;
    }

    public function bounded()
    {
        return true;
    }
}