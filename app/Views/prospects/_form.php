<?php
    $prospect = $prospect ?? [];
    $statuses = $statuses ?? ['active'];
    $nextSteps = $nextSteps ?? ['follow_up'];
    $priorities = $priorities ?? [2];
    $priorityLabels = $priorityLabels ?? [2 => 'Normal'];
    $isEdit = !empty($prospect['id']);

    $clientId = (string) old('client_id', isset($prospect['client_id']) ? (string) $prospect['client_id'] : '');
    $clientSearch = (string) old(
        'client_search',
        ($prospect['client_name'] ?? '') !== '' ? (string) $prospect['client_name'] : ($clientId !== '' ? ('Client #' . $clientId) : '')
    );

    $contactedOn = (string) old('contacted_on', $prospect['contacted_on'] ?? '');
    $followUpOn = (string) old('follow_up_on', $prospect['follow_up_on'] ?? '');
    $status = (string) old('status', $prospect['status'] ?? 'active');
    if (!in_array($status, $statuses, true)) {
        $status = 'active';
    }

    $priority = (string) old('priority_rating', isset($prospect['priority_rating']) ? (string) $prospect['priority_rating'] : '2');
    $validPriorityValues = array_map(static fn (int $value): string => (string) $value, $priorities);
    if (!in_array($priority, $validPriorityValues, true)) {
        $priority = '2';
    }

    $nextStep = (string) old('next_step', $prospect['next_step'] ?? '');
    if ($nextStep !== '' && !in_array($nextStep, $nextSteps, true)) {
        $nextStep = '';
    }

    $note = (string) old('note', $prospect['note'] ?? '');
?>
<form method="post" action="<?= url($isEdit ? '/prospects/' . ($prospect['id'] ?? '') . '/edit' : '/prospects/new') ?>">
    <?= csrf_field() ?>

    <input id="prospect_client_lookup_url" type="hidden" value="<?= e(url('/prospects/lookup/clients')) ?>" />
    <input id="prospect_client_create_url" type="hidden" value="<?= e(url('/clients/quick-create')) ?>" />

    <div class="row g-3">
        <div class="col-md-12 position-relative">
            <label class="form-label" for="client_search">Client Contact</label>
            <input id="client_id" name="client_id" type="hidden" value="<?= e($clientId) ?>" />
            <input
                class="form-control"
                id="client_search"
                name="client_search"
                type="text"
                value="<?= e($clientSearch) ?>"
                autocomplete="off"
                placeholder="Search clients..."
                required
            />
            <div id="prospect_client_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1100;"></div>
            <div class="form-text">Search an existing client or add one quickly.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label" for="contacted_on">Contacted On</label>
            <input class="form-control" id="contacted_on" name="contacted_on" type="date" value="<?= e($contactedOn) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="follow_up_on">Follow Up On</label>
            <input class="form-control" id="follow_up_on" name="follow_up_on" type="date" value="<?= e($followUpOn) ?>" />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="priority_rating">Priority</label>
            <select class="form-select" id="priority_rating" name="priority_rating">
                <?php foreach ($priorities as $value): ?>
                    <?php $valueString = (string) $value; ?>
                    <option value="<?= e($valueString) ?>" <?= $priority === $valueString ? 'selected' : '' ?>>
                        <?= e((string) ($priorityLabels[$value] ?? ('Priority ' . $valueString))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <?php foreach ($statuses as $value): ?>
                    <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="next_step">Next Step</label>
            <select class="form-select" id="next_step" name="next_step">
                <option value="">None</option>
                <?php foreach ($nextSteps as $value): ?>
                    <option value="<?= e($value) ?>" <?= $nextStep === $value ? 'selected' : '' ?>>
                        <?= e(ucwords(str_replace('_', ' ', $value))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label" for="note">Notes</label>
            <textarea class="form-control" id="note" name="note" rows="4"><?= e($note) ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Prospect' : 'Save Prospect' ?></button>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/prospects/' . ($prospect['id'] ?? '')) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/prospects') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>

<div class="modal fade" id="addProspectClientModal" tabindex="-1" aria-labelledby="addProspectClientModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProspectClientModalLabel">Add Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="prospect_client_create_error" class="alert alert-danger d-none" role="alert"></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="new_prospect_client_first_name">First Name</label>
                        <input class="form-control" id="new_prospect_client_first_name" type="text" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="new_prospect_client_last_name">Last Name</label>
                        <input class="form-control" id="new_prospect_client_last_name" type="text" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="new_prospect_client_phone">Phone</label>
                        <input class="form-control" id="new_prospect_client_phone" type="text" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="new_prospect_client_email">Email</label>
                        <input class="form-control" id="new_prospect_client_email" type="email" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="save_new_prospect_client_btn" type="button">Save Client</button>
            </div>
        </div>
    </div>
</div>
