<?php
/**
 * Generic legal page renderer.
 * $page = ['title', 'updated', 'intro', 'sections' => [[heading, paragraphs[], bullets[]?, outro?], ...]]
 * Everything escaped.
 */
use App\Core\View;
?>
<?php View::start('content'); ?>
<div class="legal-page">
    <h1><?= e($page['title']) ?></h1>
    <div class="updated"><?= e($page['updated']) ?></div>

    <p><?= e($page['intro']) ?></p>

    <?php foreach ($page['sections'] as $section): ?>
        <?php
        $heading = (string) ($section[0] ?? '');
        $paragraphs = (array) ($section[1] ?? []);
        $bullets = (array) ($section[2] ?? []);
        $outro = isset($section[3]) && is_string($section[3]) ? $section[3] : null;
        ?>
        <h2><?= e($heading) ?></h2>
        <?php foreach ($paragraphs as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
        <?php endforeach; ?>
        <?php if ($bullets): ?>
            <ul>
                <?php foreach ($bullets as $bullet): ?>
                    <li><?= e($bullet) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($outro !== null): ?>
            <p><?= e($outro) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php View::end(); ?>
