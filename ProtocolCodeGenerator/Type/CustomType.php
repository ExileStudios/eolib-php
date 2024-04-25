<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\Type;

abstract class CustomType extends Type
{
    abstract public function sourcePath();
}