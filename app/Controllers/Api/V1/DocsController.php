<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Interactive API docs (self-contained page, no external Swagger CDN)
 * + generated OpenAPI 3.1 spec.
 */
final class DocsController extends Controller
{
    public function spec(Request $request): never
    {
        $base = url('/api/v1');
        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => (string) setting('app_name', 'Krishna WhatsApp Cloud') . ' API',
                'version' => app_version(),
                'description' => 'REST API for WhatsApp messaging, contacts, templates and conversations. Authenticate with the X-Api-Key header.',
            ],
            'servers' => [['url' => $base]],
            'components' => [
                'securitySchemes' => [
                    'ApiKey' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Api-Key'],
                ],
                'schemas' => [
                    'Error' => ['type' => 'object', 'properties' => [
                        'success' => ['type' => 'boolean', 'const' => false],
                        'message' => ['type' => 'string'],
                    ]],
                    'Contact' => ['type' => 'object', 'properties' => [
                        'id' => ['type' => 'integer'], 'phone' => ['type' => 'string'],
                        'name' => ['type' => ['string', 'null']], 'email' => ['type' => ['string', 'null']],
                        'lifecycle_stage' => ['type' => 'string'], 'lead_score' => ['type' => 'integer'],
                        'opt_in' => ['type' => 'integer'], 'created_at' => ['type' => 'string'],
                    ]],
                ],
            ],
            'security' => [['ApiKey' => []]],
            'paths' => [
                '/messages' => ['post' => [
                    'summary' => 'Send a message',
                    'description' => 'Sends text, media, interactive or template messages. Free-form types require an open 24h session window; templates work anytime.',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'required' => ['to', 'type'],
                        'properties' => [
                            'to' => ['type' => 'string', 'example' => '919812345678'],
                            'type' => ['type' => 'string', 'enum' => ['text', 'image', 'video', 'audio', 'document', 'location', 'interactive_button', 'interactive_list', 'template']],
                            'text' => ['type' => 'object', 'properties' => ['body' => ['type' => 'string']]],
                            'template' => ['type' => 'object', 'properties' => [
                                'name' => ['type' => 'string'], 'language' => ['type' => 'string'],
                                'variables' => ['type' => 'object', 'additionalProperties' => ['type' => 'string']],
                            ]],
                        ],
                    ]]]],
                    'responses' => [
                        '201' => ['description' => 'Queued/sent'],
                        '422' => ['description' => 'Validation or WhatsApp error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ]],
                '/messages/{id}' => ['get' => [
                    'summary' => 'Get message status',
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses' => ['200' => ['description' => 'Message with delivery status and cost']],
                ]],
                '/contacts' => [
                    'get' => ['summary' => 'List contacts', 'parameters' => [
                        ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ], 'responses' => ['200' => ['description' => 'Paginated contacts']]],
                    'post' => ['summary' => 'Create a contact', 'responses' => ['201' => ['description' => 'Created'], '409' => ['description' => 'Duplicate phone']]],
                ],
                '/contacts/{id}' => [
                    'get' => ['summary' => 'Get a contact', 'responses' => ['200' => ['description' => 'Contact with tags + custom fields']]],
                    'put' => ['summary' => 'Update a contact', 'responses' => ['200' => ['description' => 'Updated']]],
                    'delete' => ['summary' => 'Delete a contact', 'responses' => ['200' => ['description' => 'Deleted']]],
                ],
                '/templates' => ['get' => ['summary' => 'List templates', 'responses' => ['200' => ['description' => 'Templates with components']]]],
                '/conversations' => ['get' => ['summary' => 'List conversations', 'responses' => ['200' => ['description' => 'Paginated conversations']]]],
                '/conversations/{id}/messages' => ['get' => ['summary' => 'Conversation messages', 'responses' => ['200' => ['description' => 'Messages (cursor: before_id)']]]],
            ],
        ];

        Response::json($spec);
    }

    /**
     * Self-contained interactive docs page (fetches the JSON spec).
     */
    public function page(Request $request): never
    {
        $specUrl = url('/api/openapi.json');
        $appName = e((string) setting('app_name', 'Krishna WhatsApp Cloud'));
        $css = e(asset('css/app.css'));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$appName} — API Docs</title>
<link rel="stylesheet" href="{$css}">
<style>
    .endpoint { border: 1px solid var(--border); border-radius: 12px; margin-bottom: .75rem; overflow: hidden; }
    .endpoint summary { display: flex; gap: .7rem; align-items: center; padding: .7rem 1rem; cursor: pointer; background: var(--surface-2); }
    .endpoint .body { padding: 1rem; border-top: 1px solid var(--border); font-size: .86rem; }
    .method { font-weight: 700; font-size: .72rem; padding: .18rem .55rem; border-radius: 6px; color: #fff; min-width: 52px; text-align: center; }
    .m-get { background: #3B82F6; } .m-post { background: #10B981; } .m-put { background: #F59E0B; } .m-delete { background: #EF4444; }
    pre { background: var(--surface-3); padding: .7rem; border-radius: 8px; overflow-x: auto; font-size: .78rem; }
</style>
</head>
<body>
<div class="content" style="max-width: 900px; margin: 0 auto; padding: 2rem 1rem;">
    <h1>🦚 {$appName} — REST API</h1>
    <p class="text-muted">Authenticate every request with the <code>X-Api-Key</code> header. Create keys in your workspace under <strong>API &amp; Webhooks</strong>.</p>
    <p><a class="btn btn-outline btn-sm" href="{$specUrl}" download="openapi.json">⬇ OpenAPI 3.1 spec (JSON)</a></p>
    <div id="endpoints"><div class="empty-state">Loading spec…</div></div>
</div>
<script>
fetch('{$specUrl}').then(function (r) { return r.json(); }).then(function (spec) {
    var container = document.getElementById('endpoints');
    container.innerHTML = '';
    Object.keys(spec.paths).forEach(function (path) {
        Object.keys(spec.paths[path]).forEach(function (method) {
            var op = spec.paths[path][method];
            var details = document.createElement('details');
            details.className = 'endpoint';
            var schema = op.requestBody && op.requestBody.content && op.requestBody.content['application/json']
                ? JSON.stringify(op.requestBody.content['application/json'].schema, null, 2) : null;
            var responses = Object.keys(op.responses || {}).map(function (code) {
                return '<li><code>' + code + '</code> — ' + (op.responses[code].description || '') + '</li>';
            }).join('');
            details.innerHTML =
                '<summary><span class="method m-' + method + '">' + method.toUpperCase() + '</span>'
                + '<code>' + path + '</code><span class="text-muted text-sm">' + (op.summary || '') + '</span></summary>'
                + '<div class="body">'
                + (op.description ? '<p>' + op.description + '</p>' : '')
                + (schema ? '<strong>Request body schema</strong><pre>' + schema.replace(/</g, '&lt;') + '</pre>' : '')
                + '<strong>Responses</strong><ul>' + responses + '</ul>'
                + '</div>';
            container.appendChild(details);
        });
    });
});
</script>
</body>
</html>
HTML;

        Response::html($html);
    }
}
