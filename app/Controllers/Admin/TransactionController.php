<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Request;
use App\Core\View;

final class TransactionController extends Controller
{
    public function index(Request $request): never
    {
        $page = max(1, $request->int('page', 1));
        $status = $request->str('status');

        $query = DB::table('transactions t')
            ->join('tenants', 't.tenant_id', '=', 'tenants.id')
            ->select('t.id', 't.gateway', 't.gateway_txn_id', 't.type', 't.amount', 't.currency',
                't.status', 't.created_at', 'tenants.name AS tenant_name', 'tenants.id AS tenant_id');
        if (in_array($status, ['pending', 'success', 'failed', 'refunded'], true)) {
            $query->where('t.status', $status);
        }

        Layout::title(__('admin.transactions', 'Transactions'));
        View::render('admin/transactions', [
            'result' => $query->orderBy('t.id', 'DESC')->paginate($page, 40),
            'status' => $status,
            'totals' => [
                'success' => DB::table('transactions')->where('status', 'success')->sum('amount'),
                'month' => DB::table('transactions')->where('status', 'success')
                    ->where('created_at', '>=', date('Y-m-01 00:00:00'))->sum('amount'),
            ],
        ], 'layouts/admin');
    }
}
