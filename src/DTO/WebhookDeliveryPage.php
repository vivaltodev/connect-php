<?php

declare(strict_types=1);

namespace Vivalto\Sms\DTO;

/**
 * One page of webhook delivery attempts.
 */
final readonly class WebhookDeliveryPage
{
    /**
     * @param  list<WebhookDelivery>  $items
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

        $deliveries = [];

        if (is_array($items)) {
            foreach ($items as $item) {
                if (is_array($item)) {
                    $deliveries[] = WebhookDelivery::fromArray($item);
                }
            }
        }

        $meta = is_array($meta) ? $meta : [];

        return new self(
            items: $deliveries,
            currentPage: (int) ($meta['current_page'] ?? 1),
            perPage: (int) ($meta['per_page'] ?? count($deliveries)),
            total: (int) ($meta['total'] ?? count($deliveries)),
            lastPage: (int) ($meta['last_page'] ?? 1),
        );
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
