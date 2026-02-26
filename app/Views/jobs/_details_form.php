<?php
    $job = $job ?? [];
    $isEdit = !empty($job['id']);
    $statusValue = (string) old('job_status', $job['job_status'] ?? 'pending');
    $statusRows = $statusOptions ?? lookup_options('job_status', [
        ['value_key' => 'pending', 'label' => 'Pending', 'active' => 1],
        ['value_key' => 'active', 'label' => 'Active', 'active' => 1],
        ['value_key' => 'complete', 'label' => 'Complete', 'active' => 1],
        ['value_key' => 'cancelled', 'label' => 'Cancelled', 'active' => 1],
    ]);
    $statusChoices = [];
    foreach ($statusRows as $statusRow) {
        if (!empty($statusRow['deleted_at']) || (isset($statusRow['active']) && (int) $statusRow['active'] !== 1)) {
            continue;
        }
        $value = trim((string) ($statusRow['value_key'] ?? ''));
        if ($value === '') {
            continue;
        }
        $statusChoices[$value] = (string) ($statusRow['label'] ?? ucwords(str_replace('_', ' ', $value)));
    }
    if (empty($statusChoices)) {
        $statusChoices = [
            'pending' => 'Pending',
            'active' => 'Active',
            'complete' => 'Complete',
            'cancelled' => 'Cancelled',
        ];
    }
    $ownerType = (string) old('job_owner_type', $job['resolved_owner_type'] ?? (isset($job['estate_id']) && !empty($job['estate_id']) ? 'estate' : 'client'));
    $ownerId = (string) old('job_owner_id', (string) ($job['resolved_owner_id'] ?? ''));
    $ownerClientId = (string) old(
        'owner_client_id',
        (string) ($job['owner_client_id'] ?? (($job['resolved_owner_type'] ?? '') === 'client' ? ($job['resolved_owner_id'] ?? '') : ''))
    );
    $ownerEstateId = (string) old(
        'owner_estate_id',
        (string) ($job['estate_id'] ?? (($job['resolved_owner_type'] ?? '') === 'estate' ? ($job['resolved_owner_id'] ?? '') : ''))
    );
    $ownerCompanyId = (string) old(
        'owner_company_id',
        (string) ($job['owner_company_id'] ?? (($job['resolved_owner_type'] ?? '') === 'company' ? ($job['resolved_owner_id'] ?? '') : ''))
    );
    $ownerClientSearch = (string) old(
        'owner_client_search',
        (string) ($job['owner_client_display_name'] ?? (($job['resolved_owner_type'] ?? '') === 'client' ? ($job['owner_display_name'] ?? '') : ''))
    );
    $ownerEstateSearch = (string) old(
        'owner_estate_search',
        (string) ($job['owner_estate_display_name'] ?? (($job['resolved_owner_type'] ?? '') === 'estate' ? ($job['owner_display_name'] ?? '') : ''))
    );
    $ownerCompanySearch = (string) old(
        'owner_company_search',
        (string) ($job['owner_company_display_name'] ?? (($job['resolved_owner_type'] ?? '') === 'company' ? ($job['owner_display_name'] ?? '') : ''))
    );
    $contactClientId = (string) old('contact_client_id', (string) ($job['resolved_contact_client_id'] ?? ($job['client_id'] ?? '')));
    $contactSearch = (string) old('contact_search', $job['contact_display_name'] ?? '');
    $canQuickCreateClient = can_access('clients', 'create');
    $sourceProspectId = (string) old('source_prospect_id', (string) ($job['source_prospect_id'] ?? ''));
    $cancelUrl = $isEdit
        ? url('/jobs/' . ($job['id'] ?? ''))
        : ($sourceProspectId !== '' ? url('/prospects/' . $sourceProspectId) : url('/jobs'));
