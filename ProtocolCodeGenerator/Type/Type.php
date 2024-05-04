<?php

namespace ProtocolCodeGenerator\Type;

abstract class Type
{
    protected string $name;
    protected ?int $fixedSize;
    protected bool $bounded;

    public function __construct(string $name, ?int $fixedSize, bool $bounded)
    {
        $this->name = $name;
        $this->fixedSize = $fixedSize;
        $this->bounded = $bounded;
    }

    /**
     * Returns the name of the type.
     *
     * @return string The name of the type.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the fixed size of the type, if it has one.
     *
     * @return int|null The fixed size of the type, or null if it does not have one.
     */
    public function fixedSize(): ?int
    {
        return $this->fixedSize;
    }

    /**
     * Returns whether the type is bounded.
     *
     * @return bool Whether the type is bounded.
     */
    public function bounded(): bool
    {
        return $this->bounded;
    }
}
