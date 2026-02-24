<?php
$jobId = (int) ($job['id'] ?? 0);
$lineItems = is_array($lineItems ?? null) ? $lineItems : [];
$typeLabel = \App\Models\JobDocument::typeLabel((string) ($document['document_type'] ?? 'document'));
$statusLabel = \App\Models\JobDocument::statusLabel((string) ($document['status'] ?? 'draft'));
$businessLogoDataUri = trim((string) ($businessLogoDataUri ?? ''));

$jobName = trim((string) ($job['name'] ?? ''));
if ($jobName === '') {
    $jobName = 'Job #' . $jobId;
}

$clientName = trim((string) ($document['client_name'] ?? ''));
$estateName = trim((string) ($document['estate_name'] ?? ''));
if ($clientName === '' && $estateName === '') {
    $clientDisplay = 'Client';
} elseif ($clientName !== '' && $estateName !== '') {
    $clientDisplay = $clientName . ' / ' . $estateName;
} else {
    $clientDisplay = $clientName !== '' ? $clientName : $estateName;
}

$addressLines = [];
foreach (['job_address_1', 'job_address_2'] as $column) {
    $line = trim((string) ($document[$column] ?? ''));
    if ($line !== '') {
        $addressLines[] = $line;
    }
}
$cityStateZip = trim(
    (string) ($document['job_city'] ?? '')
    . ((string) ($document['job_city'] ?? '') !== '' && (string) ($document['job_state'] ?? '') !== '' ? ', ' : '')
    . (string) ($document['job_state'] ?? '')
    . ((string) ($document['job_zip'] ?? '') !== '' ? ' ' . (string) $document['job_zip'] : '')
);
if ($cityStateZip !== '') {
    $addressLines[] = $cityStateZip;
}

$businessAddress = [];
foreach (['business_address_line1', 'business_address_line2'] as $column) {
    $line = trim((string) ($document[$column] ?? ''));
    if ($line !== '') {
        $businessAddress[] = $line;
    }
}
$businessCityState = trim(
    (string) ($document['business_city'] ?? '')
    . ((string) ($document['business_city'] ?? '') !== '' && (string) ($document['business_state'] ?? '') !== '' ? ', ' : '')
    . (string) ($document['business_state'] ?? '')
    . ((string) ($document['business_postal_code'] ?? '') !== '' ? ' ' . (string) $document['business_postal_code'] : '')
);
if ($businessCityState !== '') {
    $businessAddress[] = $businessCityState;
}
if (!empty($document['business_country'])) {
    $businessAddress[] = (string) $document['business_country'];
}

$subtotal = isset($document['subtotal_amount']) && $document['subtotal_amount'] !== null
    ? (float) $document['subtotal_amount']
    : 0.0;
$taxRate = (float) ($document['tax_rate'] ?? 0);
$taxAmount = isset($document['tax_amount']) && $document['tax_amount'] !== null
    ? (float) $document['tax_amount']
    : 0.0;
$grossTotal = isset($document['amount']) && $document['amount'] !== null
    ? (float) $document['amount']
    : 0.0;
