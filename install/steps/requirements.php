<?php
/** @var callable $e */

use Installer\RequirementChecker;

$checks = RequirementChecker::all(ROOT_PATH);
$allPass = RequirementChecker::allCriticalPass(ROOT_PATH);
$rewrite = RequirementChecker::rewriteWorks();
?>
<h1>Server requirements</h1>
<p>Every ❌ critical item must be fixed before continuing. Hints show the aaPanel fix.</p>

<table class="checks">
    <?php foreach ($checks as $check): ?>
        <tr>
            <td style="width:24px"><?= $check['ok'] ? '<span class="ok">✅</span>' : ($check['critical'] ? '<span class="bad">❌</span>' : '<span class="warn">⚠️</span>') ?></td>
            <td><?= $e($check['label']) ?><br>
                <?php if (!$check['ok']): ?><span class="hint"><?= $e($check['hint']) ?></span><?php endif; ?>
            </td>
            <td style="text-align:right" class="muted"><?= $e($check['value']) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr>
        <td><?= $rewrite === true ? '<span class="ok">✅</span>' : ($rewrite === false ? '<span class="bad">❌</span>' : '<span class="warn">⚠️</span>') ?></td>
        <td>mod_rewrite (live test)<br>
            <?php if ($rewrite === false): ?><span class="hint">Enable mod_rewrite / URL rewrite for this site. In aaPanel: Website → Settings → URL Rewrite.</span><?php endif; ?>
            <?php if ($rewrite === null): ?><span class="hint">Could not self-test — verify pretty URLs work after install.</span><?php endif; ?>
        </td>
        <td style="text-align:right" class="muted"><?= $rewrite === true ? 'working' : ($rewrite === false ? 'not working' : 'unknown') ?></td>
    </tr>
</table>

<div class="actions">
    <a class="btn outline" href="./?step=requirements">↻ Re-check</a>
    <?php if ($allPass && $rewrite !== false): ?>
        <a class="btn" href="./?step=database">Continue → </a>
    <?php else: ?>
        <button class="btn" disabled title="Fix the critical items first">Continue → </button>
    <?php endif; ?>
</div>
