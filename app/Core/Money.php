<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Currency formatting (Indian numbering for INR) and safe arithmetic.
 */
final class Money
{
    private const SYMBOLS = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'AED' => 'د.إ',
        'SGD' => 'S$',
        'AUD' => 'A$',
        'CAD' => 'C$',
    ];

    public static function symbol(string $currency): string
    {
        return self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency) . ' ';
    }

    public static function format(float $amount, string $currency = 'INR', int $decimals = 2): string
    {
        $currency = strtoupper($currency);
        if ($currency === 'INR') {
            return self::symbol($currency) . self::indianNumber($amount, $decimals);
        }
        return self::symbol($currency) . number_format($amount, $decimals);
    }

    /**
     * Indian grouping: 12,34,567.89
     */
    public static function indianNumber(float $amount, int $decimals = 2): string
    {
        $negative = $amount < 0;
        $amount = abs($amount);
        $formatted = number_format($amount, $decimals, '.', '');
        [$whole, $fraction] = array_pad(explode('.', $formatted, 2), 2, '');

        if (strlen($whole) > 3) {
            $last3 = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) ?? $rest;
            $whole = $rest . ',' . $last3;
        }

        $result = $whole . ($decimals > 0 ? '.' . $fraction : '');
        return ($negative ? '-' : '') . $result;
    }

    /**
     * GST split for Indian invoices: same-state = CGST+SGST, other = IGST.
     */
    public static function gstSplit(float $taxableAmount, float $gstPercent, bool $sameState): array
    {
        $totalTax = round($taxableAmount * $gstPercent / 100, 2);
        if ($sameState) {
            $half = round($totalTax / 2, 2);
            return [
                'cgst' => $half,
                'sgst' => round($totalTax - $half, 2),
                'igst' => 0.0,
                'total_tax' => $totalTax,
            ];
        }
        return ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => $totalTax, 'total_tax' => $totalTax];
    }

    /**
     * Percentage helper avoiding float drift for money.
     */
    public static function percent(float $amount, float $percent): float
    {
        return round($amount * $percent / 100, 2);
    }
}
