<?php
$matchTypes = is_array($matchTypes ?? null) ? $matchTypes : [];
$matchTypes = array_values(array_filter(array_map(
    static fn (mixed $type): string => trim((string) $type),
    $matchTypes
), static fn (string $type): bool => $type !== ''));

if ($matchTypes === []) {
    return;
}
?>
<div class="record-subline small muted">Matched: <?= e(implode(' · ', $matchTypes)) ?></div>
