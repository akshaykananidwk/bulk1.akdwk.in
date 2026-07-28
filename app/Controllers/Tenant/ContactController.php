<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Tenant;
use App\Core\View;
use App\Models\Contact;

final class ContactController extends Controller
{
    public function index(Request $request): never
    {
        $tenantId = (int) Tenant::id();
        $search = $request->str('q');
        $tagId = $request->int('tag');
        $stage = $request->str('stage');
        $page = max(1, $request->int('page', 1));

        $query = DB::table('contacts')->where('tenant_id', $tenantId);

        if ($search !== '') {
            // FULLTEXT search with LIKE fallback for short terms
            if (mb_strlen($search) >= 3) {
                $query->whereRaw(DB::raw('MATCH(`name`, `phone`, `email`) AGAINST (? IN BOOLEAN MODE)'), [$search . '*']);
            } else {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                $query->whereGroup(function ($q) use ($like) {
                    $q->whereLike('name', $like)->whereLike('phone', $like, 'OR');
                });
            }
        }
        if ($tagId > 0) {
            $ids = DB::table('contact_tags')->where('tag_id', $tagId)->pluck('contact_id');
            $query->whereIn('id', array_map('intval', $ids));
        }
        if ($stage !== '') {
            $query->where('lifecycle_stage', $stage);
        }

        $result = $query->orderBy('created_at', 'DESC')->paginate($page, 50);
        $tags = DB::table('tags')->where('tenant_id', $tenantId)->orderBy('name')->get();
        $fields = DB::table('contact_fields')->where('tenant_id', $tenantId)->orderBy('sort_order')->get();

        Layout::title(__('nav.contacts', 'Contacts'));
        View::render('tenant/contacts', [
            'result' => $result,
            'tags' => $tags,
            'fields' => $fields,
            'search' => $search,
            'tagId' => $tagId,
            'stage' => $stage,
        ], 'layouts/tenant');
    }

    public function show(Request $request): never
    {
        $contact = $this->findContact($request);
        $contactId = (int) $contact['id'];

        // Journey timeline: messages, orders, notes, campaign touches
        $timeline = DB::table('messages')
            ->where('contact_id', $contactId)
            ->select('id', 'direction', 'type', 'body', 'status', 'created_at')
            ->orderBy('id', 'DESC')->limit(50)->get();

        $this->json([
            'contact' => $contact,
            'fields' => Contact::fieldValues($contactId),
            'tags' => DB::table('contact_tags')
                ->join('tags', 'contact_tags.tag_id', '=', 'tags.id')
                ->where('contact_tags.contact_id', $contactId)
                ->select('tags.id', 'tags.name', 'tags.color')->get(),
            'timeline' => $timeline,
            'orders' => DB::table('orders')->where('contact_id', $contactId)->orderBy('id', 'DESC')->limit(10)->get(),
        ]);
    }

    public function store(Request $request): never
    {
        [$allowed, $limit] = Tenant::withinLimit('contacts');
        if (!$allowed) {
            $message = __('billing.contact_limit', 'Contact limit reached (:n). Upgrade your plan.', ['n' => (string) $limit]);
            if ($request->wantsJson()) {
                $this->fail($message, 422);
            }
            Redirect::back('/tenant/contacts')->with('error', $message)->send();
        }

        $data = $this->validate($request, [
            'phone' => 'required|phone',
            'name' => 'nullable|string|max:191',
            'email' => 'nullable|email',
            'lifecycle_stage' => 'nullable|in:lead,prospect,customer,vip,churned',
        ]);

        $phone = Sanitizer::phone((string) $data['phone']);
        if (DB::table('contacts')->where('tenant_id', Tenant::id())->where('phone', $phone)->exists()) {
            if ($request->wantsJson()) {
                $this->fail(__('contacts.duplicate', 'A contact with this phone already exists.'), 422);
            }
            Redirect::back('/tenant/contacts')->with('error', __('contacts.duplicate', 'A contact with this phone already exists.'))->send();
        }

        $id = Contact::create([
            'phone' => $phone,
            'name' => $data['name'] ?? null,
            'email' => isset($data['email']) ? strtolower((string) $data['email']) : null,
            'lifecycle_stage' => $data['lifecycle_stage'] ?? 'lead',
            'source' => 'manual',
        ]);
        Tenant::recordUsage('contacts');
        audit_log('contacts.created', 'contact', $id);

        if ($request->wantsJson()) {
            $this->ok(['id' => $id]);
        }
        Redirect::to('/tenant/contacts')->with('success', __('contacts.created', 'Contact added.'))->send();
    }

    public function update(Request $request): never
    {
        $contact = $this->findContact($request);
        $data = $this->validate($request, [
            'name' => 'nullable|string|max:191',
            'email' => 'nullable|email',
            'lifecycle_stage' => 'nullable|in:lead,prospect,customer,vip,churned',
            'notes' => 'nullable|string|max:5000',
            'opt_in' => 'nullable|boolean',
            'is_blocked' => 'nullable|boolean',
        ]);

        $update = [
            'name' => $data['name'] ?? $contact['name'],
            'email' => isset($data['email']) ? strtolower((string) $data['email']) : $contact['email'],
            'lifecycle_stage' => $data['lifecycle_stage'] ?? $contact['lifecycle_stage'],
            'notes' => $data['notes'] ?? $contact['notes'],
        ];
        if ($request->input('opt_in') !== null) {
            $update['opt_in'] = $request->bool('opt_in') ? 1 : 0;
            if (!$request->bool('opt_in')) {
                $update['opt_out_at'] = now();
            }
        }
        if ($request->input('is_blocked') !== null) {
            $update['is_blocked'] = $request->bool('is_blocked') ? 1 : 0;
        }

        Contact::updateById((int) $contact['id'], $update);

        // Custom field values: fields[<field_id>] = value
        foreach ($request->arr('fields') as $fieldId => $value) {
            if (is_numeric($fieldId)) {
                $field = DB::table('contact_fields')->where('id', (int) $fieldId)->where('tenant_id', Tenant::id())->first();
                if ($field !== null) {
                    Contact::setFieldValue((int) $contact['id'], (int) $fieldId, is_scalar($value) ? (string) $value : null);
                }
            }
        }

        audit_log('contacts.updated', 'contact', (int) $contact['id']);
        if ($request->wantsJson()) {
            $this->ok();
        }
        Redirect::back('/tenant/contacts')->with('success', __('contacts.updated', 'Contact updated.'))->send();
    }

