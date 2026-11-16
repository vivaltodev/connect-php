<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * The API key that authenticated the request.
 */
final readonly class AccountKey
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $prefix,
        public array $scopes,
        public bool $sandbox,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $scopes = $data['scopes'] ?? [];

        return new self(
            id: Payload::string($data, 'id'),
            name: (string) ($data['name'] ?? ''),
            prefix: (string) ($data['prefix'] ?? ''),
            scopes: is_array($scopes) ? array_values(array_map(
                static fn (mixed $scope): string => (string) $scope,
                $scopes,
            )) : [],
            sandbox: (bool) ($data['sandbox'] ?? false),
        );
    }
}
