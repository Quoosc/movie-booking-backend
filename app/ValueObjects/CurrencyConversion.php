<?php

namespace App\ValueObjects;

class CurrencyConversion
{
    public function __construct(
        public readonly float $originalAmount,
        public readonly string $sourceCurrency,
        public readonly float $convertedAmount,
        public readonly string $targetCurrency,
        public readonly float $exchangeRate
    ) {}
}
