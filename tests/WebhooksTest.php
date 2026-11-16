<?php

use Vivalto\Connect\DTO\WebhookDeliveryPage;
use Vivalto\Connect\DTO\WebhookEndpoint;
use Vivalto\Connect\Exceptions\ApiException;
use Vivalto\Connect\Tests\Support\FakeHttpClient;

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

it('rejects a webhook response without a url', function (): void {
    $payload = webhookPayload();
    unset($payload['url']);

    $http = new FakeHttpClient(jsonResponse(201, $payload));
    $client = sdkClient($http);

    try {
        $client->webhooks()->create('https://client.example.test/hooks/sms', ['message.delivered']);
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception->errorCode)->toBe('invalid_response')
            ->and($exception->getMessage())->toContain("'url'");
    }
});

it('updates a webhook endpoint with only the supplied attributes', function (): void {
    $payload = webhookPayload(['is_active' => false, 'description' => null, 'secret' => null]);
    $http = new FakeHttpClient(jsonResponse(200, $payload));

    $endpoint = sdkClient($http)->webhooks()->update('whk_abc123', [
        'is_active' => false,
        'description' => null,
    ]);

    $request = $http->requests[0];

    expect($request->getMethod())->toBe('PATCH')
        ->and((string) $request->getUri())->toBe('https://api.example.test/api/v1/webhooks/whk_abc123')
        ->and(json_decode((string) $request->getBody(), true))->toBe([
            'is_active' => false,
            'description' => null,
        ]);

    expect($endpoint)->toBeInstanceOf(WebhookEndpoint::class)
        ->and($endpoint->id)->toBe('whk_abc123')
        ->and($endpoint->isActive)->toBeFalse()
        ->and($endpoint->description)->toBeNull()
        ->and($endpoint->secret)->toBeNull();
});

it('replaces events and url on update', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, webhookPayload([
        'url' => 'https://client.example.test/hooks/v2',
        'events' => ['message.sent'],
    ])));

    sdkClient($http)->webhooks()->update('whk_abc123', [
        'url' => 'https://client.example.test/hooks/v2',
        'events' => ['message.sent'],
    ]);

    expect(json_decode((string) $http->requests[0]->getBody(), true))->toBe([
        'url' => 'https://client.example.test/hooks/v2',
        'events' => ['message.sent'],
    ]);
});

it('rejects an unknown update attribute before sending anything', function (): void {
    $http = new FakeHttpClient();

    expect(fn () => sdkClient($http)->webhooks()->update('whk_abc123', ['sercet' => 'typo']))
        ->toThrow(InvalidArgumentException::class, 'sercet')
        ->and($http->requests)->toBe([]);
});

it('rejects an empty update', function (): void {
    $http = new FakeHttpClient();

    expect(fn () => sdkClient($http)->webhooks()->update('whk_abc123', []))
        ->toThrow(InvalidArgumentException::class)
        ->and($http->requests)->toBe([]);
});

it('deletes a webhook endpoint', function (): void {
    $http = new FakeHttpClient(new GuzzleHttp\Psr7\Response(204));

    sdkClient($http)->webhooks()->delete('whk_abc123');

    $request = $http->requests[0];

    expect($request->getMethod())->toBe('DELETE')
        ->and((string) $request->getUri())->toBe('https://api.example.test/api/v1/webhooks/whk_abc123');
});

it('maps a missing webhook to a not found exception on delete', function (): void {
    $http = new FakeHttpClient(jsonResponse(404, errorPayload('not_found', 'The webhook endpoint does not exist.')));

    expect(fn () => sdkClient($http)->webhooks()->delete('whk_missing'))
        ->toThrow(Vivalto\Connect\Exceptions\NotFoundException::class);
});
