<?php
namespace App\Helpers;

class PriceHelper
{
    public static function convert(?float $price): ?float
    {
        if ($price === null) return null;

        $margin = app('currency.context')['margin'] ?? 0;

        if ($margin == 0) return round($price, 2);

        return round($price * (1 + $margin / 100), 2);
    }

    public static function symbol(): string
    {
        return app('currency.context')['symbol'] ?? 'AED';
    }

    public static function context(): array
    {
        return app('currency.context') ?? [];
    }
}