<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

use Vivalto\Connect\Exceptions\ApiException;

/**
 * Guards the fields that every valid API payload must contain.
 *
 * @internal
 */
final class Payload
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function string(array $data, string $key): string
    {
        if (! isset($data[$key]) || ! is_string($data[$key]) || $data[$key] === '') {
            throw self::missing($key);
        }

        return $data[$key];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    public static function list(array $data, string $key): array
    {
        if (! array_key_exists($key, $data) || ! is_array($data[$key])) {
            throw self::missing($key);
        }

        return $data[$key];
    }

    private static function missing(string $key): ApiException
    {
        return new ApiException(
            "The Vivalto Connect API response is missing the required field '{$key}'.",
            errorCode: 'invalid_response',
        );
    }
}
