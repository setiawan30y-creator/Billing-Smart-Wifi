<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Aritmetika uang dengan presisi desimal (tidak memakai float).
 */
class Money
{
    public static function of(string|int|float|null $value): BigDecimal
    {
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }

        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }

        return BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }

    public static function add(...$values): string
    {
        $sum = BigDecimal::zero();
        foreach ($values as $v) {
            $sum = $sum->plus(self::of($v));
        }

        return (string) $sum->toScale(2, RoundingMode::HalfUp);
    }

    public static function sub(string|int|null $a, string|int|null $b): string
    {
        return (string) self::of($a)->minus(self::of($b))->toScale(2, RoundingMode::HalfUp);
    }

    public static function mul(string|int|null $a, string|int|null $b): string
    {
        return (string) self::of($a)->multipliedBy(self::of($b))->toScale(2, RoundingMode::HalfUp);
    }

    public static function min(string|int|null $a, string|int|null $b): string
    {
        return self::cmp($a, $b) <= 0 ? (string) self::of($a) : (string) self::of($b);
    }

    public static function cmp(string|int|null $a, string|int|null $b): int
    {
        return self::of($a)->compareTo(self::of($b));
    }

    public static function isPositive(string|int|null $a): bool
    {
        return self::of($a)->isPositive();
    }

    /**
     * Normalisasi input pengguna "150.000" / "150000,50" / "150000.50".
     */
    public static function parse(string|int|null $input): string
    {
        $s = trim((string) $input);
        $s = preg_replace('/[^0-9,.\-]/', '', $s);

        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (substr_count($s, '.') > 1 || preg_match('/\.\d{3}$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        return (string) self::of($s === '' ? '0' : $s);
    }

    public static function format(string|int|float|null $value, bool $withSymbol = true): string
    {
        $d = self::of($value);
        $hasCents = ! str_ends_with((string) $d, '.00');
        $str = number_format((float) (string) $d, $hasCents ? 2 : 0, ',', '.');

        return $withSymbol ? 'Rp'.$str : $str;
    }
}
