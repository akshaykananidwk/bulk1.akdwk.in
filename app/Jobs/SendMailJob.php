<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Mail;

/**
 * Async email delivery.
 * Payload: to, subject, body (text) OR html, [to_name].
 */
final class SendMailJob implements JobInterface
{
    public function handle(array $payload): void
    {
        $to = (string) ($payload['to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $mail = Mail::make()
            ->to($to, (string) ($payload['to_name'] ?? ''))
            ->subject((string) ($payload['subject'] ?? ''));

        if (!empty($payload['html'])) {
            $mail->html((string) $payload['html']);
        } else {
            $mail->text((string) ($payload['body'] ?? ''));
        }

        if (!$mail->send()) {
            throw new \RuntimeException('Mail send failed to ' . $to
                . (Mail::$lastError !== null ? ' — ' . Mail::$lastError : ''));
        }
    }
}
