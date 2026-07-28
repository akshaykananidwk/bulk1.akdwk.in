<?php
/** @var callable $e */

use Installer\Installer;

if (empty($_SESSION['install_db'])) {
    header('Location: ./?step=database');
    exit;
}
$total = count(Installer::importPlan());
$imported = !empty($_SESSION['install_imported']);
?>
<h1>Import database</h1>
<p>The full schema (119 tables) and seed data (<?= $e($total) ?> SQL statements) will be imported with live progress. Safe to resume if interrupted.</p>

<div class="progress"><div class="progress-bar" id="import-bar" style="width:<?= $imported ? '100%' : '0' ?>"></div></div>
<div class="muted" id="import-label"><?= $imported ? 'Already imported.' : 'Ready.' ?></div>

<div id="import-result" class="alert" style="display:none"></div>

<div class="actions">
    <a class="btn outline" href="./?step=database">← Back</a>
    <span>
        <button class="btn" id="import-btn" data-offset="0" type="button" <?= $imported ? 'disabled' : '' ?>>Start Import</button>
        <a class="btn" id="import-continue" href="./?step=admin" style="display:<?= $imported ? 'inline-flex' : 'none' ?>">Continue → </a>
    </span>
</div>
