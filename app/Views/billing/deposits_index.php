<?php
$deposits = is_array($deposits ?? null) ? $deposits : [];
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Bank deposits</h1>
        <p class="muted">Match bank deposits to recorded payments</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url('/billing/deposits/create')) ?>">Add deposit</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('/billing')) ?>">Back to Billing</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($deposits === []): ?>
                        <tr><td colspan="4" class="text-muted p-3">No deposits yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($deposits as $d): ?>
                            <?php if (!is_array($d)) { continue; } ?>
                            <?php $did = (int) ($d['id'] ?? 0); ?>
                            <tr>
                                <td><a href="<?= e(url('/billing/deposits/' . (string) $did)) ?>"><?= e((string) $did) ?></a></td>
                                <td><?= e((string) ($d['deposit_date'] ?? '')) ?></td>
                                <td class="text-end">$<?= e(number_format((float) ($d['amount'] ?? 0), 2)) ?></td>
                                <td><?= e(trim((string) ($d['note'] ?? '')) ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
