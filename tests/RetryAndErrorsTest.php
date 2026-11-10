<?php

use GuzzleHttp\Psr7\Response;
use Vivalto\Sms\Exceptions\ApiException;
use Vivalto\Sms\Exceptions\AuthenticationException;
use Vivalto\Sms\Exceptions\RateLimitException;
use Vivalto\Sms\Exceptions\ServerException;
use Vivalto\Sms\Exceptions\TransportException;
use Vivalto\Sms\Exceptions\ValidationException;
use Vivalto\Sms\Tests\Support\FakeHttpClient;
use Vivalto\Sms\Tests\Support\FakeTransportException;

it('retries a 429 and a 500 and reuses the same idempotency key', function (): void {
    $http = new FakeHttpClient(
        jsonResponse(429, errorPayload('rate_limited', 'For mange forespørsler.')),
        jsonResponse(500, errorPayload('server_error', 'Noe gikk galt.')),
        jsonResponse(201, messagePayload()),
    );

    $client = sdkClient($http, retries: 2);

    $message = $client->messages()->send('+4790000000', 'Vivalto', 'Hei');

    expect($http->requests)->toHaveCount(3)
        ->and($message->id)->toBe('msg_abc123');

    $keys = array_map(
        fn ($request): string => $request->getHeaderLine('Idempotency-Key'),
        $http->requests,
    );

    expect(array_unique($keys))->toHaveCount(1)
        ->and($keys[0])->toMatch('/^[a-f0-9]{32}$/');
});

it('does not retry a 422', function (): void {
    $http = new FakeHttpClient(jsonResponse(422, errorPayload('validation_failed', 'Ugyldig.')));
    $client = sdkClient($http, retries: 3);

    expect(fn () => $client->messages()->send('+4790000000', 'Vivalto', 'Hei'))
        ->toThrow(ValidationException::class);

    expect($http->requests)->toHaveCount(1);
});

it('throws a server exception after the final 5xx attempt', function (): void {
    $http = new FakeHttpClient(
        jsonResponse(500, errorPayload('server_error', 'Første feil.')),
        jsonResponse(503, errorPayload('unavailable', 'Andre feil.')),
    );
    $client = sdkClient($http, retries: 1);

    try {
        $client->messages()->send('+4790000000', 'Vivalto', 'Hei');
        $this->fail('Expected a ServerException.');
    } catch (ServerException $exception) {
        expect($exception->status)->toBe(503)
            ->and($exception->errorCode)->toBe('unavailable')
            ->and($exception->getMessage())->toBe('Andre feil.');
    }

    expect($http->requests)->toHaveCount(2);
});

it('exposes retry-after on a rate limit exception', function (): void {
    $http = new FakeHttpClient(new Response(
        429,
        ['Content-Type' => 'application/json', 'Retry-After' => '7'],
        json_encode(errorPayload('rate_limited', 'For mange forespørsler.'), JSON_THROW_ON_ERROR),
    ));
    $client = sdkClient($http, retries: 0);

    try {
        $client->messages()->send('+4790000000', 'Vivalto', 'Hei');
        $this->fail('Expected a RateLimitException.');
    } catch (RateLimitException $exception) {
        expect($exception->status)->toBe(429)
            ->and($exception->retryAfter)->toBe(7);
    }
});

it('throws an authentication exception for a 401', function (): void {
    $http = new FakeHttpClient(jsonResponse(401, errorPayload('unauthenticated', 'Ugyldig API-nøkkel.')));
    $client = sdkClient($http);

    expect(fn () => $client->messages()->list())
        ->toThrow(AuthenticationException::class, 'Ugyldig API-nøkkel.');
});

it('falls back to a generic api exception for unmapped statuses', function (): void {
    $http = new FakeHttpClient(jsonResponse(409, errorPayload('idempotency_conflict', 'Nøkkelen er brukt.')));
    $client = sdkClient($http);

    try {
        $client->messages()->send('+4790000000', 'Vivalto', 'Hei');
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception->status)->toBe(409)
            ->and($exception->errorCode)->toBe('idempotency_conflict');
    }
});

it('builds a readable message when the error envelope is missing', function (): void {
    $http = new FakeHttpClient(jsonResponse(502, 'Bad gateway'));
    $client = sdkClient($http);

    try {
        $client->messages()->list();
        $this->fail('Expected a ServerException.');
    } catch (ServerException $exception) {
        expect($exception->status)->toBe(502)
            ->and($exception->errorCode)->toBeNull()
            ->and($exception->getMessage())->toBe('Vivalto SMS API returned HTTP 502.');
    }
});

it('keeps the transport failure as the previous exception', function (): void {
    $original = new FakeTransportException('Connection refused');
    $http = new FakeHttpClient($original);
    $client = sdkClient($http);

    try {
        $client->messages()->list();
        $this->fail('Expected a TransportException.');
    } catch (TransportException $exception) {
        expect($exception->getPrevious())->toBe($original)
            ->and($exception->errorCode)->toBeNull()
            ->and($exception->getMessage())->toContain('Connection refused');
    }
});
