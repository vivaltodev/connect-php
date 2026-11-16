<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * One price for a country, message class and part range.
 *
 * A per-part price applies to any part count, so its partsMax is null.
 */
final readonly class PriceListItem
{
    public function __construct(
        public string $country,
        public string $class,
        public int $partsMin,
        public ?int $partsMax,
        public bool $perPart,
        public Price $price,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $price = $data['price'] ?? null;

        return new self(
            country: Payload::string($data, 'country'),
            class: Payload::string($data, 'class'),
            partsMin: (int) ($data['parts_min'] ?? 1),
            partsMax: isset($data['parts_max']) ? (int) $data['parts_max'] : null,
            perPart: (bool) ($data['per_part'] ?? false),
            price: is_array($price) ? Price::fromArray($price) : new Price(0.0, 'NOK', false),
        );
    }
}
