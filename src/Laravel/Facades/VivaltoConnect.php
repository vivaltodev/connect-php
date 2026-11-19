<?php

declare(strict_types=1);

namespace Vivalto\Connect\Laravel\Facades;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Vivalto\Connect\Client;
use Vivalto\Connect\Laravel\VivaltoConnectServiceProvider;
use Vivalto\Connect\Testing\FakeClient;

/**
 * @method static \Vivalto\Connect\DTO\Account me()
 * @method static \Vivalto\Connect\Resources\Messages messages()
 * @method static \Vivalto\Connect\Resources\Documents documents()
 * @method static \Vivalto\Connect\Resources\Webhooks webhooks()
 * @method static \Vivalto\Connect\Resources\Pricing pricing()
 * @method static \Vivalto\Connect\Resources\Search search()
 * @method static \Vivalto\Connect\DTO\Health health()
 * @method static bool verifyWebhook(string $payload, string $header, string $secret, ?int $now = null, int $tolerance = 300)
 * @method static \Vivalto\Connect\Config config()
 *
 * @see Client
 */
final class VivaltoConnect extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }

    /**
     * Replace the bound client with one that answers from a fake transport.
     *
     * Keys are written as "METHOD /path" and values are a JSON body array, a
     * raw body string, a PSR-7 response or a throwable. Use the returned fake
     * to queue more responses and to assert what the SDK sent.
     *
     * @param  array<string, array<string, mixed>|string|ResponseInterface|Throwable>  $responses
     */
    public static function fake(array $responses = []): FakeClient
    {
        $app = self::getFacadeApplication();

        if ($app === null) {
            throw new LogicException('VivaltoConnect::fake() requires a Laravel application.');
        }

        $fake = new FakeClient;

        foreach ($responses as $endpoint => $response) {
            $parts = preg_split('/\s+/', trim((string) $endpoint), 2);

            if (! is_array($parts) || count($parts) !== 2 || $parts[1] === '') {
                throw new InvalidArgumentException(sprintf(
                    'The fake endpoint [%s] must be written as "METHOD /path".',
                    $endpoint,
                ));
            }

            $fake->on($parts[0], $parts[1], $response);
        }

        $config = [];

        $repository = $app->make('config');

        if ($repository instanceof Repository) {
            $stored = $repository->get('connect', []);
            $config = is_array($stored) ? $stored : [];
        }

        if (trim((string) ($config['api_key'] ?? '')) === '') {
            $config['api_key'] = 'test-key';
        }

        $app->instance(Client::class, VivaltoConnectServiceProvider::makeClient($config, $fake));

        self::clearResolvedInstance(Client::class);

        return $fake;
    }
}
