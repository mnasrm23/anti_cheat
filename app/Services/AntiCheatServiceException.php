<?php

namespace App\Services;

use Exception;
use Throwable;

class AntiCheatServiceException extends Exception
{
    public function __construct(
        string $message,
        protected int $statusCode = 502,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
