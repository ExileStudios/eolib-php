<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\CustomType;

/**
 * Represents a struct type in the protocol.
 */
class StructType extends CustomType
{
    /**
     * Creates a new struct type.
     *
     * @param string $name The name of the struct type.
     * @param ?int $fixedSize The fixed size of the struct type.
     * @param bool $bounded Whether the struct type is bounded.
     * @param string $sourcePath The source path of the struct type.
     */
    public function __construct(string $name, ?int $fixedSize, bool $bounded, string $sourcePath)
    {
        parent::__construct($name, $fixedSize, $bounded, $sourcePath);
    }
}