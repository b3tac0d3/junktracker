<?php
    $contact = $contact ?? [];
    $contactMethods = $contactMethods ?? ['call'];
    $directions = $directions ?? ['outbound'];
    $linkTypes = $linkTypes ?? ['general'];
    $linkTypeLabels = $linkTypeLabels ?? [];

    $clientId = (string) old('client_id', isset($contact['client_id']) ? (string) $contact['client_id'] : '');
    $clientSearch = (string) old(
        'client_search',
        (string) (($contact['client_name'] ?? '') !== '' ? $contact['client_name'] : ($clientId !== '' ? ('Client #' . $clientId) : ''))
    );

    $contactMethod = (string) old('contact_method', $contact['contact_method'] ?? 'call');
    if (!in_array($contactMethod, $contactMethods, true)) {
        $contactMethod = 'call';
    }

    $direction = (string) old('direction', $contact['direction'] ?? 'outbound');
    if (!in_array($direction, $directions, true)) {
        $direction = 'outbound';
    }

    $contactedAt = (string) old('contacted_at', format_datetime_local($contact['contacted_at'] ?? date('Y-m-d H:i:s')));
    $followUpAt = (string) old('follow_up_at', format_datetime_local($contact['follow_up_at'] ?? null));
    $subject = (string) old('subject', $contact['subject'] ?? '');
    $notes = (string) old('notes', $contact['notes'] ?? '');

    $linkType = (string) old('link_type', $contact['link_type'] ?? 'general');
    if (!in_array($linkType, $linkTypes, true)) {
        $linkType = 'general';
    }
    $linkId = (string) old('link_id', isset($contact['link_id']) ? (string) $contact['link_id'] : '');
    $linkSearch = (string) old('link_search', $contact['link_label'] ?? '');

    $taskHref = $clientId !== '' ? url('/tasks/new?link_type=client&link_id=' . $clientId) : url('/tasks/new');
?>
<form method="post" action="<?= url('/client-contacts/new') ?>">
    <?= csrf_field() ?>

    <input id="client_contact_client_lookup_url" type="hidden" value="<?= e(url('/clients/lookup')) ?>" />
    <input id="task_link_lookup_url" type="hidden" value="<?= e(url('/tasks/lookup/links')) ?>" />
    <input id="client_contact_task_base_url" type="hidden" value="<?= e(url('/tasks/new')) ?>" />

    <div class="row g-3">
        <div class="col-md-12 position-relative">
            <label class="form-label" for="contact_client_search">Client</label>
            <input id="contact_client_id" name="client_id" type="hidden" value="<?= e($clientId) ?>" />
            <input
                class="form-control"
                id="contact_client_search"
                name="client_search"
                type="text"
                value="<?= e($clientSearch) ?>"
                autocomplete="off"
                placeholder="Search client..."
                required
            />
            <div id="client_contact_client_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1100;"></div>
            <div class="form-text">Start typing the client name and pick from suggestions.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label" for="contact_method">Method</label>
            <select class="form-select" id="contact_method" name="contact_method">
                <?php foreach ($contactMethods as $method): ?>
                    <option value="<?= e($method) ?>" <?= $contactMethod === $method ? 'selected' : '' ?>>
                        <?= e(ucwords(str_replace('_', ' ', $method))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="direction">Direction</label>
            <select class="form-select" id="direction" name="direction">
                <?php foreach ($directions as $value): ?>
                    <option value="<?= e($value) ?>" <?= $direction === $value ? 'selected' : '' ?>>
                        <?= e(ucfirst($value)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="contacted_at">Contacted At</label>
            <input class="form-control" id="contacted_at" name="contacted_at" type="datetime-local" value="<?= e($contactedAt) ?>" required />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="follow_up_at">Follow Up At</label>
            <input class="form-control" id="follow_up_at" name="follow_up_at" type="datetime-local" value="<?= e($followUpAt) ?>" />
        </div>

        <div class="col-md-12">
            <label class="form-label" for="subject">Subject</label>
            <input class="form-control" id="subject" name="subject" type="text" value="<?= e($subject) ?>" placeholder="Ex: Estimate follow-up call" />
        </div>

        <div class="col-md-3">
            <label class="form-label" for="link_type">Linked Record Type</label>
            <select class="form-select" id="link_type" name="link_type">
                <?php foreach ($linkTypes as $type): ?>
                    <option value="<?= e($type) ?>" <?= $linkType === $type ? 'selected' : '' ?>>
                        <?= e((string) ($linkTypeLabels[$type] ?? ucwords($type))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-9 position-relative">
            <label class="form-label" for="link_search">Linked Record</label>
            <input id="link_id" name="link_id" type="hidden" value="<?= e($linkId) ?>" />
            <input
                class="form-control"
                id="link_search"
                name="link_search"
                type="text"
                autocomplete="off"
                value="<?= e($linkSearch) ?>"
                placeholder="Search selected type..."
                <?= $linkType === 'general' ? 'disabled' : '' ?>
            />
            <div id="task_link_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1100;"></div>
        </div>

        <div class="col-12">
            <label class="form-label" for="notes">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="5" placeholder="Call notes, outcomes, next steps..."><?= e($notes) ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex flex-wrap gap-2">
        <button class="btn btn-primary" type="submit">
            <i class="fas fa-save me-1"></i>
            Save Contact
        </button>
        <a
            id="add_task_for_client_btn"
            class="btn btn-success <?= $clientId === '' ? 'disabled' : '' ?>"
            href="<?= e($taskHref) ?>"
        >
            <i class="fas fa-list-check me-1"></i>
            Add Task
        </a>
        <a class="btn btn-outline-secondary" href="<?= url('/client-contacts') ?>">Cancel</a>
    </div>
</form>
