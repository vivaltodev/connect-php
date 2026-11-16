<?php

declare(strict_types=1);

namespace Vivalto\Connect\Resources;

use Vivalto\Connect\Client;
use Vivalto\Connect\DTO\SearchStatus;
use Vivalto\Connect\DTO\SearchToken;
use Vivalto\Connect\DTO\SearchUsage;

/**
 * Hosted Meilisearch: status, usage and tenant tokens.
 *
 * Connect is the control plane. Search traffic goes directly from the client
 * to the Meilisearch host, not through this API.
 */
final class Search
{
    public function __construct(private readonly Client $client) {}

    /**
     * Read the namespace, host, indexes, usage and credential state.
     *
     * The answer is built from cached tables, so it keeps working while
     * Meilisearch is down. Requires the search:read scope.
     */
    public function status(): SearchStatus
    {
        return SearchStatus::fromArray($this->client->request('GET', '/api/v1/search'));
    }

    /**
     * Read daily usage for the last N days, between 1 and 365.
     *
     * Requires the search:read scope.
     */
    public function usage(int $days = 30): SearchUsage
    {
        return SearchUsage::fromArray(
            $this->client->request('GET', '/api/v1/search/usage', query: ['days' => $days]),
        );
    }

    /**
     * Sign a short-lived tenant token for a private frontend.
     *
     * Requires the search:token scope. The lifetime is 60 to 3600 seconds and
     * defaults to 900. Pass a filter to isolate the end user, for example
     * `user_id = 42`. A 409 means the credential is still provisioning.
     */
    public function token(
        ?string $index = null,
        ?string $filter = null,
        ?int $expiresIn = null,
    ): SearchToken {
        $payload = array_filter([
            'index' => $index,
            'filter' => $filter,
            'expires_in' => $expiresIn,
        ], static fn (mixed $value): bool => $value !== null);

        return SearchToken::fromArray(
            $this->client->request('POST', '/api/v1/search/token', $payload),
        );
    }
}
