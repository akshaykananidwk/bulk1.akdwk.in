<?php

declare(strict_types=1);

namespace App\Services\Meta;

/**
 * Translates Meta error codes into human messages (localised) with a
 * suggested fix. See §5.6 of the platform design.
 */
final class ErrorCodeMapper
{
    /**
     * @return array{title:string, fix:string, retryable:bool}
     */
    public static function map(int $code, string $raw = ''): array
    {
        $entry = match (true) {
            $code === 0 => ['errors.meta.unknown', 'errors.meta.unknown_fix', false],
            $code === 100 => ['errors.meta.invalid_param', 'errors.meta.invalid_param_fix', false],
            $code === 190 => ['errors.meta.token_expired', 'errors.meta.token_expired_fix', false],
            $code === 368 => ['errors.meta.blocked', 'errors.meta.blocked_fix', false],
            $code === 131026 => ['errors.meta.undeliverable', 'errors.meta.undeliverable_fix', false],
            $code === 131047 => ['errors.meta.window_expired', 'errors.meta.window_expired_fix', false],
            $code === 131048 => ['errors.meta.spam_rate', 'errors.meta.spam_rate_fix', true],
            $code === 131049 => ['errors.meta.marketing_limit', 'errors.meta.marketing_limit_fix', true],
            $code === 131051 => ['errors.meta.unsupported_type', 'errors.meta.unsupported_type_fix', false],
            $code === 130429 => ['errors.meta.rate_limit', 'errors.meta.rate_limit_fix', true],
            $code === 131056 => ['errors.meta.pair_rate_limit', 'errors.meta.pair_rate_limit_fix', true],
            $code >= 132000 && $code <= 132015 => ['errors.meta.template_param', 'errors.meta.template_param_fix', false],
            $code >= 133000 && $code <= 133010 => ['errors.meta.registration', 'errors.meta.registration_fix', false],
            $code === 131031 => ['errors.meta.account_locked', 'errors.meta.account_locked_fix', false],
            $code === 80007 => ['errors.meta.throughput', 'errors.meta.throughput_fix', true],
            $code === 4 || $code === 80004 => ['errors.meta.app_rate_limit', 'errors.meta.rate_limit_fix', true],
            default => ['errors.meta.unknown', 'errors.meta.unknown_fix', false],
        };

        $defaults = self::defaults();
        [$titleKey, $fixKey, $retryable] = $entry;

        return [
            'title' => __($titleKey, $defaults[$titleKey] ?? $raw),
            'fix' => __($fixKey, $defaults[$fixKey] ?? ''),
            'retryable' => $retryable,
        ];
    }

    /**
     * English defaults; gu.php/hi.php can override any key.
     */
    private static function defaults(): array
    {
        return [
            'errors.meta.unknown' => 'The message could not be sent.',
            'errors.meta.unknown_fix' => 'Check the error details and try again. If it persists, contact support.',
            'errors.meta.invalid_param' => 'Invalid request parameter.',
            'errors.meta.invalid_param_fix' => 'A field in the message is invalid — check the phone number format and message content.',
            'errors.meta.token_expired' => 'WhatsApp access token expired.',
            'errors.meta.token_expired_fix' => 'Reconnect your WhatsApp account from Settings → WhatsApp.',
            'errors.meta.blocked' => 'This account is temporarily blocked by Meta.',
            'errors.meta.blocked_fix' => 'Review Meta Business Manager for policy issues and appeal if needed.',
            'errors.meta.undeliverable' => 'The message could not be delivered to this number.',
            'errors.meta.undeliverable_fix' => 'The recipient may not be on WhatsApp, may have blocked you, or has not accepted new WhatsApp terms.',
            'errors.meta.window_expired' => '24-hour session window has expired.',
            'errors.meta.window_expired_fix' => 'You can only send an approved template until the customer replies again.',
            'errors.meta.spam_rate' => 'Sending paused — spam rate limit hit.',
            'errors.meta.spam_rate_fix' => 'Slow down sending and improve message quality; try again later.',
            'errors.meta.marketing_limit' => 'Meta limited marketing messages to this user.',
            'errors.meta.marketing_limit_fix' => 'This user has received too many marketing messages recently. Try a utility template or wait.',
            'errors.meta.unsupported_type' => 'Unsupported message type for this recipient.',
            'errors.meta.unsupported_type_fix' => 'Send a different message type (e.g. plain text).',
            'errors.meta.rate_limit' => 'Rate limit reached.',
            'errors.meta.rate_limit_fix' => 'The system will retry automatically with backoff.',
            'errors.meta.pair_rate_limit' => 'Too many messages to this same number.',
            'errors.meta.pair_rate_limit_fix' => 'Wait before sending more messages to this recipient.',
            'errors.meta.template_param' => 'Template parameter mismatch.',
            'errors.meta.template_param_fix' => 'The number of variables sent does not match the approved template. Fix the variable mapping.',
            'errors.meta.registration' => 'Phone number registration problem.',
            'errors.meta.registration_fix' => 'Re-register the number from Settings → WhatsApp → Numbers.',
            'errors.meta.account_locked' => 'The WhatsApp account is locked.',
            'errors.meta.account_locked_fix' => 'Check Meta Business Manager for verification requirements.',
            'errors.meta.throughput' => 'Number throughput limit reached.',
            'errors.meta.throughput_fix' => 'Messages are queued and will send as capacity frees up.',
            'errors.meta.app_rate_limit' => 'App-level rate limit reached.',
        ];
    }
}
