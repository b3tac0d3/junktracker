<?php
$url = trim((string) ($url ?? ''));
$caption = trim((string) ($caption ?? ''));
$alt = trim((string) ($alt ?? 'Screenshot attachment'));
if ($url === '') {
    return;
}
?>
<button
    type="button"
    class="jt-screenshot-thumb-btn"
    data-jt-lightbox-src="<?= e($url) ?>"
    data-jt-lightbox-caption="<?= e($caption) ?>"
    data-jt-lightbox-alt="<?= e($alt) ?>"
    aria-label="View screenshot full size"
>
    <img src="<?= e($url) ?>" alt="<?= e($alt) ?>" class="jt-screenshot-thumb" loading="lazy" />
</button>
