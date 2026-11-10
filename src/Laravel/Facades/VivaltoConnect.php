<?php

declare(strict_types=1);

namespace Vivalto\Connect\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Vivalto\Connect\Client;

/**
 * @method static \Vivalto\Connect\Resources\Messages messages()
 * @method static \Vivalto\Connect\Resources\Documents documents()
 * @method static \Vivalto\Connect\Resources\Webhooks webhooks()
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
}
