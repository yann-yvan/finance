<?php
return [
    'default_payment_provider_id' => 'LOCAL_PROVIDER',
    'default_payment_provider_name' => env('APP_NAME')."'s Local Provider",
    'default_threshold' => 0,
    'refresh_account_ttl' => 60, #in minute


    'prefix' => 'finance',
    'middleware' => ['api'],

    'app_account_id' => 1,

    'user_email_field' => "email",


    'finance_account_id_parameter' => "finance_account_id",


    'payment_providers' => [
        \NYCorp\Finance\Http\Payment\DefaultPaymentProvider::class,
        \NYCorp\Finance\Http\Payment\OrangePaymentProvider::class,
        \NYCorp\Finance\Http\Payment\DohonePaymentProvider::class,
    ],

    'force_balance_check_min_amount' => 5000,

    /*
       |--------------------------------------------------------------------------
       | The method to launch in case of success
       |--------------------------------------------------------------------------
       |
       | A class to make action after successful transaction
       |
       | success($financeWallet,$user){}
       |
       | E.g. [ 'class' => \NYCorp\Finance\Http\Payment\Notification::class, 'method' => 'success' ]
       |
       */
    'deposit_success_notification' => ["class" => null, "method" => null],
];