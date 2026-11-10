<?php

declare(strict_types=1);

namespace Vivalto\Connect;

use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Immutable configuration for the Vivalto Connect client.
 *
 * The PSR-18 client and PSR-17 factories are optional. When they are omitted
 * the SDK uses the Guzzle implementations.
 */
final readonly class Config
{
    public function __construct(
        public string $baseUrl,
        public string $apiKey,
        public int $timeout = 30,
        public int $retries = 3,
        public ?ClientInterface $httpClient = null,
        public ?RequestFactoryInterface $requestFactory = null,
        public ?StreamFactoryInterface $streamFactory = null,
    ) {
        if (trim($this->baseUrl) === '') {
            throw new InvalidArgumentException('The Vivalto Connect base URL cannot be empty.');
        }

        if (trim($this->apiKey) === '') {
            throw new InvalidArgumentException('The Vivalto Connect API key cannot be empty.');
        }

        if ($this->timeout < 1) {
            throw new InvalidArgumentException('The Vivalto Connect timeout must be at least one second.');
        }

        if ($this->retries < 0) {
            throw new InvalidArgumentException('The Vivalto Connect retry count cannot be negative.');
        }

        if ($this->retries > 10) {
            throw new InvalidArgumentException('The Vivalto Connect retry count cannot exceed 10.');
        }
    }
}
