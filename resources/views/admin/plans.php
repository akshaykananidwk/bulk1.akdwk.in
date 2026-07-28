<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('admin.plans', 'Plans')) ?></h1>
</div>

<div class="grid-2">
    <?php foreach ($plans as $plan): ?>
        <?php $planId = (int) $plan['id']; ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= e($plan['name']) ?> <span class="text-sm text-muted">/<?= e($plan['slug']) ?></span></h2>
                <div class="flex gap-2 items-center">
                    <?php if ((int) $plan['is_featured'] === 1): ?><span class="badge badge-primary"><?= e(__('admin.featured', 'Featured')) ?></span><?php endif; ?>
                    <span class="badge badge-<?= (int) $plan['is_active'] === 1 ? 'success' : 'muted' ?>"><?= (int) $plan['is_active'] === 1 ? e(__('common.active', 'Active')) : e(__('common.inactive', 'Inactive')) ?></span>
                </div>
            </div>
            <form method="post" action="<?= e(url('/admin/plans/' . $planId)) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label"><?= e(__('fields.name', 'Name')) ?></label>
                    <input class="input" name="name" value="<?= e($plan['name']) ?>" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(__('fields.description', 'Description')) ?></label>
                    <textarea class="input" name="description" rows="2"><?= e($plan['description'] ?? '') ?></textarea>
                </div>
                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label"><?= e(__('admin.price_monthly', 'Monthly price')) ?></label>
                        <input class="input" type="number" step="0.01" min="0" name="price_monthly" value="<?= e($plan['price_monthly']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('admin.price_yearly', 'Yearly price')) ?></label>
                        <input class="input" type="number" step="0.01" min="0" name="price_yearly" value="<?= e($plan['price_yearly']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('admin.trial_days', 'Trial days')) ?></label>
                        <input class="input" type="number" name="trial_days" value="<?= e($plan['trial_days']) ?>" min="0" max="90" required>
                    </div>
                </div>
                <div class="grid-2">
                    <label class="checkbox-row">
                        <input type="checkbox" name="is_active" value="1" <?= (int) $plan['is_active'] === 1 ? 'checked' : '' ?>>
                        <?= e(__('common.active', 'Active')) ?>
                    </label>
                    <label class="checkbox-row">
                        <input type="checkbox" name="is_featured" value="1" <?= (int) $plan['is_featured'] === 1 ? 'checked' : '' ?>>
                        <?= e(__('admin.featured', 'Featured')) ?>
                    </label>
                </div>
                <h3 class="text-sm"><?= e(__('admin.plan_limits', 'Limits')) ?></h3>
                <div class="form-hint mb-2"><?= e(__('admin.limits_hint', '-1 or blank = unlimited.')) ?></div>
                <div class="grid-3">
                    <?php foreach ($featureKeys as $key): ?>
                        <div class="form-group">
                            <label class="form-label"><?= e(ucfirst(str_replace('_', ' ', $key))) ?></label>
                            <input class="input" type="number" name="features[<?= e($key) ?>]" value="<?= e($features[$planId][$key] ?? '') ?>" placeholder="∞">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-primary btn-sm" type="submit"><?= e(__('common.save', 'Save')) ?></button>
            </form>
        </div>
    <?php endforeach; ?>

    <div class="card">
        <div class="card-header"><h2 class="card-title">＋ <?= e(__('admin.new_plan', 'New plan')) ?></h2></div>
        <form method="post" action="<?= e(url('/admin/plans')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label"><?= e(__('fields.name', 'Name')) ?></label>
                <input class="input" name="name" required maxlength="100" placeholder="Pro">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('fields.description', 'Description')) ?></label>
                <textarea class="input" name="description" rows="2"></textarea>
            </div>
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label"><?= e(__('admin.price_monthly', 'Monthly price')) ?></label>
                    <input class="input" type="number" step="0.01" min="0" name="price_monthly" value="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(__('admin.price_yearly', 'Yearly price')) ?></label>
                    <input class="input" type="number" step="0.01" min="0" name="price_yearly">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(__('admin.trial_days', 'Trial days')) ?></label>
                    <input class="input" type="number" name="trial_days" value="7" min="0" max="90" required>
                </div>
            </div>
            <h3 class="text-sm"><?= e(__('admin.plan_limits', 'Limits')) ?></h3>
            <div class="form-hint mb-2"><?= e(__('admin.limits_hint', '-1 or blank = unlimited.')) ?></div>
            <div class="grid-3">
                <?php foreach ($featureKeys as $key): ?>
                    <div class="form-group">
                        <label class="form-label"><?= e(ucfirst(str_replace('_', ' ', $key))) ?></label>
                        <input class="input" type="number" name="features[<?= e($key) ?>]" placeholder="∞">
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-primary btn-sm" type="submit"><?= e(__('admin.create_plan', 'Create plan')) ?></button>
        </form>
    </div>
</div>
<?php View::end(); ?>
