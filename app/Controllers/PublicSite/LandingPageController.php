<?php

declare(strict_types=1);

namespace App\Controllers\PublicSite;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

/**
 * /p/{tenant}/{slug} — published landing pages (section-based renderer).
 */
final class LandingPageController extends Controller
{
    public function show(Request $request): never
    {
        $tenant = DB::table('tenants')->where('slug', (string) $request->route('tenant'))->where('status', 'active')->first();
        if ($tenant === null) {
            Response::abort(404);
        }
        $page = DB::table('landing_pages')
            ->where('tenant_id', $tenant['id'])
            ->where('slug', (string) $request->route('slug'))
            ->where('status', 'published')
            ->first();
        if ($page === null) {
            Response::abort(404);
        }

        DB::table('landing_pages')->where('id', $page['id'])->increment('views');

        \App\Core\View::render('public/landing', [
            'page' => $page,
            'sections' => json_decode((string) ($page['sections'] ?? '[]'), true) ?: [],
        ]);
    }
}
