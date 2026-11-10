<?php

namespace Vivalto\Sms;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Vivalto\Sms\Exceptions\ApiException;
use Vivalto\Sms\Exceptions\AuthenticationException;
use Vivalto\Sms\Exceptions\NotFoundException;
use Vivalto\Sms\Exceptions\RateLimitException;
use Vivalto\Sms\Exceptions\ServerException;
use Vivalto\Sms\Exceptions\TransportException;
use Vivalto\Sms\Exceptions\ValidationException;
use Vivalto\Sms\Resources\Messages;
use Vivalto\Sms\Resources\Webhooks;

/**
 * The entry point for the Vivalto SMS API.
 *
 * The client speaks PSR-18 and PSR-17. Guzzle is used by default, but any
 * compliant implementation can be injected through Config.
 */
final class Client
{
    public const VERSION = '0.1.0';

    private readonly ClientInterface $httpClient;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    private ?Messages $messages = null;

    private ?Webhooks $webhooks = null;

    public function __construct(private readonly Config $config)
    {
        $factory = new HttpFactory;

        $this->httpClient = $config->httpClient ?? new GuzzleClient([
            'timeout' => $config->timeout,
            'connect_timeout' => $config->timeout,
        ]);
        $this->requestFactory = $config->requestFactory ?? $factory;
        $this->streamFactory = $config->streamFactory ?? $factory;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function messages(): Messages
    {
        return $this->messages ??= new Messages($this);
    }

    public function webhooks(): Webhooks
    {
        return $this->webhooks ??= new Webhooks($this);
    }

    /**
     * Verify the signature header on an incoming webhook.
     */
    public function verifyWebhook(
        string $payload,
        string $header,
        string $secret,
        ?int $now = null,
        int $tolerance = WebhookSignature::DEFAULT_TOLERANCE,
    ): bool {
        return WebhookSignature::verify($payload, $header, $secret, $now, $tolerance);
    }

    /**
     * Send one API request and decode its JSON body.
     *
     * 429 and 5xx responses are retried with exponential backoff, reusing the
     * caller supplied headers (including Idempotency-Key). Other 4xx responses
     * are never retried.
     *
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @internal
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $headers = [],
        array $query = [],
    ): array {
        $attempt = 0;

        while (true) {
            $request = $this->buildRequest($method, $path, $body, $headers, $query);

            try {
                $response = $this->httpClient->sendRequest($request);
            } catch (ClientExceptionInterface $exception) {
                throw new TransportException(
                    'Could not reach the Vivalto SMS API: '.$exception->getMessage(),
                    $exception,
                );
            }

            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                return $this->decode($response);
            }

            $retryable = $status === 429 || $status >= 500;

            if ($retryable && $attempt < $this->config->retries) {
                $this->sleepBeforeRetry($attempt);
                $attempt++;

                continue;
            }

            throw $this->exceptionFrom($response);
        }
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $query
     */
    private function buildRequest(
        string $method,
        string $path,
        ?array $body,
        array $headers,
        array $query,
    ): RequestInterface {
        $request = $this->requestFactory->createRequest($method, $this->url($path, $query))
            ->withHeader('Authorization', 'Bearer '.$this->config->apiKey)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', 'vivalto-sms-php/'.self::VERSION);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body === null) {
            return $request;
        }

        $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($json));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function url(string $path, array $query): string
    {
        $url = rtrim($this->config->baseUrl, '/').'/'.ltrim($path, '/');

        $query = array_filter(
            $query,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        if ($query === []) {
            return $url;
        }

        return $url.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $contents = (string) $response->getBody();

        if (trim($contents) === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException(
                'The Vivalto SMS API returned invalid JSON.',
                $response->getStatusCode(),
                'invalid_response',
            );
        }

        if (! is_array($decoded)) {
            throw new ApiException(
                'The Vivalto SMS API returned an unexpected JSON payload.',
                $response->getStatusCode(),
                'invalid_response',
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function exceptionFrom(ResponseInterface $response): ApiException
    {
        $status = $response->getStatusCode();
        $contents = (string) $response->getBody();

        $payload = json_decode($contents, true);
        $error = is_array($payload) && isset($payload['error']) && is_array($payload['error'])
            ? $payload['error']
            : [];

        $code = isset($error['code']) && is_string($error['code']) ? $error['code'] : null;
        $message = isset($error['message']) && is_string($error['message']) && $error['message'] !== ''
            ? $error['message']
            : 'Vivalto SMS API returned HTTP '.$status.'.';
        $details = $error['details'] ?? null;

        return match (true) {
            $status === 401, $status === 403 => new AuthenticationException($message, $status, $code, $details),
            $status === 404 => new NotFoundException($message, $status, $code, $details),
            $status === 422 => new ValidationException($message, $status, $code, $details),
            $status === 429 => new RateLimitException($message, $status, $code, $details, $this->retryAfter($response)),
            $status >= 500 => new ServerException($message, $status, $code, $details),
            default => new ApiException($message, $status, $code, $details),
        };
    }

    private function retryAfter(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));

        return ctype_digit($value) ? (int) $value : null;
    }

    /**
     * Sleep 100ms, 200ms, 400ms and so on, plus up to 100ms of jitter.
     */
    private function sleepBeforeRetry(int $attempt): void
    {
        $backoff = 100 * (2 ** $attempt);
        $jitter = random_int(0, 100);

        usleep(($backoff + $jitter) * 1000);
    }
}
