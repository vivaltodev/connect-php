<?php

namespace Vivalto\Sms;

/**
 * Signs and verifies Vivalto SMS webhook payloads.
 *
 * The header format is "t=<timestamp>,v1=<hex>", where the digest is
 * HMAC-SHA256 over "<timestamp>.<raw body>". The timestamp limits replay.
 */
final class WebhookSignature
{
    public const DEFAULT_TOLERANCE = 300;

    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        $digest = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return 't='.$timestamp.',v1='.$digest;
    }

    public static function verify(
        string $payload,
        string $header,
        string $secret,
        ?int $now = null,
        int $tolerance = self::DEFAULT_TOLERANCE,
    ): bool {
        $segments = self::parseHeader($header);

        if (! isset($segments['t'], $segments['v1'])) {
            return false;
        }

        if (! ctype_digit($segments['t'])) {
            return false;
        }

        $timestamp = (int) $segments['t'];
        $now ??= time();

        if (abs($now - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $segments['v1']);
    }

    /**
     * @return array<string, string>
     */
    private static function parseHeader(string $header): array
    {
        $segments = [];

        foreach (explode(',', $header) as $segment) {
            $pair = explode('=', trim($segment), 2);

            if (count($pair) === 2 && $pair[0] !== '') {
                $segments[$pair[0]] = trim($pair[1]);
            }
        }

        return $segments;
    }
}
