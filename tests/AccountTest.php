<?php

use Vivalto\Connect\DTO\Account;
use Vivalto\Connect\Exceptions\ApiException;
use Vivalto\Connect\Tests\Support\FakeHttpClient;

it('reads the identity and limits of the api key', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, [
        'key' => [
            'id' => 'key_abc123',
            'name' => 'Production',
            'prefix' => 'sk_live_abc',
            'scopes' => ['messages:send', 'messages:read'],
            'sandbox' => false,
        ],
        'customer' => [
            'id' => 'cus_abc123',
            'name' => 'Acme AS',
        ],
        'project' => [
            'id' => 'prj_abc123',
            'name' => 'Nettsiden',
            'default_region' => 'NO',
        ],
        'products' => [
            ['key' => 'sms', 'name' => 'SMS', 'unit' => 'message'],
        ],
        'rate_limit_per_minute' => 600,
        'default_region' => 'NO',
        'sandbox' => false,
    ]));

    $client = sdkClient($http);
    $account = $client->me();

    expect($http->requests[0]->getMethod())->toBe('GET')
        ->and((string) $http->requests[0]->getUri())->toBe('https://api.example.test/api/v1/me');

    expect($account)->toBeInstanceOf(Account::class)
        ->and($account->key->id)->toBe('key_abc123')
        ->and($account->key->name)->toBe('Production')
        ->and($account->key->prefix)->toBe('sk_live_abc')
        ->and($account->key->scopes)->toBe(['messages:send', 'messages:read'])
        ->and($account->key->sandbox)->toBeFalse()
        ->and($account->customer->id)->toBe('cus_abc123')
        ->and($account->customer->name)->toBe('Acme AS')
        ->and($account->project->id)->toBe('prj_abc123')
        ->and($account->project->name)->toBe('Nettsiden')
        ->and($account->project->defaultRegion)->toBe('NO')
        ->and($account->products)->toHaveCount(1)
        ->and($account->products[0]->key)->toBe('sms')
        ->and($account->products[0]->name)->toBe('SMS')
        ->and($account->products[0]->unit)->toBe('message')
        ->and($account->rateLimitPerMinute)->toBe(600)
        ->and($account->defaultRegion)->toBe('NO')
        ->and($account->sandbox)->toBeFalse();
});

it('answers scope and product questions without walking the arrays', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, [
        'key' => [
            'id' => 'key_abc123',
            'name' => 'Test',
            'prefix' => 'sk_test_abc',
            'scopes' => ['messages:send', 'documents:render'],
            'sandbox' => true,
        ],
        'customer' => ['id' => 'cus_abc123', 'name' => 'Acme AS'],
        'project' => ['id' => 'prj_abc123', 'name' => 'Test', 'default_region' => 'DK'],
        'products' => [
            ['key' => 'sms', 'name' => 'SMS', 'unit' => 'message'],
            ['key' => 'pdf', 'name' => 'PDF', 'unit' => 'document'],
        ],
        'rate_limit_per_minute' => 60,
        'default_region' => 'DK',
        'sandbox' => true,
    ]));

    $account = sdkClient($http)->me();

    expect($account->hasScope('messages:send'))->toBeTrue()
        ->and($account->hasScope('pricing:read'))->toBeFalse()
        ->and($account->productKeys())->toBe(['sms', 'pdf'])
        ->and($account->sandbox)->toBeTrue();
});

it('rejects a me response without a key block', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, [
        'customer' => ['id' => 'cus_abc123', 'name' => 'Acme AS'],
        'project' => ['id' => 'prj_abc123', 'name' => 'Nettsiden', 'default_region' => 'NO'],
        'rate_limit_per_minute' => 600,
    ]));

    expect(fn () => sdkClient($http)->me())
        ->toThrow(ApiException::class, "missing the required field 'key'");
});
