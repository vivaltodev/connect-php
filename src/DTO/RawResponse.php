<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * A raw HTTP response from the API, used for binary endpoints.
 */
final readonly class RawResponse
{
    /**
     * @param  array<string, array<int, string>>  $headers
     */
    public function __construct(
        public string $contents,
        public string $contentType,
        public int $status,
        public array $headers = [],
    ) {}

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
