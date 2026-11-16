<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A short-lived Meilisearch tenant token.
 *
 * The token only works inside the project namespace and becomes invalid as
 * soon as the parent API key is revoked. Send it to a private frontend; never
 * send the search key to the browser.
 */
final readonly class SearchToken
{
    public function __construct(
        public string $token,
        public string $host,
        public string $expiresAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            token: Payload::string($data, 'token'),
            host: (string) ($data['host'] ?? ''),
            expiresAt: Payload::string($data, 'expires_at'),
        );
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return new DateTimeImmutable($this->expiresAt) <= $now;
    }
}
