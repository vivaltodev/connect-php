<?php

declare(strict_types=1);

namespace Vivalto\Sms\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for every error returned by the Vivalto SMS API.
 *
 * The API maps its error envelope {error: {code, message, details}} onto the
 * properties below. The untouched envelope is available through $details.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $errorCode = null,
        public readonly mixed $details = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
