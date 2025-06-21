
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CCAvenue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for CCAvenue payment gateway integration
    |
    */

    'access_code' => env('CCAVENUE_ACCESS_CODE', ''),
    'working_key' => env('CCAVENUE_WORKING_KEY', ''),
    'merchant_id' => env('CCAVENUE_MERCHANT_ID', ''),
    'redirect_url' => env('CCAVENUE_REDIRECT_URL', ''),
    'cancel_url' => env('CCAVENUE_CANCEL_URL', ''),
    
    // CCAvenue URLs
    'test_url' => 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction',
    'live_url' => 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction',
    
    // Environment
    'test_mode' => env('CCAVENUE_TEST_MODE', true),
    
    // Get the appropriate URL based on environment
    'payment_url' => env('CCAVENUE_TEST_MODE', true) 
        ? 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction'
        : 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction',
];