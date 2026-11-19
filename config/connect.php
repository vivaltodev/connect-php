<?php

return [
    'api_key' => env('VIVALTO_CONNECT_API_KEY'),
    'sender' => env('VIVALTO_CONNECT_SENDER'),
    'base_url' => env('VIVALTO_CONNECT_BASE_URL', 'https://connect.vivalto.no'),
    'timeout' => (int) env('VIVALTO_CONNECT_TIMEOUT', 30),
    'retries' => (int) env('VIVALTO_CONNECT_RETRIES', 3),

    // Optional PSR-18 transport: a class name or an instance. When it is null
    // and Laravel's HTTP client is installed, the provider uses it so
    // Http::fake() intercepts every SDK request.
    'http_client' => null,
];
