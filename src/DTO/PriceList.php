<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * The prices that currently apply to the project, sorted by the API.
 */
final readonly class PriceList
{
    /**
     * @param  list<PriceListItem>  $items
     */
    public function __construct(public array $items) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $items = [];

        foreach (Payload::list($data, 'data') as $item) {
            if (is_array($item)) {
                $items[] = PriceListItem::fromArray($item);
            }
        }

        return new self($items);
    }

    /**
     * The prices for one country and message class, in API order.
     *
     * @return list<PriceListItem>
     */
    public function for(string $country, string $class): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (PriceListItem $item): bool => $item->country === $country && $item->class === $class,
        ));
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
