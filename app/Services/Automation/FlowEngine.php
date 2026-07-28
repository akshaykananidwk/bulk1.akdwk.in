<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Core\DB;
use App\Core\Logger;
use App\Core\Str;
use App\Services\Meta\MessageSender;

/**
 * Server-side, RESUMABLE flow executor. State lives in flow_runs
 * (current_node, variables, status, resume_at) so delays / waits survive
 * restarts. The scheduler resumes due runs every minute.
 *
 * Flow definition JSON:
 * { "nodes": { "<key>": { "type": "...", "config": {...}, "next": "<key>",
 *              "branches": {"yes": "<key>", "no": "<key>", ...} } },
 *   "start": "<key>" }
 */
final class FlowEngine
{
    private const MAX_STEPS_PER_TICK = 30;

    /**
     * Start a flow for a contact (from a trigger).
     */
    public static function start(array $flow, array $contact, ?int $conversationId, array $seedVariables = []): int
    {
        // One active run per contact per flow
        $existing = DB::table('flow_runs')
            ->where('flow_id', $flow['id'])
            ->where('contact_id', $contact['id'])
            ->whereIn('status', ['running', 'waiting_reply', 'waiting_delay'])
            ->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $definition = json_decode((string) ($flow['definition'] ?? '{}'), true) ?: [];
        $startNode = (string) ($definition['start'] ?? '');
        if ($startNode === '') {
            throw new \RuntimeException('Flow has no start node.');
        }

        $runId = DB::table('flow_runs')->insert([
            'tenant_id' => (int) $flow['tenant_id'],
            'flow_id' => (int) $flow['id'],
            'contact_id' => (int) $contact['id'],
            'conversation_id' => $conversationId,
            'current_node' => $startNode,
            'variables' => json_encode(array_merge([
                'contact' => [
                    'id' => (int) $contact['id'],
                    'name' => (string) ($contact['name'] ?? ''),
                    'phone' => (string) $contact['phone'],
                    'email' => (string) ($contact['email'] ?? ''),
                ],
            ], $seedVariables), JSON_UNESCAPED_UNICODE),
            'status' => 'running',
            'started_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('flows')->where('id', $flow['id'])->increment('runs_count');
        self::advance($runId);
        return $runId;
    }

    /**
     * Continue a run until it completes or blocks (wait/delay).
     */
    public static function advance(int $runId, ?string $incomingReply = null, array $replyExtra = []): void
    {
        $run = DB::table('flow_runs')->where('id', $runId)->first();
        if ($run === null || in_array($run['status'], ['completed', 'failed', 'cancelled'], true)) {
            return;
        }

        $flow = DB::table('flows')->where('id', $run['flow_id'])->first();
        if ($flow === null || $flow['status'] !== 'active') {
            self::finish($runId, 'cancelled');
            return;
        }

        \App\Core\Tenant::setId((int) $run['tenant_id']);
        $definition = json_decode((string) ($flow['definition'] ?? '{}'), true) ?: [];
        $nodes = (array) ($definition['nodes'] ?? []);
        $variables = json_decode((string) ($run['variables'] ?? '{}'), true) ?: [];

        // Waiting-for-reply node consumes the incoming reply first
        if ($run['status'] === 'waiting_reply') {
            if ($incomingReply === null) {
                // timeout path handled by resumeDueRuns
                return;
            }
            $currentNode = (array) ($nodes[$run['current_node']] ?? []);
            $saveTo = (string) ($currentNode['config']['save_to'] ?? 'reply');
            \App\Core\Arr::set($variables, $saveTo, $incomingReply);
            if (!empty($replyExtra['id'])) {
                \App\Core\Arr::set($variables, $saveTo . '_id', $replyExtra['id']);
            }
            $next = self::pickBranchForReply($currentNode, $incomingReply, $replyExtra);
            self::moveTo($runId, $next, $variables);
            $run = DB::table('flow_runs')->where('id', $runId)->first();
            if ($run === null) {
                return;
            }
        }

        $steps = 0;
        while ($steps < self::MAX_STEPS_PER_TICK) {
            $steps++;
            $run = DB::table('flow_runs')->where('id', $runId)->first();
            if ($run === null || $run['status'] !== 'running') {
                return;
            }
            $nodeKey = (string) $run['current_node'];
            if ($nodeKey === '' || !isset($nodes[$nodeKey])) {
                self::finish($runId, 'completed');
                return;
            }
            $node = (array) $nodes[$nodeKey];
            $variables = json_decode((string) ($run['variables'] ?? '{}'), true) ?: [];

            try {
                $outcome = NodeExecutor::execute($run, $node, $variables);
            } catch (\Throwable $e) {
                self::log($runId, $nodeKey, (string) ($node['type'] ?? '?'), 'error', $e->getMessage());
                self::finish($runId, 'failed');
                return;
            }

            self::log($runId, $nodeKey, (string) ($node['type'] ?? '?'), 'ok', $outcome['detail'] ?? null);

            // Persist variable changes
            if (isset($outcome['variables'])) {
                $variables = $outcome['variables'];
            }

            switch ($outcome['action']) {
                case 'next':
                    $next = $outcome['next'] ?? ($node['next'] ?? null);
                    if ($next === null || $next === '') {
                        self::finish($runId, 'completed', $variables);
                        return;
                    }
                    self::moveTo($runId, (string) $next, $variables);
                    break;

                case 'wait_reply':
                    DB::table('flow_runs')->where('id', $runId)->update([
                        'status' => 'waiting_reply',
                        'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
                        'wait_timeout_at' => isset($outcome['timeout_seconds'])
                            ? date('Y-m-d H:i:s', time() + (int) $outcome['timeout_seconds'])
                            : null,
                        'updated_at' => now(),
                    ]);
                    return;

                case 'delay':
                    DB::table('flow_runs')->where('id', $runId)->update([
                        'status' => 'waiting_delay',
                        'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
                        'resume_at' => date('Y-m-d H:i:s', time() + max(1, (int) ($outcome['seconds'] ?? 60))),
                        'current_node' => (string) ($outcome['next'] ?? $node['next'] ?? ''),
                        'updated_at' => now(),
                    ]);
                    return;

                case 'end':
                default:
                    self::finish($runId, 'completed', $variables);
                    return;
            }
        }
        // Step budget hit — loop guard; resume next scheduler tick
        DB::table('flow_runs')->where('id', $runId)->update([
            'status' => 'waiting_delay',
            'resume_at' => date('Y-m-d H:i:s', time() + 5),
            'updated_at' => now(),
        ]);
    }

    /**
     * Scheduler entrypoint: resume delayed runs + time-out waiting replies.
     */
    public static function resumeDueRuns(): void
    {
        $due = DB::table('flow_runs')
            ->where('status', 'waiting_delay')
            ->where('resume_at', '<=', now())
            ->limit(100)->get();
        foreach ($due as $run) {
            DB::table('flow_runs')->where('id', $run['id'])->update(['status' => 'running', 'resume_at' => null, 'updated_at' => now()]);
            try {
                self::advance((int) $run['id']);
            } catch (\Throwable $e) {
                Logger::channel('app')->error('Flow resume failed', ['run' => $run['id'], 'error' => $e->getMessage()]);
            }
        }

        // Reply timeouts → follow the "timeout" branch (or end)
        $timedOut = DB::table('flow_runs')
            ->where('status', 'waiting_reply')
            ->whereNotNull('wait_timeout_at')
            ->where('wait_timeout_at', '<=', now())
            ->limit(100)->get();
        foreach ($timedOut as $run) {
            $flow = DB::table('flows')->where('id', $run['flow_id'])->first();
            $definition = $flow !== null ? (json_decode((string) $flow['definition'], true) ?: []) : [];
            $node = (array) (($definition['nodes'] ?? [])[$run['current_node']] ?? []);
            $timeoutNext = (string) (($node['branches'] ?? [])['timeout'] ?? '');
            DB::table('flow_runs')->where('id', $run['id'])->update([
                'status' => 'running',
                'current_node' => $timeoutNext,
                'wait_timeout_at' => null,
                'updated_at' => now(),
            ]);
            self::log((int) $run['id'], (string) $run['current_node'], 'wait_for_reply', 'skipped', 'timeout');
            if ($timeoutNext === '') {
                self::finish((int) $run['id'], 'completed');
            } else {
                self::advance((int) $run['id']);
            }
        }
    }

    /**
     * Inbound message hook: feed active waiting runs for this contact.
     * Returns true if a run consumed the message.
     */
    public static function onReply(int $tenantId, int $contactId, string $text, array $extra = []): bool
    {
        $waiting = DB::table('flow_runs')
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contactId)
            ->where('status', 'waiting_reply')
            ->orderBy('updated_at', 'DESC')
            ->first();
        if ($waiting === null) {
            return false;
        }
        self::advance((int) $waiting['id'], $text, $extra);
        return true;
    }

    // -- internals ---------------------------------------------------------------

    private static function pickBranchForReply(array $node, string $reply, array $extra): string
    {
        $branches = (array) ($node['branches'] ?? []);
        // Button/list replies match branch by id first, then by title text
        $replyId = (string) ($extra['id'] ?? '');
        if ($replyId !== '' && isset($branches[$replyId])) {
            return (string) $branches[$replyId];
        }
        $normalized = mb_strtolower(trim($reply));
        foreach ($branches as $key => $target) {
            if ($key === 'timeout' || $key === 'default') {
                continue;
            }
            if (mb_strtolower((string) $key) === $normalized) {
                return (string) $target;
            }
        }
        return (string) ($branches['default'] ?? $node['next'] ?? '');
    }

    private static function moveTo(int $runId, string $nodeKey, array $variables): void
    {
        DB::table('flow_runs')->where('id', $runId)->update([
            'status' => 'running',
            'current_node' => $nodeKey,
            'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    private static function finish(int $runId, string $status, ?array $variables = null): void
    {
        $update = [
            'status' => $status,
            'finished_at' => now(),
            'updated_at' => now(),
        ];
        if ($variables !== null) {
            $update['variables'] = json_encode($variables, JSON_UNESCAPED_UNICODE);
        }
        DB::table('flow_runs')->where('id', $runId)->update($update);
    }

    private static function log(int $runId, string $nodeKey, string $nodeType, string $status, ?string $detail): void
    {
        try {
            DB::table('flow_run_logs')->insert([
                'flow_run_id' => $runId,
                'node_key' => mb_substr($nodeKey, 0, 64),
                'node_type' => mb_substr($nodeType, 0, 32),
                'status' => in_array($status, ['ok', 'error', 'skipped'], true) ? $status : 'ok',
                'detail' => $detail !== null ? mb_substr($detail, 0, 2000) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // logging must not break flow execution
        }
    }
}
