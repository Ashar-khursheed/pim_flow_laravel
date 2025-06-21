<?php

return [
    'merchant_id' => env('CCAVENUE_MERCHANT_ID', ''),
    'access_code' => env('CCAVENUE_ACCESS_CODE', ''),
    'working_key' => env('CCAVENUE_WORKING_KEY', ''),
    'redirect_url' => env('CCAVENUE_REDIRECT_URL', ''),
    'cancel_url' => env('CCAVENUE_CANCEL_URL', ''),
    'test_mode' => env('CCAVENUE_TEST_MODE', true),
    'payment_url' => env('CCAVENUE_TEST_MODE', true) 
        ? 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction'
        : 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction'
];