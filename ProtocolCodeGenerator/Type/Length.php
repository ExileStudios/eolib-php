<?php

namespace ProtocolCodeGenerator\Type;

class Length
{
    private $string = null;
    private $integer;

    private function __construct($lengthString)
    {
        $this->string = $lengthString;
        $this->integer = tryCastInt($lengthString);
    }

    public static function fromString($lengthString)
    {
        return new self($lengthString);
    }

    public static function unspecified()
    {
        return new self(null);
    }

    public function asInteger()
    {
        return $this->integer;
    }

    public function isSpecified()
    {
        return !empty($this->string);
    }

    public function __toString()
    {
        return $this->isSpecified() ? $this->string : "[unspecified]";
    }
}