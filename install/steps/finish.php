<?php
/** @var callable $e */
if (empty($_SESSION['install_db']) || empty($_SESSION['install_admin']) || empty($_SESSION['install_settings'])) {
    header('Location: ./?step=welcome');
    exit;
}
$settings = $_SESSION['install_settings'];
$admin = $_SESSION['install_admin'];
$modeSelected = 'cron';
?>
<div id="finish-pre">
    <h1>Ready to install</h1>
    <p>Review and click <strong>Install Now</strong>. This will:</p>
    <ul>
        <li>Create the super admin account <strong><?= $e($admin['email']) ?></strong></li>
        <li>Generate <code>config/config.php</code> with a fresh encryption key</li>
        <li>Write <code>installed.lock</code> and <strong>disable this installer</strong></li>
    </ul>

    <div class="field">
        <label>Worker mode (from previous step)</label>
        <label style="font-weight:400"><input type="radio" name="worker_mode" value="cron" checked> Cron-only</label>
        <label style="font-weight:400; margin-left:1rem"><input type="radio" name="worker_mode" value="pm2"> PM2</label>
    </div>

    <div id="finish-result" class="alert" style="display:none"></div>

    <div class="actions">
        <a class="btn outline" href="./?step=cron">← Back</a>
        <button class="btn" id="finish-btn" type="button">🚀 Install Now</button>
    </div>
</div>

<div id="finish-done" style="display:none">
    <h1>Installation complete 🎉</h1>
    <div class="alert success">Krishna WhatsApp Cloud is installed and the installer has locked itself.</div>
    <p><strong>Installation ID:</strong> <code id="install-id"></code></p>
    <h2>Next steps</h2>
    <ol>
        <li>Log in as super admin and configure your <strong>Meta App</strong> (Admin → Meta App)</li>
        <li>Add the cron entry if you have not already</li>
        <li>Create your first tenant or share the registration link</li>
    </ol>
    <div class="actions">
        <span></span>
        <a class="btn" id="login-link" href="/login">Go to login → </a>
    </div>
</div>
