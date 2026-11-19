<?php

use PHPUnit\Framework\AssertionFailedError;
use Vivalto\Connect\Client;
use Vivalto\Connect\Config;
use Vivalto\Connect\Exceptions\ValidationException;
use Vivalto\Connect\Testing\FakeClient;

/**
 * Build a client wired to the framework independent fake transport.
 */
function sdkClientOverFake(FakeClient $fake): Client
{
    return new Client(new Config(
        baseUrl: 'https://api.example.test',
        apiKey: 'test-key',
        retries: 0,
        httpClient: $fake,
    ));
}

it('returns queued json responses in order', function (): void {
    $fake = new FakeClient;
    $fake->json(messagePayload(['id' => 'msg_1']), 201)
        ->json(messagePayload(['id' => 'msg_2']), 201);

    $client = sdkClientOverFake($fake);

    $first = $client->messages()->send(to: '+4790000000', from: 'Vivalto', text: 'En');
    $second = $client->messages()->send(to: '+4790000000', from: 'Vivalto', text: 'To');

    expect($first->id)->toBe('msg_1')
        ->and($second->id)->toBe('msg_2');

    $fake->assertSentCount(2);
});

it('answers registered endpoints before the queue', function (): void {
    $fake = new FakeClient;
    $fake->json(messagePayload(['id' => 'msg_queued']), 201);
    $fake->on('POST', '/api/v1/messages', messagePayload(['id' => 'msg_on']), 201);

    $message = sdkClientOverFake($fake)->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
    );

    expect($message->id)->toBe('msg_on');
});

it('matches endpoints on a full url as well as a path', function (): void {
    $fake = new FakeClient;
    $fake->on('GET', 'https://api.example.test/api/v1/messages', pagePayload([messagePayload()]));

    $page = sdkClientOverFake($fake)->messages()->list();

    expect($page->items)->toHaveCount(1);

    $fake->assertSentTo('GET', '/api/v1/messages');
});

it('records what the sdk sent', function (): void {
    $fake = new FakeClient;
    $fake->json(messagePayload(), 201);

    sdkClientOverFake($fake)->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
        idempotencyKey: 'idem-1',
    );

    $fake->assertSent(function ($request): bool {
        return (string) $request->getUri() === 'https://api.example.test/api/v1/messages'
            && $request->getHeaderLine('Idempotency-Key') === 'idem-1'
            && $request->getHeaderLine('Authorization') === 'Bearer test-key'
            && json_decode((string) $request->getBody(), true)['to'] === '+4790000000';
    });

    $fake->assertSentTo('POST', '/api/v1/messages');
    $fake->assertSentCount(1);

    expect($fake->sent('POST', '/api/v1/messages'))->toHaveCount(1)
        ->and($fake->sent('GET'))->toBe([])
        ->and($fake->lastRequest())->toBe($fake->requests()[0]);
});

it('throws when no fake response is queued', function (): void {
    $fake = new FakeClient;

    expect(fn () => sdkClientOverFake($fake)->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
    ))->toThrow(RuntimeException::class, 'No fake response for POST');
});

it('throws a queued throwable instead of a response', function (): void {
    $fake = new FakeClient;
    $fake->queue(new RuntimeException('transport is down'));

    expect(fn () => sdkClientOverFake($fake)->messages()->list())
        ->toThrow(RuntimeException::class, 'transport is down');
});

it('maps error responses to sdk exceptions', function (): void {
    $fake = new FakeClient;
    $fake->json(errorPayload('validation_failed', 'The to field is required.', ['to' => ['The to field is required.']]), 422);

    expect(fn () => sdkClientOverFake($fake)->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
    ))->toThrow(ValidationException::class, 'The to field is required.');

    $fake->assertSentCount(1);
});

it('fails assertions when nothing was sent', function (): void {
    $fake = new FakeClient;

    expect(fn () => $fake->assertNothingSent())->not->toThrow(AssertionFailedError::class);

    expect(fn () => $fake->assertSentCount(1))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertSentTo('POST', '/api/v1/messages'))->toThrow(AssertionFailedError::class);
});

it('creates a fake with queued responses', function (): void {
    $fake = FakeClient::create(
        FakeClient::jsonResponse(messagePayload(['id' => 'msg_created']), 201),
    );

    $message = sdkClientOverFake($fake)->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
    );

    expect($message->id)->toBe('msg_created');
});
