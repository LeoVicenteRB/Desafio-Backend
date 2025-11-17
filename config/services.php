<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'subadq_a' => [
        'base_url' => env('SUBADQA_BASE_URL', 'https://subadqa.mock'),
        'api_key' => env('SUBADQA_API_KEY'),
        'api_secret' => env('SUBADQA_API_SECRET'),
        'timeout' => env('SUBADQA_TIMEOUT', 30),
        'merchant_id' => env('SUBADQA_MERCHANT_ID', 'default_merchant'),
    ],

    'subadq_b' => [
        'base_url' => env('SUBADQB_BASE_URL', 'https://subadqb.mock'),
        'api_key' => env('SUBADQB_API_KEY'),
        'api_secret' => env('SUBADQB_API_SECRET'),
        'timeout' => env('SUBADQB_TIMEOUT', 30),
    ],

];

