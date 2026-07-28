<?php /** @var callable $e */ /** @var string $csrf */ ?>
<h1>Already installed 🔒</h1>
<p>Krishna WhatsApp Cloud is already installed on this server. The installer is locked to protect your data.</p>

<?php if (!empty($repairError)): ?>
    <div class="alert error"><?= $e($repairError) ?></div>
<?php endif; ?>

<h2>Repair / re-install</h2>
<p class="hint">To unlock the installer (for repair), confirm your <strong>database password</strong> from <code>config/config.php</code>. A re-import can overwrite data — take a backup first.</p>

<form method="post" action="./?action=repair">
    <input type="hidden" name="_token" value="<?= $e($csrf) ?>">
    <div class="field">
        <label for="db_password">Database password</label>
        <input id="db_password" name="db_password" type="password" required>
    </div>
    <div class="actions">
        <a class="btn outline" href="/">← Back to app</a>
        <button class="btn danger" type="submit">Unlock installer</button>
    </div>
</form>
