<?php

declare(strict_types=1);

namespace Vivalto\Connect\Testing;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * A deterministic PSR-18 transport for tests.
 *
 * Responses are matched by endpoint first and then consumed from the queue in
 * order. A queued throwable is thrown instead of returning a response. Every
 * request is recorded, so assertions can inspect what the SDK sent.
 *
 * Inject it through Config::httpClient, or use VivaltoConnect::fake() in
 * Laravel. Laravel applications can also use Http::fake(), which intercepts
 * the SDK through the Laravel transport.
 */
final class FakeClient implements ClientInterface
{
    /**
     * @var list<RequestInterface>
     */
    private array $requests = [];

    /**
     * @var list<ResponseInterface|Throwable>
     */
    private array $queue = [];

    /**
     * @var array<string, list<ResponseInterface|Throwable>>
     */
    private array $endpoints = [];

    public function __construct(ResponseInterface|Throwable ...$responses)
    {
        $this->queue = array_values($responses);
    }

    public static function create(ResponseInterface|Throwable ...$responses): self
    {
        return new self(...$responses);
    }

    /**
     * Queue a response that is returned after any endpoint specific ones.
     */
    public function queue(ResponseInterface|Throwable ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }

        return $this;
    }

    /**
     * Queue a JSON response with the given status code.
     *
     * @param  array<string, mixed>|string  $body
     * @param  array<string, string>  $headers
     */
    public function json(array|string $body, int $status = 200, array $headers = []): self
    {
        $this->queue[] = self::jsonResponse($body, $status, $headers);

        return $this;
    }

    /**
     * Answer every request to the given endpoint with the same response.
     *
     * @param  array<string, mixed>|string|ResponseInterface|Throwable  $response
     * @param  array<string, string>  $headers
     */
    public function on(
        string $method,
        string $path,
        array|string|ResponseInterface|Throwable $response,
        int $status = 200,
        array $headers = [],
    ): self {
        $key = self::key($method, $path);

        $this->endpoints[$key][] = match (true) {
            $response instanceof ResponseInterface, $response instanceof Throwable => $response,
            default => self::jsonResponse($response, $status, $headers),
        };

        return $this;
    }

    /**
     * Build a PSR-7 JSON response without queueing it.
     *
     * @param  array<string, mixed>|string  $body
     * @param  array<string, string>  $headers
     */
    public static function jsonResponse(array|string $body, int $status = 200, array $headers = []): Response
    {
        $payload = is_string($body)
            ? $body
            : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new Response($status, $headers + ['Content-Type' => 'application/json'], $payload);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        $key = self::key($request->getMethod(), (string) $request->getUri());

        if (isset($this->endpoints[$key]) && $this->endpoints[$key] !== []) {
            $next = array_shift($this->endpoints[$key]);

            return $this->respond($next);
        }

        if ($this->queue !== []) {
            return $this->respond(array_shift($this->queue));
        }

        throw new RuntimeException(sprintf(
            'No fake response for %s %s. Queue one with json() or register one with on().',
            $request->getMethod(),
            (string) $request->getUri(),
        ));
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function lastRequest(): ?RequestInterface
    {
        return $this->requests === [] ? null : $this->requests[array_key_last($this->requests)];
    }

    /**
     * @return list<RequestInterface>
     */
    public function sent(string $method, ?string $path = null): array
    {
        return array_values(array_filter(
            $this->requests,
            fn (RequestInterface $request): bool => $this->is($request, $method, $path),
        ));
    }

    public function assertSent(callable $callback): void
    {
        $matches = array_filter($this->requests, $callback);

        Assert::assertNotEmpty(
            $matches,
            'No request matching the given callback was sent. Sent: '.$this->describe(),
        );
    }

    public function assertNotSent(callable $callback): void
    {
        $matches = array_filter($this->requests, $callback);

        Assert::assertEmpty(
            $matches,
            'A request matching the given callback was sent. Sent: '.$this->describe(),
        );
    }

    public function assertSentTo(string $method, string $path, ?callable $callback = null): void
    {
        $matches = array_filter(
            $this->sent($method, $path),
            fn (RequestInterface $request): bool => $callback === null || $callback($request),
        );

        Assert::assertNotEmpty($matches, sprintf(
            'Expected a %s request to %s, but none was sent. Sent: %s',
            strtoupper($method),
            $path,
            $this->describe(),
        ));
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame(
            [],
            $this->requests,
            'Expected no request to be sent, but the SDK sent: '.$this->describe(),
        );
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->requests, sprintf(
            'Expected %d requests, but %d were sent.',
            $count,
            count($this->requests),
        ));
    }

    private function respond(ResponseInterface|Throwable|null $next): ResponseInterface
    {
        if ($next instanceof Throwable) {
            throw $next;
        }

        if (! $next instanceof ResponseInterface) {
            throw new RuntimeException('The fake response queue is empty.');
        }

        return $next;
    }

    private function is(RequestInterface $request, string $method, ?string $path): bool
    {
        if (strtoupper($request->getMethod()) !== strtoupper($method)) {
            return false;
        }

        return $path === null || self::pathOf((string) $request->getUri()) === self::pathOf($path);
    }

    private static function key(string $method, string $path): string
    {
        return strtoupper($method).' '.self::pathOf($path);
    }

    private static function pathOf(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        return $path;
    }

    private function describe(): string
    {
        if ($this->requests === []) {
            return 'no requests';
        }

        return implode(', ', array_map(
            static fn (RequestInterface $request): string => $request->getMethod().' '.self::pathOf((string) $request->getUri()),
            $this->requests,
        ));
    }
}
