# Vivalto SMS PHP SDK

PHP client for the Vivalto SMS API v1. It sends SMS, reads message history,
manages webhook endpoints and verifies incoming webhook signatures.

- PHP 8.2 or newer
- PSR-18 HTTP client and PSR-17 factories
- Guzzle is the default transport

## Installation

```bash
composer require vivaltodev/sms-php
```

The package is not on Packagist yet. Until it is published, add the repository
to the client application:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:vivaltodev/sms-php.git"
        }
    ],
    "require": {
        "vivaltodev/sms-php": "^0.1"
    }
}
```

## Quick start

```php
use Vivalto\Sms\Client;
use Vivalto\Sms\Config;

$client = new Client(new Config(
    baseUrl: 'https://sms.vivalto.no',
    apiKey: getenv('VIVALTO_SMS_API_KEY'),
));

$message = $client->messages()->send(
    to: '+4790000000',
    from: 'Vivalto',
    text: 'Hei fra Vivalto SMS',
    reference: 'order-123',
);

echo $message->id;
```

`send()` generates an `Idempotency-Key` with `bin2hex(random_bytes(16))` and
reuses it for every retry of the same call. Pass `idempotencyKey:` to supply
your own, for example when a queue job may be retried by the queue worker:

```php
$client->messages()->send(
    to: '+4790000000',
    from: 'Vivalto',
    text: 'Hei',
    idempotencyKey: 'order-123-sms-1',
);
```

The optional arguments are `class` (`standard`, `premium`, `secret`),
`reference` and `metadata`.

### Read a message

```php
$message = $client->messages()->get('msg_abc123');

echo $message->status;       // queued, sending, sent, delivered, failed, ...
echo $message->deliveredAt;  // ISO 8601 timestamp or null
```

### List messages

```php
$page = $client->messages()->list([
    'status' => 'delivered',
    'recipient' => '+4790000000',
    'page' => 2,
    'per_page' => 50,
]);

foreach ($page->items as $message) {
    echo $message->id;
}

echo $page->total;
```

Supported filters are `status`, `reference`, `recipient`, `created_after`,
`created_before`, `per_page` and `page`.

## Webhooks

```php
$endpoint = $client->webhooks()->create(
    url: 'https://client.example.com/hooks/vivalto',
    events: ['message.delivered', 'message.failed'],
    description: 'Production',
);

// Returned once. Store it now; the API never returns it again.
$secret = $endpoint->secret;

$endpoints = $client->webhooks()->list();

$deliveries = $client->webhooks()->deliveries($endpoint->id, page: 1, perPage: 20);
```

### Verify an incoming webhook

The signature header has the form `t=<timestamp>,v1=<hex>`. The digest is
HMAC-SHA256 over `<timestamp>.<raw body>`.

```php
use Vivalto\Sms\WebhookSignature;

$payload = file_get_contents('php://input');
$header = $_SERVER['HTTP_X_VIVALTO_SIGNATURE'] ?? '';

if (! $client->verifyWebhook($payload, $header, $secret)) {
    http_response_code(400);

    exit;
}

// The payload is authentic and inside the 300 second tolerance window.
```

`verifyWebhook()` accepts an optional `$now` unix timestamp (useful in tests)
and a `$tolerance` in seconds. `WebhookSignature::sign($payload, $secret, $timestamp)`
is available for test fixtures.

## Errors

Every non-2xx response is converted to an exception. All of them extend
`Vivalto\Sms\Exceptions\ApiException` and expose `status`, `errorCode` and
`details`.

| Exception | Status |
| --- | --- |
| `AuthenticationException` | 401, 403 |
| `NotFoundException` | 404 |
| `ValidationException` | 422 |
| `RateLimitException` | 429, with `retryAfter` |
| `ServerException` | 5xx after retries are exhausted |
| `ApiException` | any other status |
| `TransportException` | PSR-18 transport failure |

```php
use Vivalto\Sms\Exceptions\ValidationException;

try {
    $client->messages()->send('+4790000000', 'Vivalto', 'Hei');
} catch (ValidationException $exception) {
    $errors = $exception->errors();
}
```

## Retries

The client retries 429 and 5xx responses up to `retries` times, with
exponential backoff (100ms, 200ms, 400ms, and so on) plus up to 100ms of
jitter. Other 4xx responses are never retried. The `Idempotency-Key` header is
identical on every attempt, so a retry cannot create a duplicate message.

```php
$client = new Client(new Config(
    baseUrl: 'https://sms.vivalto.no',
    apiKey: $apiKey,
    timeout: 30,
    retries: 3,
));
```

## Custom PSR-18 client

```php
use Vivalto\Sms\Client;
use Vivalto\Sms\Config;

$client = new Client(new Config(
    baseUrl: 'https://sms.vivalto.no',
    apiKey: $apiKey,
    httpClient: $psr18Client,
    requestFactory: $psr17RequestFactory,
    streamFactory: $psr17StreamFactory,
));
```

## Laravel

The service provider and facade are optional and are discovered automatically
by Laravel package discovery.

```bash
php artisan vendor:publish --tag=vivalto-sms-config
```

```dotenv
VIVALTO_SMS_API_KEY=your-api-key
VIVALTO_SMS_BASE_URL=https://sms.vivalto.no
VIVALTO_SMS_TIMEOUT=30
VIVALTO_SMS_RETRIES=3
```

```php
use Vivalto\Sms\Laravel\Facades\VivaltoSms;

VivaltoSms::messages()->send('+4790000000', 'Vivalto', 'Hei');
```

The provider binds `Vivalto\Sms\Client` as a singleton, so constructor
injection works as usual.

## Development

```bash
composer install
composer test
composer lint
composer analyse
```

## Versioning

The package follows semantic versioning. The current release line is 0.1.

## License

This package is proprietary. `composer.json` marks it as `proprietary`, and the
license must be confirmed with the project owner before publishing to Packagist
or sharing it outside the Vivalto client applications.
