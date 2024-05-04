<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\Type;

abstract class CustomType extends Type
{
    protected string $sourcePath;

    /**
     * Creates a new custom type.
     *
     * @param string $name The name of the custom type.
     * @param ?int $fixedSize The fixed size of the custom type.
     * @param bool $bounded Whether the custom type is bounded.
     * @param string $sourcePath The source path of the custom type.
     */
    public function __construct(string $name, ?int $fixedSize, bool $bounded, string $sourcePath)
    {
        parent::__construct($name, $fixedSize, $bounded);
        $this->sourcePath = $sourcePath;
    }
    
    /**
     * Returns the source path of the custom type.
     *
     * @return string The source path of the custom type.
     */
    public function sourcePath(): string
    {
        return namespaceToPascalCase($this->sourcePath);
    }
}