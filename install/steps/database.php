<?php /** @var callable $e */ $db = $_SESSION['install_db'] ?? []; ?>
<h1>Database</h1>
<p>Enter your MySQL / MariaDB credentials. The database is created automatically if the user has permission.</p>

<div class="grid2">
    <div class="field">
        <label for="db-host">Host</label>
        <input id="db-host" value="<?= $e($db['host'] ?? '127.0.0.1') ?>">
    </div>
    <div class="field">
        <label for="db-port">Port</label>
        <input id="db-port" type="number" value="<?= $e($db['port'] ?? 3306) ?>">
    </div>
</div>
<div class="field">
    <label for="db-name">Database name</label>
    <input id="db-name" value="<?= $e($db['database'] ?? '') ?>" placeholder="krishna_wa">
</div>
<div class="grid2">
    <div class="field">
        <label for="db-user">Username</label>
        <input id="db-user" value="<?= $e($db['username'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="db-pass">Password</label>
        <input id="db-pass" type="password" value="<?= $e($db['password'] ?? '') ?>">
    </div>
</div>
<div class="field">
    <label for="db-prefix">Table prefix <span class="muted">(optional)</span></label>
    <input id="db-prefix" value="<?= $e($db['prefix'] ?? '') ?>" placeholder="kwc_">
    <div class="hint">Leave empty unless you share the database with another app.</div>
</div>

<div id="db-result" class="alert" style="display:none"></div>

<div class="actions">
    <a class="btn outline" href="./?step=requirements">← Back</a>
    <span>
        <button class="btn outline" id="test-db-btn" type="button">Test Connection</button>
        <a class="btn" id="db-continue" href="./?step=import" style="display:<?= empty($db) ? 'none' : 'inline-flex' ?>">Continue → </a>
    </span>
</div>
