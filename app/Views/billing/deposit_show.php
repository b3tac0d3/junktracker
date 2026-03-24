<?php
$deposit = is_array($deposit ?? null) ? $deposit : [];
$linkedPayments = is_array($linkedPayments ?? null) ? $linkedPayments : [];
$unassignedPayments = is_array($unassignedPayments ?? null) ? $unassignedPayments : [];
$linkedTotal = (float) ($linkedTotal ?? 0);
$depositId = (int) ($deposit['id'] ?? 0);
$depositAmount = (float) ($deposit['amount'] ?? 0);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Deposit #<?= e((string) $depositId) ?></h1>
        <p class="muted"><?= e((string) ($deposit['deposit_date'] ?? '')) ?> · $<?= e(number_format($depositAmount, 2)) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/billing/deposits')) ?>">All deposits</a>
</div>

<section class="card index-card mb-3">
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3">
            <div class="record-field">
                <span class="record-label">Linked payments total</span>
                <span class="record-value">$<?= e(number_format($linkedTotal, 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Deposit amount</span>
                <span class="record-value">$<?= e(number_format($depositAmount, 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Difference</span>
                <span class="record-value">$<?= e(number_format($depositAmount - $linkedTotal, 2)) ?></span>
            </div>
        </div>
        <?php if (trim((string) ($deposit['note'] ?? '')) !== ''): ?>
            <p class="mb-0 mt-2"><strong>Note:</strong> <?= e((string) $deposit['note']) ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header"><strong>Linked payments</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Payment</th>
                        <th class="text-end">Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($linkedPayments === []): ?>
                        <tr><td colspan="3" class="text-muted p-3">No payments linked yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($linkedPayments as $p): ?>
                            <?php if (!is_array($p)) { continue; } ?>
                            <?php $pid = (int) ($p['id'] ?? 0); ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('/billing/payments/' . (string) $pid . '?invoice_id=' . (string) ((int) ($p['invoice_id'] ?? 0)))) ?>">Payment #<?= e((string) $pid) ?></a>
                                </td>
                                <td class="text-end">$<?= e(number_format((float) ($p['amount'] ?? 0), 2)) ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= e(url('/billing/deposits/' . (string) $depositId . '/unlink-payment')) ?>" class="d-inline" onsubmit="return confirm('Unlink this payment?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="payment_id" value="<?= e((string) $pid) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Unlink</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header"><strong>Unassigned payments</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Payment</th>
                        <th class="text-end">Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($unassignedPayments === []): ?>
                        <tr><td colspan="3" class="text-muted p-3">No unassigned payments.</td></tr>
                    <?php else: ?>
                        <?php foreach ($unassignedPayments as $p): ?>
                            <?php if (!is_array($p)) { continue; } ?>
                            <?php $pid = (int) ($p['id'] ?? 0); ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('/billing/payments/' . (string) $pid . '?invoice_id=' . (string) ((int) ($p['invoice_id'] ?? 0)))) ?>">Payment #<?= e((string) $pid) ?></a>
                                </td>
                                <td class="text-end">$<?= e(number_format((float) ($p['amount'] ?? 0), 2)) ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= e(url('/billing/deposits/' . (string) $depositId . '/link-payment')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="payment_id" value="<?= e((string) $pid) ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">Link</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
