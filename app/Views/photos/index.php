<?php
    $mode = (string) ($mode ?? 'jobs');
    if (!in_array($mode, ['jobs', 'tags', 'photos'], true)) {
        $mode = 'jobs';
    }

    $search = trim((string) ($search ?? ''));
    $jobs = is_array($jobs ?? null) ? $jobs : [];
    $tagGroups = is_array($tagGroups ?? null) ? $tagGroups : [];
    $photos = is_array($photos ?? null) ? $photos : [];
    $selectedJob = is_array($selectedJob ?? null) ? $selectedJob : null;
    $selectedTag = (string) ($selectedTag ?? '');
    $selectedTagLabel = $selectedTag !== '' ? \App\Models\Attachment::tagLabel($selectedTag) : '';
    $selectedJobId = (int) ($selectedJob['id'] ?? 0);
    $selectedJobName = trim((string) ($selectedJob['name'] ?? ''));
    $currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '/photos');
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Photo Library</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/photos') ?>">Photos</a></li>
                <?php if ($selectedJobId > 0): ?>
                    <li class="breadcrumb-item">
                        <a href="<?= url('/photos?job_id=' . $selectedJobId) ?>"><?= e($selectedJobName !== '' ? $selectedJobName : ('Job #' . $selectedJobId)) ?></a>
                    </li>
                <?php endif; ?>
                <?php if ($mode === 'photos' && $selectedTagLabel !== ''): ?>
                    <li class="breadcrumb-item active"><?= e($selectedTagLabel) ?></li>
                <?php elseif ($mode === 'tags' && $selectedJobId > 0): ?>
                    <li class="breadcrumb-item active">Photo Types</li>
                <?php elseif ($mode === 'jobs'): ?>
                    <li class="breadcrumb-item active">Jobs</li>
                <?php endif; ?>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <?php if ($mode === 'photos' && $selectedJobId > 0): ?>
                <a class="btn btn-outline-secondary" href="<?= url('/photos?job_id=' . $selectedJobId) ?>">
                    <i class="fas fa-arrow-left me-1"></i>
                    Back to Types
                </a>
            <?php endif; ?>
            <?php if (($mode === 'tags' || $mode === 'photos') && $selectedJobId > 0): ?>
                <a class="btn btn-outline-secondary" href="<?= url('/photos') ?>">
                    <i class="fas fa-arrow-left me-1"></i>
                    All Jobs
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-search me-1"></i>
            <?= $mode === 'photos' ? 'Search Photos' : 'Search Jobs' ?>
        </div>
        <div class="card-body">
            <form method="get" action="<?= url('/photos') ?>">
                <?php if ($selectedJobId > 0): ?>
                    <input type="hidden" name="job_id" value="<?= e((string) $selectedJobId) ?>" />
                <?php endif; ?>
                <?php if ($mode === 'photos' && $selectedTag !== ''): ?>
                    <input type="hidden" name="tag" value="<?= e($selectedTag) ?>" />
                <?php endif; ?>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-8">
                        <label class="form-label"><?= $mode === 'photos' ? 'Photo Name / Label' : 'Job Name / ID' ?></label>
                        <input
                            class="form-control"
                            type="text"
                            name="q"
                            value="<?= e($search) ?>"
                            placeholder="<?= $mode === 'photos' ? 'Search photo file or label...' : 'Search jobs with photos...' ?>"
                        />
                    </div>
                    <div class="col-12 col-md-4 d-flex gap-2 mobile-two-col-buttons">
                        <button class="btn btn-primary" type="submit">Apply</button>
                        <?php if ($selectedJobId > 0): ?>
                            <a class="btn btn-outline-secondary" href="<?= url('/photos?job_id=' . $selectedJobId . ($mode === 'photos' && $selectedTag !== '' ? '&tag=' . urlencode($selectedTag) : '')) ?>">
                                Clear
                            </a>
                        <?php else: ?>
                            <a class="btn btn-outline-secondary" href="<?= url('/photos') ?>">Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($mode === 'jobs'): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-briefcase me-1"></i>
                Jobs With Photos
            </div>
            <div class="card-body">
                <?php if (empty($jobs)): ?>
                    <div class="text-muted">No job photo galleries found.</div>
                <?php else: ?>
                    <div class="photo-job-grid">
                        <?php foreach ($jobs as $jobRow): ?>
                            <?php
                                $jobId = (int) ($jobRow['job_id'] ?? 0);
                                if ($jobId <= 0) {
                                    continue;
                                }
                                $jobName = trim((string) ($jobRow['job_name'] ?? ''));
                                if ($jobName === '') {
                                    $jobName = 'Job #' . $jobId;
                                }
                                $coverAttachmentId = (int) ($jobRow['cover_attachment_id'] ?? 0);
                                $coverInlineUrl = $coverAttachmentId > 0 ? url('/attachments/' . $coverAttachmentId . '/download?inline=1') : '';
                                $jobGalleryUrl = url('/photos?job_id=' . $jobId);
                            ?>
                            <a class="photo-job-card" href="<?= e($jobGalleryUrl) ?>">
                                <div class="photo-job-card-media">
                                    <?php if ($coverInlineUrl !== ''): ?>
                                        <img class="photo-job-card-image" src="<?= e($coverInlineUrl) ?>" alt="<?= e($jobName) ?>" loading="lazy" />
                                    <?php else: ?>
                                        <div class="photo-job-card-empty">
                                            <i class="fas fa-image me-1"></i>
                                            No Preview
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="photo-job-card-name"><?= e($jobName) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($mode === 'tags'): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <div>
                    <i class="fas fa-layer-group me-1"></i>
                    <?= e($selectedJobName !== '' ? $selectedJobName : ('Job #' . $selectedJobId)) ?> Photo Types
                </div>
            </div>
            <div class="card-body">
                <div class="photo-job-grid">
                    <?php foreach ($tagGroups as $group): ?>
                        <?php
                            $tag = (string) ($group['tag'] ?? '');
                            $label = trim((string) ($group['label'] ?? \App\Models\Attachment::tagLabel($tag)));
                            $count = (int) ($group['photo_count'] ?? 0);
                            $coverAttachmentId = (int) ($group['cover_attachment_id'] ?? 0);
                            $coverInlineUrl = $coverAttachmentId > 0 ? url('/attachments/' . $coverAttachmentId . '/download?inline=1') : '';
                            $tagUrl = url('/photos?job_id=' . $selectedJobId . '&tag=' . urlencode($tag));
                        ?>
                        <a class="photo-job-card <?= $count < 1 ? 'photo-job-card-empty-link' : '' ?>" href="<?= $count > 0 ? e($tagUrl) : '#' ?>" <?= $count > 0 ? '' : 'onclick="return false;"' ?>>
                            <div class="photo-job-card-media">
                                <?php if ($coverInlineUrl !== ''): ?>
                                    <img class="photo-job-card-image" src="<?= e($coverInlineUrl) ?>" alt="<?= e($label) ?>" loading="lazy" />
                                <?php else: ?>
                                    <div class="photo-job-card-empty">
                                        <i class="fas fa-image me-1"></i>
                                        No Photos
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="photo-job-card-name d-flex justify-content-between align-items-center">
                                <span><?= e($label) ?></span>
                                <span class="badge bg-secondary"><?= e((string) $count) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <form method="post" action="<?= url('/photos/download') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= e($currentUri) ?>" />
            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <i class="fas fa-images me-1"></i>
                        <?= e($selectedTagLabel !== '' ? $selectedTagLabel : 'Photos') ?> • <?= e($selectedJobName !== '' ? $selectedJobName : ('Job #' . $selectedJobId)) ?>
                    </div>
                    <div class="d-flex gap-2 align-items-center mobile-two-col-buttons">
                        <div class="form-check m-0">
                            <input id="photosSelectAll" class="form-check-input" type="checkbox" />
                            <label class="form-check-label small" for="photosSelectAll">Select All</label>
                        </div>
                        <span class="small text-muted"><span id="photoSelectedCount">0</span> selected</span>
                        <button class="btn btn-sm btn-primary" type="submit">
                            <i class="fas fa-file-zipper me-1"></i>
                            Download Selected
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($photos)): ?>
                        <div class="text-muted">No photos found for this type.</div>
                    <?php else: ?>
                        <div class="photo-library-gallery-grid">
                            <?php foreach ($photos as $photo): ?>
                                <?php
                                    $photoId = (int) ($photo['id'] ?? 0);
                                    if ($photoId <= 0) {
                                        continue;
                                    }
                                    $downloadUrl = url('/attachments/' . $photoId . '/download');
                                    $inlineUrl = url('/attachments/' . $photoId . '/download?inline=1');
                                    $fileName = trim((string) ($photo['original_name'] ?? 'Photo'));
                                    $displayName = trim((string) ($photo['note'] ?? ''));
                                ?>
                                <div class="photo-library-card">
                                    <div class="photo-library-card-media">
                                        <input class="form-check-input photo-select-item photo-library-card-check" type="checkbox" name="attachment_ids[]" value="<?= e((string) $photoId) ?>" />
                                        <a
                                            class="photo-library-card-link js-job-photo-preview"
                                            href="<?= e($inlineUrl) ?>"
                                            data-full-src="<?= e($inlineUrl) ?>"
                                            data-filename="<?= e($fileName) ?>"
                                            data-meta=""
                                            title="<?= e($fileName) ?>"
                                        >
                                            <img class="photo-library-card-image" src="<?= e($inlineUrl) ?>" alt="<?= e($fileName) ?>" loading="lazy" />
                                        </a>
                                    </div>
                                    <?php if ($displayName !== '' && !can_access('jobs', 'edit')): ?>
                                        <div class="photo-library-card-body">
                                            <div class="fw-semibold photo-library-photo-name" title="<?= e($displayName) ?>"><?= e($displayName) ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (can_access('jobs', 'edit')): ?>
                                        <div class="photo-library-card-body">
                                            <form method="post" action="<?= url('/attachments/' . $photoId . '/label') ?>" class="d-flex gap-2 align-items-center">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= e($currentUri) ?>" />
                                                <input class="form-control form-control-sm" type="text" name="label" value="<?= e($displayName) ?>" maxlength="255" placeholder="Name (optional)" />
                                                <button class="btn btn-sm btn-outline-secondary" type="submit">Save</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                    <div class="photo-library-card-actions">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e($downloadUrl) ?>">
                                            <i class="fas fa-download me-1"></i>
                                            Download
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <div class="modal fade" id="photoPreviewModal" data-photo-preview-modal="1" tabindex="-1" aria-labelledby="photoPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="photoPreviewModalLabel">Photo Preview</h5>
                        <div class="small text-muted js-job-photo-modal-meta"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img class="img-fluid rounded js-job-photo-modal-image" src="" alt="Photo preview" />
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <div class="small text-muted js-job-photo-modal-counter"></div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary js-job-photo-prev">
                            <i class="fas fa-chevron-left me-1"></i>
                            Prev
                        </button>
                        <a class="btn btn-outline-primary js-job-photo-open" href="#" target="_blank" rel="noopener">
                            <i class="fas fa-up-right-from-square me-1"></i>
                            Open
                        </a>
                        <button type="button" class="btn btn-primary js-job-photo-next">
                            Next
                            <i class="fas fa-chevron-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
