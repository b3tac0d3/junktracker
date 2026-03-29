<?php
$types = is_array($types ?? null) ? $types : [];
$defaults = is_array($defaults ?? null) ? $defaults : [];

$labelForType = static function (string $t): string {
    $t = trim($t);
    if ($t === '') {
        return '—';
    }
    return ucwords(str_replace('_', ' ', $t));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
    <div>
        <h1>Sales default fees</h1>
        <p class="muted mb-0">Set a default fee per sale type (percentage or fixed dollar amount). Sales that use &ldquo;Default for sale type&rdquo; always use the current value here—changing eBay from 15% to 17% updates all those sales automatically.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/admin')) ?>">Back to Business Admin</a>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-percent me-2"></i>Default fees by sale type</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('/admin/sale-fee-defaults/update')) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Sale type</th>
                                <th>Fee</th>
                                <th>Amount / %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($types as $type): ?>
                                <?php
                                $t = (string) $type;
                                $row = $defaults[$t] ?? null;
                                $kind = is_array($row) ? (string) ($row['fee_kind'] ?? 'none') : 'none';
                                if ($kind !== 'percent' && $kind !== 'amount') {
                                    $kind = 'none';
                                }
                                $val = is_array($row) ? (float) ($row['fee_value'] ?? 0) : 0.0;
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($labelForType($t)) ?></td>
                                    <td style="min-width: 11rem;">
                                        <select name="fee_kind[<?= e($t) ?>]" class="form-select form-select-sm jt-sale-fee-kind">
                                            <option value="none" <?= $kind === 'none' ? 'selected' : '' ?>>No default fee</option>
                                            <option value="percent" <?= $kind === 'percent' ? 'selected' : '' ?>>Percentage (%)</option>
                                            <option value="amount" <?= $kind === 'amount' ? 'selected' : '' ?>>Fixed amount ($)</option>
                                        </select>
                                    </td>
                                    <td style="max-width: 10rem;">
                                        <input
                                            type="number"
                                            name="fee_value[<?= e($t) ?>]"
                                            class="form-control form-control-sm jt-sale-fee-value"
                                            step="0.01"
                                            min="0"
                                            <?= $kind === 'percent' ? 'max="100"' : '' ?>
                                            value="<?= $kind !== 'none' ? e((string) $val) : '' ?>"
                                            placeholder="—"
                                        />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save defaults</button>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.jt-sale-fee-kind').forEach((sel) => {
        const sync = () => {
            const row = sel.closest('tr');
            if (!row) return;
            const input = row.querySelector('.jt-sale-fee-value');
            if (!input) return;
            const v = sel.value;
            input.disabled = v === 'none';
            if (v === 'percent') {
                input.setAttribute('max', '100');
            } else {
                input.removeAttribute('max');
            }
            if (v === 'none') {
                input.value = '';
            }
        };
        sel.addEventListener('change', sync);
        sync();
    });
});
</script>
