<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * A Meilisearch key provisioned for the API key.
 *
 * The key itself is never returned by the API; the portal shows it once.
 */
final readonly class SearchCredential
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $status,
        public ?string $lastError,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            kind: (string) ($data['kind'] ?? ''),
            status: (string) ($data['status'] ?? 'unknown'),
            lastError: isset($data['last_error']) ? (string) $data['last_error'] : null,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
