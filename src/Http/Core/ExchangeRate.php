<?php

namespace NYCorp\Finance\Http\Core;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRate
{
    protected array $rates = [];
    protected string $baseCurrency;

    public static function make(string $baseCurrency = null): static
    {
        $instance = new static();

        $currency = $baseCurrency ?? ConfigReader::getDefaultCurrency();
        $key = "{$currency}_base_rates";
        $keyBackup = "{$currency}_base_rates_backup";

        $currencies = Cache::get($key, static function () use ($currency, $key, $keyBackup) {
            Log::debug("$currency base currency exchange rate update in progress");
            try {
                $apiKey = ConfigReader::getExchangeRateApiKey();
                $response = Http::get(ConfigReader::getExchangeRateApiUrl(), ["access_key" => $apiKey, "base" => $currency]);
                if ($response->successful()) {
                    $currencies = $response->json();
                    Log::info("$currency base currency exchange rate updated", $currencies);
                    Cache::put($key, $currencies, now()->addMinutes(ConfigReader::getExchangeRateRefreshTTL()));
                    Cache::put($keyBackup, $currencies);
                    return $currencies;
                }
            } catch (\Exception|\Throwable $exception) {
                Log::error("$currency base currency exchange rate updated with " . $exception->getMessage(), $exception->getTrace());
            }

            $currencies = Cache::get($keyBackup, []);
            Log::warning("$currency base currency exchange rate backup used", $currencies);
            return $currencies;
        });

        $instance->rates = Arr::get($currencies ?? [], 'rates',[]);
        $instance->baseCurrency = $currency;
        return $instance;
    }

    /**
     * @return array
     */
    public function getRates(): array
    {
        return $this->rates;
    }

    /**
     * From base currency to target currency
     * @param string $currency
     * @param float $amount
     * @return float
     */
    public function exchangeTo(string $currency, float $amount): float
    {
        return self::round($amount * $this->getRate($currency));
    }

    public static function round($amount): float
    {
        return round($amount, 5);
    }

    public function getRate($currency): float
    {
        return $this->baseCurrency === $currency ? 1 : Arr::get($this->rates, $currency);
    }


    /**
     * From target currency to base currency
     * @param string $currency
     * @param float $amount
     * @return float
     */
    public function exchangeFrom(string $currency, float $amount): float
    {
        return self::round($amount / $this->getRate($currency));
    }
}