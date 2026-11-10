<?php

declare(strict_types=1);

namespace Vivalto\Sms\Exceptions;

/**
 * The API rate limit was exceeded.
 */
final class RateLimitException extends ApiException
{
    public function __construct(
        string $message,
        int $status = 429,
        ?string $errorCode = null,
        mixed $details = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status, $errorCode, $details);
    }
}
