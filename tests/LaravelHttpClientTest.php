<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as LaravelRequest;
use Vivalto\Connect\Client;
use Vivalto\Connect\Config;
use Vivalto\Connect\Exceptions\TransportException;
use Vivalto\Connect\Laravel\HttpClient;

/**
 * Build a client that sends every request through Laravel's HTTP client.
 */
function sdkClientOverLaravel(Factory $factory, int $retries = 0): Client
{
    return new Client(new Config(
        baseUrl: 'https://api.example.test',
        apiKey: 'test-key',
        timeout: 5,
        retries: $retries,
        httpClient: new HttpClient($factory, 5),
    ));
}

it('lets Http::fake intercept sdk requests', function (): void {
    $factory = new Factory;
    $factory->fake(['api.example.test/*' => Factory::response(messagePayload(), 201)]);

    $message = sdkClientOverLaravel($factory)->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
        idempotencyKey: 'idem-1',
    );

    expect($message->id)->toBe('msg_abc123');

    $factory->assertSent(function (LaravelRequest $request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/v1/messages'
            && $request['to'] === '+4790000000'
            && $request['from'] === 'Vivalto'
            && $request['text'] === 'Hei'
            && $request->hasHeader('Idempotency-Key', 'idem-1')
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request->hasHeader('User-Agent', 'vivalto-connect-php/'.Client::VERSION);
    });
});

it('records requests for Http::assertSentCount and Http::recorded', function (): void {
    $factory = new Factory;
    $factory->fake(['api.example.test/*' => Factory::response(pagePayload([messagePayload()]))]);

    $client = sdkClientOverLaravel($factory);

    $client->messages()->list();
    $client->messages()->list(['status' => 'sent']);

    $factory->assertSentCount(2);

    $recorded = $factory->recorded();

    expect($recorded)->toHaveCount(2)
        ->and($recorded[0][0]->method())->toBe('GET')
        ->and($recorded[1][0]->url())->toContain('status=sent');
});

it('walks a fake sequence across sdk retries', function (): void {
    $factory = new Factory;
    $factory->fakeSequence('api.example.test/*')
        ->push(errorPayload('service_unavailable', 'Try again later.'), 503)
        ->push(messagePayload(), 201);

    $message = sdkClientOverLaravel($factory, retries: 1)->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
        idempotencyKey: 'idem-retry',
    );

    expect($message->id)->toBe('msg_abc123');

    $factory->assertSentCount(2);

    $keys = array_map(
        static fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0],
        $factory->recorded()->all(),
    );

    expect(array_unique($keys))->toBe(['idem-retry']);
});

it('picks up fakes registered after the client was built', function (): void {
    $factory = new Factory;

    $client = sdkClientOverLaravel($factory);

    $factory->fake(['api.example.test/*' => Factory::response(messagePayload(['id' => 'msg_late']), 201)]);
    $factory->assertNothingSent();

    $message = $client->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
    );

    expect($message->id)->toBe('msg_late');

    $factory->assertSentCount(1);
});

it('maps laravel connection failures to a transport exception', function (): void {
    $factory = new Factory;
    $factory->fake([
        'api.example.test/*' => static fn (): never => throw new ConnectionException('Connection refused'),
    ]);

    expect(fn () => sdkClientOverLaravel($factory)->me())
        ->toThrow(TransportException::class, 'Connection refused');
});

it('honours Http::preventStrayRequests', function (): void {
    $factory = new Factory;
    $factory->preventStrayRequests();

    expect(fn () => sdkClientOverLaravel($factory)->me())
        ->toThrow(TransportException::class, 'without a matching fake');
});

it('renders document responses through the laravel transport', function (): void {
    $factory = new Factory;
    $factory->fake([
        'api.example.test/*' => Factory::response('%PDF-1.7 fake', 200, [
            'Content-Type' => 'application/pdf',
        ]),
    ]);

    $result = sdkClientOverLaravel($factory)->documents()->render(
        html: '<h1>Hei</h1>',
        options: ['type' => 'pdf'],
    );

    expect($result->contents)->toBe('%PDF-1.7 fake')
        ->and($result->mimeType)->toBe('application/pdf')
        ->and($result->isPdf())->toBeTrue();
});
