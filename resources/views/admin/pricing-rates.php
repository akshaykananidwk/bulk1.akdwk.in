<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('admin.pricing', 'Pricing Rates')) ?></h1>
</div>

<div class="alert alert-info">
    <span>ℹ️</span>
    <div><?= e(__('admin.pricing_note', 'Rates are charged per delivered conversation by category. Use country code "*" for the default fallback rate applied when no country-specific row matches. The newest effective date wins.')) ?></div>
</div>

<div class="card mb-2">
    <div class="card-header"><h2 class="card-title"><?= e(__('admin.add_rate', 'Add rate')) ?></h2></div>
    <form method="post" action="<?= e(url('/admin/pricing-rates')) ?>">
        <?= csrf_field() ?>
        <div class="grid-3">
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.country_code', 'Country code')) ?></label>
                <input class="input" name="country_code" required maxlength="5" placeholder="IN / US / *">
                <div class="form-hint"><?= e(__('admin.country_code_hint', '2-letter ISO code, or * for the default rate.')) ?></div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.country_name', 'Country name')) ?></label>
                <input class="input" name="country_name" maxlength="100" placeholder="India">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.category', 'Category')) ?></label>
                <select class="input" name="category" required>
                    <?php foreach (['marketing', 'utility', 'authentication', 'service'] as $category): ?>
                        <option value="<?= e($category) ?>"><?= e(ucfirst($category)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid-3">
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.rate', 'Rate')) ?></label>
                <input class="input" type="number" name="rate" step="0.000001" min="0" required placeholder="0.7846">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('fields.currency', 'Currency')) ?></label>
                <select class="input" name="currency" required>
                    <?php foreach (['INR', 'USD', 'EUR', 'GBP', 'AED'] as $currency): ?>
                        <option value="<?= e($currency) ?>"><?= e($currency) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.effective_from', 'Effective from')) ?></label>
                <input class="input" type="date" name="effective_from" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
        </div>
        <button class="btn btn-primary btn-sm" type="submit"><?= e(__('common.save', 'Save')) ?></button>
    </form>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th><?= e(__('admin.country_code', 'Country')) ?></th>
            <th><?= e(__('admin.country_name', 'Name')) ?></th>
            <th><?= e(__('admin.category', 'Category')) ?></th>
            <th><?= e(__('admin.rate', 'Rate')) ?></th>
            <th><?= e(__('fields.currency', 'Currency')) ?></th>
            <th><?= e(__('admin.effective_from', 'Effective from')) ?></th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rates as $rate): ?>
            <tr>
                <td class="tabular"><?= e($rate['country_code']) ?><?php if ($rate['country_code'] === '*'): ?> <span class="badge badge-muted"><?= e(__('admin.default_rate', 'default')) ?></span><?php endif; ?></td>
                <td><?= $rate['country_name'] !== null && $rate['country_name'] !== '' ? e($rate['country_name']) : '—' ?></td>
                <td>
                    <?php $badge = match ($rate['category']) { 'marketing' => 'info', 'utility' => 'success', 'authentication' => 'warning', default => 'muted' }; ?>
                    <span class="badge badge-<?= e($badge) ?>"><?= e($rate['category']) ?></span>
                </td>
                <td class="tabular"><?= e(number_format((float) $rate['rate'], 6)) ?></td>
                <td><?= e($rate['currency']) ?></td>
                <td class="text-sm text-muted"><?= e(\App\Core\DateHelper::display((string) $rate['effective_from'])) ?></td>
                <td>
                    <form method="post" action="<?= e(url('/admin/pricing-rates/' . (int) $rate['id'] . '/delete')) ?>" data-confirm="<?= e(__('admin.rate_delete_confirm', 'Delete this rate?')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-sm" type="submit">🗑</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rates)): ?>
            <tr><td colspan="7" class="text-center text-muted"><?= e(__('admin.no_rates', 'No rates configured yet. Messages cannot be billed until rates exist.')) ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php View::end(); ?>
