# Vivalto Connect PHP SDK

PHP client for the Vivalto Connect API v1. It sends SMS, renders HTML to PDF
or images, reads message and document history, manages webhook endpoints,
verifies incoming webhook signatures, reads account limits and prices, and
handles hosted Meilisearch status, usage and tenant tokens.

- API documentation: https://connect.vivalto.no/docs
- PHP 8.2 or newer
- PSR-18 HTTP client and PSR-17 factories
- Guzzle is the default transport

## Installation

```bash
composer require vivaltodev/connect-php
```

The package is published on Packagist as `vivaltodev/connect-php`. To develop
the SDK inside the Connect monorepo, add the package directory as a path
repository instead:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../connect/packages/php-sdk",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "vivaltodev/connect-php": "^0.4"
    }
}
```

## Quick start

```php
use Vivalto\Connect\Client;
use Vivalto\Connect\Config;

$client = new Client(new Config(
    baseUrl: 'https://connect.vivalto.no',
    apiKey: getenv('VIVALTO_CONNECT_API_KEY'),
));

$message = $client->messages()->send(
    to: '+4790000000',
    from: 'Vivalto',
    text: 'Hei fra Vivalto Connect',
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

## Account and limits

`me()` returns the identity, scopes, products and rate limits of the API key.
Use it to fail fast on a misconfigured environment.

```php
$account = $client->me();

echo $account->project->name;          // Nettsiden
echo $account->rateLimitPerMinute;     // 600

if (! $account->hasScope('messages:send')) {
    throw new RuntimeException('This key cannot send SMS.');
}

$account->productKeys();               // ['sms', 'pdf']
```

## Prices

`pricing()` returns every price that currently applies to the project, merged
in precedence order. The API never exposes provider cost or margin.

```php
$prices = $client->pricing()->list();

foreach ($prices->for('NO', 'standard') as $item) {
    echo $item->partsMin.'-'.($item->partsMax ?? '∞').': '
        .$item->price->amount.' '.$item->price->currency;
}
```

A per-part price applies to any part count, so its `partsMax` is `null` and
`perPart` is `true`. A fixed price only applies inside `partsMin`-`partsMax`.

## Documents

### Render a PDF

```php
$result = $client->documents()->render(
    html: '<h1>Hei fra Vivalto</h1>',
    options: [
        'format' => 'A4',
        'orientation' => 'portrait',
        'margins' => ['top' => 20, 'right' => 15, 'bottom' => 20, 'left' => 15],
    ],
);

file_put_contents('dokument.pdf', $result->contents);

echo $result->mimeType;    // application/pdf
echo $result->documentId;  // doc_...
```

`render()` returns the binary file synchronously and works for both PDF and
images. Use `renderToFile()` to write it directly:

```php
$client->documents()->renderToFile('dokument.pdf', '<h1>Hei</h1>');
```

### Render an image

```php
$result = $client->documents()->render(
    html: '<h1>Hei</h1>',
    options: [
        'type' => 'png',
        'window_size' => ['width' => 1200, 'height' => 630],
        'full_page' => true,
    ],
);

file_put_contents('bilde.png', $result->contents);
```

### Asynchronous rendering

```php
$document = $client->documents()->create(
    html: '<h1>Stor rapport</h1>',
    options: ['format' => 'A4'],
);

// Poll until the document is completed.
$document = $client->documents()->get($document->id);

if ($document->isCompleted()) {
    $pdf = $client->documents()->download($document->downloadUrl);
}
```

### List documents

```php
$page = $client->documents()->list([
    'type' => 'pdf',
    'status' => 'completed',
    'per_page' => 25,
]);

foreach ($page->items as $document) {
    echo $document->id.' '.$document->status;
}
```

Supported filters are `type`, `status`, `from`, `to`, `per_page` and `page`.

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

// Only the supplied attributes are sent. null clears a field.
$client->webhooks()->update($endpoint->id, [
    'events' => ['message.delivered', 'message.failed', 'message.queued'],
    'is_active' => false,
]);

// Deleting an endpoint stops further deliveries immediately.
$client->webhooks()->delete($endpoint->id);
```

The signing secret cannot be changed on an existing endpoint. Create a new
one, switch the receiving URL, and delete the old one to rotate it.

### Verify an incoming webhook

The signature header has the form `t=<timestamp>,v1=<hex>`. The digest is
HMAC-SHA256 over `<timestamp>.<raw body>`.

```php
use Vivalto\Connect\WebhookSignature;

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

## Search

Search is a hosted Meilisearch instance with one isolated namespace per
project. Connect is the control plane: the SDK reads status and usage and
signs tenant tokens, while search traffic goes directly from the client to
the Meilisearch host.

```php
$status = $client->search()->status();

echo $status->namespace;      // prj_a1b2c3d4e5__
echo $status->status;         // active, degraded, pending, failed or disabled
echo $status->documents;      // 12480
echo $status->index('products')?->documents;
```

`status()` is built from cached tables, so it keeps answering while
Meilisearch itself is down. `healthy()` can be `false` while `status` is still
`active`.

```php
$usage = $client->search()->usage(days: 7);

foreach ($usage->rows as $day) {
    echo $day->date.' '.$day->documents.' '.($day->searches ?? '-');
}
```

The window is clamped to 1-365 days and defaults to 30.

### Tenant tokens

For a private frontend, sign a short-lived token instead of shipping a search
key to the browser:

```php
$token = $client->search()->token(
    index: 'products',
    filter: 'user_id = 42',
    expiresIn: 900,
);

// Send token and host to the frontend.
echo $token->token;
echo $token->host;
```

The lifetime is 60 to 3600 seconds and defaults to 900. The token only works
inside the project namespace, can carry one filter, and becomes invalid the
moment the parent API key is revoked. A 409 means the credential is still
provisioning; retry shortly.

### Laravel Scout

Scout is not wrapped by the SDK, and does not need to be. Scout talks directly
to Meilisearch with four environment variables from the portal:

```dotenv
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=https://search.connect.vivalto.no
MEILISEARCH_KEY=<write key from the portal>
SCOUT_PREFIX=prj_a1b2c3d4e5__
```

Meilisearch keys are deliberately not part of the API, so the SDK can neither
fetch nor rotate them. Use the SDK for status, usage and tenant tokens, and
the portal for the write key. `SCOUT_PREFIX` only applies through the default
`searchableAs()`; a model that overrides it must include the namespace itself.

## Health

`health()` reads the public health check. A degraded API answers 503; the SDK
returns the reported checks instead of throwing, and it does not retry.

```php
$health = $client->health();

if (! $health->isHealthy()) {
    foreach ($health->failedChecks() as $check) {
        logger()->warning($check->name.': '.$check->message);
    }
}
```

## Errors

Every non-2xx response is converted to an exception. All of them extend
`Vivalto\Connect\Exceptions\ApiException` and expose `status`, `errorCode` and
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
use Vivalto\Connect\Exceptions\ValidationException;

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
    baseUrl: 'https://connect.vivalto.no',
    apiKey: $apiKey,
    timeout: 30,
    retries: 3,
));
```

## Custom PSR-18 client

```php
use Vivalto\Connect\Client;
use Vivalto\Connect\Config;

