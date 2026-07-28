<?php /** @var callable $e */ $admin = $_SESSION['install_admin'] ?? []; ?>
<h1>Super admin account</h1>
<p>This account manages the whole platform: tenants, plans, billing and updates.</p>

<form id="admin-form">
    <div class="grid2">
        <div class="field">
            <label for="admin-name">Full name</label>
            <input id="admin-name" required value="<?= $e($admin['name'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="admin-phone">WhatsApp number <span class="muted">(optional)</span></label>
            <input id="admin-phone" value="<?= $e($admin['phone'] ?? '') ?>" placeholder="9198XXXXXXXX">
        </div>
    </div>
    <div class="field">
        <label for="admin-email">Email</label>
        <input id="admin-email" type="email" required value="<?= $e($admin['email'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="admin-pass">Password</label>
        <input id="admin-pass" type="password" required minlength="8">
        <div class="progress" style="height:6px; margin:.4rem 0 0"><div class="progress-bar" id="pw-bar" style="width:0"></div></div>
        <div class="hint">Minimum 8 characters — mix cases, numbers and symbols for a strong password.</div>
    </div>
    <div class="field">
        <label for="admin-tz">Timezone</label>
        <select id="admin-tz">
            <?php foreach (['Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Europe/London', 'America/New_York', 'UTC'] as $tz): ?>
                <option value="<?= $e($tz) ?>" <?= ($admin['timezone'] ?? 'Asia/Kolkata') === $tz ? 'selected' : '' ?>><?= $e($tz) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="admin-result" class="alert" style="display:none"></div>

    <div class="actions">
        <a class="btn outline" href="./?step=import">← Back</a>
        <button class="btn" type="submit">Continue → </button>
    </div>
</form>
