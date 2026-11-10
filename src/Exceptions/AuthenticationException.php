<?php

declare(strict_types=1);

namespace Vivalto\Sms\Exceptions;

/**
 * The API key is missing, invalid or lacks the required scope.
 */
final class AuthenticationException extends ApiException {}
