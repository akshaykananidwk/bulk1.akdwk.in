<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Tenant;

final class TemplateApiController extends Controller
{
    public function index(Request $request): never
    {
        $status = strtoupper($request->str('status', 'APPROVED'));
        $query = DB::table('templates')
            ->where('tenant_id', Tenant::id())
            ->select('id', 'name', 'language', 'category', 'status', 'components', 'created_at');
        if (in_array($status, ['APPROVED', 'PENDING', 'REJECTED', 'DRAFT', 'PAUSED', 'DISABLED'], true) && $status !== 'ALL') {
            $query->where('status', $status);
        }
        $rows = $query->orderBy('name')->limit(200)->get();
        foreach ($rows as &$row) {
            $row['components'] = json_decode((string) $row['components'], true);
        }
        unset($row);
        $this->json(['success' => true, 'data' => $rows]);
    }
}
