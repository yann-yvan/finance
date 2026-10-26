<?php

namespace NYCorp\Finance\Http\Core;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Exceptions\UnknownExchangeRateException;

class ExchangeRate
{
    protected array $rates = [];
    protected string $baseCurrency;
    protected bool $loaded = false;

    public static function make(string $baseCurrency = null): static
    {
        $instance = new static();
        $instance->baseCurrency = $baseCurrency ?? ConfigReader::getDefaultCurrency();
        return $instance;
    }

    /**
     * Rates are fetched on first use only: a same-currency conversion needs no API call.
     */
    protected function loadRates(): array
    {
        if ($this->loaded) {
            return $this->rates;
        }

        $currency = $this->baseCurrency;
        $key = "{$currency}_base_rates";
        $keyBackup = "{$currency}_base_rates_backup";

        $currencies = Cache::get($key, static function () use ($currency, $key, $keyBackup) {
            Log::debug("$currency base currency exchange rate update in progress");
            try {
                $apiKey = ConfigReader::getExchangeRateApiKey();
                $response = Http::get(ConfigReader::getExchangeRateApiUrl(), ["access_key" => $apiKey, "base" => $currency]);
                if ($response->successful()) {
                    $currencies = $response->json();
                    Log::info("$currency base currency exchange rate updated");
                    Cache::put($key, $currencies, now()->addMinutes(ConfigReader::getExchangeRateRefreshTTL()));
                    Cache::put($keyBackup, $currencies);
                    return $currencies;
                }
            } catch (\Exception|\Throwable $exception) {
                Log::error("$currency base currency exchange rate updated with " . $exception->getMessage());
            }

            $currencies = Cache::get($keyBackup, []);
            Log::warning("$currency base currency exchange rate backup used");
            return $currencies;
        });

        $this->rates = (array)Arr::get(is_array($currencies) ? $currencies : [], 'rates', []);
        $this->loaded = true;
        return $this->rates;
    }

    /**
     * @return array
     */
    public function getRates(): array
    {
        return $this->loadRates();
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

    /**
     * @throws UnknownExchangeRateException when the rate is unknown (instead of a TypeError on null)
     */
    public function getRate($currency): float
    {
        if ($currency === null || $this->baseCurrency === $currency) {
            return 1;
        }

        $rate = Arr::get($this->loadRates(), $currency);
        if (!is_numeric($rate) || (float)$rate <= 0) {
            throw new UnknownExchangeRateException($this->baseCurrency, (string)$currency);
        }

        return (float)$rate;
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