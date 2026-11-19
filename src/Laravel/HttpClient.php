<?php

declare(strict_types=1);

namespace Vivalto\Connect\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\StrayRequestException;
use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Vivalto\Connect\Exceptions\NetworkException;

/**
 * A PSR-18 transport built on Laravel's HTTP client.
 *
 * Laravel installs Http::fake() stubs as Guzzle middleware on every
 * PendingRequest created by the factory. Routing SDK requests through
 * Factory::createPendingRequest() therefore makes Http::fake(),
 * Http::fakeSequence(), Http::assertSent(), Http::recorded() and
 * Http::preventStrayRequests() work on every SDK call.
 */
final class HttpClient implements ClientInterface
{
    /**
     * Headers Guzzle computes itself; copying them would send stale values
     * after the JSON body is re-encoded.
     */
    private const SKIPPED_HEADERS = [
        'connection',
        'content-length',
        'host',
        'transfer-encoding',
    ];

    public function __construct(
        private readonly Factory $http,
        private readonly int $timeout = 30,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $options = ['allow_redirects' => false];

        $body = (string) $request->getBody();
        $json = $this->decodeJson($request, $body);

        if ($json !== null) {
            $options['json'] = $json;
        }

        try {
            return $this->pending($request, $body, $json !== null)
                ->send($request->getMethod(), (string) $request->getUri(), $options)
                ->toPsrResponse();
        } catch (ConnectionException|RequestException|StrayRequestException $exception) {
            throw new NetworkException($exception->getMessage(), $request, $exception);
        }
    }

    private function pending(RequestInterface $request, string $body, bool $json): PendingRequest
    {
        $pending = $this->http->createPendingRequest()
            ->timeout($this->timeout)
            ->withHeaders($this->headers($request));

        if ($body === '') {
            return $pending;
        }

        if ($json) {
            return $pending->asJson();
        }

        $contentType = $request->getHeaderLine('Content-Type');

        return $pending->withBody($body, $contentType !== '' ? $contentType : 'application/octet-stream');
    }

    /**
     * @return array<string, string|string[]>
     */
    private function headers(RequestInterface $request): array
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            if (in_array(strtolower($name), self::SKIPPED_HEADERS, true)) {
                continue;
            }

            $headers[$name] = $values;
        }

        return $headers;
    }

    /**
     * Decode a JSON request body so Laravel assertions can read
     * $request->data() and $request['field'] instead of the raw string.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(RequestInterface $request, string $body): ?array
    {
        if ($body === '' || ! str_contains(strtolower($request->getHeaderLine('Content-Type')), 'json')) {
            return null;
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
