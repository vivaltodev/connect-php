<?php

declare(strict_types=1);

namespace Vivalto\Connect\Resources;

use InvalidArgumentException;
use Vivalto\Connect\Client;
use Vivalto\Connect\DTO\WebhookDeliveryPage;
use Vivalto\Connect\DTO\WebhookEndpoint;

/**
 * Manage webhook endpoints and inspect their delivery log.
 */
final class Webhooks
{
    /**
     * The attributes PATCH /webhooks/{id} accepts.
     *
     * @var list<string>
     */
    private const UPDATABLE = ['url', 'events', 'description', 'is_active', 'api_key_id'];

    public function __construct(private readonly Client $client) {}

    /**
     * Create a webhook endpoint.
     *
     * The returned endpoint carries the one-time signing secret.
     *
     * @param  list<string>  $events
     */
    public function create(
        string $url,
        array $events,
        ?string $description = null,
        ?string $apiKeyId = null,
    ): WebhookEndpoint {
        $payload = array_filter([
            'url' => $url,
            'events' => $events,
            'description' => $description,
            'api_key_id' => $apiKeyId,
        ], static fn (mixed $value): bool => $value !== null);

        return WebhookEndpoint::fromArray(
            $this->client->request('POST', '/api/v1/webhooks', $payload),
        );
    }

    /**
     * List every webhook endpoint on the project.
     *
     * @return list<WebhookEndpoint>
     */
    public function list(): array
    {
        $response = $this->client->request('GET', '/api/v1/webhooks');
        $data = $response['data'] ?? null;

        if (! is_array($data)) {
            return [];
        }

        $endpoints = [];

        foreach ($data as $endpoint) {
            if (is_array($endpoint)) {
                $endpoints[] = WebhookEndpoint::fromArray($endpoint);
            }
        }

        return $endpoints;
    }

    /**
     * Update a webhook endpoint.
     *
     * Only the supplied attributes are sent. Pass null explicitly to clear
     * the description or the API key binding. The signing secret cannot be
     * changed; create a new endpoint to rotate it.
     *
     * Valid attributes are url, events, description, is_active and api_key_id.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $id, array $attributes): WebhookEndpoint
    {
        if ($attributes === []) {
            throw new InvalidArgumentException(
                'At least one attribute is required to update a webhook endpoint.',
            );
        }

        $unknown = array_values(array_diff(array_keys($attributes), self::UPDATABLE));

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown webhook attribute(s): %s. Valid attributes are %s.',
                implode(', ', $unknown),
                implode(', ', self::UPDATABLE),
            ));
        }

        return WebhookEndpoint::fromArray(
            $this->client->request(
                'PATCH',
                '/api/v1/webhooks/'.rawurlencode($id),
                $attributes,
            ),
        );
    }

    /**
     * Delete a webhook endpoint and stop further deliveries.
     */
    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/api/v1/webhooks/'.rawurlencode($id));
    }

    /**
     * Get one page of delivery attempts for a webhook endpoint.
     */
    public function deliveries(string $id, int $page = 1, int $perPage = 20): WebhookDeliveryPage
    {
        return WebhookDeliveryPage::fromArray(
            $this->client->request(
                'GET',
                '/api/v1/webhooks/'.rawurlencode($id).'/deliveries',
                query: ['page' => $page, 'per_page' => $perPage],
            ),
        );
    }
}
