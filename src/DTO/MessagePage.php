<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * One page of messages.
 */
final readonly class MessagePage
{
    /**
     * @param  list<Message>  $items
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

        $messages = [];

        if (is_array($items)) {
            foreach ($items as $item) {
                if (is_array($item)) {
                    $messages[] = Message::fromArray($item);
                }
            }
        }

        $meta = is_array($meta) ? $meta : [];

        return new self(
            items: $messages,
            currentPage: (int) ($meta['current_page'] ?? 1),
            perPage: (int) ($meta['per_page'] ?? count($messages)),
            total: (int) ($meta['total'] ?? count($messages)),
            lastPage: (int) ($meta['last_page'] ?? 1),
        );
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
