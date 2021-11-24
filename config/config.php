<?php
return [
    'prefix' => 'finance',
    'middleware' => ['api'],
    'app_account_id' => 1,
    'payment_providers' => [
        \NYCorp\Finance\Http\Payment\DefaultPaymentProvider::class,
        \NYCorp\Finance\Http\Payment\OrangePaymentProvider::class,
        \NYCorp\Finance\Http\Payment\DohonePaymentProvider::class
    ],
];