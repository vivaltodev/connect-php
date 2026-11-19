<?php

declare(strict_types=1);

namespace Vivalto\Connect\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Vivalto\Connect\Client;
use Vivalto\Connect\Config;
use Vivalto\Connect\Laravel\Commands\SendTestSmsCommand;

/**
 * Optional Laravel integration.
 *
 * The package works without Illuminate; this provider is only loaded by
 * Laravel package discovery or when an application registers it manually.
 */
final class VivaltoConnectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/connect.php', 'connect');

        $this->app->singleton(Client::class, function (): Client {
            /** @var Repository $repository */
            $repository = $this->app->make('config');
            $config = $repository->get('connect', []);
            $config = is_array($config) ? $config : [];

            return self::makeClient($config, self::transport($this->app, $config));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/connect.php' => $this->app->configPath('connect.php'),
            ], 'connect-config');

            $this->commands(self::artisanCommands());
        }
    }

    /**
     * The Artisan commands the package registers.
     *
     * @return list<class-string>
     */
    public static function artisanCommands(): array
    {
        return [SendTestSmsCommand::class];
    }

    /**
     * Build a client from a Laravel config array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function makeClient(array $config, ?ClientInterface $httpClient = null): Client
    {
        return new Client(new Config(
            baseUrl: (string) ($config['base_url'] ?? 'https://connect.vivalto.no'),
            apiKey: (string) ($config['api_key'] ?? ''),
            timeout: (int) ($config['timeout'] ?? 30),
            retries: (int) ($config['retries'] ?? 3),
            httpClient: $httpClient,
        ));
    }

    /**
     * Resolve the transport used by the client.
     *
     * An explicit http_client from config wins. Otherwise Laravel's HTTP
     * client is used when it is installed, which makes Http::fake(),
     * Http::fakeSequence() and Http::preventStrayRequests() intercept every
     * SDK request. Without Laravel the SDK falls back to Guzzle.
     *
     * @param  array<string, mixed>  $config
     */
    private static function transport(Container $app, array $config): ?ClientInterface
    {
        $configured = $config['http_client'] ?? null;

        if ($configured instanceof ClientInterface) {
            return $configured;
        }

        if (is_string($configured) && $configured !== '') {
            $resolved = $app->make($configured);

            if (! $resolved instanceof ClientInterface) {
                throw new InvalidArgumentException(sprintf(
                    'The connect.http_client class [%s] must implement %s.',
                    $configured,
                    ClientInterface::class,
                ));
            }

            return $resolved;
        }

        if (! class_exists(HttpFactory::class) || ! $app->bound(HttpFactory::class)) {
            return null;
        }

        return new HttpClient($app->make(HttpFactory::class), (int) ($config['timeout'] ?? 30));
    }
}
