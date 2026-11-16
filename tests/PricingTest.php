<?php

use Vivalto\Connect\DTO\PriceList;
use Vivalto\Connect\DTO\PriceListItem;
use Vivalto\Connect\Tests\Support\FakeHttpClient;

function pricingPayload(): array
{
    return [
        'data' => [
            [
                'country' => 'NO',
                'class' => 'standard',
                'parts_min' => 1,
                'parts_max' => 1,
                'per_part' => false,
                'price' => ['amount' => 0.55, 'currency' => 'NOK'],
            ],
            [
                'country' => 'NO',
                'class' => 'standard',
                'parts_min' => 1,
                'parts_max' => null,
                'per_part' => true,
                'price' => ['amount' => 0.42, 'currency' => 'NOK'],
            ],
            [
                'country' => 'DK',
                'class' => 'secret',
                'parts_min' => 1,
                'parts_max' => 1,
                'per_part' => false,
                'price' => ['amount' => 1.10, 'currency' => 'NOK'],
            ],
        ],
    ];
}

it('lists the prices that apply to the project', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, pricingPayload()));

    $prices = sdkClient($http)->pricing()->list();

    expect($http->requests[0]->getMethod())->toBe('GET')
        ->and((string) $http->requests[0]->getUri())->toBe('https://api.example.test/api/v1/pricing');

    expect($prices)->toBeInstanceOf(PriceList::class)
        ->and($prices->items)->toHaveCount(3)
        ->and($prices->items[0])->toBeInstanceOf(PriceListItem::class)
        ->and($prices->items[0]->country)->toBe('NO')
        ->and($prices->items[0]->class)->toBe('standard')
        ->and($prices->items[0]->partsMin)->toBe(1)
        ->and($prices->items[0]->partsMax)->toBe(1)
        ->and($prices->items[0]->perPart)->toBeFalse()
        ->and($prices->items[0]->price->amount)->toBe(0.55)
        ->and($prices->items[0]->price->currency)->toBe('NOK');
});

it('keeps per-part prices open ended', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, pricingPayload()));

    $perPart = sdkClient($http)->pricing()->list()->items[1];

    expect($perPart->perPart)->toBeTrue()
        ->and($perPart->partsMax)->toBeNull()
        ->and($perPart->price->amount)->toBe(0.42);
});

it('filters by country and class', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, pricingPayload()));

    $prices = sdkClient($http)->pricing()->list();

    expect($prices->for('NO', 'standard'))->toHaveCount(2)
        ->and($prices->for('DK', 'secret'))->toHaveCount(1)
        ->and($prices->for('SE', 'standard'))->toBe([]);
});

it('accepts an empty price list', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, ['data' => []]));

    $prices = sdkClient($http)->pricing()->list();

    expect($prices->items)->toBe([])
        ->and($prices->isEmpty())->toBeTrue();
});
