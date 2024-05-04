<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\BasicType;

/**
 * IntegerType represents a type that is an integer.
 */
class IntegerType extends BasicType
{
    /**
     * @param string $name
     * @param int|null $fixedSize
     */
    public function __construct(string $name, ?int $fixedSize)
    {
        parent::__construct($name, $fixedSize, true);
    }
}