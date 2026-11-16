<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * The project the API key belongs to.
 */
final readonly class AccountProject
{
    public function __construct(
        public string $id,
        public string $name,
        public string $defaultRegion,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            name: (string) ($data['name'] ?? ''),
            defaultRegion: (string) ($data['default_region'] ?? ''),
        );
    }
}
