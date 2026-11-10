<?php

declare(strict_types=1);

namespace Vivalto\Connect\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Vivalto\Connect\Client;
use Vivalto\Connect\Config;

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

            return self::makeClient(is_array($config) ? $config : []);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/connect.php' => $this->app->configPath('connect.php'),
            ], 'connect-config');
        }
    }

    /**
     * Build a client from a Laravel config array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function makeClient(array $config): Client
    {
        return new Client(new Config(
            baseUrl: (string) ($config['base_url'] ?? 'https://connect.vivalto.no'),
            apiKey: (string) ($config['api_key'] ?? ''),
            timeout: (int) ($config['timeout'] ?? 30),
            retries: (int) ($config['retries'] ?? 3),
        ));
    }
}
