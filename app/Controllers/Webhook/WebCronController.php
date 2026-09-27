<?php

declare(strict_types=1);

namespace App\Controllers\Webhook;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * "Web cron" — for servers where a system cron cannot be added.
 * A free external pinger (cron-job.org, UptimeRobot…) opens
 *   https://<site>/cron/run/<secret-key>
 * every minute, and this runs exactly what cron/scheduler.php runs.
 */
final class WebCronController extends Controller
{
    public static function key(): string
    {
        return substr(hash_hmac('sha256', 'kwc-web-cron', (string) Config::get('app.key', '')), 0, 32);
    }

    public static function url(): string
    {
        return url('/cron/run/' . self::key());
    }

    public function run(Request $request): never
    {
        $given = (string) $request->route('key');
        if ((string) Config::get('app.key', '') === '' || !hash_equals(self::key(), $given)) {
            Response::text('Forbidden', 403);
        }

        ignore_user_abort(true);
        @set_time_limit(120);

        // Answer the pinger immediately, then keep working in the background
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'OK ' . date('c');
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            @ob_end_flush();
            @flush();
        }

        if (!defined('KWC_WEB_CRON')) {
            define('KWC_WEB_CRON', true);
        }
        require ROOT_PATH . '/cron/scheduler.php';
        exit(0);
    }
}
