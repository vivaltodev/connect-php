<?php

use GuzzleHttp\Psr7\Response;
use Vivalto\Connect\Client;
use Vivalto\Connect\Config;
use Vivalto\Connect\Tests\Support\FakeHttpClient;

/**
 * Build a client wired to the fake PSR-18 transport.
 */
function sdkClient(FakeHttpClient $http, int $retries = 0, string $baseUrl = 'https://api.example.test'): Client
{
    return new Client(new Config(
        baseUrl: $baseUrl,
        apiKey: 'test-key',
        timeout: 5,
        retries: $retries,
        httpClient: $http,
    ));
}

/**
 * Build a PSR-7 response with a JSON body.
 *
 * @param  array<string, mixed>|string  $body
 * @param  array<string, string>  $headers
 */
function jsonResponse(int $status, array|string $body, array $headers = []): Response
{
    $payload = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);

    return new Response($status, $headers + ['Content-Type' => 'application/json'], $payload);
}

/**
 * A message payload shaped like the API v1 MessageResource.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function messagePayload(array $overrides = []): array
{
    return array_merge([
        'id' => 'msg_abc123',
        'reference' => 'order-1',
        'status' => 'queued',
        'to' => '+4790000000',
        'from' => 'Vivalto',
        'class' => 'standard',
        'parts' => 1,
        'price' => [
            'amount' => 0.45,
            'currency' => 'NOK',
            'estimated' => true,
        ],
        'created_at' => '2026-11-10T10:00:00Z',
        'sent_at' => null,
        'delivered_at' => null,
        'error' => null,
        'links' => [
            'self' => 'https://api.example.test/api/v1/messages/msg_abc123',
        ],
    ], $overrides);
}

/**
 * An API error envelope.
 *
 * @param  array<string, mixed>|null  $details
 * @return array<string, mixed>
 */
function errorPayload(string $code, string $message, ?array $details = null): array
{
    return [
        'error' => [
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ],
    ];
}

/**
 * A paginated envelope with the given items and meta overrides.
 *
 * @param  array<int, array<string, mixed>>  $items
 * @param  array<string, mixed>  $meta
 * @return array<string, mixed>
 */
function pagePayload(array $items, array $meta = []): array
{
    $total = $meta['total'] ?? count($items);

    return [
        'data' => $items,
        'links' => [
            'first' => 'https://api.example.test/api/v1/messages?page=1',
            'last' => 'https://api.example.test/api/v1/messages?page=1',
            'prev' => null,
            'next' => null,
        ],
        'meta' => array_merge([
            'current_page' => 1,
            'from' => 1,
            'last_page' => 1,
            'links' => [],
            'path' => 'https://api.example.test/api/v1/messages',
            'per_page' => 20,
            'to' => $total,
            'total' => $total,
        ], $meta),
    ];
}

/**
 * A document payload shaped like the API v1 DocumentResource.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function documentPayload(array $overrides = []): array
{
    return array_merge([
        'id' => 'doc_abc123',
        'type' => 'pdf',
        'status' => 'completed',
        'mime_type' => 'application/pdf',
        'bytes' => 1234,
        'duration_ms' => 120,
        'created_at' => '2026-11-10T10:00:00Z',
        'completed_at' => '2026-11-10T10:00:01Z',
        'expires_at' => '2026-12-10T10:00:00Z',
        'error' => null,
        'links' => [
            'self' => 'https://api.example.test/api/v1/documents/doc_abc123',
            'download' => 'https://api.example.test/api/v1/documents/doc_abc123/download?signature=abc',
        ],
    ], $overrides);
}

/**
 * A PSR-7 response with a binary body.
 *
 * @param  array<string, string>  $headers
 */
function binaryResponse(int $status, string $body, string $contentType, array $headers = []): Response
{
    return new Response($status, $headers + ['Content-Type' => $contentType], $body);
}
