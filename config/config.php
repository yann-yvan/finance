<?php
return [
    'default_payment_provider_id' => 'LOCAL_PROVIDER',
    'default_payment_provider_name' => env('APP_NAME') . "'s Local Provider",
    'default_threshold' => 0,
    'default_currency' => 'USD',
    'refresh_account_ttl' => 60, #in minute

    'exchange_rate_api_url' => env('EXCHANGE_RATE_API_URL', 'https://api.exchangeratesapi.io/v1/latest'),
    'exchange_rate_api_key' => env('EXCHANGE_RATE_API_KEY'),
    'exchange_rate_refresh_ttl' => env('EXCHANGE_RATE_REFRESH_TTL', 60 * 24 * 5), # in minute here is 5 days


    # Routes: the API is served under {api_prefix}/{prefix} (default api/finance), the web routes under {prefix}.
    # The provider callback URLs follow; tell the providers if you change them. Either may be empty.
    'api_prefix' => env('FINANCE_API_PREFIX', 'api'),
    'prefix' => env('FINANCE_ROUTE_PREFIX', 'finance'),
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

    /*
       |--------------------------------------------------------------------------
       | Integrity signature (v3)
       |--------------------------------------------------------------------------
       |
       | HMAC-SHA256 key used to sign transactions, wallets, ledgers, the hash chain and the audit log.
       | Generate one with: php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
       | To rotate, give the new key a new id and keep the old one in previous_keys ("id:key,id2:key2").
       | Without a key the library keeps the legacy unkeyed hashes (v2).
       |
       */
    'signature' => [
        'key_id' => env('FINANCE_SIGNATURE_KEY_ID', 'k1'),
        'key' => env('FINANCE_SIGNATURE_KEY'),
        'previous_keys' => env('FINANCE_SIGNATURE_PREVIOUS_KEYS', ''),
    ],

    /*
       |--------------------------------------------------------------------------
       | Acknowledged transactions
       |--------------------------------------------------------------------------
       |
       | Legacy transactions whose signature failure was investigated and accepted, as id => reason.
       | finance:verify-integrity reports them as warnings instead of errors; the chain, wallets and balances
       | are still verified. In "rounding" mode (default) an entry is only accepted when the signature verifies
       | with the amount found in start_log (amount signed with 6 decimals then rounded to 5 by the column);
       | "any" accepts every listed id.
       |
       */
    'integrity' => [
        'acknowledged' => [
            // '340fe1de-e9a8-4218-a572-8e3f946c76cd' => 'Fee signed with 6 decimals before 2.0, column rounded to 5',
        ],
        'acknowledged_mode' => env('FINANCE_INTEGRITY_ACKNOWLEDGED_MODE', 'rounding'),
    ],

    # Pending debits younger than this (in minutes) are reserved when checking a new withdrawal
    'pending_reservation_ttl' => env('FINANCE_PENDING_RESERVATION_TTL', 60),

    /*
       |--------------------------------------------------------------------------
       | Transaction logs
       |--------------------------------------------------------------------------
       |
       | Request parameters whose key matches one of these (case-insensitive, nested) are masked in start_log/end_log.
       |
       */
    'log' => [
        'redacted_keys' => [
            'phone', 'phone_number', 'client_phone', 'customer_phone_number', 'msisdn',
            'name', 'account_name', 'client_name', 'customer_name', 'customer_surname', 'target_name',
            'email', 'account_email', 'client_email', 'customer_email',
            'account_number', 'receiver_account', 'target_account', 'address', 'customer_address',
            'otp', 'code', 'pin', 'password', 'token', 'apikey', 'api_key',
        ],
        'anonymize_ip' => true,
    ],

    # Rate limit of the deprecated Dohone SMS verification endpoint (attempts,minutes)
    'sms_verify_throttle' => env('FINANCE_SMS_VERIFY_THROTTLE', '5,1'),

];