<?php

use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Symfony\Component\Console\Tester\CommandTester;
use Vivalto\Connect\Client;
use Vivalto\Connect\Laravel\Commands\SendTestSmsCommand;
use Vivalto\Connect\Laravel\VivaltoConnectServiceProvider;
use Vivalto\Connect\Tests\Support\FakeHttpClient;
use Vivalto\Connect\Tests\Support\TestApplication;

/**
 * Run connect:test-sms against a container wired to the fake transport.
 *
 * @param  array<string, mixed>  $arguments
 * @param  array<string, mixed>  $config
 * @return array{status: int, output: string}
 */
function runTestSms(array $arguments, FakeHttpClient $http, array $config = []): array
{
    $container = new TestApplication;

    $repository = new Repository([
        'connect' => array_merge([
            'api_key' => 'test-key',
            'sender' => 'Vivalto',
            'base_url' => 'https://api.example.test',
            'timeout' => 5,
            'retries' => 0,
        ], $config),
    ]);

    $container->instance('config', $repository);
    $container->instance(ConfigRepository::class, $repository);
    $container->instance(Client::class, sdkClient($http));

    $command = new SendTestSmsCommand;
    $command->setLaravel($container);

    $tester = new CommandTester($command);
    $status = $tester->execute($arguments, ['interactive' => false]);

    return ['status' => $status, 'output' => $tester->getDisplay()];
}

it('sends a test sms with the configured sender', function (): void {
    $http = new FakeHttpClient(jsonResponse(201, messagePayload()));

    $result = runTestSms([
        'to' => '+4790000000',
        '--text' => 'Hei fra testen',
    ], $http);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('msg_abc123')
        ->and($result['output'])->toContain('queued')
        ->and($result['output'])->toContain('0,45 NOK');

    expect($http->requests)->toHaveCount(1);

    $request = $http->requests[0];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://api.example.test/api/v1/messages')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer test-key')
        ->and($request->getHeaderLine('Idempotency-Key'))->not->toBe('');

    expect(json_decode((string) $request->getBody(), true))->toBe([
        'to' => '+4790000000',
        'from' => 'Vivalto',
        'text' => 'Hei fra testen',
        'class' => 'standard',
    ]);
});

it('prefers the --from option over the configured sender', function (): void {
    $http = new FakeHttpClient(jsonResponse(201, messagePayload(['from' => 'TEST'])));

    $result = runTestSms([
        'to' => '+4790000000',
        '--from' => 'TEST',
        '--text' => 'Hei',
    ], $http);

    expect($result['status'])->toBe(0);

    expect(json_decode((string) $http->requests[0]->getBody(), true))
        ->toMatchArray(['from' => 'TEST']);
});

it('builds a timestamped default text with the standard class', function (): void {
    $http = new FakeHttpClient(jsonResponse(201, messagePayload()));

    $result = runTestSms(['to' => '+4790000000'], $http);

    expect($result['status'])->toBe(0);

    $body = json_decode((string) $http->requests[0]->getBody(), true);

    expect($body['text'])->toStartWith('Test-SMS fra Vivalto Connect ')
        ->and($body['class'])->toBe('standard')
        ->and($body)->not->toHaveKey('reference');
});

it('passes the class and reference through to the api', function (): void {
    $http = new FakeHttpClient(jsonResponse(201, messagePayload([
        'class' => 'premium',
        'reference' => 'test-1',
    ])));

    $result = runTestSms([
        'to' => '+4790000000',
        '--class' => 'premium',
        '--reference' => 'test-1',
        '--text' => 'Hei',
    ], $http);

    expect($result['status'])->toBe(0);

    expect(json_decode((string) $http->requests[0]->getBody(), true))
        ->toMatchArray(['class' => 'premium', 'reference' => 'test-1']);
});

it('fails without a request when no sender is configured', function (): void {
    $http = new FakeHttpClient(jsonResponse(201, messagePayload()));

    $result = runTestSms(['to' => '+4790000000'], $http, ['sender' => null]);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('No sender is configured')
        ->and($result['output'])->toContain('VIVALTO_CONNECT_SENDER');

    expect($http->requests)->toBe([]);
});

