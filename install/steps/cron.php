<?php /** @var callable $e */ /** @var array $cronPaths */ ?>
<h1>Cron &amp; workers</h1>
<p>One cron entry is <strong>required</strong>. PM2 workers are optional but recommended for high volume.</p>

<h2>1. Required — cron (every minute)</h2>
<pre class="code">* * * * * <?= $e($cronPaths['php']) ?> <?= $e($cronPaths['scheduler']) ?> >> /dev/null 2>&1<button class="copy-btn" type="button">Copy</button></pre>
<p class="hint">aaPanel: Cron → Add Task → Shell Script → period "N minutes: 1" → paste the command (without the <code>* * * * *</code> part).</p>

<h2>2. Optional — PM2 workers (recommended for &gt; 5k messages/day)</h2>
<pre class="code">pm2 start <?= $e($cronPaths['worker']) ?> --name kwc-worker --interpreter <?= $e($cronPaths['php']) ?> -- --queue=default,messages,webhook,ai,media,mail,reports
pm2 start <?= $e($cronPaths['campaign']) ?> --name kwc-campaign --interpreter <?= $e($cronPaths['php']) ?>
pm2 save<button class="copy-btn" type="button">Copy</button></pre>

<h2>3. Optional — watchdog (every 5 minutes)</h2>
<pre class="code">*/5 * * * * <?= $e($cronPaths['php']) ?> <?= $e($cronPaths['watchdog']) ?> >> /dev/null 2>&1<button class="copy-btn" type="button">Copy</button></pre>

<div class="field" style="margin-top:1rem">
    <label>Worker mode</label>
    <label style="font-weight:400"><input type="radio" name="worker_mode" value="cron" checked> <strong>Cron-only mode</strong> — no PM2 needed; the scheduler drains the queue every minute (fine for most sites, works on shared hosting)</label><br>
    <label style="font-weight:400"><input type="radio" name="worker_mode" value="pm2"> <strong>PM2 mode</strong> — long-running workers for maximum throughput</label>
</div>

<div id="cron-result" class="alert" style="display:none"></div>

<div class="actions">
    <a class="btn outline" href="./?step=settings">← Back</a>
    <span>
        <button class="btn outline" id="cron-test-btn" type="button">Test Cron</button>
        <a class="btn" href="./?step=finish">Continue → </a>
    </span>
</div>
