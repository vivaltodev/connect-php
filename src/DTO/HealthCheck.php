<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * One named health check reported by the API.
 */
final readonly class HealthCheck
{
    public function __construct(
        public string $name,
        public string $status,
        public string $message,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $name, array $data): self
    {
        return new self(
            name: $name,
            status: (string) ($data['status'] ?? 'unknown'),
            message: (string) ($data['message'] ?? ''),
        );
    }

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }
}
