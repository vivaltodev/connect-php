<?php

declare(strict_types=1);

namespace Vivalto\Connect;

/**
 * Signs and verifies Vivalto Connect webhook payloads.
 *
 * The header format is "t=<timestamp>,v1=<hex>", where the digest is
 * HMAC-SHA256 over "<timestamp>.<raw body>". The timestamp limits replay.
 * More than one v1 segment is accepted so a rotated secret can be validated
 * during the overlap window.
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
        $timestampValue = null;
        $signatures = [];

        foreach (explode(',', $header) as $segment) {
            $pair = explode('=', trim($segment), 2);

            if (count($pair) !== 2 || $pair[0] === '') {
                continue;
            }

            $value = trim($pair[1]);

            if ($pair[0] === 't') {
                $timestampValue = $value;
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestampValue === null || $signatures === []) {
            return false;
        }

        if (! ctype_digit($timestampValue)) {
            return false;
        }

        $timestamp = (int) $timestampValue;
        $now ??= time();

        if (abs($now - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
