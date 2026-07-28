<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Queue;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Scheduler;
use App\Core\View;

final class QueueController extends Controller
{
    public function index(Request $request): never
    {
        $byQueue = [];
        foreach (DB::table('jobs')->selectRaw(DB::raw('`queue`, COUNT(*) AS n, SUM(`reserved_at` IS NOT NULL) AS reserved')) ->groupBy('queue')->get() as $row) {
            $byQueue[] = $row;
        }

        Layout::title(__('admin.queue', 'Queue Monitor'));
        View::render('admin/queue', [
            'byQueue' => $byQueue,
            'pendingTotal' => Queue::size(),
            'failed' => DB::table('failed_jobs')->orderBy('id', 'DESC')->limit(50)->get(),
            'failedTotal' => DB::table('failed_jobs')->count(),
            'cronHeartbeat' => Scheduler::lastHeartbeat(),
            'workerHeartbeat' => is_file(STORAGE_PATH . '/locks/worker_heartbeat')
                ? (int) @filemtime(STORAGE_PATH . '/locks/worker_heartbeat') : null,
            'recentCron' => DB::table('cron_logs')->orderBy('id', 'DESC')->limit(15)->get(),
        ], 'layouts/admin');
    }

    public function retryFailed(Request $request): never
    {
        $count = Queue::retryFailed();
        audit_log('admin.queue_retry_all', 'failed_jobs', null, ['count' => $count]);
        Redirect::to('/admin/queue')->with('success', __('admin.jobs_requeued', ':n failed jobs requeued.', ['n' => (string) $count]))->send();
    }

    public function retryOne(Request $request): never
    {
        Queue::retryFailed((int) $request->route('id'));
        Redirect::to('/admin/queue')->with('success', __('admin.job_requeued', 'Job requeued.'))->send();
    }

    public function clearFailed(Request $request): never
    {
        DB::table('failed_jobs')->where('id', '>', 0)->delete();
        audit_log('admin.failed_jobs_cleared');
        Redirect::to('/admin/queue')->with('success', __('admin.failed_cleared', 'Failed jobs cleared.'))->send();
    }
}
