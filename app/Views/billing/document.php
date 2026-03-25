<?php
declare(strict_types=1);
$invoice = is_array($invoice ?? null) ? $invoice : [];
$items = is_array($items ?? null) ? $items : [];
$payments = is_array($payments ?? null) ? $payments : [];
$business = is_array($business ?? null) ? $business : [];
$hidePaymentsDetail = (bool) ($hidePaymentsDetail ?? false);

$recordId = (int) ($invoice['id'] ?? 0);
$docType = strtolower(trim((string) ($invoice['type'] ?? 'invoice')));
if (!in_array($docType, ['estimate', 'invoice'], true)) {
    $docType = 'invoice';
}
$docTitle = $docType === 'estimate' ? 'Estimate' : 'Invoice';
$docKindLabel = strtoupper($docTitle);
$dateLabel = $docType === 'estimate' ? 'Estimate date' : 'Invoice date';
$dueLabel = $docType === 'estimate' ? 'Expires' : 'Due date';

$docNumber = trim((string) ($invoice['invoice_number'] ?? ''));
if ($docNumber === '') {
    $docNumber = (string) $recordId;
}
$docHeaderNumber = $docNumber . ' · #' . (string) $recordId;

$clientName = trim((string) ($invoice['client_name'] ?? '')) ?: '—';
$jobTitle = trim((string) ($invoice['job_title'] ?? '')) ?: '—';

$addressParts = [];
$line1 = trim((string) ($invoice['job_address_line1'] ?? ''));
$line2 = trim((string) ($invoice['job_address_line2'] ?? ''));
$city = trim((string) ($invoice['job_city'] ?? ''));
$state = trim((string) ($invoice['job_state'] ?? ''));
$zip = trim((string) ($invoice['job_postal_code'] ?? ''));
if ($line1 !== '') {
    $addressParts[] = $line1;
}
if ($line2 !== '') {
    $addressParts[] = $line2;
}
$cityStateZip = trim(implode(', ', array_filter([$city, $state], static fn ($value): bool => $value !== '')));
if ($zip !== '') {
    $cityStateZip = trim($cityStateZip . ' ' . $zip);
}
if ($cityStateZip !== '') {
    $addressParts[] = $cityStateZip;
}

$businessName = trim((string) ($business['name'] ?? '')) ?: '—';
$businessLegal = trim((string) ($business['legal_name'] ?? ''));
$businessDisplayName = $businessLegal !== '' ? $businessLegal : $businessName;
$businessPhone = trim((string) ($business['phone'] ?? ''));
$businessContact = trim((string) ($business['primary_contact_name'] ?? ''));
$businessWebsite = trim((string) ($business['website_url'] ?? ''));
$businessEin = trim((string) ($business['ein_number'] ?? ''));

$businessLogoUrl = business_logo_url($business);

$businessAddress = [];
foreach ([
    trim((string) ($business['address_line1'] ?? '')),
    trim((string) ($business['address_line2'] ?? '')),
] as $line) {
    if ($line !== '') {
        $businessAddress[] = $line;
    }
}
$businessCityStateZip = trim(implode(', ', array_filter([
    trim((string) ($business['city'] ?? '')),
    trim((string) ($business['state'] ?? '')),
], static fn ($value): bool => $value !== '')));
$businessPostal = trim((string) ($business['postal_code'] ?? ''));
if ($businessPostal !== '') {
    $businessCityStateZip = trim($businessCityStateZip . ' ' . $businessPostal);
}
if ($businessCityStateZip !== '') {
    $businessAddress[] = $businessCityStateZip;
}

$totalPayments = 0.0;
foreach ($payments as $payment) {
    if (!is_array($payment)) {
        continue;
    }
    $totalPayments += (float) ($payment['amount'] ?? 0);
}
$totalPayments = round($totalPayments, 2);
$invoiceTotal = (float) ($invoice['total'] ?? 0);
$tipAmount = round(max(0.0, $totalPayments - $invoiceTotal), 2);
$balanceDue = round(max(0.0, $invoiceTotal - $totalPayments), 2);

