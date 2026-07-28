<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('nav.templates', 'Templates')) ?></h1>
    <div class="flex gap-2">
        <form method="post" action="<?= e(url('/tenant/templates/sync')) ?>"><?= csrf_field() ?>
            <button class="btn btn-outline" type="submit">🔄 <?= e(__('templates.sync', 'Sync from Meta')) ?></button>
        </form>
        <a class="btn btn-primary" href="<?= e(url('/tenant/templates/create')) ?>">＋ <?= e(__('templates.new', 'New template')) ?></a>
    </div>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th><?= e(__('fields.name', 'Name')) ?></th>
            <th><?= e(__('templates.language', 'Language')) ?></th>
            <th><?= e(__('templates.category', 'Category')) ?></th>
            <th><?= e(__('fields.status', 'Status')) ?></th>
            <th><?= e(__('templates.preview', 'Preview')) ?></th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($result['data'] as $template): ?>
            <?php
            $badge = match ($template['status']) {
                'APPROVED' => 'success', 'REJECTED' => 'danger', 'PAUSED', 'DISABLED' => 'warning', default => 'muted',
            };
            $components = json_decode((string) $template['components'], true) ?: [];
            $bodyText = '';
            foreach ($components as $component) {
                if (strtoupper((string) ($component['type'] ?? '')) === 'BODY') {
                    $bodyText = (string) ($component['text'] ?? '');
                    break;
                }
            }
            ?>
            <tr>
                <td class="font-semi"><?= e($template['name']) ?></td>
                <td><?= e($template['language']) ?></td>
                <td><span class="badge badge-<?= $template['category'] === 'MARKETING' ? 'warning' : 'info' ?>"><?= e($template['category']) ?></span></td>
                <td>
                    <span class="badge badge-<?= e($badge) ?>"><?= e($template['status']) ?></span>
                    <?php if ($template['rejected_reason']): ?>
                        <div class="text-xs" style="color:var(--danger)"><?= e($template['rejected_reason']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-sm text-muted" style="max-width:340px"><span class="truncate" style="display:block"><?= e(\App\Core\Str::limit($bodyText, 90)) ?></span></td>
                <td>
                    <form method="post" action="<?= e(url('/tenant/templates/' . (int) $template['id'] . '/delete')) ?>" data-confirm="<?= e(__('templates.delete_confirm', 'Delete this template from Meta and locally?')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-sm" type="submit">🗑</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($result['data'])): ?>
            <tr><td colspan="6"><div class="empty-state"><div class="icon">📋</div><p><?= e(__('templates.empty', 'No templates. Sync from Meta or create a new one.')) ?></p></div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php View::partial('partials/pagination', ['result' => $result, 'baseUrl' => url('/tenant/templates')]); ?>
<?php View::end(); ?>
