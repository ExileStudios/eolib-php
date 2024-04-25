<?php

namespace Eolib\Protocol;

/**
 * Represents an error that occurs during serialization or deserialization.
 */
class SerializationError extends \Exception
{
    /**
     * Constructs a SerializationError with the specified error message.
     *
     * @param string $message The error message.
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
