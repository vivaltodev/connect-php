<?php

use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Symfony\Component\Console\Tester\CommandTester;
use Vivalto\Connect\Client;
use Vivalto\Connect\Laravel\Commands\VerifyCommand;
use Vivalto\Connect\Laravel\VivaltoConnectServiceProvider;
use Vivalto\Connect\Tests\Support\FakeHttpClient;
use Vivalto\Connect\Tests\Support\TestApplication;

/**
 * Run connect:verify against a container wired to the fake transport.
 *
 * @param  array<string, mixed>  $arguments
 * @param  array<string, mixed>  $config
 * @return array{status: int, output: string}
 */
function runVerifyCommand(array $arguments, FakeHttpClient $http, array $config = []): array
{
    $container = new TestApplication;

    $repository = new Repository([
        'connect' => array_merge([
            'api_key' => 'test-key',
            'base_url' => 'https://api.example.test',
            'timeout' => 5,
            'retries' => 0,
        ], $config),
    ]);

    $container->instance('config', $repository);
    $container->instance(ConfigRepository::class, $repository);
    $container->instance(Client::class, sdkClient($http));

    $command = new VerifyCommand;
    $command->setLaravel($container);

    $tester = new CommandTester($command);
    $status = $tester->execute($arguments, ['interactive' => false]);

    return ['status' => $status, 'output' => $tester->getDisplay()];
}

it('authenticates and prints the identity of the API key', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, accountPayload()));

    $result = runVerifyCommand([], $http);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('The API key is valid.')
        ->and($result['output'])->toContain('Production (key_abc123)')
        ->and($result['output'])->toContain('sk_live_ab')
        ->and($result['output'])->toContain('Infografikk')
        ->and($result['output'])->toContain('Tradein')
        ->and($result['output'])->toContain('60 requests per minute')
        ->and($result['output'])->toContain('messages:send')
        ->and($result['output'])->toContain('The key works and carries every scope this check requires.');

    expect($http->requests)->toHaveCount(1);

    $request = $http->requests[0];

    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('https://api.example.test/api/v1/me')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer test-key');
});

it('fails with a hint when the api rejects the key', function (): void {
    $http = new FakeHttpClient(jsonResponse(401, errorPayload('unauthenticated', 'Missing API key.')));

    $result = runVerifyCommand([], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('The API key was not accepted.')
        ->and($result['output'])->toContain('Code: unauthenticated')
        ->and($result['output'])->toContain('VIVALTO_CONNECT_API_KEY');
});

it('passes when every required scope is present', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, accountPayload()));

    $result = runVerifyCommand([
        '--need' => ['messages:send', 'documents:render'],
    ], $http);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('All 2 required scopes are present.');
});

it('fails and names the scopes that are missing', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, accountPayload()));

    $result = runVerifyCommand([
        '--need' => ['messages:send', 'search:write'],
    ], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('Missing scopes: search:write')
        ->and($result['output'])->toContain('Edit the key in the portal');
});

it('fails when the key has no access to a requested product', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, accountPayload()));

    $result = runVerifyCommand([
        '--product' => ['search'],
    ], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('The key has no access to: search');
});

it('passes when the key has access to the requested product', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, accountPayload()));

    $result = runVerifyCommand([
        '--product' => ['sms', 'documents'],
    ], $http);

    expect($result['status'])->toBe(0);
});

it('checks platform scopes as products', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, accountPayload([
        'key' => [
            'id' => 'key_abc123',
            'name' => 'Production',
            'prefix' => 'sk_live_ab',
            'scopes' => ['messages:send', 'messages:read', 'webhooks:manage'],
            'sandbox' => false,
        ],
        'products' => [
            ['key' => 'sms', 'name' => 'SMS', 'unit' => 'message'],
        ],
    ])));

    $result = runVerifyCommand([
        '--product' => ['webhooks', 'pricing'],
    ], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('The key has no access to: pricing');
});

it('passes when a platform scope grants the requested product', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, accountPayload([
        'key' => [
            'id' => 'key_abc123',
            'name' => 'Production',
            'prefix' => 'sk_live_ab',
            'scopes' => ['messages:send', 'messages:read', 'webhooks:manage', 'pricing:read'],
            'sandbox' => false,
        ],
    ])));

    $result = runVerifyCommand([
        '--product' => ['webhooks', 'pricing'],
    ], $http);

    expect($result['status'])->toBe(0);
});

it('warns when a send scope has no read scope', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, accountPayload([
        'key' => [
            'id' => 'key_abc123',
            'name' => 'Production',
            'prefix' => 'sk_live_ab',
            'scopes' => ['messages:send'],
            'sandbox' => false,
        ],
        'products' => [
            ['key' => 'sms', 'name' => 'SMS', 'unit' => 'message'],
        ],
    ])));

    $result = runVerifyCommand([], $http);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('messages:send is enabled without messages:read')
        ->and($result['output'])->toContain('cannot read the status back');
});

it('fails when the key has no scopes at all', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, accountPayload([
        'key' => [
            'id' => 'key_abc123',
            'name' => 'Production',
            'prefix' => 'sk_live_ab',
            'scopes' => [],
            'sandbox' => false,
        ],
        'products' => [],
    ])));

    $result = runVerifyCommand([], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('The key has no scopes');
});

it('warns when the key is a sandbox key', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, accountPayload(['sandbox' => true])));

    $result = runVerifyCommand([], $http);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('This is a sandbox key.');
});

it('prints the account as json on request', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, accountPayload()));

    $result = runVerifyCommand([
        '--json' => true,
        '--need' => ['search:write'],
    ], $http);

    $payload = json_decode($result['output'], true);

    expect($result['status'])->toBe(1)
        ->and($payload['ok'])->toBeFalse()
        ->and($payload['account']['key']['id'])->toBe('key_abc123')
        ->and($payload['account']['project']['name'])->toBe('Tradein')
        ->and($payload['missing_scopes'])->toBe(['search:write']);
});

it('explains a missing api key instead of throwing', function (): void {
    $container = new TestApplication;

    $repository = new Repository([
        'connect' => [
            'api_key' => '',
            'base_url' => 'https://api.example.test',
            'timeout' => 5,
            'retries' => 0,
        ],
    ]);

    $container->instance('config', $repository);
    $container->instance(ConfigRepository::class, $repository);

    $provider = new VivaltoConnectServiceProvider($container);
    $provider->register();

    $command = new VerifyCommand;
    $command->setLaravel($container);

    $tester = new CommandTester($command);
    $status = $tester->execute([], ['interactive' => false]);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('The Vivalto Connect client is not configured.')
        ->and($tester->getDisplay())->toContain('VIVALTO_CONNECT_API_KEY');
});

it('registers the command and its alias through the service provider', function (): void {
    $commands = VivaltoConnectServiceProvider::artisanCommands();

    expect($commands)->toContain(VerifyCommand::class);

    $command = new VerifyCommand;

    expect($command->getName())->toBe('connect:verify')
        ->and($command->getAliases())->toContain('connect:test-auth')
        ->and($command->getDefinition()->hasOption('need'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('product'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('json'))->toBeTrue();
});
