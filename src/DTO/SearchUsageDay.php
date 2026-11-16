<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * One day of search usage for the project.
 */
final readonly class SearchUsageDay
{
    public function __construct(
        public string $date,
        public int $documents,
        public int $indexSizeBytes,
        public ?int $searches,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: Payload::string($data, 'date'),
            documents: (int) ($data['documents'] ?? 0),
            indexSizeBytes: (int) ($data['index_size_bytes'] ?? 0),
            searches: isset($data['searches']) ? (int) $data['searches'] : null,
        );
    }
}
