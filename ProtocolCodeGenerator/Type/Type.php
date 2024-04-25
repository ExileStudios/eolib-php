<?php

namespace ProtocolCodeGenerator\Type;

abstract class Type
{
    abstract public function name();
    abstract public function fixedSize();
    abstract public function bounded();
}