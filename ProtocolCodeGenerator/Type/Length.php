<?php

namespace ProtocolCodeGenerator\Type;

class Length
{
    private string $string;
    private int|string $integer;

    /**
     * @param string $lengthString
     */
    private function __construct(string $lengthString)
    {
        $this->string = $lengthString;
        $this->integer = tryCastInt($lengthString);
    }

    /**
     * @param string $lengthString
     * @return Length
     */
    public static function fromString(string $lengthString): Length
    {
        return new self($lengthString);
    }

    /**
     * @return Length
     */
    public static function unspecified(): Length
    {
        return new self("");
    }

    public function asInteger(): int
    {
        return intval($this->integer);
    }

    public function isSpecified(): bool
    {
        return !empty($this->string);
    }

    public function __toString(): string
    {
        return $this->isSpecified() ? $this->string : "[unspecified]";
    }
}