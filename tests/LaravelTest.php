<?php

use Vivalto\Sms\Client;
use Vivalto\Sms\Laravel\Facades\VivaltoSms;
use Vivalto\Sms\Laravel\VivaltoSmsServiceProvider;

it('ships a publishable config file with the expected keys', function (): void {
    $config = require __DIR__.'/../config/vivalto-sms.php';

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys(['api_key', 'base_url', 'timeout', 'retries'])
        ->and($config['timeout'])->toBe(30)
        ->and($config['retries'])->toBe(3);
});

it('builds a client from laravel config', function (): void {
    $client = VivaltoSmsServiceProvider::makeClient([
        'api_key' => 'laravel-key',
        'base_url' => 'https://sms.example.test',
        'timeout' => 10,
        'retries' => 5,
    ]);

    expect($client)->toBeInstanceOf(Client::class);
});

it('falls back to safe defaults for a partial config', function (): void {
    $client = VivaltoSmsServiceProvider::makeClient([
        'api_key' => 'laravel-key',
    ]);

    expect($client)->toBeInstanceOf(Client::class);
});

it('resolves the client through the facade', function (): void {
    $method = new ReflectionMethod(VivaltoSms::class, 'getFacadeAccessor');

    expect($method->invoke(null))->toBe(Client::class);
});
