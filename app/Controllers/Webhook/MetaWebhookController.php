<?php

declare(strict_types=1);

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Core\Crypt;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Queue;
use App\Core\Request;
use App\Core\Response;

/**
 * Meta webhook endpoint.
 *  GET  — subscription verification (hub.challenge echo)
 *  POST — signature check, store raw payload, queue processing, 200 fast.
 */
final class MetaWebhookController extends Controller
{
    public function verify(Request $request): never
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        // PHP replaces dots with underscores in query keys; handle both.
        if ($mode === '' && isset($_GET['hub_mode'])) {
            $mode = (string) $_GET['hub_mode'];
        }

        $expected = self::verifyTokens();

        if ($mode === 'subscribe' && $token !== '' && in_array($token, $expected, true)) {
            Response::text($challenge);
        }

        Logger::channel('webhook')->warning('Meta webhook verification failed', ['mode' => $mode]);
        Response::text('Verification failed', 403);
    }

    public function receive(Request $request): never
    {
        $raw = $request->rawBody();
        $signature = (string) ($request->header('X-Hub-Signature-256') ?? '');

        if (!self::signatureValid($raw, $signature)) {
            Logger::channel('webhook')->warning('Meta webhook signature mismatch');
            Response::text('Invalid signature', 403);
        }

        // Store + queue; NEVER process inline (200 within 200ms requirement)
        $logId = DB::table('webhook_logs')->insert([
            'direction' => 'in',
            'source' => 'meta',
            'payload' => $raw,
            'status' => 'received',
            'created_at' => now(),
        ]);

        Queue::push(\App\Jobs\ProcessWebhookJob::class, ['webhook_log_id' => $logId], 'webhook', 1, 0, null);

        Response::json(['success' => true]);
    }

    /**
     * All valid verify tokens (per configured Meta app).
     */
    private static function verifyTokens(): array
    {
        $tokens = [];
        $configured = (string) config('meta.webhook_verify_token', '');
        if ($configured !== '') {
            $tokens[] = $configured;
        }
        try {
            foreach (DB::table('meta_apps')->get() as $app) {
                if (!empty($app['webhook_verify_token'])) {
                    $tokens[] = (string) $app['webhook_verify_token'];
                }
            }
        } catch (\Throwable) {
            // not installed yet
        }
        return $tokens;
    }

    /**
     * X-Hub-Signature-256 = 'sha256=' . HMAC-SHA256(raw body, app_secret).
     * Checked against every configured Meta app secret.
     */
    private static function signatureValid(string $raw, string $signature): bool
    {
        if (!str_starts_with($signature, 'sha256=')) {
            return false;
        }
        $provided = substr($signature, 7);

        try {
            $apps = DB::table('meta_apps')->get();
        } catch (\Throwable) {
            return false;
        }

        foreach ($apps as $app) {
            $secret = Crypt::decrypt((string) $app['app_secret_encrypted']);
            if ($secret === null || $secret === '') {
                continue;
            }
            $expected = hash_hmac('sha256', $raw, $secret);
            if (hash_equals($expected, $provided)) {
                return true;
            }
        }
        return false;
    }
}
