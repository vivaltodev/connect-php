<?php

declare(strict_types=1);

namespace Vivalto\Connect\Exceptions;

/**
 * The API returned a 5xx response after every retry was used.
 */
final class ServerException extends ApiException {}
