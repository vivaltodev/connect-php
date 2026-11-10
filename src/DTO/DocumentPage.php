<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * One page of documents.
 */
final readonly class DocumentPage
{
    /**
     * @param  list<Document>  $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $perPage,
        public int $total,
        public int $lastPage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $items = $data['data'] ?? null;
        $meta = $data['meta'] ?? null;

        $documents = [];

        if (is_array($items)) {
            foreach ($items as $item) {
                if (is_array($item)) {
                    $documents[] = Document::fromArray($item);
                }
            }
        }

        $meta = is_array($meta) ? $meta : [];

        return new self(
            items: $documents,
            currentPage: (int) ($meta['current_page'] ?? 1),
            perPage: (int) ($meta['per_page'] ?? count($documents)),
            total: (int) ($meta['total'] ?? count($documents)),
            lastPage: (int) ($meta['last_page'] ?? 1),
        );
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