    public function destroy(Request $request): never
    {
        $contact = $this->findContact($request);
        Contact::deleteById((int) $contact['id']);
        audit_log('contacts.deleted', 'contact', (int) $contact['id'], ['phone' => $contact['phone']]);
        if ($request->wantsJson()) {
            $this->ok();
        }
        Redirect::to('/tenant/contacts')->with('success', __('contacts.deleted', 'Contact deleted.'))->send();
    }

    public function syncTags(Request $request): never
    {
        $contact = $this->findContact($request);
        $tagIds = array_map('intval', $request->arr('tag_ids'));
        $validIds = array_map('intval', DB::table('tags')->where('tenant_id', Tenant::id())->whereIn('id', $tagIds)->pluck('id'));

        DB::table('contact_tags')->where('contact_id', $contact['id'])->delete();
        foreach ($validIds as $tagId) {
            DB::table('contact_tags')->insert(['contact_id' => (int) $contact['id'], 'tag_id' => $tagId]);
        }
        $this->ok();
    }

    /**
     * CSV import with column mapping + duplicate detection by phone.
     * Expects: file (csv), map[phone], map[name], map[email] column indexes.
     */
    public function import(Request $request): never
    {
        $file = $request->file('file');
        if ($file === null) {
            Redirect::back('/tenant/contacts')->with('error', __('upload.failed', 'Upload failed. Please try again.'))->send();
        }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt'], true)) {
            Redirect::back('/tenant/contacts')->with('error', __('contacts.import_csv_only', 'Upload a CSV file (Excel: save as CSV).'))->send();
        }

        $map = $request->arr('map');
        $phoneCol = (int) ($map['phone'] ?? 0);
        $nameCol = isset($map['name']) && $map['name'] !== '' ? (int) $map['name'] : null;
        $emailCol = isset($map['email']) && $map['email'] !== '' ? (int) $map['email'] : null;
        $skipHeader = $request->bool('skip_header', true);

        $handle = fopen((string) $file['tmp_name'], 'r');
        if ($handle === false) {
            Redirect::back('/tenant/contacts')->with('error', __('upload.failed', 'Upload failed. Please try again.'))->send();
        }

        $imported = 0;
        $duplicates = 0;
        $invalid = 0;
        $row = 0;
        $tenantId = (int) Tenant::id();
        $existing = array_flip(DB::table('contacts')->where('tenant_id', $tenantId)->pluck('phone'));

        [$allowed, $limit, $used] = Tenant::withinLimit('contacts', 0);
        $remaining = $limit === null ? PHP_INT_MAX : max(0, $limit - $used);

        while (($columns = fgetcsv($handle)) !== false) {
            $row++;
            if ($row === 1 && $skipHeader) {
                continue;
            }
            $phone = Sanitizer::phone((string) ($columns[$phoneCol] ?? ''));
            if ($phone === '' || strlen($phone) < 8) {
                $invalid++;
                continue;
            }
            if (isset($existing[$phone])) {
                $duplicates++;
                continue;
            }
            if ($imported >= $remaining) {
                break; // plan limit
            }
            Contact::create([
                'phone' => $phone,
                'name' => $nameCol !== null ? trim((string) ($columns[$nameCol] ?? '')) ?: null : null,
                'email' => $emailCol !== null ? strtolower(trim((string) ($columns[$emailCol] ?? ''))) ?: null : null,
                'source' => 'import',
            ]);
            $existing[$phone] = true;
            $imported++;
        }
        fclose($handle);

        Tenant::recordUsage('contacts', $imported);
        audit_log('contacts.imported', 'contact', null, ['imported' => $imported, 'duplicates' => $duplicates, 'invalid' => $invalid]);

        Redirect::to('/tenant/contacts')->with('success', __(
            'contacts.import_done',
            ':i imported, :d duplicates skipped, :x invalid rows',
            ['i' => (string) $imported, 'd' => (string) $duplicates, 'x' => (string) $invalid]
        ))->send();
    }

    public function export(Request $request): never
    {
        $tenantId = (int) Tenant::id();
        $filename = 'contacts_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel
        fputcsv($out, ['phone', 'name', 'email', 'lifecycle_stage', 'lead_score', 'opt_in', 'source', 'created_at']);

        $offset = 0;
        while (true) {
            $rows = DB::table('contacts')->where('tenant_id', $tenantId)
                ->select('phone', 'name', 'email', 'lifecycle_stage', 'lead_score', 'opt_in', 'source', 'created_at')
                ->orderBy('id')->limit(1000)->offset($offset)->get();
            if (empty($rows)) {
                break;
            }
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            $offset += 1000;
        }
        fclose($out);
        audit_log('contacts.exported');
        exit;
    }

    private function findContact(Request $request): array
    {
        $contact = DB::table('contacts')
            ->where('id', (int) $request->route('id'))
            ->where('tenant_id', Tenant::id())
            ->first();
        if ($contact === null) {
            Response::abort(404);
        }
        return $contact;
    }
}
