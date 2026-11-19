<?php

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Vivalto\Connect\Client;
use Vivalto\Connect\Laravel\Facades\VivaltoConnect;
use Vivalto\Connect\Laravel\VivaltoConnectServiceProvider;
use Vivalto\Connect\Testing\FakeClient;

it('ships a publishable config file with the expected keys', function (): void {
    $config = require __DIR__.'/../config/connect.php';

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys(['api_key', 'sender', 'base_url', 'timeout', 'retries'])
        ->and($config['timeout'])->toBe(30)
        ->and($config['retries'])->toBe(3);
});

it('builds a client from laravel config', function (): void {
    $client = VivaltoConnectServiceProvider::makeClient([
        'api_key' => 'laravel-key',
        'base_url' => 'https://sms.example.test',
        'timeout' => 10,
        'retries' => 5,
    ]);

    expect($client)->toBeInstanceOf(Client::class);
});

it('falls back to safe defaults for a partial config', function (): void {
    $client = VivaltoConnectServiceProvider::makeClient([
        'api_key' => 'laravel-key',
    ]);

    expect($client)->toBeInstanceOf(Client::class);
});

it('resolves the client through the facade', function (): void {
    $method = new ReflectionMethod(VivaltoConnect::class, 'getFacadeAccessor');

    expect($method->invoke(null))->toBe(Client::class);
});

it('registers the client in a container from the config binding', function (): void {
    $container = new Container;
    $container->instance('config', new Repository([
        'connect' => [
            'api_key' => 'container-key',
            'base_url' => 'https://sms.example.test',
            'timeout' => 10,
            'retries' => 2,
        ],
    ]));

    $provider = new VivaltoConnectServiceProvider($container);
    $provider->register();

    $client = $container->make(Client::class);

    expect($client)->toBeInstanceOf(Client::class)
        ->and($client->config()->apiKey)->toBe('container-key')
        ->and($client->config()->baseUrl)->toBe('https://sms.example.test')
        ->and($client->config()->timeout)->toBe(10)
        ->and($client->config()->retries)->toBe(2);
});

it('uses the laravel http client transport when it is bound', function (): void {
    $container = new Container;
    $container->instance('config', new Repository([
        'connect' => [
            'api_key' => 'container-key',
            'base_url' => 'https://api.example.test',
            'timeout' => 10,
            'retries' => 2,
        ],
    ]));

    $factory = new HttpFactory;
    $container->instance(HttpFactory::class, $factory);

    $provider = new VivaltoConnectServiceProvider($container);
    $provider->register();

    $factory->fake(['api.example.test/*' => HttpFactory::response(messagePayload(), 201)]);

    $client = $container->make(Client::class);

    $message = $client->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
    );

    expect($message->id)->toBe('msg_abc123')
        ->and($client->config()->timeout)->toBe(10);

    $factory->assertSentCount(1);
});

it('accepts an http client class from config', function (): void {
    $container = new Container;
    $container->instance('config', new Repository([
        'connect' => [
            'api_key' => 'container-key',
            'base_url' => 'https://api.example.test',
            'http_client' => FakeClient::class,
        ],
    ]));

    $provider = new VivaltoConnectServiceProvider($container);
    $provider->register();

    expect($container->make(Client::class)->config()->httpClient)
        ->toBeInstanceOf(FakeClient::class);
});

it('fakes the bound client through the facade', function (): void {
    $container = new Container;
    $container->instance('config', new Repository([
        'connect' => [
            'api_key' => 'facade-key',
            'base_url' => 'https://api.example.test',
        ],
    ]));

    Facade::setFacadeApplication($container);
    Facade::clearResolvedInstances();

    try {
        $fake = VivaltoConnect::fake([
            'POST /api/v1/messages' => messagePayload(['id' => 'msg_fake']),
        ]);

        $message = VivaltoConnect::messages()->send(
            to: '+4790000000',
            from: 'Vivalto',
            text: 'Hei',
        );

        expect($message->id)->toBe('msg_fake');

        $fake->assertSentTo('POST', '/api/v1/messages');
        $fake->assertSentCount(1);
    } finally {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }
});
