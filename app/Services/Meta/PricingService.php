<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Core\DB;

/**
 * Message pricing: admin-editable rate card in `pricing_rates`
 * (country_code + category + effective_from). Never hardcoded.
 * Reseller markup applied from tenant settings.
 */
final class PricingService
{
    /**
     * Estimate the platform cost for a message.
     * @return array{rate: float, currency: string}
     */
    public static function estimate(int $tenantId, string $recipientPhone, string $category): array
    {
        if ($category === 'service') {
            // Service conversations are free under current Meta pricing
            return ['rate' => 0.0, 'currency' => (string) setting('default_currency', 'INR')];
        }

        $countryCode = self::countryFromPhone($recipientPhone);

        $row = DB::table('pricing_rates')
            ->where('country_code', $countryCode)
            ->where('category', $category)
            ->where('effective_from', '<=', date('Y-m-d'))
            ->orderBy('effective_from', 'DESC')
            ->first();

        if ($row === null) {
            // Fallback: default row keyed by country_code '*'
            $row = DB::table('pricing_rates')
                ->where('country_code', '*')
                ->where('category', $category)
                ->orderBy('effective_from', 'DESC')
                ->first();
        }

        $rate = $row !== null ? (float) $row['rate'] : 0.0;
        $currency = $row !== null ? (string) $row['currency'] : (string) setting('default_currency', 'INR');

        // Reseller markup: tenant may resell to sub-tenants at cost + margin
        $markup = (float) (DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('key', 'message_markup_percent')
            ->value('value') ?? 0);
        if ($markup > 0) {
            $rate = round($rate * (1 + $markup / 100), 6);
        }

        return ['rate' => $rate, 'currency' => $currency];
    }

    /**
     * Update a message's cost from webhook pricing info (authoritative).
     */
    public static function applyWebhookPricing(string $wamid, array $pricing, ?array $conversationInfo): void
    {
        $update = [];
        if (isset($pricing['category'])) {
            $category = strtolower((string) $pricing['category']);
            $allowed = ['marketing', 'utility', 'authentication', 'service', 'referral_conversion'];
            if (in_array($category, $allowed, true)) {
                $update['pricing_category'] = $category;
            }
        }
        if (isset($pricing['pricing_model'])) {
            $update['pricing_model'] = mb_substr((string) $pricing['pricing_model'], 0, 20);
        }
        if (isset($conversationInfo['id'])) {
            $update['conversation_meta_id'] = mb_substr((string) $conversationInfo['id'], 0, 191);
        }
        if ($update) {
            $update['updated_at'] = now();
            DB::table('messages')->where('wamid', $wamid)->update($update);
        }
    }

    /**
     * Rough country detection from an E.164 number (prefix match).
     */
    public static function countryFromPhone(string $phone): string
    {
        $digits = ltrim(preg_replace('/\D/', '', $phone) ?? '', '0');
        $prefixes = [
            '91' => 'IN', '1' => 'US', '44' => 'GB', '971' => 'AE', '966' => 'SA',
            '65' => 'SG', '60' => 'MY', '62' => 'ID', '880' => 'BD', '92' => 'PK',
            '94' => 'LK', '977' => 'NP', '61' => 'AU', '49' => 'DE', '33' => 'FR',
            '55' => 'BR', '52' => 'MX', '234' => 'NG', '27' => 'ZA', '254' => 'KE',
        ];
        // Longest prefix first
        foreach ([3, 2, 1] as $length) {
            $prefix = substr($digits, 0, $length);
            if (isset($prefixes[$prefix])) {
                return $prefixes[$prefix];
            }
        }
        return '*';
    }
}
