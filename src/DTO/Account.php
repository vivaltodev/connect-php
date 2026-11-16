<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * The identity, scopes, products and limits of the API key.
 *
 * Returned by GET /api/v1/me.
 */
final readonly class Account
{
    /**
     * @param  list<Product>  $products
     */
    public function __construct(
        public AccountKey $key,
        public AccountCustomer $customer,
        public AccountProject $project,
        public array $products,
        public int $rateLimitPerMinute,
        public string $defaultRegion,
        public bool $sandbox,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $key = AccountKey::fromArray(Payload::assoc($data, 'key'));
        $customer = AccountCustomer::fromArray(Payload::assoc($data, 'customer'));
        $project = AccountProject::fromArray(Payload::assoc($data, 'project'));

        $products = [];

        foreach (Payload::list($data, 'products') as $product) {
            if (is_array($product)) {
                $products[] = Product::fromArray($product);
            }
        }

        return new self(
            key: $key,
            customer: $customer,
            project: $project,
            products: $products,
            rateLimitPerMinute: (int) ($data['rate_limit_per_minute'] ?? 0),
            defaultRegion: (string) ($data['default_region'] ?? ''),
            sandbox: (bool) ($data['sandbox'] ?? false),
        );
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->key->scopes, true);
    }

    /**
     * @return list<string>
     */
    public function productKeys(): array
    {
        return array_map(
            static fn (Product $product): string => $product->key,
            $this->products,
        );
    }
}
