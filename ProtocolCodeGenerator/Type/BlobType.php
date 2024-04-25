<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\Type;

class BlobType extends Type
{
    public function name()
    {
        return "blob";
    }

    public function fixedSize()
    {
        return null;
    }

    public function bounded()
    {
        return false;
    }
}