<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Core\Arr;
use App\Core\DB;
use App\Core\Http;
use App\Core\Str;
use App\Services\Meta\MessageSender;
use App\Services\Meta\TemplateService;

/**
 * Executes a single flow node. Returns an outcome array:
 *   ['action' => 'next'|'wait_reply'|'delay'|'end', 'next' => ?, 'seconds' => ?,
 *    'timeout_seconds' => ?, 'variables' => ?, 'detail' => ?]
 */
final class NodeExecutor
{
    public static function execute(array $run, array $node, array $variables): array
    {
        $type = (string) ($node['type'] ?? '');
        $config = (array) ($node['config'] ?? []);
        $contactId = (int) $run['contact_id'];
        $contact = DB::table('contacts')->where('id', $contactId)->first() ?? [];
        $interpolate = fn (string $text): string => Str::interpolate($text, $variables);

        switch ($type) {
            case 'start':
                return ['action' => 'next'];

            case 'send_message':
                $body = $interpolate((string) ($config['text'] ?? ''));
                if ($body !== '') {
                    MessageSender::sendToContact($contact, 'text', ['body' => $body], ['flow_run_id' => (int) $run['id']]);
                }
                return ['action' => 'next', 'detail' => Str::limit($body, 80)];

            case 'send_template':
                $template = DB::table('templates')->where('id', (int) ($config['template_id'] ?? 0))
                    ->where('tenant_id', $run['tenant_id'])->where('status', 'APPROVED')->first();
                if ($template === null) {
                    throw new \RuntimeException('Template unavailable for flow node.');
                }
                $components = TemplateService::buildSendComponents(
                    $template,
                    (array) ($config['variables'] ?? []),
                    $contact,
                    \App\Models\Contact::fieldValues($contactId)
                );
                MessageSender::sendToContact($contact, 'template', [
                    'name' => $template['name'],
                    'language' => $template['language'],
                    'components' => $components,
                ], ['flow_run_id' => (int) $run['id'], 'template_category' => strtolower((string) $template['category'])]);
                return ['action' => 'next', 'detail' => (string) $template['name']];

            case 'buttons':
                $buttons = [];
                foreach ((array) ($config['buttons'] ?? []) as $i => $button) {
                    $buttons[] = ['id' => (string) ($button['id'] ?? ('b' . $i)), 'title' => $interpolate((string) ($button['title'] ?? ''))];
                }
                MessageSender::sendToContact($contact, 'interactive_button', [
                    'body' => $interpolate((string) ($config['text'] ?? '')),
                    'buttons' => $buttons,
                ], ['flow_run_id' => (int) $run['id']]);
                return ['action' => 'wait_reply', 'timeout_seconds' => (int) ($config['timeout_seconds'] ?? 86400)];

            case 'list':
                MessageSender::sendToContact($contact, 'interactive_list', [
                    'body' => $interpolate((string) ($config['text'] ?? '')),
                    'button' => (string) ($config['button'] ?? 'Choose'),
                    'sections' => (array) ($config['sections'] ?? []),
                ], ['flow_run_id' => (int) $run['id']]);
                return ['action' => 'wait_reply', 'timeout_seconds' => (int) ($config['timeout_seconds'] ?? 86400)];

            case 'ask_question':
                $question = $interpolate((string) ($config['text'] ?? ''));
                if ($question !== '') {
                    MessageSender::sendToContact($contact, 'text', ['body' => $question], ['flow_run_id' => (int) $run['id']]);
                }
                return ['action' => 'wait_reply', 'timeout_seconds' => (int) ($config['timeout_seconds'] ?? 86400)];

            case 'wait_for_reply':
                return ['action' => 'wait_reply', 'timeout_seconds' => (int) ($config['timeout_seconds'] ?? 86400)];

            case 'condition':
                $matched = ConditionEvaluator::evaluate((array) ($config['conditions'] ?? []), (string) ($config['match'] ?? 'all'), $variables);
                $branches = (array) ($node['branches'] ?? []);
                return [
                    'action' => 'next',
                    'next' => (string) ($matched ? ($branches['yes'] ?? '') : ($branches['no'] ?? '')),
                    'detail' => $matched ? 'yes' : 'no',
                ];

            case 'switch':
                $value = (string) Arr::get($variables, (string) ($config['variable'] ?? ''), '');
                $branches = (array) ($node['branches'] ?? []);
                $next = (string) ($branches[$value] ?? $branches['default'] ?? '');
                return ['action' => 'next', 'next' => $next, 'detail' => $value];

            case 'delay':
                $seconds = (int) ($config['seconds'] ?? 0);
                if ($seconds <= 0) {
                    $seconds = ((int) ($config['minutes'] ?? 0)) * 60 + ((int) ($config['hours'] ?? 0)) * 3600 + ((int) ($config['days'] ?? 0)) * 86400;
                }
                return ['action' => 'delay', 'seconds' => max(5, $seconds)];

            case 'wait_until':
                $timestamp = strtotime($interpolate((string) ($config['datetime'] ?? '')));
                $seconds = $timestamp !== false ? max(5, $timestamp - time()) : 60;
                return ['action' => 'delay', 'seconds' => $seconds];

            case 'set_variable':
                Arr::set($variables, (string) ($config['name'] ?? 'var'), $interpolate((string) ($config['value'] ?? '')));
                return ['action' => 'next', 'variables' => $variables];

            case 'math':
                $left = (float) Arr::get($variables, (string) ($config['left'] ?? ''), $config['left'] ?? 0);
                $right = (float) Arr::get($variables, (string) ($config['right'] ?? ''), $config['right'] ?? 0);
                $result = match ((string) ($config['op'] ?? '+')) {
                    '-' => $left - $right,
                    '*' => $left * $right,
                    '/' => $right != 0.0 ? $left / $right : 0,
                    default => $left + $right,
                };
                Arr::set($variables, (string) ($config['save_to'] ?? 'result'), $result);
                return ['action' => 'next', 'variables' => $variables];

            case 'http_request':
                $url = $interpolate((string) ($config['url'] ?? ''));
                if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'http')) {
                    throw new \RuntimeException('http_request node has an invalid URL.');
                }
                // SSRF guard: block internal targets
                $host = (string) parse_url($url, PHP_URL_HOST);
                $ip = gethostbyname($host);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    throw new \RuntimeException('http_request target is not allowed.');
                }
                $http = Http::make()->timeout(15)->retry(1, [2]);
                foreach ((array) ($config['headers'] ?? []) as $name => $value) {
                    if (is_string($name) && is_scalar($value)) {
                        $http->withHeaders([$name => $interpolate((string) $value)]);
                    }
                }
                $method = strtoupper((string) ($config['method'] ?? 'GET'));
                $bodyRaw = $interpolate((string) ($config['body'] ?? ''));
                $response = match ($method) {
                    'POST' => $http->post($url, $bodyRaw !== '' ? $bodyRaw : null),
                    'PUT' => $http->put($url, $bodyRaw !== '' ? $bodyRaw : null),
                    'DELETE' => $http->delete($url),
                    default => $http->get($url),
                };
                Arr::set($variables, (string) ($config['save_to'] ?? 'http'), [
                    'status' => $response->status,
                    'body' => Str::limit($response->body, 4000),
                    'json' => $response->json(),
                ]);
                return ['action' => 'next', 'variables' => $variables, 'detail' => $method . ' ' . $url . ' → ' . $response->status];

            case 'tag_contact':
            case 'untag':
                $tagId = (int) ($config['tag_id'] ?? 0);
                if ($tagId > 0 && DB::table('tags')->where('id', $tagId)->where('tenant_id', $run['tenant_id'])->exists()) {
                    if ($type === 'tag_contact') {
                        \App\Models\Contact::attachTag($contactId, $tagId);
                    } else {
                        \App\Models\Contact::detachTag($contactId, $tagId);
                    }
                }
                return ['action' => 'next'];

            case 'update_field':
                $fieldId = (int) ($config['field_id'] ?? 0);
                if ($fieldId > 0) {
                    \App\Models\Contact::setFieldValue($contactId, $fieldId, $interpolate((string) ($config['value'] ?? '')));
                }
                return ['action' => 'next'];

            case 'lead_score':
                DB::table('contacts')->where('id', $contactId)->increment('lead_score', (int) ($config['delta'] ?? 1));
                return ['action' => 'next'];

            case 'add_to_group':
            case 'remove_from_group':
                $groupId = (int) ($config['group_id'] ?? 0);
                if ($groupId > 0 && DB::table('groups')->where('id', $groupId)->where('tenant_id', $run['tenant_id'])->exists()) {
                    if ($type === 'add_to_group') {
                        $exists = DB::table('group_contacts')->where('group_id', $groupId)->where('contact_id', $contactId)->exists();
                        if (!$exists) {
                            DB::table('group_contacts')->insert(['group_id' => $groupId, 'contact_id' => $contactId]);
                            DB::table('groups')->where('id', $groupId)->increment('contact_count');
                        }
                    } else {
                        $deleted = DB::table('group_contacts')->where('group_id', $groupId)->where('contact_id', $contactId)->delete();
                        if ($deleted > 0) {
                            DB::table('groups')->where('id', $groupId)->increment('contact_count', -1);
                        }
                    }
                }
                return ['action' => 'next'];

            case 'assign_agent':
            case 'handover':
                $conversationId = (int) ($run['conversation_id'] ?? 0);
                if ($conversationId > 0) {
                    $assigneeId = (int) ($config['user_id'] ?? 0) ?: null;
                    DB::table('conversations')->where('id', $conversationId)->update([
                        'assigned_to' => $assigneeId,
                        'status' => 'open',
                        'ai_enabled' => 0,
                        'updated_at' => now(),
                    ]);
                    \App\Core\Event::publish('inbox', 'conversation.assigned', [
                        'conversation_id' => $conversationId,
                        'assigned_to' => $assigneeId,
                    ], (int) $run['tenant_id']);
                    if ($type === 'handover') {
                        DB::table('ai_handovers')->insert([
                            'tenant_id' => (int) $run['tenant_id'],
                            'conversation_id' => $conversationId,
                            'reason' => (string) ($config['reason'] ?? 'flow_handover'),
                            'assigned_to' => $assigneeId,
                            'created_at' => now(),
                        ]);
                    }
                }
                return ['action' => 'next'];

            case 'notify_team':
                DB::table('notifications')->insert([
                    'tenant_id' => (int) $run['tenant_id'],
                    'user_id' => (int) ($config['user_id'] ?? 0) ?: null,
                    'type' => 'flow.notify',
                    'title' => $interpolate((string) ($config['title'] ?? 'Flow notification')),
                    'body' => $interpolate((string) ($config['body'] ?? '')),
                    'link' => $run['conversation_id'] ? '/tenant/inbox?open=' . $run['conversation_id'] : null,
                    'created_at' => now(),
                ]);
                \App\Core\Event::publish('notifications', 'notification', [
                    'title' => $interpolate((string) ($config['title'] ?? 'Flow notification')),
                ], (int) $run['tenant_id']);
                return ['action' => 'next'];

            case 'send_email':
                $to = $interpolate((string) ($config['to'] ?? ''));
                if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    \App\Core\Queue::push(\App\Jobs\SendMailJob::class, [
                        'to' => $to,
                        'subject' => $interpolate((string) ($config['subject'] ?? '')),
                        'body' => $interpolate((string) ($config['body'] ?? '')),
                    ], 'mail', 5, 0, (int) $run['tenant_id']);
                }
                return ['action' => 'next'];

            case 'otp_send':
                $otp = \App\Core\Hash::otp(6);
                Arr::set($variables, '_otp', password_hash($otp, PASSWORD_BCRYPT));
                Arr::set($variables, '_otp_expires', time() + 300);
                MessageSender::sendToContact($contact, 'text', [
                    'body' => $interpolate((string) ($config['text'] ?? 'Your verification code is: ')) . $otp,
                ], ['flow_run_id' => (int) $run['id']]);
                return ['action' => 'next', 'variables' => $variables];

            case 'otp_verify':
                $provided = (string) Arr::get($variables, (string) ($config['reply_var'] ?? 'reply'), '');
                $hash = (string) Arr::get($variables, '_otp', '');
                $expires = (int) Arr::get($variables, '_otp_expires', 0);
                $valid = $hash !== '' && $expires > time() && password_verify(preg_replace('/\D/', '', $provided) ?? '', $hash);
                $branches = (array) ($node['branches'] ?? []);
                return [
                    'action' => 'next',
                    'next' => (string) ($valid ? ($branches['yes'] ?? '') : ($branches['no'] ?? '')),
                    'detail' => $valid ? 'verified' : 'invalid',
                ];

            case 'order_lookup':
                $orderNumber = $interpolate((string) ($config['order_number'] ?? '{{reply}}'));
                $order = DB::table('orders')
                    ->where('tenant_id', $run['tenant_id'])
                    ->where('order_number', trim($orderNumber))
                    ->first();
                Arr::set($variables, 'order', $order ?? ['status' => 'not_found']);
                $branches = (array) ($node['branches'] ?? []);
                return [
                    'action' => 'next',
                    'next' => (string) ($order !== null ? ($branches['found'] ?? $node['next'] ?? '') : ($branches['not_found'] ?? '')),
                    'variables' => $variables,
                ];

            case 'jump_to_flow':
                $targetFlow = DB::table('flows')->where('id', (int) ($config['flow_id'] ?? 0))
                    ->where('tenant_id', $run['tenant_id'])->where('status', 'active')->first();
                if ($targetFlow !== null) {
                    FlowEngine::start($targetFlow, $contact, $run['conversation_id'] !== null ? (int) $run['conversation_id'] : null, $variables);
                }
                return ['action' => 'end'];

            case 'subscribe_campaign':
                // Adds the contact to a group used as a campaign audience
                $groupId = (int) ($config['group_id'] ?? 0);
                if ($groupId > 0) {
                    $exists = DB::table('group_contacts')->where('group_id', $groupId)->where('contact_id', $contactId)->exists();
                    if (!$exists) {
                        DB::table('group_contacts')->insert(['group_id' => $groupId, 'contact_id' => $contactId]);
                        DB::table('groups')->where('id', $groupId)->increment('contact_count');
                    }
                }
                return ['action' => 'next'];

            case 'webhook_out':
                \App\Services\Integration\WebhookDispatcher::dispatch(
                    (int) $run['tenant_id'],
                    'flow.webhook',
                    ['flow_run_id' => (int) $run['id'], 'contact' => Arr::get($variables, 'contact'), 'data' => $config['data'] ?? null]
                );
                return ['action' => 'next'];

            case 'end':
                return ['action' => 'end'];

            default:
                // Unknown node types skip forward — flows stay importable
                return ['action' => 'next', 'detail' => 'skipped unknown type: ' . $type];
        }
    }
}
