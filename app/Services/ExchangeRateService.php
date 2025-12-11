<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\CustomException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class ExchangeRateService
{
    protected string $apiBaseUrl;
    protected int $cacheTtlSeconds;

    public function __construct()
    {
        $this->apiBaseUrl = rtrim(
            config('currency.exchange_api', 'https://latest.currency-api.pages.dev/v1/currencies'),
            '/'
        );

        $this->cacheTtlSeconds = (int) config('currency.exchange_cache_ttl', 900);
    }

    public function convert(float $amount, string $sourceCurrency, string $targetCurrency): CurrencyConversion
    {
        if ($amount === null) {
            throw new CustomException('Amount is required for conversion', Response::HTTP_BAD_REQUEST);
        }

        $source = $this->normalizeCurrency($sourceCurrency);
        $target = $this->normalizeCurrency($targetCurrency);

        if ($source === $target) {
            $normalizedAmount = round($amount, 2);
            return new CurrencyConversion(
                $amount,
                $source,
                $normalizedAmount,
                $target,
                1.0
            );
        }

        $rate = $this->getRate($source, $target);
        $converted = round($amount * $rate, 2);

        return new CurrencyConversion(
            $amount,
            $source,
            $converted,
            $target,
            $rate
        );
    }

    protected function getRate(string $source, string $target): float
    {
        $cacheKey = "rate:{$source}_{$target}";

        // Check cache
        if ($this->cacheTtlSeconds > 0 && Cache::has($cacheKey)) {
            return (float) Cache::get($cacheKey);
        }

        try {
            $url = $this->buildUrl($source);
            $json = Http::get($url)->json();

            if (!$json) {
                throw new CustomException(
                    'Exchange API returned empty response',
                    Response::HTTP_BAD_GATEWAY
                );
            }

            $lowerSource = Str::lower($source);
            $lowerTarget = Str::lower($target);

            if (!isset($json[$lowerSource][$lowerTarget])) {
                throw new CustomException(
                    "Exchange rate {$source} -> {$target} not available",
                    Response::HTTP_BAD_REQUEST
                );
            }

            $rate = (float) $json[$lowerSource][$lowerTarget];

            if ($this->cacheTtlSeconds > 0) {
                Cache::put($cacheKey, $rate, $this->cacheTtlSeconds);
            }

            return $rate;
        } catch (\Throwable $e) {
            if ($this->cacheTtlSeconds > 0 && Cache::has($cacheKey)) {
                // Fallback to stale cache
                return (float) Cache::get($cacheKey);
            }

            throw new CustomException(
                'Unable to fetch exchange rates at the moment. Please try again later.',
                Response::HTTP_SERVICE_UNAVAILABLE,
                $e
            );
        }
    }

    protected function buildUrl(string $source): string
    {
        return $this->apiBaseUrl . '/' . Str::lower($source) . '.json';
    }

    protected function normalizeCurrency(?string $currency): string
    {
        if ($currency === null || trim($currency) === '') {
            throw new CustomException('Currency code is required', Response::HTTP_BAD_REQUEST);
        }

        return Str::upper(trim($currency));
    }
}

class CurrencyConversion
{
    public function __construct(
        public float $sourceAmount,
        public string $sourceCurrency,
        public float $targetAmount,
        public string $targetCurrency,
        public float $rate,
    ) {}
}
