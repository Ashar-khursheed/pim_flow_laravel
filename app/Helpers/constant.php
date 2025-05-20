<?php

if (!function_exists('app_constants')) {
    function app_constants($key = null) {
        $constants = [
            'DELIVERY_DAYS' => [
                '2 to 3 Days',
                '5 to 7 Days',
                '10 to 12 Days',
                '3 to 4 Weeks',
                '6 Weeks',
                '8 to 10 Weeks',
                '12 Weeks',
            ],
            'WARRANTY_OPTIONS' => [
                '1 Month',
                '2 Months',
                '3 Months',
                '6 Months',
                '1 Year',
                '2 Year',
                '3 Year',
                '5 Year',
                '10 Year',
                'Lifetime Warranty',
            ],
            'REFUND_PERIODS' => [
                '7 Days',
                '15 Days',
                '30 Days',
                'No Refund',
            ],
            'IN_STOCK_OPTIONS' => [
                1 => 'Yes',
                0 => 'No',
            ],
        ];

        return $key ? ($constants[$key] ?? []) : $constants;
    }
}
