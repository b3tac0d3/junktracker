<?php
    $document = is_array($document ?? null) ? $document : [];
    $types = is_array($types ?? null) ? $types : ['estimate', 'invoice'];
    $statuses = is_array($statuses ?? null) ? $statuses : \App\Models\JobDocument::STATUSES;

    $fieldValue = static function (string $key, mixed $default = '') use ($document): mixed {
        $oldValue = old($key, null);
        if ($oldValue !== null) {
            return $oldValue;
        }
        if (array_key_exists($key, $document)) {
            return $document[$key];
        }
        return $default;
    };
?>
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-file-invoice-dollar me-1"></i>
        Document Details
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="document_type">Type</label>
                <select class="form-select" id="document_type" name="document_type" required>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= e((string) $type) ?>" <?= (string) $fieldValue('document_type', 'estimate') === (string) $type ? 'selected' : '' ?>>
                            <?= e(\App\Models\JobDocument::typeLabel((string) $type)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e((string) $status) ?>" <?= (string) $fieldValue('status', 'draft') === (string) $status ? 'selected' : '' ?>>
                            <?= e(\App\Models\JobDocument::statusLabel((string) $status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="amount">Amount</label>
                <input
                    class="form-control"
                    id="amount"
                    name="amount"
                    type="number"
                    min="0"
                    step="0.01"
                    value="<?= e((string) $fieldValue('amount', '')) ?>"
                    placeholder="0.00"
                />
            </div>

            <div class="col-md-8">
                <label class="form-label" for="title">Title</label>
                <input
                    class="form-control"
                    id="title"
                    name="title"
                    type="text"
                    required
                    maxlength="190"
                    value="<?= e((string) $fieldValue('title', '')) ?>"
                    placeholder="Estimate #, Invoice #, or descriptive title"
                />
            </div>
            <div class="col-md-4">
                <label class="form-label" for="issued_at">Issued At</label>
                <input
                    class="form-control"
                    id="issued_at"
                    name="issued_at"
                    type="datetime-local"
                    value="<?= e(format_datetime_local((string) $fieldValue('issued_at', ''))) ?>"
                />
            </div>

            <div class="col-md-4">
                <label class="form-label" for="due_at">Due At</label>
                <input
                    class="form-control"
                    id="due_at"
                    name="due_at"
                    type="datetime-local"
                    value="<?= e(format_datetime_local((string) $fieldValue('due_at', ''))) ?>"
                />
            </div>
            <div class="col-md-4">
                <label class="form-label" for="sent_at">Sent At</label>
                <input
                    class="form-control"
                    id="sent_at"
                    name="sent_at"
                    type="datetime-local"
                    value="<?= e(format_datetime_local((string) $fieldValue('sent_at', ''))) ?>"
                />
            </div>
            <div class="col-md-4">
                <label class="form-label" for="approved_at">Approved At</label>
                <input
                    class="form-control"
                    id="approved_at"
                    name="approved_at"
                    type="datetime-local"
                    value="<?= e(format_datetime_local((string) $fieldValue('approved_at', ''))) ?>"
                />
            </div>

            <div class="col-md-4">
                <label class="form-label" for="paid_at">Paid At</label>
                <input
                    class="form-control"
                    id="paid_at"
                    name="paid_at"
                    type="datetime-local"
                    value="<?= e(format_datetime_local((string) $fieldValue('paid_at', ''))) ?>"
                />
            </div>

            <div class="col-12">
                <label class="form-label" for="note">Notes</label>
                <textarea class="form-control" id="note" name="note" rows="4" placeholder="Optional notes for this document."><?= e((string) $fieldValue('note', '')) ?></textarea>
            </div>
        </div>
    </div>
</div>
