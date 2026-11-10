<?php

declare(strict_types=1);

namespace Vivalto\Connect\Exceptions;

/**
 * The API key is missing, invalid or lacks the required scope.
 */
final class AuthenticationException extends ApiException {}
