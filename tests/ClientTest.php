<?php

use Vivalto\Connect\Client;
use Vivalto\Connect\Tests\Support\FakeHttpClient;

it('identifies itself with the package version in the user agent', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, messagePayload()));
    $client = sdkClient($http);

    $client->messages()->get('msg_abc123');

    expect($http->requests[0]->getHeaderLine('User-Agent'))
        ->toBe('vivalto-connect-php/'.Client::VERSION)
        ->and(Client::VERSION)->toBe('0.3.0');
});