?>
<form method="post" action="<?= url($isEdit ? '/jobs/' . ($job['id'] ?? '') . '/edit' : '/jobs/new') ?>">
    <?= csrf_field() ?>
    <?php if (!$isEdit && $sourceProspectId !== ''): ?>
        <input type="hidden" name="source_prospect_id" value="<?= e($sourceProspectId) ?>" />
    <?php endif; ?>
    <input type="hidden" id="owner_lookup_url" value="<?= e(url('/jobs/lookup/owners')) ?>" />
    <input type="hidden" id="contact_lookup_url" value="<?= e(url('/jobs/lookup/contacts')) ?>" />
    <?php if ($canQuickCreateClient): ?>
        <input type="hidden" id="contact_create_url" value="<?= e(url('/clients/quick-create')) ?>" />
    <?php endif; ?>
    <div class="row g-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm job-owner-contact-card">
                <div class="card-header"><i class="fas fa-clipboard-list me-1"></i>Job Basics</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="name">Job Name</label>
                            <input class="form-control" id="name" name="name" type="text" value="<?= e((string) old('name', $job['name'] ?? '')) ?>" required />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="job_status">Status</label>
                            <select class="form-select" id="job_status" name="job_status">
                                <?php foreach ($statusChoices as $value => $label): ?>
                                    <option value="<?= e((string) $value) ?>" <?= $statusValue === (string) $value ? 'selected' : '' ?>>
                                        <?= e((string) $label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 position-relative">
                            <label class="form-label" for="owner_client_search">Linked Client</label>
                            <input class="form-control" id="owner_client_search" name="owner_client_search" type="text" autocomplete="off" value="<?= e($ownerClientSearch) ?>" placeholder="Search client..." />
                            <input type="hidden" id="owner_client_id" name="owner_client_id" value="<?= e($ownerClientId) ?>" />
                            <div id="jobOwnerClientResults" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1080; top: 100%;"></div>
                        </div>
                        <div class="col-md-4 position-relative">
                            <label class="form-label" for="owner_estate_search">Linked Estate</label>
                            <input class="form-control" id="owner_estate_search" name="owner_estate_search" type="text" autocomplete="off" value="<?= e($ownerEstateSearch) ?>" placeholder="Search estate..." />
                            <input type="hidden" id="owner_estate_id" name="owner_estate_id" value="<?= e($ownerEstateId) ?>" />
                            <div id="jobOwnerEstateResults" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1080; top: 100%;"></div>
                        </div>
                        <div class="col-md-4 position-relative">
                            <label class="form-label" for="owner_company_search">Linked Company</label>
                            <input class="form-control" id="owner_company_search" name="owner_company_search" type="text" autocomplete="off" value="<?= e($ownerCompanySearch) ?>" placeholder="Search company..." />
                            <input type="hidden" id="owner_company_id" name="owner_company_id" value="<?= e($ownerCompanyId) ?>" />
                            <div id="jobOwnerCompanyResults" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1080; top: 100%;"></div>
                            <div class="form-text">Link one or more owner records.</div>
                        </div>
                        <input type="hidden" id="job_owner_type" name="job_owner_type" value="<?= e($ownerType) ?>" />
                        <input type="hidden" id="job_owner_id" name="job_owner_id" value="<?= e($ownerId) ?>" />
                        <div class="col-md-6 position-relative">
                            <label class="form-label" for="contact_search">Contact</label>
                            <input class="form-control" id="contact_search" name="contact_search" type="text" autocomplete="off" value="<?= e($contactSearch) ?>" placeholder="Search clients..." required />
                            <input type="hidden" id="contact_client_id" name="contact_client_id" value="<?= e($contactClientId) ?>" />
                            <div id="contactResults" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1080; top: 100%;"></div>
                            <div class="form-text">Contacts are selected from clients only.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header"><i class="fas fa-calendar-day me-1"></i>Scheduling & Milestones</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label" for="quote_date">Quote Date</label>
                            <input class="form-control" id="quote_date" name="quote_date" type="datetime-local" value="<?= e((string) old('quote_date', format_datetime_local($job['quote_date'] ?? null))) ?>" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="scheduled_start_at">Scheduled Start</label>
                            <input class="form-control" id="scheduled_start_at" name="scheduled_start_at" type="datetime-local" value="<?= e((string) old('scheduled_start_at', format_datetime_local($job['scheduled_start_at'] ?? ($job['scheduled_date'] ?? null)))) ?>" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="scheduled_end_at">Scheduled End</label>
                            <input class="form-control" id="scheduled_end_at" name="scheduled_end_at" type="datetime-local" value="<?= e((string) old('scheduled_end_at', format_datetime_local($job['scheduled_end_at'] ?? null))) ?>" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="start_date">Actual Start</label>
                            <input class="form-control" id="start_date" name="start_date" type="datetime-local" value="<?= e((string) old('start_date', format_datetime_local($job['start_date'] ?? null))) ?>" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="end_date">Actual End</label>
                            <input class="form-control" id="end_date" name="end_date" type="datetime-local" value="<?= e((string) old('end_date', format_datetime_local($job['end_date'] ?? null))) ?>" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="billed_date">Billed Date</label>
                            <input class="form-control" id="billed_date" name="billed_date" type="datetime-local" value="<?= e((string) old('billed_date', format_datetime_local($job['billed_date'] ?? null))) ?>" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="paid_date">Paid-in-Full Date</label>
                            <input class="form-control" id="paid_date" name="paid_date" type="datetime-local" value="<?= e((string) old('paid_date', format_datetime_local($job['paid_date'] ?? null))) ?>" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header"><i class="fas fa-location-dot me-1"></i>Service Location & Contact</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="address_1">Address</label>
                            <input class="form-control" id="address_1" name="address_1" type="text" value="<?= e((string) old('address_1', $job['address_1'] ?? '')) ?>" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="address_2">Address 2</label>
                            <input class="form-control" id="address_2" name="address_2" type="text" value="<?= e((string) old('address_2', $job['address_2'] ?? '')) ?>" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="city">City</label>
                            <input class="form-control" id="city" name="city" type="text" value="<?= e((string) old('city', $job['city'] ?? '')) ?>" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="state">State</label>
                            <input class="form-control" id="state" name="state" type="text" value="<?= e((string) old('state', $job['state'] ?? '')) ?>" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="zip">Zip</label>
                            <input class="form-control" id="zip" name="zip" type="text" value="<?= e((string) old('zip', $job['zip'] ?? '')) ?>" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone</label>
                            <input class="form-control" id="phone" name="phone" type="text" value="<?= e((string) old('phone', $job['phone'] ?? '')) ?>" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" id="email" name="email" type="email" value="<?= e((string) old('email', $job['email'] ?? '')) ?>" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header"><i class="fas fa-file-invoice-dollar me-1"></i>Financials & Notes</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="total_quote">Total Quote</label>
                            <input class="form-control" id="total_quote" name="total_quote" type="number" step="0.01" value="<?= e((string) old('total_quote', (string) ($job['total_quote'] ?? ''))) ?>" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="total_billed">Total Billed</label>
                            <input class="form-control" id="total_billed" name="total_billed" type="number" step="0.01" value="<?= e((string) old('total_billed', (string) ($job['total_billed'] ?? ''))) ?>" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="note">Notes</label>
                            <textarea class="form-control" id="note" name="note" rows="4"><?= e((string) old('note', $job['note'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" id="ignore_duplicate_warning" name="ignore_duplicate_warning" type="checkbox" value="1" <?= old('ignore_duplicate_warning') === '1' ? 'checked' : '' ?> />
                                <label class="form-check-label" for="ignore_duplicate_warning">
                                    Ignore duplicate warning and save anyway
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit">Save Job</button>
        <a class="btn btn-outline-secondary" href="<?= $cancelUrl ?>">Cancel</a>
    </div>
</form>

<?php if ($canQuickCreateClient): ?>
    <div class="modal fade" id="addJobContactClientModal" tabindex="-1" aria-labelledby="addJobContactClientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addJobContactClientModalLabel">Add Client Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="job_contact_client_create_error" class="alert alert-danger d-none mb-3" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="job_new_contact_first_name">First Name</label>
                            <input class="form-control" id="job_new_contact_first_name" type="text" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="job_new_contact_last_name">Last Name</label>
                            <input class="form-control" id="job_new_contact_last_name" type="text" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="job_new_contact_phone">Phone</label>
                            <input class="form-control" id="job_new_contact_phone" type="text" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="job_new_contact_email">Email</label>
                            <input class="form-control" id="job_new_contact_email" type="email" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save_new_job_contact_client_btn">Save Client</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
