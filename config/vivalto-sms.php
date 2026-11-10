<?php

return [
    'api_key' => env('VIVALTO_SMS_API_KEY'),
    'base_url' => env('VIVALTO_SMS_BASE_URL', 'https://sms.vivalto.no'),
    'timeout' => (int) env('VIVALTO_SMS_TIMEOUT', 30),
    'retries' => (int) env('VIVALTO_SMS_RETRIES', 3),
];
