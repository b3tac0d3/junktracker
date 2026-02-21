<?php
    $attachments = is_array($attachments ?? null) ? $attachments : [];
    $attachmentPanelTitle = trim((string) ($attachmentPanelTitle ?? 'Attachments'));
    $attachmentLinkType = strtolower(trim((string) ($attachmentLinkType ?? '')));
    $attachmentLinkId = isset($attachmentLinkId) ? (int) $attachmentLinkId : 0;
    $attachmentReturnTo = trim((string) ($attachmentReturnTo ?? '/'));

    $moduleKey = match ($attachmentLinkType) {
        'job' => 'jobs',
        'client' => 'clients',
        'prospect' => 'prospects',
        'sale' => 'sales',
        default => 'dashboard',
    };

    $canEditAttachments = can_access($moduleKey, 'edit');
    $canDeleteAttachments = can_access($moduleKey, 'delete') || can_access($moduleKey, 'edit');
    $canViewAttachments = can_access($moduleKey, 'view');
    $tags = \App\Models\Attachment::TAGS;
?>
<div class="card mb-4 attachments-panel-card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 mobile-two-col-buttons">
        <div>
            <i class="fas fa-paperclip me-1"></i>
            <?= e($attachmentPanelTitle) ?>
        </div>
    </div>
    <div class="card-body">
        <?php if ($canEditAttachments && $attachmentLinkType !== '' && $attachmentLinkId > 0): ?>
            <form method="post" action="<?= url('/attachments/upload') ?>" enctype="multipart/form-data" class="row g-2 mb-3 align-items-end attachments-upload-form">
                <?= csrf_field() ?>
                <input type="hidden" name="link_type" value="<?= e($attachmentLinkType) ?>" />
                <input type="hidden" name="link_id" value="<?= e((string) $attachmentLinkId) ?>" />
                <input type="hidden" name="return_to" value="<?= e($attachmentReturnTo) ?>" />

                <div class="col-12 col-lg-5">
                    <label class="form-label">File</label>
                    <input class="form-control" type="file" name="attachment_file" required />
                </div>
                <div class="col-12 col-sm-4 col-lg-2">
                    <label class="form-label">Tag</label>
                    <select class="form-select" name="tag">
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?= e($tag) ?>"><?= e(\App\Models\Attachment::tagLabel((string) $tag)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-8 col-lg-3">
                    <label class="form-label">Note</label>
                    <input class="form-control" type="text" name="note" maxlength="255" placeholder="Optional note" />
                </div>
                <div class="col-12 col-lg-2 d-grid">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-upload me-1"></i>
                        Upload
                    </button>
                </div>
            </form>
        <?php elseif (!$canEditAttachments): ?>
            <div class="alert alert-light border small mb-3">You have read-only access to attachments.</div>
        <?php endif; ?>

        <div class="table-responsive attachments-table-wrap">
            <table class="table table-striped table-hover align-middle mb-0 attachments-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Tag</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                        <th>By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attachments)): ?>
                        <tr>
                            <td colspan="6" class="text-muted">No attachments uploaded.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attachments as $file): ?>
                            <?php
                                $attachmentId = (int) ($file['id'] ?? 0);
                                $sizeBytes = (int) ($file['file_size'] ?? 0);
                                $sizeLabel = $sizeBytes > 0 ? round($sizeBytes / 1024, 1) . ' KB' : '—';
                            ?>
                            <tr>
                                <td class="attachments-file-cell">
                                    <?php $fileName = (string) (($file['original_name'] ?? '') !== '' ? $file['original_name'] : 'Attachment'); ?>
                                    <div class="fw-semibold attachments-file-name" title="<?= e($fileName) ?>"><?= e($fileName) ?></div>
                                    <?php if (!empty($file['note'])): ?>
                                        <div class="small text-muted"><?= e((string) $file['note']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(\App\Models\Attachment::tagLabel((string) ($file['tag'] ?? 'other'))) ?></td>
                                <td><?= e($sizeLabel) ?></td>
                                <td><?= e(format_datetime($file['created_at'] ?? null)) ?></td>
                                <td><?= e((string) (($file['created_by_name'] ?? '') !== '' ? $file['created_by_name'] : '—')) ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <?php if ($canViewAttachments): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('/attachments/' . $attachmentId . '/download') ?>" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($canDeleteAttachments): ?>
                                            <form method="post" action="<?= url('/attachments/' . $attachmentId . '/delete') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= e($attachmentReturnTo) ?>" />
                                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
