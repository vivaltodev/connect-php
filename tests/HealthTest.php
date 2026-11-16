<?php

use Vivalto\Connect\DTO\Health;
use Vivalto\Connect\DTO\HealthCheck;
use Vivalto\Connect\Tests\Support\FakeHttpClient;

function healthPayload(string $status = 'ok'): array
{
    $checkStatus = $status === 'ok' ? 'ok' : 'failed';

    return [
        'status' => $status,
        'checked_at' => '2026-11-16T10:12:00+00:00',
        'checks' => [
            'database' => ['status' => $checkStatus, 'message' => 'Database reachable.'],
            'cache' => ['status' => 'ok', 'message' => 'Cache reachable.'],
            'queue' => ['status' => 'ok', 'message' => '3 workers.'],
            'scheduler' => ['status' => 'ok', 'message' => 'Last run 12 seconds ago.'],
            'provider' => ['status' => 'ok', 'message' => 'GatewayAPI reachable.'],
            'pricing' => ['status' => 'ok', 'message' => 'Prices are 3 hours old.'],
            'webhooks' => ['status' => 'ok', 'message' => 'No failures.'],
        ],
    ];
}

it('reports a healthy api', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, healthPayload()));

    $health = sdkClient($http)->health();

    expect($http->requests[0]->getMethod())->toBe('GET')
        ->and((string) $http->requests[0]->getUri())->toBe('https://api.example.test/api/v1/health');

    expect($health)->toBeInstanceOf(Health::class)
        ->and($health->status)->toBe('ok')
        ->and($health->checkedAt)->toBe('2026-11-16T10:12:00+00:00')
        ->and($health->isHealthy())->toBeTrue()
        ->and($health->checks)->toHaveCount(7)
        ->and($health->checks['database'])->toBeInstanceOf(HealthCheck::class)
        ->and($health->checks['database']->status)->toBe('ok')
        ->and($health->failedChecks())->toBe([]);
});

it('returns a degraded health instead of throwing on 503', function (): void {
    $http = new FakeHttpClient(jsonResponse(503, healthPayload('degraded')));

    $health = sdkClient($http, retries: 3)->health();

    expect($http->requests)->toHaveCount(1)
        ->and($health->isHealthy())->toBeFalse()
        ->and($health->status)->toBe('degraded')
        ->and($health->check('database')?->status)->toBe('failed')
        ->and($health->failedChecks())->toHaveCount(1)
        ->and($health->failedChecks()[0]->name)->toBe('database')
        ->and($health->failedChecks()[0]->message)->toBe('Database reachable.');
});

it('returns an unknown check as null', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, healthPayload()));

    expect(sdkClient($http)->health()->check('missing'))->toBeNull();
});
