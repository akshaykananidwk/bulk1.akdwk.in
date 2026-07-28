<?php
/**
 * Landing page renderer. Sections: hero, features, cta, text, image, faq.
 * Section content is tenant-authored — every value escaped on output.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page['seo_title'] ?? $page['title']) ?></title>
    <?php if (!empty($page['seo_description'])): ?>
        <meta name="description" content="<?= e($page['seo_description']) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <style>
        .lp-section { max-width: 960px; margin: 0 auto; padding: 3rem 1.25rem; }
        .lp-hero { text-align: center; padding: 5rem 1.25rem 3rem; }
        .lp-hero h1 { font-size: 2.2rem; }
        .lp-features { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; }
    </style>
</head>
<body>
<?php foreach ($sections as $section): ?>
    <?php $type = (string) ($section['type'] ?? 'text'); ?>
    <?php if ($type === 'hero'): ?>
        <section class="lp-hero">
            <h1><?= e($section['heading'] ?? '') ?></h1>
            <p class="text-muted" style="font-size:1.05rem"><?= e($section['subheading'] ?? '') ?></p>
            <?php if (!empty($section['button_text']) && !empty($section['button_url'])): ?>
                <a class="btn btn-primary btn-lg" href="<?= e($section['button_url']) ?>" rel="noopener"><?= e($section['button_text']) ?></a>
            <?php endif; ?>
        </section>
    <?php elseif ($type === 'features'): ?>
        <section class="lp-section">
            <?php if (!empty($section['heading'])): ?><h2 class="text-center mb-4"><?= e($section['heading']) ?></h2><?php endif; ?>
            <div class="lp-features">
                <?php foreach ((array) ($section['items'] ?? []) as $item): ?>
                    <div class="card">
                        <div style="font-size:1.6rem"><?= e($item['icon'] ?? '✨') ?></div>
                        <h3><?= e($item['title'] ?? '') ?></h3>
                        <p class="text-muted text-sm"><?= e($item['text'] ?? '') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php elseif ($type === 'cta'): ?>
        <section class="lp-section text-center">
            <div class="card" style="background:var(--primary);color:#fff">
                <h2><?= e($section['heading'] ?? '') ?></h2>
                <?php if (!empty($section['button_text']) && !empty($section['button_url'])): ?>
                    <a class="btn btn-accent btn-lg" href="<?= e($section['button_url']) ?>" rel="noopener"><?= e($section['button_text']) ?></a>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($type === 'image' && !empty($section['url'])): ?>
        <section class="lp-section text-center">
            <img src="<?= e($section['url']) ?>" alt="<?= e($section['alt'] ?? '') ?>" style="border-radius:14px;max-width:100%">
        </section>
    <?php elseif ($type === 'faq'): ?>
        <section class="lp-section">
            <?php if (!empty($section['heading'])): ?><h2 class="text-center mb-4"><?= e($section['heading']) ?></h2><?php endif; ?>
            <?php foreach ((array) ($section['items'] ?? []) as $item): ?>
                <details class="card mb-2">
                    <summary class="font-semi"><?= e($item['q'] ?? '') ?></summary>
                    <p class="text-muted mt-2"><?= e($item['a'] ?? '') ?></p>
                </details>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <section class="lp-section">
            <?php if (!empty($section['heading'])): ?><h2><?= e($section['heading']) ?></h2><?php endif; ?>
            <p><?= nl2br(e($section['text'] ?? '')) ?></p>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

<?php if (empty($sections)): ?>
    <section class="lp-hero"><h1><?= e($page['title']) ?></h1></section>
<?php endif; ?>

<footer class="text-center text-muted text-sm" style="padding:2rem">
    <?= e($page['title']) ?>
</footer>
</body>
</html>
