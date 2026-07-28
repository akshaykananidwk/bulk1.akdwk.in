<?php /** @var callable $e */ /** @var array $serverInfo */ ?>
<h1>Welcome 🙏</h1>
<p>This wizard installs <strong>Krishna WhatsApp Cloud</strong> — a self-hosted, multi-tenant WhatsApp Business Cloud API platform. It takes about 3 minutes.</p>

<h2>What you need ready</h2>
<ul>
    <li>An empty <strong>MySQL / MariaDB database</strong> and its credentials</li>
    <li>The site served over <strong>HTTPS</strong> (required for Meta webhooks)</li>
    <li>SSH or aaPanel access to add <strong>one cron job</strong></li>
</ul>

<h2>Detected server</h2>
<table class="serverinfo">
    <?php foreach ($serverInfo as $label => $value): ?>
        <tr><td><strong><?= $e($label) ?></strong></td><td><?= $e($value) ?></td></tr>
    <?php endforeach; ?>
</table>

<p class="hint" style="margin-top:1rem">By continuing you agree to use this software in accordance with the WhatsApp Business Terms and Meta Platform Policies. Messages are sent via your own Meta WhatsApp Cloud API account.</p>

<div class="actions">
    <span></span>
    <a class="btn" href="./?step=requirements">Start → </a>
</div>
