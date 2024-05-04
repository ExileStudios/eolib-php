<?php

namespace Eolib\Tests\Protocol;

use PHPUnit\Framework\TestCase;
use Eolib\Protocol\SerializationError;

class SerializationErrorTest extends TestCase
{
    public function testMessage(): void
    {
        $message = "Oh no, the sun exploded!";
        try {
            throw new SerializationError($message);
        } catch (SerializationError $error) {
            $this->assertEquals($message, $error->getMessage());
        }
    }    
}
