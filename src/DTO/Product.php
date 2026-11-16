<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * A product the API key has access to.
 */
final readonly class Product
{
    public function __construct(
        public string $key,
        public string $name,
        public string $unit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: Payload::string($data, 'key'),
            name: (string) ($data['name'] ?? ''),
            unit: (string) ($data['unit'] ?? ''),
        );
    }
}
