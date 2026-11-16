<?php

declare(strict_types=1);

namespace Vivalto\Connect\Resources;

use Vivalto\Connect\Client;
use Vivalto\Connect\DTO\PriceList;

/**
 * Read the prices that currently apply to the project.
 *
 * The list contains customer, project and default prices merged in precedence
 * order. The API never returns provider cost or margin.
 */
final class Pricing
{
    public function __construct(private readonly Client $client) {}

    /**
     * List every price for the project, sorted by country and class.
     *
     * A per-part price applies to any part count and has partsMax null.
     */
    public function list(): PriceList
    {
        return PriceList::fromArray($this->client->request('GET', '/api/v1/pricing'));
    }
}
