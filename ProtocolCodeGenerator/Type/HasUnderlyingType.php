<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\Type;

interface HasUnderlyingType
{
    public function underlyingType(): Type;
}