$formatDate = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '—';
    }
    $stamp = strtotime($raw);
    if ($stamp === false) {
        return '—';
    }
    return date('M j, Y', $stamp);
};
?>

<div class="jt-doc-wrap">
    <?php if (!empty($portalToken)): ?>
        <div class="jt-no-print mb-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/portal/' . rawurlencode((string) $portalToken) . '?download=1')) ?>">Download HTML</a>
        </div>
    <?php endif; ?>

    <header class="jt-doc-header">
        <div class="jt-doc-header__left">
            <div class="jt-doc-header__brand">
                <?php if ($businessLogoUrl !== null): ?>
                    <img src="<?= e($businessLogoUrl) ?>" alt="" class="jt-doc-logo" />
                <?php endif; ?>
                <div class="min-w-0">
                    <p class="jt-doc-company-name"><?= e($businessDisplayName) ?></p>
                    <div class="jt-doc-company-meta">
                        <?php foreach ($businessAddress as $line): ?>
                            <p><?= e($line) ?></p>
                        <?php endforeach; ?>
                        <?php if ($businessPhone !== ''): ?><p><?= e($businessPhone) ?></p><?php endif; ?>
                        <?php if ($businessContact !== ''): ?><p><?= e($businessContact) ?></p><?php endif; ?>
                        <?php if ($businessWebsite !== ''): ?><p><?= e($businessWebsite) ?></p><?php endif; ?>
                        <?php if ($businessEin !== ''): ?><p>EIN <?= e($businessEin) ?></p><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="jt-doc-header__right">
            <div class="jt-doc-kind"><?= e($docKindLabel) ?></div>
            <div class="jt-doc-number"><?= e($docHeaderNumber) ?></div>
            <dl class="jt-doc-meta-grid">
                <div class="jt-doc-meta-row">
                    <dt><?= e($dateLabel) ?></dt>
                    <dd><?= e($formatDate((string) ($invoice['issue_date'] ?? ''))) ?></dd>
                </div>
                <div class="jt-doc-meta-row">
                    <dt><?= e($dueLabel) ?></dt>
                    <dd><?= e($formatDate((string) ($invoice['due_date'] ?? ''))) ?></dd>
                </div>
                <div class="jt-doc-meta-row">
                    <dt>Status</dt>
                    <dd><?= e($invoice['status'] ?? '—') ?></dd>
                </div>
            </dl>
        </div>
    </header>

    <section class="jt-doc-parties" aria-label="Client and job">
        <div class="jt-doc-party">
            <h3>Bill to</h3>
            <p class="jt-doc-party-title"><?= e($clientName) ?></p>
        </div>
        <div class="jt-doc-party">
            <h3>Job &amp; service location</h3>
            <p class="jt-doc-party-title"><?= e($jobTitle) ?></p>
            <div class="jt-doc-party-lines">
                <?php if ($addressParts === []): ?>
                    <p>—</p>
                <?php else: ?>
                    <?php foreach ($addressParts as $line): ?>
                        <p><?= e($line) ?></p>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="jt-doc-lines" aria-label="Line items">
        <div class="jt-doc-table-wrap">
            <table class="jt-doc-table">
                <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">Note</th>
                        <th scope="col" class="text-end">Qty</th>
                        <th scope="col" class="text-end">Rate</th>
                        <th scope="col" class="text-end">Amount</th>
                        <th scope="col" class="text-center">Tax</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items === []): ?>
                        <tr>
                            <td colspan="6" class="jt-doc-empty">No line items.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $name = trim((string) ($item['description'] ?? $item['item_type'] ?? ''));
                            $note = trim((string) ($item['note'] ?? ''));
                            ?>
                            <tr>
                                <td><?= e($name !== '' ? $name : '—') ?></td>
                                <td><?= e($note !== '' ? $note : '—') ?></td>
                                <td class="text-end"><?= e(number_format((float) ($item['quantity'] ?? 0), 2)) ?></td>
                                <td class="text-end">$<?= e(number_format((float) ($item['unit_price'] ?? 0), 2)) ?></td>
                                <td class="text-end">$<?= e(number_format((float) ($item['line_total'] ?? 0), 2)) ?></td>
                                <td class="text-center"><?= ((int) ($item['taxable'] ?? 0)) === 1 ? 'Y' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="jt-doc-totals">
        <dl class="jt-doc-totals-row">
            <dt>Subtotal</dt>
            <dd>$<?= e(number_format((float) ($invoice['subtotal'] ?? 0), 2)) ?></dd>
        </dl>
        <dl class="jt-doc-totals-row">
            <dt>Tax</dt>
            <dd>
                $<?= e(number_format((float) ($invoice['tax_amount'] ?? 0), 2)) ?>
                <div class="jt-doc-totals-sub"><?= e(number_format((float) ($invoice['tax_rate'] ?? 0), 2)) ?>% rate</div>
            </dd>
        </dl>
        <dl class="jt-doc-totals-row jt-doc-totals-row--grand">
            <dt>Total</dt>
            <dd>$<?= e(number_format((float) ($invoice['total'] ?? 0), 2)) ?></dd>
        </dl>
        <?php if ($docType === 'invoice'): ?>
            <dl class="jt-doc-totals-row">
                <dt>Payments</dt>
                <dd>$<?= e(number_format($totalPayments, 2)) ?></dd>
            </dl>
            <?php if ($tipAmount > 0.0): ?>
                <dl class="jt-doc-totals-row">
                    <dt>Tip (over invoice)</dt>
                    <dd>$<?= e(number_format($tipAmount, 2)) ?></dd>
                </dl>
            <?php endif; ?>
            <dl class="jt-doc-totals-row jt-doc-totals-row--grand">
                <dt>Balance due</dt>
                <dd>$<?= e(number_format($balanceDue, 2)) ?></dd>
            </dl>
        <?php endif; ?>
    </div>

    <div class="jt-doc-notes">
        <h3>Notes</h3>
        <div class="jt-doc-notes-body"><?= e(trim((string) ($invoice['customer_note'] ?? '')) !== '' ? (string) ($invoice['customer_note'] ?? '') : '—') ?></div>
    </div>

    <?php if ($docType === 'invoice' && !$hidePaymentsDetail): ?>
        <div class="jt-doc-payments">
            <h3>Payments</h3>
            <?php if ($payments === []): ?>
                <p class="jt-doc-party-lines mb-0">No payments recorded.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($payments as $payment): ?>
                        <?php
                        $paymentId = (int) ($payment['id'] ?? 0);
                        if ($paymentId <= 0) {
                            continue;
                        }
                        $method = strtolower(trim((string) ($payment['method'] ?? 'other')));
                        $methodLabel = match ($method) {
                            'check' => 'Check',
                            'cc' => 'Card',
                            'cash' => 'Cash',
                            'venmo' => 'Venmo',
                            'cashapp' => 'Cash App',
                            default => 'Other',
                        };
                        $reference = trim((string) ($payment['reference_number'] ?? ''));
                        $paymentDate = trim((string) ($payment['paid_at'] ?? ''));
                        $paymentDateLabel = '—';
                        if ($paymentDate !== '') {
                            $stamp = strtotime($paymentDate);
                            if ($stamp !== false) {
                                $paymentDateLabel = date('M j, Y', $stamp);
                            }
                        }
                        ?>
                        <li>
                            <strong>#<?= e((string) $paymentId) ?></strong>
                            · <?= e($paymentDateLabel) ?>
                            · <?= e($methodLabel) ?>
                            <?php if ($reference !== ''): ?> · Ref <?= e($reference) ?><?php endif; ?>
                            · $<?= e(number_format((float) ($payment['amount'] ?? 0), 2)) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($portalApprove) && !empty($portalToken) && isset($portalCsrf)): ?>
    <div class="jt-doc-wrap jt-no-print mt-3">
        <form method="post" action="<?= e(url('/portal/' . rawurlencode((string) $portalToken) . '/approve')) ?>" class="d-flex flex-wrap gap-2 align-items-center">
            <input type="hidden" name="portal_csrf" value="<?= e((string) $portalCsrf) ?>">
            <button type="submit" class="btn btn-success">Approve this estimate</button>
            <span class="small text-muted">By approving, you confirm acceptance of this estimate.</span>
        </form>
    </div>
<?php endif; ?>
