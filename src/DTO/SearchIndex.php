<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * One index inside the project namespace.
 */
final readonly class SearchIndex
{
    public function __construct(
        public string $uid,
        public string $name,
        public int $documents,
        public int $sizeBytes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uid: Payload::string($data, 'uid'),
            name: (string) ($data['name'] ?? ''),
            documents: (int) ($data['documents'] ?? 0),
            sizeBytes: (int) ($data['size_bytes'] ?? 0),
        );
    }
}