?>
<div class="sheet-header">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px; flex-wrap:wrap;">
        <div style="display:flex; gap:14px; align-items:flex-start;">
            <?php if ($businessLogoDataUri !== ''): ?>
                <img src="<?= e($businessLogoDataUri) ?>" alt="Business logo" style="max-height:64px; max-width:160px; border-radius:8px; background:#fff; padding:4px;" />
            <?php endif; ?>
            <div>
                <div style="font-size:24px; font-weight:700; margin-bottom:4px;"><?= e((string) (($document['business_name'] ?? '') !== '' ? $document['business_name'] : 'JunkTracker')) ?></div>
                <?php if (!empty($document['business_legal_name'])): ?>
                    <div style="opacity:.92;"><?= e((string) $document['business_legal_name']) ?></div>
                <?php endif; ?>
                <?php foreach ($businessAddress as $line): ?>
                    <div style="opacity:.92;"><?= e((string) $line) ?></div>
                <?php endforeach; ?>
                <?php if (!empty($document['business_phone'])): ?>
                    <div style="opacity:.92;"><?= e(format_phone((string) $document['business_phone'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($document['business_email'])): ?>
                    <div style="opacity:.92;"><?= e((string) $document['business_email']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:22px; font-weight:700;"><?= e($typeLabel) ?></div>
            <div>Document #<?= e((string) ($document['id'] ?? '')) ?></div>
            <div>Status: <?= e($statusLabel) ?></div>
        </div>
    </div>
</div>

<div class="sheet-content">
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:20px; margin-bottom:24px;">
        <div>
            <div style="font-size:12px; text-transform:uppercase; color:#64748b; letter-spacing:.06em; margin-bottom:6px;">Job</div>
            <div style="font-weight:700; font-size:18px;"><?= e($jobName) ?></div>
            <div style="color:#334155;">#<?= e((string) $jobId) ?></div>
            <?php if (!empty($addressLines)): ?>
                <div style="margin-top:10px; color:#334155; line-height:1.4;">
                    <?php foreach ($addressLines as $line): ?>
                        <div><?= e((string) $line) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <div style="font-size:12px; text-transform:uppercase; color:#64748b; letter-spacing:.06em; margin-bottom:6px;">Customer</div>
            <div style="font-weight:700; font-size:18px;"><?= e($clientDisplay) ?></div>
            <?php if (!empty($document['client_email'])): ?>
                <div style="color:#334155;"><?= e((string) $document['client_email']) ?></div>
            <?php endif; ?>
            <?php if (!empty($document['client_phone'])): ?>
                <div style="color:#334155;"><?= e(format_phone((string) $document['client_phone'])) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <table style="width:100%; border-collapse:collapse; margin-bottom:24px;">
        <tbody>
            <tr>
                <td style="padding:10px; border:1px solid #dbe4f0; width:28%; color:#64748b;">Title</td>
                <td style="padding:10px; border:1px solid #dbe4f0;"><?= e((string) ($document['title'] ?? '—')) ?></td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #dbe4f0; color:#64748b;">Issued</td>
                <td style="padding:10px; border:1px solid #dbe4f0;"><?= e(format_datetime($document['issued_at'] ?? null)) ?></td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #dbe4f0; color:#64748b;">Due</td>
                <td style="padding:10px; border:1px solid #dbe4f0;"><?= e(format_datetime($document['due_at'] ?? null)) ?></td>
            </tr>
        </tbody>
    </table>

    <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
        <thead>
            <tr>
                <th style="padding:10px; border:1px solid #dbe4f0; text-align:left; background:#f8fafc;">Type</th>
                <th style="padding:10px; border:1px solid #dbe4f0; text-align:left; background:#f8fafc;">Description</th>
                <th style="padding:10px; border:1px solid #dbe4f0; text-align:center; background:#f8fafc;">Taxable</th>
                <th style="padding:10px; border:1px solid #dbe4f0; text-align:right; background:#f8fafc;">Qty</th>
                <th style="padding:10px; border:1px solid #dbe4f0; text-align:right; background:#f8fafc;">Unit</th>
                <th style="padding:10px; border:1px solid #dbe4f0; text-align:right; background:#f8fafc;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($lineItems)): ?>
                <tr>
                    <td colspan="6" style="padding:10px; border:1px solid #dbe4f0; color:#64748b;">No line items available.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($lineItems as $lineItem): ?>
                    <tr>
                        <td style="padding:10px; border:1px solid #dbe4f0;"><?= e((string) ($lineItem['item_type_label'] ?? '')) ?></td>
                        <td style="padding:10px; border:1px solid #dbe4f0;">
                            <div><?= e((string) ($lineItem['item_description'] ?? '')) ?></div>
                            <?php if (!empty($lineItem['line_note'])): ?>
                                <div style="font-size:12px; color:#64748b;"><?= e((string) $lineItem['line_note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px; border:1px solid #dbe4f0; text-align:center;"><?= (int) ($lineItem['is_taxable'] ?? 1) === 1 ? 'Yes' : 'No' ?></td>
                        <td style="padding:10px; border:1px solid #dbe4f0; text-align:right;"><?= e(number_format((float) ($lineItem['quantity'] ?? 0), 2)) ?></td>
                        <td style="padding:10px; border:1px solid #dbe4f0; text-align:right;"><?= e('$' . number_format((float) ($lineItem['unit_price'] ?? 0), 2)) ?></td>
                        <td style="padding:10px; border:1px solid #dbe4f0; text-align:right;"><?= e('$' . number_format((float) ($lineItem['line_total'] ?? 0), 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr>
                <td colspan="5" style="padding:10px; border:1px solid #dbe4f0; text-align:right; font-weight:700;">Net Subtotal</td>
                <td style="padding:10px; border:1px solid #dbe4f0; text-align:right; font-weight:700;"><?= e('$' . number_format($subtotal, 2)) ?></td>
            </tr>
            <tr>
                <td colspan="5" style="padding:10px; border:1px solid #dbe4f0; text-align:right; font-weight:700;">Tax (<?= e(number_format($taxRate, 2)) ?>%)</td>
                <td style="padding:10px; border:1px solid #dbe4f0; text-align:right; font-weight:700;"><?= e('$' . number_format($taxAmount, 2)) ?></td>
            </tr>
            <tr>
                <td colspan="5" style="padding:10px; border:1px solid #dbe4f0; text-align:right; font-weight:700;">Gross Total</td>
                <td style="padding:10px; border:1px solid #dbe4f0; text-align:right; font-weight:700;"><?= e('$' . number_format($grossTotal, 2)) ?></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-bottom:20px;">
        <div style="font-size:12px; text-transform:uppercase; color:#64748b; letter-spacing:.06em; margin-bottom:6px;">Customer Note</div>
        <div style="border:1px solid #dbe4f0; border-radius:8px; padding:14px; min-height:80px; white-space:pre-wrap;"><?= e((string) (($document['customer_note'] ?? '') !== '' ? $document['customer_note'] : '—')) ?></div>
    </div>

    <div style="margin-bottom:20px;">
        <div style="font-size:12px; text-transform:uppercase; color:#64748b; letter-spacing:.06em; margin-bottom:6px;">Internal Note</div>
        <div style="border:1px solid #dbe4f0; border-radius:8px; padding:14px; min-height:80px; white-space:pre-wrap;"><?= e((string) (($document['note'] ?? '') !== '' ? $document['note'] : '—')) ?></div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; margin-top:26px; color:#64748b;">
        <div>Generated on <?= e(format_datetime(date('Y-m-d H:i:s'))) ?></div>
        <div>Created by <?= e((string) (($document['created_by_name'] ?? '') !== '' ? $document['created_by_name'] : 'System')) ?></div>
    </div>
</div>
