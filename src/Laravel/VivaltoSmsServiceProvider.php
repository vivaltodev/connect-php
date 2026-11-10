<?php

declare(strict_types=1);

namespace Vivalto\Sms\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Vivalto\Sms\Client;
use Vivalto\Sms\Config;

/**
 * Optional Laravel integration.
 *
 * The package works without Illuminate; this provider is only loaded by
 * Laravel package discovery or when an application registers it manually.
 */
final class VivaltoSmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/vivalto-sms.php', 'vivalto-sms');

        $this->app->singleton(Client::class, function (): Client {
            /** @var Repository $repository */
            $repository = $this->app->make('config');
            $config = $repository->get('vivalto-sms', []);

            return self::makeClient(is_array($config) ? $config : []);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/vivalto-sms.php' => $this->app->configPath('vivalto-sms.php'),
            ], 'vivalto-sms-config');
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
            baseUrl: (string) ($config['base_url'] ?? 'https://sms.vivalto.no'),
            apiKey: (string) ($config['api_key'] ?? ''),
            timeout: (int) ($config['timeout'] ?? 30),
            retries: (int) ($config['retries'] ?? 3),
        ));
    }
}
