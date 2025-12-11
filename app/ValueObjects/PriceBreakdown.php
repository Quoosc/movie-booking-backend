<?php
// app/ValueObjects/PriceBreakdown.php

namespace App\ValueObjects;

class PriceBreakdown
{
    public float $basePrice;
    public array $modifiers = [];
    public float $finalPrice;

    public function __construct(float $basePrice, array $modifiers = [], float $finalPrice = 0.0)
    {
        $this->basePrice = $basePrice;
        $this->modifiers = $modifiers;
        $this->finalPrice = $finalPrice ?: $basePrice;
    }

    public function addModifier(string $name, string $type, float $value): void
    {
        $this->modifiers[] = [
            'name'  => $name,
            'type'  => $type,
            'value' => $value,
        ];
    }

    public function toArray(): array
    {
        return [
            'base_price' => $this->basePrice,
            'modifiers'  => $this->modifiers,
            'final'      => $this->finalPrice,
        ];
    }
}
