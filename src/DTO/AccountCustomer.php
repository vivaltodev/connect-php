<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * The customer that owns the project.
 */
final readonly class AccountCustomer
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            name: (string) ($data['name'] ?? ''),
        );
    }
}
