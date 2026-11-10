<?php

use Vivalto\Sms\DTO\WebhookDeliveryPage;
use Vivalto\Sms\DTO\WebhookEndpoint;
use Vivalto\Sms\Tests\Support\FakeHttpClient;

function webhookPayload(array $overrides = []): array
{
    return array_merge([
        'id' => 'whk_abc123',
        'url' => 'https://client.example.test/hooks/sms',
        'events' => ['message.delivered', 'message.failed'],
        'is_active' => true,
        'description' => 'Production',
        'api_key_id' => 'key_abc123',
        'created_at' => '2026-11-10T10:00:00Z',
        'updated_at' => '2026-11-10T10:00:00Z',
        'secret' => 'super-secret',
    ], $overrides);
}

it('creates a webhook endpoint and returns the one-time secret', function (): void {
    $http = new FakeHttpClient(jsonResponse(201, webhookPayload()));
    $client = sdkClient($http);

    $endpoint = $client->webhooks()->create(
        url: 'https://client.example.test/hooks/sms',
        events: ['message.delivered', 'message.failed'],
        description: 'Production',
        apiKeyId: 'key_abc123',
    );

    $request = $http->requests[0];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://api.example.test/api/v1/webhooks')
        ->and(json_decode((string) $request->getBody(), true))->toBe([
            'url' => 'https://client.example.test/hooks/sms',
            'events' => ['message.delivered', 'message.failed'],
            'description' => 'Production',
            'api_key_id' => 'key_abc123',
        ]);

    expect($endpoint)->toBeInstanceOf(WebhookEndpoint::class)
        ->and($endpoint->id)->toBe('whk_abc123')
        ->and($endpoint->url)->toBe('https://client.example.test/hooks/sms')
        ->and($endpoint->events)->toBe(['message.delivered', 'message.failed'])
        ->and($endpoint->isActive)->toBeTrue()
        ->and($endpoint->description)->toBe('Production')
        ->and($endpoint->apiKeyId)->toBe('key_abc123')
        ->and($endpoint->secret)->toBe('super-secret')
        ->and($endpoint->createdAt)->toBe('2026-11-10T10:00:00Z')
        ->and($endpoint->updatedAt)->toBe('2026-11-10T10:00:00Z');
});

it('lists webhook endpoints from the data envelope', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, ['data' => [
        webhookPayload(),
        webhookPayload(['id' => 'whk_def456', 'secret' => null]),
    ]]));
    $client = sdkClient($http);

    $endpoints = $client->webhooks()->list();

    expect((string) $http->requests[0]->getUri())->toBe('https://api.example.test/api/v1/webhooks')
        ->and($endpoints)->toHaveCount(2)
        ->and($endpoints[0])->toBeInstanceOf(WebhookEndpoint::class)
        ->and($endpoints[1]->id)->toBe('whk_def456')
        ->and($endpoints[1]->secret)->toBeNull();
});

it('fetches the paginated delivery log for a webhook', function (): void {
    $delivery = [
        'id' => 'dlv_abc123',
        'event_id' => 'evt_abc123',
        'event_type' => 'message.delivered',
        'status' => 'delivered',
        'attempts' => 2,
        'response_status' => 200,
        'next_attempt_at' => null,
        'delivered_at' => '2026-11-10T10:01:00Z',
        'last_error' => null,
        'created_at' => '2026-11-10T10:00:00Z',
    ];

    $http = new FakeHttpClient(jsonResponse(200, pagePayload([$delivery], [
        'current_page' => 3,
        'per_page' => 20,
        'total' => 45,
        'last_page' => 3,
    ])));
    $client = sdkClient($http);

    $page = $client->webhooks()->deliveries('whk_abc123', page: 3, perPage: 20);

    $request = $http->requests[0];
    parse_str($request->getUri()->getQuery(), $query);

    expect($request->getUri()->getPath())->toBe('/api/v1/webhooks/whk_abc123/deliveries')
        ->and($query)->toBe(['page' => '3', 'per_page' => '20'])
        ->and($page)->toBeInstanceOf(WebhookDeliveryPage::class)
        ->and($page->items)->toHaveCount(1)
        ->and($page->items[0]->id)->toBe('dlv_abc123')
        ->and($page->items[0]->eventId)->toBe('evt_abc123')
        ->and($page->items[0]->eventType)->toBe('message.delivered')
        ->and($page->items[0]->status)->toBe('delivered')
        ->and($page->items[0]->attempts)->toBe(2)
        ->and($page->items[0]->responseStatus)->toBe(200)
        ->and($page->items[0]->nextAttemptAt)->toBeNull()
        ->and($page->items[0]->deliveredAt)->toBe('2026-11-10T10:01:00Z')
        ->and($page->items[0]->lastError)->toBeNull()
        ->and($page->items[0]->createdAt)->toBe('2026-11-10T10:00:00Z')
        ->and($page->currentPage)->toBe(3)
        ->and($page->lastPage)->toBe(3);
});
