<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Sanitizer;
use App\Core\Tenant;
use App\Models\Contact;

final class ContactApiController extends Controller
{
    private const FIELDS = ['id', 'phone', 'name', 'email', 'lifecycle_stage', 'lead_score', 'source', 'opt_in', 'last_message_at', 'created_at', 'updated_at'];

    public function index(Request $request): never
    {
        $page = max(1, $request->int('page', 1));
        $perPage = min(100, max(1, $request->int('per_page', 25)));

        $query = DB::table('contacts')->where('tenant_id', Tenant::id())->select(...self::FIELDS);
        $search = $request->str('q');
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->whereGroup(function ($q) use ($like) {
                $q->whereLike('name', $like)->whereLike('phone', $like, 'OR');
            });
        }
        $result = $query->orderBy('id', 'DESC')->paginate($page, $perPage);
        $this->json(['success' => true, 'data' => $result['data'], 'meta' => [
            'total' => $result['total'], 'page' => $result['page'], 'per_page' => $result['per_page'], 'last_page' => $result['last_page'],
        ]]);
    }

    public function show(Request $request): never
    {
        $contact = $this->findContact($request);
        $contact['tags'] = DB::table('contact_tags')
            ->join('tags', 'contact_tags.tag_id', '=', 'tags.id')
            ->where('contact_tags.contact_id', $contact['id'])
            ->select('tags.name')->pluck('name');
        $contact['fields'] = Contact::fieldValues((int) $contact['id']);
        $this->json(['success' => true, 'data' => $contact]);
    }

    public function store(Request $request): never
    {
        $phone = Sanitizer::phone($request->str('phone'));
        if ($phone === '' || strlen($phone) < 8) {
            $this->fail('A valid "phone" with country code is required.', 422);
        }
        [$allowed] = Tenant::withinLimit('contacts');
        if (!$allowed) {
            $this->fail('Contact limit reached for the current plan.', 402);
        }
        if (DB::table('contacts')->where('tenant_id', Tenant::id())->where('phone', $phone)->exists()) {
            $this->fail('A contact with this phone already exists.', 409);
        }

        $id = Contact::create([
            'phone' => $phone,
            'name' => $request->str('name') ?: null,
            'email' => $request->str('email') !== '' ? strtolower($request->str('email')) : null,
            'lifecycle_stage' => in_array($request->str('lifecycle_stage'), ['lead', 'prospect', 'customer', 'vip', 'churned'], true)
                ? $request->str('lifecycle_stage') : 'lead',
            'source' => 'api',
        ]);
        Tenant::recordUsage('contacts');
        $this->json(['success' => true, 'data' => ['id' => $id, 'phone' => $phone]], 201);
    }

    public function update(Request $request): never
    {
        $contact = $this->findContact($request);
        $update = [];
        if ($request->input('name') !== null) {
            $update['name'] = $request->str('name') ?: null;
        }
        if ($request->input('email') !== null) {
            $email = $request->str('email');
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $this->fail('Invalid email.', 422);
            }
            $update['email'] = $email !== '' ? strtolower($email) : null;
        }
        if ($request->input('lifecycle_stage') !== null && in_array($request->str('lifecycle_stage'), ['lead', 'prospect', 'customer', 'vip', 'churned'], true)) {
            $update['lifecycle_stage'] = $request->str('lifecycle_stage');
        }
        if ($request->input('opt_in') !== null) {
            $update['opt_in'] = $request->bool('opt_in') ? 1 : 0;
        }
        if ($update) {
            Contact::updateById((int) $contact['id'], $update);
        }
        $this->json(['success' => true, 'data' => ['id' => (int) $contact['id']]]);
    }

    public function destroy(Request $request): never
    {
        $contact = $this->findContact($request);
        Contact::deleteById((int) $contact['id']);
        $this->json(['success' => true]);
    }

    private function findContact(Request $request): array
    {
        $contact = DB::table('contacts')
            ->where('id', (int) $request->route('id'))
            ->where('tenant_id', Tenant::id())
            ->select(...self::FIELDS)
            ->first();
        if ($contact === null) {
            $this->fail('Contact not found.', 404);
        }
        return $contact;
    }
}
