<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\CustomType;

class StructType extends CustomType
{
    private $name;
    private $fixedSize;
    private $bounded;
    private $sourcePath;

    public function __construct($name, $fixedSize, $bounded, $sourcePath)
    {
        $this->name = $name;
        $this->fixedSize = $fixedSize;
        $this->bounded = $bounded;
        $this->sourcePath = $sourcePath;
    }

    public function name()
    {
        return $this->name;
    }

    public function fixedSize()
    {
        return $this->fixedSize;
    }

    public function bounded()
    {
        return $this->bounded;
    }

    public function sourcePath()
    {
        return namespaceToPascalCase($this->sourcePath);
    }
}