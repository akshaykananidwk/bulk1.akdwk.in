<?php /** @var callable $e */ /** @var string $detectedUrl */ $settings = $_SESSION['install_settings'] ?? []; ?>
<h1>Application settings</h1>

<form id="settings-form">
    <div class="field">
        <label for="set-name">Application name</label>
        <input id="set-name" value="<?= $e($settings['app_name'] ?? 'Krishna WhatsApp Cloud') ?>">
    </div>
    <div class="field">
        <label for="set-url">Application URL</label>
        <input id="set-url" type="url" required value="<?= $e($settings['app_url'] ?? $detectedUrl) ?>">
        <div class="hint">Auto-detected. Must be HTTPS in production for Meta webhooks.</div>
    </div>
    <div class="grid2">
        <div class="field">
            <label for="set-locale">Default language</label>
            <select id="set-locale">
                <option value="en" <?= ($settings['locale'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                <option value="gu" <?= ($settings['locale'] ?? '') === 'gu' ? 'selected' : '' ?>>ગુજરાતી (Gujarati)</option>
                <option value="hi" <?= ($settings['locale'] ?? '') === 'hi' ? 'selected' : '' ?>>हिन्दी (Hindi)</option>
            </select>
        </div>
        <div class="field">
            <label for="set-currency">Currency</label>
            <select id="set-currency">
                <?php foreach (['INR', 'USD', 'EUR', 'GBP', 'AED'] as $currency): ?>
                    <option value="<?= $e($currency) ?>" <?= ($settings['currency'] ?? 'INR') === $currency ? 'selected' : '' ?>><?= $e($currency) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="field">
        <label for="set-dateformat">Date format</label>
        <select id="set-dateformat">
            <option value="d M Y" <?= ($settings['date_format'] ?? 'd M Y') === 'd M Y' ? 'selected' : '' ?>><?= $e(date('d M Y')) ?> (d M Y)</option>
            <option value="d/m/Y" <?= ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>><?= $e(date('d/m/Y')) ?> (d/m/Y)</option>
            <option value="Y-m-d" <?= ($settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' ?>><?= $e(date('Y-m-d')) ?> (Y-m-d)</option>
        </select>
    </div>

    <div id="settings-result" class="alert" style="display:none"></div>

    <div class="actions">
        <a class="btn outline" href="./?step=admin">← Back</a>
        <button class="btn" type="submit">Continue → </button>
    </div>
</form>
