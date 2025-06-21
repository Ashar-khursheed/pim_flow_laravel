<?php
return [
    'merchant_id' => env('CCAVENUE_MERCHANT_ID', ''),
    'working_key' => env('CCAVENUE_WORKING_KEY', ''),
    'access_code' => env('CCAVENUE_ACCESS_CODE', ''),
    'production_url' => env('CCAVENUE_PRODUCTION_URL', 'https://secure.ccavenue.ae/transaction/transaction.do'),
    'test_url' => env('CCAVENUE_TEST_URL', 'https://test.ccavenue.com/transaction/transaction.do'),
    'environment' => env('CCAVENUE_ENVIRONMENT', 'test'), // test or production
];