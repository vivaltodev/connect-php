<?php

namespace Vivalto\Sms\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Vivalto\Sms\Client;

/**
 * @method static \Vivalto\Sms\Resources\Messages messages()
 * @method static \Vivalto\Sms\Resources\Webhooks webhooks()
 * @method static bool verifyWebhook(string $payload, string $header, string $secret, ?int $now = null, int $tolerance = 300)
 * @method static \Vivalto\Sms\Config config()
 *
 * @see Client
 */
final class VivaltoSms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