$client = new Client(new Config(
    baseUrl: 'https://connect.vivalto.no',
    apiKey: $apiKey,
    httpClient: $psr18Client,
    requestFactory: $psr17RequestFactory,
    streamFactory: $psr17StreamFactory,
));
```

## Framework integration

The package includes an optional service provider and facade for frameworks
that support package discovery. They are not required; the client works
standalone with any PSR-18 implementation.

In Laravel the provider is discovered automatically and binds `Client` as a
singleton from `config/connect.php`. Publish the config when you want to edit
it:

```bash
php artisan vendor:publish --tag=connect-config
```

| Variable | Purpose |
| --- | --- |
| `VIVALTO_CONNECT_API_KEY` | API key from the portal. Required. |
| `VIVALTO_CONNECT_SENDER` | Default sender for the test SMS command. Must be approved for the customer. |
| `VIVALTO_CONNECT_BASE_URL` | API base URL. Defaults to `https://connect.vivalto.no`. |
| `VIVALTO_CONNECT_TIMEOUT` | Request timeout in seconds. Defaults to 30. |
| `VIVALTO_CONNECT_RETRIES` | Retries for 429 and 5xx responses. Defaults to 3. |

### Send a test SMS

Install the package in a Laravel app and send a real message with the API key
the app already uses:

```bash
php artisan connect:test-sms +4790000000
```

| Argument or option | Purpose |
| --- | --- |
| `to` | Recipient in E.164 format. Required. |
| `--from=` | Approved sender. Falls back to `VIVALTO_CONNECT_SENDER`. |
| `--text=` | Message body. Defaults to a timestamped test message. |
| `--class=` | `standard` (default), `premium` or `secret`. |
| `--reference=` | Optional reference stored on the message. |
| `--wait=` | Seconds to poll for a final status. Needs the `messages:read` scope. `0` returns immediately. |

The command prints the message id, status, part count and price. A failed
request exits with `1` and prints the API error code, field errors and a hint
for the codes that are easy to get wrong, such as `invalid_sender` and
`invalid_recipient`. With `--wait` the command follows the status changes and
exits with `1` when the message ends as `failed`, `expired` or `rejected`.

```text
The test SMS was accepted by the API.

+------------+---------------------------+
| Field      | Value                     |
+------------+---------------------------+
| ID         | msg_7f3k9m2p1q            |
| Status     | queued                    |
| To         | +4791234567               |
| From       | VIVALTO                   |
| Class      | standard                  |
| Parts      | 1                         |
| Price      | 0,45 NOK (estimated)      |
+------------+---------------------------+

Use --wait=30 to follow the delivery status.
```

The command needs the `messages:send` scope, and `messages:read` when `--wait`
is used because it reads the message back. An empty
`VIVALTO_CONNECT_API_KEY` or a missing sender stops the command before any
request is sent.

```bash
php artisan connect:test-sms +4790000000 --from=VIVALTO --wait=30
```

The default text includes the current time to the second, because the API
returns the same message when an identical send repeats within 60 seconds.

## Development

```bash
composer install
composer test
composer analyse
```

## Versioning

The package follows semantic versioning. The current release line is 0.4.

## License

This package is proprietary. `composer.json` marks it as `proprietary`.
