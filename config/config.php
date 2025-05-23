<?php
return [
    'default_payment_provider_id' => 'LOCAL_PROVIDER',
    'default_payment_provider_name' => env('APP_NAME')."'s Local Provider",
    'default_threshold' => 0,
    'default_currency' => 'USD',
    'refresh_account_ttl' => 60, #in minute


    'prefix' => 'finance',
    'middleware' => ['api'],


    'user_email_field' => "email",


    'finance_account_id_parameter' => "finance_account_id",


    /*
       |--------------------------------------------------------------------------
       | Allow providers
       |--------------------------------------------------------------------------
       |
       | A payment gateway that are available and manage by the payment service
       |
       */
    'payment_providers' => [
        \NYCorp\Finance\Http\Payment\DefaultPaymentProvider::class,
        \NYCorp\Finance\Http\Payment\OrangePaymentProvider::class,
        \NYCorp\Finance\Http\Payment\DohonePaymentProvider::class,
    ],

    'force_balance_check_min_amount' => 5000,

];