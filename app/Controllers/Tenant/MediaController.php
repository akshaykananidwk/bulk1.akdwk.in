<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Storage;

/**
 * Serves tenant media with authorization. Uploads live outside the web
 * root's directly-guessable space and are tenant-checked here.
 */
final class MediaController extends Controller
{
    public function serve(Request $request): never
    {
        $relative = (string) $request->route('path');
        $user = Auth::user();
        $tenantId = (int) ($user['tenant_id'] ?? 0);

        // Path format: {folder}/{tenant_id}/{yyyy}/{mm}/{file}
        $parts = explode('/', trim($relative, '/'));
        $ownerSegment = $parts[1] ?? '';
        if (!Auth::isSuperAdmin() && $ownerSegment !== (string) $tenantId && $ownerSegment !== 'system') {
            Response::abort(403);
        }

        try {
            $absolute = Storage::path($relative);
        } catch (\Throwable) {
            Response::abort(404);
        }

        Response::file($absolute);
    }
}
