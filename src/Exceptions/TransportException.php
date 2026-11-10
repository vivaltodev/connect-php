<?php

namespace Vivalto\Sms\Exceptions;

use Throwable;

/**
 * The PSR-18 transport failed before the API returned a response.
 */
final class TransportException extends ApiException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
