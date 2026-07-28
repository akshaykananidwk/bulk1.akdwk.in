<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header"><h1><?= e(__('campaigns.new', 'New campaign')) ?></h1></div>

<form method="post" action="<?= e(url('/tenant/campaigns')) ?>" x-data="{ audience: 'all', templateId: '' }">
    <?= csrf_field() ?>
    <div class="grid-2">
        <div class="card">
            <h3 class="card-title">1 · <?= e(__('campaigns.basics', 'Basics')) ?></h3>
            <div class="form-group">
                <label class="form-label"><?= e(__('campaigns.name', 'Campaign name')) ?></label>
                <input class="input" name="name" value="<?= e(old('name')) ?>" required placeholder="Diwali Offer 2026">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('campaigns.number', 'Send from number')) ?></label>
                <select class="input" name="phone_number_id">
                    <?php foreach ($numbers as $number): ?>
                        <option value="<?= (int) $number['id'] ?>"><?= e($number['display_phone_number']) ?><?= $number['is_default'] ? ' ⭐' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('campaigns.template', 'Approved template')) ?></label>
                <select class="input" name="template_id" x-model="templateId" required>
                    <option value=""><?= e(__('common.choose', 'Choose…')) ?></option>
                    <?php foreach ($templates as $template): ?>
                        <option value="<?= (int) $template['id'] ?>"><?= e($template['name']) ?> (<?= e($template['language']) ?> · <?= e($template['category']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint"><?= e(__('campaigns.template_hint', 'Broadcasts outside the 24h window require an APPROVED template.')) ?></div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label"><?= e(__('campaigns.throttle', 'Messages / minute')) ?></label>
                    <input class="input" type="number" name="throttle_per_minute" value="60" min="1" max="1000">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(__('campaigns.schedule', 'Schedule (optional)')) ?></label>
                    <input class="input" type="datetime-local" name="scheduled_at">
                </div>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title">2 · <?= e(__('campaigns.audience', 'Audience')) ?></h3>
            <div class="form-group">
                <label class="form-label"><?= e(__('campaigns.audience_type', 'Send to')) ?></label>
                <select class="input" name="audience_type" x-model="audience">
                    <option value="all"><?= e(__('campaigns.aud_all', 'All opted-in contacts')) ?></option>
                    <option value="groups"><?= e(__('campaigns.aud_groups', 'Groups')) ?></option>
                    <option value="tags"><?= e(__('campaigns.aud_tags', 'Tags')) ?></option>
                    <option value="segments"><?= e(__('campaigns.aud_segments', 'Segments')) ?></option>
                </select>
            </div>
            <div class="form-group" x-show="audience === 'groups'" x-cloak>
                <label class="form-label"><?= e(__('campaigns.groups', 'Groups')) ?></label>
                <?php foreach ($groups as $group): ?>
                    <label class="checkbox-row"><input type="checkbox" name="group_ids[]" value="<?= (int) $group['id'] ?>"> <?= e($group['name']) ?> (<?= (int) $group['contact_count'] ?>)</label>
                <?php endforeach; ?>
                <?php if (empty($groups)): ?><div class="text-sm text-muted"><?= e(__('campaigns.no_groups', 'No groups yet.')) ?></div><?php endif; ?>
            </div>
            <div class="form-group" x-show="audience === 'tags'" x-cloak>
                <label class="form-label"><?= e(__('campaigns.tags', 'Tags')) ?></label>
                <?php foreach ($tags as $tag): ?>
                    <label class="checkbox-row"><input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>"> <?= e($tag['name']) ?></label>
                <?php endforeach; ?>
                <?php if (empty($tags)): ?><div class="text-sm text-muted"><?= e(__('campaigns.no_tags', 'No tags yet.')) ?></div><?php endif; ?>
            </div>
            <div class="form-group" x-show="audience === 'segments'" x-cloak>
                <label class="form-label"><?= e(__('campaigns.segments', 'Segments')) ?></label>
                <?php foreach ($segments as $segment): ?>
                    <label class="checkbox-row"><input type="checkbox" name="segment_ids[]" value="<?= (int) $segment['id'] ?>"> <?= e($segment['name']) ?> (~<?= (int) $segment['contact_count'] ?>)</label>
                <?php endforeach; ?>
                <?php if (empty($segments)): ?><div class="text-sm text-muted"><?= e(__('campaigns.no_segments', 'No segments yet.')) ?></div><?php endif; ?>
            </div>

            <h3 class="card-title mt-4">3 · <?= e(__('campaigns.variables', 'Template variables')) ?></h3>
            <p class="text-sm text-muted"><?= e(__('campaigns.variables_hint', 'Map {{1}}, {{2}}… to contact fields or static text. Unmapped variables send empty.')) ?></p>
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">{{<?= $i ?>}} <?= e(__('campaigns.source', 'source')) ?></label>
                        <select class="input" name="variables[<?= $i ?>][source]">
                            <option value="static"><?= e(__('campaigns.static', 'Static text')) ?></option>
                            <option value="contact.name"><?= e(__('campaigns.contact_name', 'Contact name')) ?></option>
                            <option value="contact.phone"><?= e(__('campaigns.contact_phone', 'Contact phone')) ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('campaigns.value', 'Value / fallback')) ?></label>
                        <input class="input" name="variables[<?= $i ?>][value]" placeholder="<?= e(__('campaigns.value_ph', 'text or fallback')) ?>">
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="mt-4 flex gap-2">
        <a class="btn btn-outline" href="<?= e(url('/tenant/campaigns')) ?>"><?= e(__('common.cancel', 'Cancel')) ?></a>
        <button class="btn btn-primary btn-lg" type="submit"><?= e(__('campaigns.create_button', 'Create campaign')) ?></button>
    </div>
</form>
<?php View::end(); ?>
