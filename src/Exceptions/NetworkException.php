<?php

declare(strict_types=1);

namespace Vivalto\Connect\Exceptions;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

/**
 * A transport failure reported through the PSR-18 network exception contract.
 *
 * The Laravel transport uses this class so the client can map connection
 * failures to TransportException, exactly like the Guzzle transport does.
 */
final class NetworkException extends RuntimeException implements NetworkExceptionInterface
{
    public function __construct(
        string $message,
        private readonly RequestInterface $request,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
