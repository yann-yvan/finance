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

    public static function getSignatureKeyId()
    {
        return config(self::FINANCE_CONFIG . ".signature.key_id", 'k1');
    }

    public static function getSignatureKey()
    {
        return config(self::FINANCE_CONFIG . ".signature.key");
    }

    public static function getSignaturePreviousKeys()
    {
        return config(self::FINANCE_CONFIG . ".signature.previous_keys", []);
    }

    public static function getPendingReservationTtl(): int
    {
        return (int)config(self::FINANCE_CONFIG . ".pending_reservation_ttl", 60);
    }

    public static function getRedactedLogKeys(): array
    {
        return (array)config(self::FINANCE_CONFIG . ".log.redacted_keys", []);
    }

    public static function shouldAnonymizeIp(): bool
    {
        return (bool)config(self::FINANCE_CONFIG . ".log.anonymize_ip", true);
    }

    public static function getSmsVerifyThrottle(): string
    {
        return (string)config(self::FINANCE_CONFIG . ".sms_verify_throttle", '5,1');
    }
}
