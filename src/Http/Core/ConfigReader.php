<?php

namespace NYCorp\Finance\Http\Core;

class ConfigReader
{
    public const FINANCE_CONFIG = "finance";

    public static function getPaymentProviders()
    {
        return config(self::FINANCE_CONFIG . ".payment_providers");
    }

    public static function getDefaultPaymentProviderId()
    {
        return config(self::FINANCE_CONFIG . ".default_payment_provider_id");
    }

    public static function getDefaultPaymentProviderName()
    {
        return config(self::FINANCE_CONFIG . ".default_payment_provider_name");
    }

    public static function getMinAmountCheckForce()
    {
        return config(self::FINANCE_CONFIG . ".force_balance_check_min_amount");
    }

    public static function getDefaultThreshold()
    {
        return config(self::FINANCE_CONFIG . ".default_threshold");
    }

    public static function getDefaultCurrency(): string
    {
        return config(self::FINANCE_CONFIG . ".default_currency");
    }

    public static function getExchangeRateApiKey()
    {
        return config(self::FINANCE_CONFIG . ".exchange_rate_api_key");
    }

    public static function getExchangeRateApiUrl()
    {
        return config(self::FINANCE_CONFIG . ".exchange_rate_api_url");
    }

    public static function getExchangeRateRefreshTTL()
    {
        return config(self::FINANCE_CONFIG . ".exchange_rate_refresh_ttl");
    }

    public static function getRefreshTtl()
    {
        return config(self::FINANCE_CONFIG . ".refresh_account_ttl");
    }
}