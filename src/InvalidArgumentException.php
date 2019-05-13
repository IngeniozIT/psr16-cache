<?php

namespace IngeniozIT\Psr16;

/**
 * Exception for invalid cache arguments.
 */
class InvalidArgumentException extends \Exception implements \Psr\SimpleCache\InvalidArgumentException
{
}
