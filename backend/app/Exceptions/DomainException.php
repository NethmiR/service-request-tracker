<?php

// Base class for every business rule violation raised by the application.
// Each subclass names the error code that the central renderer returns.

namespace App\Exceptions;

use App\Enums\ErrorCode;
use RuntimeException;
use Throwable;

abstract class DomainException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct($this->errorCode()->message(), 0, $previous);
    }

    abstract public function errorCode(): ErrorCode;
}
