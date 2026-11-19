# Changelog

All notable changes to `vivaltodev/connect-php` are documented here. The
format follows Keep a Changelog and the package uses semantic versioning.

## 0.5.0 - 2026-11-19

### Added

- Laravel applications use `Illuminate\Http\Client\Factory` as the transport
  automatically. `Http::fake()`, `Http::fakeSequence()`, `Http::assertSent()`,
  `Http::assertSentCount()`, `Http::recorded()` and
  `Http::preventStrayRequests()` now intercept every SDK request without
  extra setup. JSON request bodies are attached as data, so assertions can
  read `$request['field']` instead of the raw string.
- `Vivalto\Connect\Laravel\HttpClient`, a PSR-18 transport over Laravel's
  HTTP client for applications that wire the transport themselves.
- `Vivalto\Connect\Testing\FakeClient`, a framework independent PSR-18 fake
  with ordered responses, endpoint responders (`on()`), queueing of raw
  responses and throwables, request recording and the assertions
  `assertSent()`, `assertNotSent()`, `assertSentTo()`, `assertNothingSent()`
  and `assertSentCount()`.
- `VivaltoConnect::fake()`, which swaps the container binding for a
  `FakeClient` and returns it, in the style of `Mail::fake()`.
- A `http_client` key in `config/connect.php` that accepts a PSR-18 class
  name or instance. Laravel's HTTP client is used when the key is null.
- `illuminate/http` is suggested and listed as a development dependency.

### Changed

- `VivaltoConnectServiceProvider::makeClient()` accepts an optional
  `ClientInterface` transport as its second argument.
- The README and the API documentation describe testing with `Http::fake()`,
  `VivaltoConnect::fake()` and `FakeClient`.

## 0.4.0 - 2026-11-18

### Added

- `connect:test-sms`, an Artisan command that sends a real test SMS with the
  configured API key. It accepts any recipient, an optional `--from`,
  `--text`, `--class` and `--reference`, prints the message id, status, parts
  and price, and `--wait=N` follows the status until it is final. The command
  exits with 1 when the request fails or the message ends as `failed`,
  `expired` or `rejected`.
- A `sender` key in `config/connect.php`, read from
  `VIVALTO_CONNECT_SENDER`, used as the default sender for the command.
- `illuminate/console` is suggested for Laravel apps that want the command.

### Changed

- The PHP SDK documentation and the API documentation on `/docs` describe the
  test SMS command, its options, exit codes and the scopes it needs.

## 0.3.0 - 2026-11-16

### Added

- `Client::me()` reads the identity, scopes, products, default region and rate
  limits of the API key.
- `Client::pricing()` lists every price that applies to the project, with
  `perPart` and open ended part ranges.
- `Client::health()` reads the public health check. A degraded API answers
  503; the SDK returns the reported checks instead of throwing and does not
  retry.
- `Client::search()` exposes search status, daily usage and short-lived
  tenant tokens.
- `Webhooks::update()` patches the url, events, description, active flag or
  API key binding of an endpoint.
- `Webhooks::delete()` removes an endpoint and stops further deliveries.
- `Payload::assoc()` for required nested objects in responses.

### Fixed

- The `User-Agent` header reported 0.2.0 for every 0.2.x release. It now
  matches `Client::VERSION`.

## 0.2.3 - 2026-11-10

### Added

- Document rendering to PDF, PNG and JPEG, both synchronous and asynchronous,
  with list, lookup and signed download.

### Fixed

- Pagination and transport handling in the SDK.

## 0.2.2 - 2026-11-10

### Changed

- Documentation points to the API documentation and the Packagist package.

## 0.2.1 - 2026-11-10

### Changed

- Installation instructions install from Packagist.

## 0.2.0 - 2026-11-10

### Added

- First public release: messages, documents, webhooks and webhook signature
  verification, plus an optional Laravel service provider and facade.
