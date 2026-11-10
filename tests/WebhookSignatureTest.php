<?php

use Vivalto\Sms\Tests\Support\FakeHttpClient;
use Vivalto\Sms\WebhookSignature;

it('signs a payload as t=<timestamp>,v1=<hex>', function (): void {
    $signature = WebhookSignature::sign('{"hello":"world"}', 'secret', 1_700_000_000);

    $expected = hash_hmac('sha256', '1700000000.{"hello":"world"}', 'secret');

    expect($signature)->toBe('t=1700000000,v1='.$expected);
});

it('accepts a valid webhook signature', function (): void {
    $payload = '{"event":"message.delivered"}';
    $secret = 'whsec_test';
    $now = 1_700_000_000;
    $header = WebhookSignature::sign($payload, $secret, $now);

    $client = sdkClient(new FakeHttpClient);

    expect($client->verifyWebhook($payload, $header, $secret, $now))->toBeTrue();
});

it('accepts a signature inside the tolerance window', function (): void {
    $payload = '{"event":"message.delivered"}';
    $secret = 'whsec_test';
    $header = WebhookSignature::sign($payload, $secret, 1_700_000_000);

    $client = sdkClient(new FakeHttpClient);

    expect($client->verifyWebhook($payload, $header, $secret, 1_700_000_299))->toBeTrue();
});

it('rejects a signature outside the tolerance window', function (): void {
    $payload = '{"event":"message.delivered"}';
    $secret = 'whsec_test';
    $header = WebhookSignature::sign($payload, $secret, 1_700_000_000);

    $client = sdkClient(new FakeHttpClient);

    expect($client->verifyWebhook($payload, $header, $secret, 1_700_000_301))->toBeFalse()
        ->and($client->verifyWebhook($payload, $header, $secret, 1_700_000_300))->toBeTrue()
        ->and($client->verifyWebhook($payload, $header, $secret, 1_700_000_301, tolerance: 301))->toBeTrue();
});

it('rejects a tampered payload', function (): void {
    $secret = 'whsec_test';
    $header = WebhookSignature::sign('{"amount":10}', $secret, 1_700_000_000);

    expect(WebhookSignature::verify('{"amount":1000}', $header, $secret, 1_700_000_000))->toBeFalse();
});

it('rejects a wrong secret', function (): void {
    $payload = '{"event":"message.delivered"}';
    $header = WebhookSignature::sign($payload, 'right-secret', 1_700_000_000);

    expect(WebhookSignature::verify($payload, $header, 'wrong-secret', 1_700_000_000))->toBeFalse();
});

it('rejects malformed signature headers', function (string $header): void {
    expect(WebhookSignature::verify('{}', $header, 'secret', 1_700_000_000))->toBeFalse();
})->with([
    'empty' => '',
    'missing digest' => 't=1700000000',
    'missing timestamp' => 'v1=abc',
    'non numeric timestamp' => 't=abc,v1=abc',
    'extra whitespace' => '  t=1700000000 , v1=abc ',
]);
