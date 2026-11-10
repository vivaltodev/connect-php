<?php

namespace Vivalto\Sms\DTO;

/**
 * The price of a message in NOK.
 */
final readonly class Price
{
    public function __construct(
        public float $amount,
        public string $currency,
        public bool $estimated,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) ($data['amount'] ?? 0.0),
            currency: (string) ($data['currency'] ?? 'NOK'),
            estimated: (bool) ($data['estimated'] ?? false),
        );
    }
}
