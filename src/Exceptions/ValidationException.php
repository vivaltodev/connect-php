<?php

declare(strict_types=1);

namespace Vivalto\Sms\Exceptions;

/**
 * The request failed validation.
 */
final class ValidationException extends ApiException
{
    /**
     * The per-field validation messages from the error envelope.
     *
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return is_array($this->details) ? $this->details : [];
    }
}
