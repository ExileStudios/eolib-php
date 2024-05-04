<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\BasicType;
use ProtocolCodeGenerator\Type\Length;

class StringType extends BasicType
{
    private Length $length;

    public function __construct(string $name, Length $length)
    {
        parent::__construct($name, null, false);
        $this->length = $length;
    }

    public function fixedSize(): ?int
    {
        return $this->length->asInteger();
    }

    public function bounded(): bool
    {
        return $this->length->isSpecified();
    }
}