it('fails before the request when the class is unknown', function (): void {
    $http = new FakeHttpClient(jsonResponse(201, messagePayload()));

    $result = runTestSms([
        'to' => '+4790000000',
        '--class' => 'express',
    ], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('Unknown message class "express"');

    expect($http->requests)->toBe([]);
});

it('explains a missing api key instead of throwing', function (): void {
    $container = new TestApplication;

    $repository = new Repository([
        'connect' => [
            'api_key' => '',
            'sender' => 'Vivalto',
            'base_url' => 'https://api.example.test',
            'timeout' => 5,
            'retries' => 0,
        ],
    ]);

    $container->instance('config', $repository);
    $container->instance(ConfigRepository::class, $repository);

    $provider = new VivaltoConnectServiceProvider($container);
    $provider->register();

    $command = new SendTestSmsCommand;
    $command->setLaravel($container);

    $tester = new CommandTester($command);
    $status = $tester->execute(['to' => '+4790000000'], ['interactive' => false]);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('The Vivalto Connect client is not configured.')
        ->and($tester->getDisplay())->toContain('VIVALTO_CONNECT_API_KEY');
});

it('follows the status changes when --wait is used', function (): void {
    $http = new FakeHttpClient(
        jsonResponse(201, messagePayload(['status' => 'queued'])),
        jsonResponse(200, messagePayload(['status' => 'sent'])),
        jsonResponse(200, messagePayload(['status' => 'delivered', 'delivered_at' => '2026-11-10T10:00:05Z'])),
    );

    $result = runTestSms([
        'to' => '+4790000000',
        '--text' => 'Hei',
        '--wait' => '10',
    ], $http);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('queued -> sent')
        ->and($result['output'])->toContain('sent -> delivered')
        ->and($result['output'])->toContain('The message was delivered.');

    expect($http->requests)->toHaveCount(3)
        ->and($http->requests[1]->getMethod())->toBe('GET')
        ->and((string) $http->requests[1]->getUri())->toBe('https://api.example.test/api/v1/messages/msg_abc123');
});

it('fails when the message ends in a failed status', function (): void {
    $http = new FakeHttpClient(
        jsonResponse(201, messagePayload(['status' => 'queued'])),
        jsonResponse(200, messagePayload([
            'status' => 'failed',
            'error' => ['code' => 'provider_error', 'message' => 'Rejected by the provider.'],
        ])),
    );

    $result = runTestSms([
        'to' => '+4790000000',
        '--text' => 'Hei',
        '--wait' => '10',
    ], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('The message ended as failed.')
        ->and($result['output'])->toContain('provider_error: Rejected by the provider.');
});

it('returns success with a warning when the status is still pending at the timeout', function (): void {
    $http = new FakeHttpClient(
        jsonResponse(201, messagePayload(['status' => 'queued'])),
        jsonResponse(200, messagePayload(['status' => 'queued'])),
        jsonResponse(200, messagePayload(['status' => 'queued'])),
        jsonResponse(200, messagePayload(['status' => 'queued'])),
    );

    $result = runTestSms([
        'to' => '+4790000000',
        '--text' => 'Hei',
        '--wait' => '1',
    ], $http);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('The message is still queued after 1 seconds.');
});

it('explains a missing send scope after a forbidden response', function (): void {
    $http = new FakeHttpClient(jsonResponse(403, errorPayload('forbidden', 'Forbidden.')));

    $result = runTestSms([
        'to' => '+4790000000',
        '--text' => 'Hei',
    ], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('Code: forbidden')
        ->and($result['output'])->toContain('messages:send');
});

it('warns when the read scope is missing for --wait', function (): void {
    $http = new FakeHttpClient(
        jsonResponse(201, messagePayload(['status' => 'queued'])),
        jsonResponse(403, errorPayload('forbidden', 'Forbidden.')),
    );

    $result = runTestSms([
        'to' => '+4790000000',
        '--text' => 'Hei',
        '--wait' => '10',
    ], $http);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('Could not read the message status: Forbidden.')
        ->and($result['output'])->toContain('messages:read');
});

it('reports an invalid sender with a hint', function (): void {
    $http = new FakeHttpClient(
        jsonResponse(422, errorPayload(
            'invalid_sender',
            'The sender TEST is not approved for the customer.',
            ['field' => 'from'],
        )),
    );

    $result = runTestSms([
        'to' => '+4790000000',
        '--from' => 'TEST',
        '--text' => 'Hei',
    ], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('The test SMS was not sent.')
        ->and($result['output'])->toContain('Code: invalid_sender')
        ->and($result['output'])->toContain('not approved for the customer')
        ->and($result['output'])->toContain('ask Vivalto to approve');
});

it('registers the command through the service provider', function (): void {
    $commands = VivaltoConnectServiceProvider::artisanCommands();

    expect($commands)->toContain(SendTestSmsCommand::class);

    $command = new SendTestSmsCommand;

    expect($command->getName())->toBe('connect:test-sms')
        ->and($command->getDefinition()->hasArgument('to'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('from'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('text'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('class'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('reference'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('wait'))->toBeTrue();
});
