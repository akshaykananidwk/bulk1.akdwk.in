<?php
/**
 * Pagination partial. Expects: $result (from paginate()) and $baseUrl.
 */
$page = (int) ($result['page'] ?? 1);
$lastPage = (int) ($result['last_page'] ?? 1);
$baseUrl = $baseUrl ?? '?';
$separator = str_contains($baseUrl, '?') ? '&' : '?';
if ($lastPage <= 1) {
    return;
}
$window = 2;
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a href="<?= e($baseUrl . $separator . 'page=' . ($page - 1)) ?>">‹</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $lastPage; $i++): ?>
        <?php if ($i === 1 || $i === $lastPage || abs($i - $page) <= $window): ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= e((string) $i) ?></span>
            <?php else: ?>
                <a href="<?= e($baseUrl . $separator . 'page=' . $i) ?>"><?= e((string) $i) ?></a>
            <?php endif; ?>
        <?php elseif ($i === 2 || $i === $lastPage - 1): ?>
            <span>…</span>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $lastPage): ?>
        <a href="<?= e($baseUrl . $separator . 'page=' . ($page + 1)) ?>">›</a>
    <?php endif; ?>
</nav>
