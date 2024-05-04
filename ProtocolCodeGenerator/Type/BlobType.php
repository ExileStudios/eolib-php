<?php

namespace ProtocolCodeGenerator\Type;

use ProtocolCodeGenerator\Type\Type;

class BlobType extends Type
{
    public function __construct()
    {
        parent::__construct("blob", null, false);
    }
}