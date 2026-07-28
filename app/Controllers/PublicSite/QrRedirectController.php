<?php

declare(strict_types=1);

namespace App\Controllers\PublicSite;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

/**
 * /q/{slug} — QR code redirect with scan analytics.
 */
final class QrRedirectController extends Controller
{
    public function handle(Request $request): never
    {
        $qr = DB::table('qr_codes')
            ->where('slug', (string) $request->route('slug'))
            ->where('is_active', 1)
            ->first();
        if ($qr === null) {
            Response::abort(404);
        }

        DB::table('qr_scans')->insert([
            'qr_code_id' => (int) $qr['id'],
            'ip' => $request->ip(),
            'user_agent' => mb_substr($request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
        DB::table('qr_codes')->where('id', $qr['id'])->increment('scan_count');

        $target = (string) $qr['target'];
        if ($qr['type'] === 'whatsapp') {
            // target = phone number; add prefilled message
            $url = 'https://wa.me/' . preg_replace('/\D/', '', $target);
            if (!empty($qr['prefilled_message'])) {
                $url .= '?text=' . rawurlencode((string) $qr['prefilled_message']);
            }
            Response::redirect($url);
        }

        // url / review / dynamic: direct external redirect (validated at creation)
        if (!preg_match('#^https?://#i', $target)) {
            Response::abort(404);
        }
        Response::redirect($target);
    }
}
