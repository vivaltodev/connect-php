<?php

use Vivalto\Sms\DTO\Message;
use Vivalto\Sms\DTO\MessagePage;
use Vivalto\Sms\Exceptions\NotFoundException;
use Vivalto\Sms\Exceptions\ValidationException;
use Vivalto\Sms\Tests\Support\FakeHttpClient;

it('sends a message with bearer auth, an idempotency key and a json body', function (): void {
    $http = new FakeHttpClient(jsonResponse(201, messagePayload()));
    $client = sdkClient($http);

    $message = $client->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
        reference: 'order-1',
        metadata: ['customer' => 'acme'],
    );

    expect($http->requests)->toHaveCount(1);

    $request = $http->requests[0];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://api.example.test/api/v1/messages')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer test-key')
        ->and($request->getHeaderLine('Accept'))->toBe('application/json')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($request->getHeaderLine('Idempotency-Key'))->toMatch('/^[a-f0-9]{32}$/');

    expect(json_decode((string) $request->getBody(), true))->toBe([
        'to' => '+4790000000',
        'from' => 'Vivalto',
        'text' => 'Hei',
        'reference' => 'order-1',
        'metadata' => ['customer' => 'acme'],
    ]);

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->id)->toBe('msg_abc123')
        ->and($message->reference)->toBe('order-1')
        ->and($message->status)->toBe('queued')
        ->and($message->to)->toBe('+4790000000')
        ->and($message->from)->toBe('Vivalto')
        ->and($message->class)->toBe('standard')
        ->and($message->parts)->toBe(1)
        ->and($message->price?->amount)->toBe(0.45)
        ->and($message->price?->currency)->toBe('NOK')
        ->and($message->price?->estimated)->toBeTrue()
        ->and($message->createdAt)->toBe('2026-11-10T10:00:00Z')
        ->and($message->sentAt)->toBeNull()
        ->and($message->deliveredAt)->toBeNull()
        ->and($message->error)->toBeNull()
        ->and($message->events)->toBe([]);
});

it('uses a supplied idempotency key and omits empty optional fields', function (): void {
    $http = new FakeHttpClient(jsonResponse(201, messagePayload()));
    $client = sdkClient($http);

    $client->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
        idempotencyKey: 'client-key-123',
    );

    $request = $http->requests[0];

    expect($request->getHeaderLine('Idempotency-Key'))->toBe('client-key-123')
        ->and(json_decode((string) $request->getBody(), true))->toBe([
            'to' => '+4790000000',
            'from' => 'Vivalto',
            'text' => 'Hei',
        ]);
});

it('sends the class when one is given', function (): void {
    $http = new FakeHttpClient(jsonResponse(201, messagePayload(['class' => 'premium'])));
    $client = sdkClient($http);

    $client->messages()->send(
        to: '+4790000000',
        from: 'Vivalto',
        text: 'Hei',
        class: 'premium',
    );

    expect(json_decode((string) $http->requests[0]->getBody(), true))
        ->toBe(['to' => '+4790000000', 'from' => 'Vivalto', 'text' => 'Hei', 'class' => 'premium']);
});

it('fetches a message with its events', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, messagePayload([
        'status' => 'delivered',
        'sent_at' => '2026-11-10T10:00:01Z',
        'delivered_at' => '2026-11-10T10:00:02Z',
        'events' => [
            ['id' => 'evt_1', 'type' => 'queued', 'status' => 'queued', 'occurred_at' => '2026-11-10T10:00:00Z'],
            ['id' => 'evt_2', 'type' => 'delivered', 'status' => 'delivered', 'occurred_at' => '2026-11-10T10:00:02Z'],
        ],
    ])));
    $client = sdkClient($http);

    $message = $client->messages()->get('msg_abc123');

    expect((string) $http->requests[0]->getUri())->toBe('https://api.example.test/api/v1/messages/msg_abc123')
        ->and($http->requests[0]->getMethod())->toBe('GET')
        ->and($message->status)->toBe('delivered')
        ->and($message->sentAt)->toBe('2026-11-10T10:00:01Z')
        ->and($message->deliveredAt)->toBe('2026-11-10T10:00:02Z')
        ->and($message->events)->toHaveCount(2)
        ->and($message->events[1]->id)->toBe('evt_2')
        ->and($message->events[1]->type)->toBe('delivered');
});

it('lists messages with filters and parses the paginated envelope', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, pagePayload([
        messagePayload(),
        messagePayload(['id' => 'msg_def456']),
    ], ['current_page' => 2, 'per_page' => 50, 'total' => 120, 'last_page' => 3])));
    $client = sdkClient($http);

    $page = $client->messages()->list([
        'status' => 'delivered',
        'recipient' => '+4790000000',
        'page' => 2,
        'per_page' => 50,
    ]);

    $request = $http->requests[0];
    parse_str($request->getUri()->getQuery(), $query);

    expect($request->getMethod())->toBe('GET')
        ->and($request->getUri()->getPath())->toBe('/api/v1/messages')
        ->and($query)->toBe([
            'status' => 'delivered',
            'recipient' => '+4790000000',
            'page' => '2',
            'per_page' => '50',
        ]);

    expect($page)->toBeInstanceOf(MessagePage::class)
        ->and($page->items)->toHaveCount(2)
        ->and($page->items[1]->id)->toBe('msg_def456')
        ->and($page->currentPage)->toBe(2)
        ->and($page->perPage)->toBe(50)
        ->and($page->total)->toBe(120)
        ->and($page->lastPage)->toBe(3);
});

it('turns a 422 response into a validation exception', function (): void {
    $http = new FakeHttpClient(jsonResponse(422, errorPayload(
        'validation_failed',
        'Ugyldig mottaker.',
        ['to' => ['Ugyldig mottaker.']],
    )));
    $client = sdkClient($http, retries: 3);

    try {
        $client->messages()->send('+4790000000', 'Vivalto', 'Hei');
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422)
            ->and($exception->errorCode)->toBe('validation_failed')
            ->and($exception->getMessage())->toBe('Ugyldig mottaker.')
            ->and($exception->errors())->toBe(['to' => ['Ugyldig mottaker.']]);
    }

    expect($http->requests)->toHaveCount(1);
});

it('turns a 404 response into a not found exception', function (): void {
    $http = new FakeHttpClient(jsonResponse(404, errorPayload('not_found', 'Meldingen finnes ikke.')));
    $client = sdkClient($http);

    expect(fn () => $client->messages()->get('msg_missing'))
        ->toThrow(NotFoundException::class, 'Meldingen finnes ikke.');
});
