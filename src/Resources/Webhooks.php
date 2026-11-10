<?php

namespace Vivalto\Sms\Resources;

use Vivalto\Sms\Client;
use Vivalto\Sms\DTO\WebhookDeliveryPage;
use Vivalto\Sms\DTO\WebhookEndpoint;

/**
 * Manage webhook endpoints and inspect their delivery log.
 */
final class Webhooks
{
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
