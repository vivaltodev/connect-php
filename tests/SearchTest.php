<?php

use Vivalto\Connect\DTO\SearchStatus;
use Vivalto\Connect\DTO\SearchToken;
use Vivalto\Connect\DTO\SearchUsage;
use Vivalto\Connect\Tests\Support\FakeHttpClient;

function searchStatusPayload(array $overrides = []): array
{
    return array_merge([
        'project' => ['id' => 'prj_a1b2c3d4e5', 'name' => 'Nettsiden'],
        'host' => 'https://search.connect.vivalto.no',
        'namespace' => 'prj_a1b2c3d4e5__',
        'sandbox' => false,
        'status' => 'active',
        'as_of' => '2026-11-16T10:12:00+00:00',
        'healthy' => true,
        'usage' => ['documents' => 12480, 'document_limit' => 250000],
        'indexes' => [
            [
                'uid' => 'prj_a1b2c3d4e5__products',
                'name' => 'products',
                'documents' => 12480,
                'size_bytes' => 3400000,
            ],
        ],
        'credentials' => [
            [
                'id' => 'src_a1b2c3d4e5',
                'kind' => 'write',
                'status' => 'active',
                'last_error' => null,
            ],
            [
                'id' => 'src_f6g7h8i9j0',
                'kind' => 'search',
                'status' => 'failed',
                'last_error' => 'The key was rejected by Meilisearch.',
            ],
        ],
    ], $overrides);
}

it('reads the search status of the project', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, searchStatusPayload()));

    $status = sdkClient($http)->search()->status();

    expect($http->requests[0]->getMethod())->toBe('GET')
        ->and((string) $http->requests[0]->getUri())->toBe('https://api.example.test/api/v1/search');

    expect($status)->toBeInstanceOf(SearchStatus::class)
        ->and($status->projectId)->toBe('prj_a1b2c3d4e5')
        ->and($status->projectName)->toBe('Nettsiden')
        ->and($status->host)->toBe('https://search.connect.vivalto.no')
        ->and($status->namespace)->toBe('prj_a1b2c3d4e5__')
        ->and($status->sandbox)->toBeFalse()
        ->and($status->status)->toBe('active')
        ->and($status->asOf)->toBe('2026-11-16T10:12:00+00:00')
        ->and($status->healthy)->toBeTrue()
        ->and($status->documents)->toBe(12480)
        ->and($status->documentLimit)->toBe(250000)
        ->and($status->isReady())->toBeTrue();

    expect($status->indexes)->toHaveCount(1)
        ->and($status->indexes[0]->uid)->toBe('prj_a1b2c3d4e5__products')
        ->and($status->indexes[0]->name)->toBe('products')
        ->and($status->indexes[0]->documents)->toBe(12480)
        ->and($status->indexes[0]->sizeBytes)->toBe(3400000)
        ->and($status->index('products')?->documents)->toBe(12480)
        ->and($status->index('missing'))->toBeNull();

    expect($status->credentials)->toHaveCount(2)
        ->and($status->credentials[0]->kind)->toBe('write')
        ->and($status->credentials[0]->isActive())->toBeTrue()
        ->and($status->credentials[1]->status)->toBe('failed')
        ->and($status->credentials[1]->lastError)->toBe('The key was rejected by Meilisearch.');
});

it('handles a project that has not synced yet', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, searchStatusPayload([
        'status' => 'pending',
        'as_of' => null,
        'healthy' => null,
        'usage' => ['documents' => 0, 'document_limit' => null],
        'indexes' => [],
        'credentials' => [],
    ])));

    $status = sdkClient($http)->search()->status();

    expect($status->status)->toBe('pending')
        ->and($status->isReady())->toBeFalse()
        ->and($status->asOf)->toBeNull()
        ->and($status->healthy)->toBeNull()
        ->and($status->documentLimit)->toBeNull()
        ->and($status->documents)->toBe(0)
        ->and($status->indexes)->toBe([])
        ->and($status->credentials)->toBe([]);
});

it('reads daily search usage for the default window', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, [
        'data' => [
            ['date' => '2026-11-15', 'documents' => 12000, 'index_size_bytes' => 3300000, 'searches' => null],
            ['date' => '2026-11-16', 'documents' => 12480, 'index_size_bytes' => 3400000, 'searches' => 512],
        ],
        'meta' => ['from' => '2026-10-18', 'to' => '2026-11-16', 'days' => 30],
    ]));

    $usage = sdkClient($http)->search()->usage();

    expect((string) $http->requests[0]->getUri())
        ->toBe('https://api.example.test/api/v1/search/usage?days=30');

    expect($usage)->toBeInstanceOf(SearchUsage::class)
        ->and($usage->rows)->toHaveCount(2)
        ->and($usage->rows[0]->date)->toBe('2026-11-15')
        ->and($usage->rows[0]->documents)->toBe(12000)
        ->and($usage->rows[0]->indexSizeBytes)->toBe(3300000)
        ->and($usage->rows[0]->searches)->toBeNull()
        ->and($usage->rows[1]->searches)->toBe(512)
        ->and($usage->from)->toBe('2026-10-18')
        ->and($usage->to)->toBe('2026-11-16')
        ->and($usage->days)->toBe(30)
        ->and($usage->latest()?->date)->toBe('2026-11-16');
});

it('passes a custom usage window', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, [
        'data' => [],
        'meta' => ['from' => '2026-11-10', 'to' => '2026-11-16', 'days' => 7],
    ]));

    sdkClient($http)->search()->usage(days: 7);

    expect((string) $http->requests[0]->getUri())
        ->toBe('https://api.example.test/api/v1/search/usage?days=7');
});

it('creates a tenant token with only the supplied constraints', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, [
        'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.payload.signature',
        'host' => 'https://search.connect.vivalto.no',
        'expires_at' => '2026-11-16T10:27:00+00:00',
    ]));

    $token = sdkClient($http)->search()->token(
        index: 'products',
        filter: 'user_id = 42',
        expiresIn: 600,
    );

    $request = $http->requests[0];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://api.example.test/api/v1/search/token')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and(json_decode((string) $request->getBody(), true))->toBe([
            'index' => 'products',
            'filter' => 'user_id = 42',
            'expires_in' => 600,
        ]);

    expect($token)->toBeInstanceOf(SearchToken::class)
        ->and($token->token)->toBe('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.payload.signature')
        ->and($token->host)->toBe('https://search.connect.vivalto.no')
        ->and($token->expiresAt)->toBe('2026-11-16T10:27:00+00:00')
        ->and($token->isExpired(new DateTimeImmutable('2026-11-16T10:26:59+00:00')))->toBeFalse()
        ->and($token->isExpired(new DateTimeImmutable('2026-11-16T10:27:01+00:00')))->toBeTrue();
});

it('sends an empty body when the token has no constraints', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, [
        'token' => 'token',
        'host' => 'https://search.connect.vivalto.no',
        'expires_at' => '2026-11-16T10:27:00+00:00',
    ]));

    sdkClient($http)->search()->token();

    expect(json_decode((string) $http->requests[0]->getBody(), true))->toBe([]);
});

it('surfaces a provisioning conflict as an api exception', function (): void {
    $http = new FakeHttpClient(jsonResponse(409, errorPayload(
        'conflict',
        'The search credential is still provisioning.',
    )));

    expect(fn () => sdkClient($http)->search()->token())
        ->toThrow(Vivalto\Connect\Exceptions\ApiException::class);
});
