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
}