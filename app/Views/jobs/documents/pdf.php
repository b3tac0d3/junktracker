<?php
    $jobId = (int) ($job['id'] ?? 0);
    $typeLabel = \App\Models\JobDocument::typeLabel((string) ($document['document_type'] ?? 'document'));
    $statusLabel = \App\Models\JobDocument::statusLabel((string) ($document['status'] ?? 'draft'));

    $jobName = trim((string) ($job['name'] ?? ''));
    if ($jobName === '') {
        $jobName = 'Job #' . $jobId;
    }

    $clientName = trim((string) ($document['client_name'] ?? ''));
    if ($clientName === '') {
        $clientName = 'Client';
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
?>
<div class="sheet-header">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px; flex-wrap:wrap;">
        <div>
            <div style="font-size:24px; font-weight:700; margin-bottom:4px;">JunkTracker</div>
            <div style="opacity:.9;">Professional Operations Platform</div>
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
                        <div><?= e($line) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <div style="font-size:12px; text-transform:uppercase; color:#64748b; letter-spacing:.06em; margin-bottom:6px;">Client</div>
            <div style="font-weight:700; font-size:18px;"><?= e($clientName) ?></div>
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
                <td style="padding:10px; border:1px solid #dbe4f0; color:#64748b;">Amount</td>
                <td style="padding:10px; border:1px solid #dbe4f0; font-weight:700;">
                    <?= isset($document['amount']) && $document['amount'] !== null ? e('$' . number_format((float) $document['amount'], 2)) : '—' ?>
                </td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #dbe4f0; color:#64748b;">Issued</td>
                <td style="padding:10px; border:1px solid #dbe4f0;"><?= e(format_datetime($document['issued_at'] ?? null)) ?></td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #dbe4f0; color:#64748b;">Due</td>
                <td style="padding:10px; border:1px solid #dbe4f0;"><?= e(format_datetime($document['due_at'] ?? null)) ?></td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #dbe4f0; color:#64748b;">Sent</td>
                <td style="padding:10px; border:1px solid #dbe4f0;"><?= e(format_datetime($document['sent_at'] ?? null)) ?></td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #dbe4f0; color:#64748b;">Approved</td>
                <td style="padding:10px; border:1px solid #dbe4f0;"><?= e(format_datetime($document['approved_at'] ?? null)) ?></td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #dbe4f0; color:#64748b;">Paid</td>
                <td style="padding:10px; border:1px solid #dbe4f0;"><?= e(format_datetime($document['paid_at'] ?? null)) ?></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-bottom:20px;">
        <div style="font-size:12px; text-transform:uppercase; color:#64748b; letter-spacing:.06em; margin-bottom:6px;">Notes</div>
        <div style="border:1px solid #dbe4f0; border-radius:8px; padding:14px; min-height:100px; white-space:pre-wrap;">
            <?= e((string) (($document['note'] ?? '') !== '' ? $document['note'] : '—')) ?>
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; margin-top:26px; color:#64748b;">
        <div>Generated on <?= e(format_datetime(date('Y-m-d H:i:s'))) ?></div>
        <div>Created by <?= e((string) (($document['created_by_name'] ?? '') !== '' ? $document['created_by_name'] : 'System')) ?></div>
    </div>
</div>
