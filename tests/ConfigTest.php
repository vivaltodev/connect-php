<?php

use Vivalto\Connect\Config;

it('accepts up to ten retries', function (): void {
    expect(new Config(baseUrl: 'https://api.example.test', apiKey: 'key', retries: 10))->toBeInstanceOf(Config::class);
});

it('rejects more than ten retries', function (): void {
    expect(fn () => new Config(baseUrl: 'https://api.example.test', apiKey: 'key', retries: 11))
        ->toThrow(InvalidArgumentException::class, 'cannot exceed 10');
});
