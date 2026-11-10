<?php

return [
    'api_key' => env('VIVALTO_CONNECT_API_KEY'),
    'base_url' => env('VIVALTO_CONNECT_BASE_URL', 'https://connect.vivalto.no'),
    'timeout' => (int) env('VIVALTO_CONNECT_TIMEOUT', 30),
    'retries' => (int) env('VIVALTO_CONNECT_RETRIES', 3),
];
