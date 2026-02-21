<?php
    $client = $client ?? [];
    $clientTypes = $clientTypes ?? ['client'];
    $selectedCompany = $selectedCompany ?? null;
    $isEdit = !empty($client['id']);
    $duplicateMatches = is_array($duplicateMatches ?? null) ? $duplicateMatches : [];
    $requireDuplicateConfirm = !empty($requireDuplicateConfirm);
    $canCreateContact = !empty($canCreateContact);
    $createAsContactNow = old('create_contact_now', !empty($createAsContactNow) ? '1' : '0') === '1';

    $firstName = (string) old('first_name', $client['first_name'] ?? '');
    $lastName = (string) old('last_name', $client['last_name'] ?? '');
    $phone = (string) old('phone', $client['phone'] ?? '');
    $email = (string) old('email', $client['email'] ?? '');
    $address1 = (string) old('address_1', $client['address_1'] ?? '');
    $address2 = (string) old('address_2', $client['address_2'] ?? '');
    $city = (string) old('city', $client['city'] ?? '');
    $state = (string) old('state', $client['state'] ?? '');
    $zip = (string) old('zip', $client['zip'] ?? '');
    $clientType = (string) old('client_type', $client['client_type'] ?? 'client');
    if (!in_array($clientType, $clientTypes, true)) {
        $clientType = 'client';
    }
    $note = (string) old('note', $client['note'] ?? '');

    $canTextValue = old('can_text', isset($client['can_text']) ? (string) $client['can_text'] : '0');
    $canTextChecked = $canTextValue === '1' || $canTextValue === 1 || $canTextValue === 'on';

    $companyId = (string) old('company_id', $selectedCompany['id'] ?? '');
    $companyName = (string) old('company_name', $selectedCompany['name'] ?? '');
?>
<form method="post" action="<?= url($isEdit ? '/clients/' . ($client['id'] ?? '') . '/edit' : '/clients/new') ?>">
    <?= csrf_field() ?>
    <?php if (!$isEdit): ?>
        <input id="client_duplicate_lookup_url" type="hidden" value="<?= e(url('/clients/duplicate-check')) ?>" />
        <input id="client_show_base_url" type="hidden" value="<?= e(url('/clients')) ?>" />
    <?php endif; ?>

    <?php if (!$isEdit && $requireDuplicateConfirm && !empty($duplicateMatches)): ?>
        <div class="alert alert-warning mb-3">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <div class="fw-semibold mb-1">Possible duplicate clients found</div>
                    <div class="small text-muted">Review matches below before creating another client record.</div>
                </div>
                <button class="btn btn-danger btn-sm" name="force_create" type="submit" value="1">Create New Anyway</button>
            </div>
            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Match Factors</th>
                            <th class="text-end">Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duplicateMatches as $match): ?>
                            <tr>
                                <td><?= e((string) ($match['display_name'] ?? 'Client')) ?></td>
                                <td><?= e(format_phone($match['phone'] ?? null)) ?></td>
                                <td><?= e((string) (($match['email'] ?? '') !== '' ? $match['email'] : '—')) ?></td>
                                <td>
                                    <?php
                                        $matchStatus = (string) ($match['status'] ?? 'active');
                                        $badgeClass = 'bg-success';
                                        $label = 'Active';
                                        if ($matchStatus === 'deleted') {
                                            $badgeClass = 'bg-danger';
                                            $label = 'Deleted';
                                        } elseif ($matchStatus === 'inactive') {
                                            $badgeClass = 'bg-secondary';
                                            $label = 'Inactive';
                                        }
                                    ?>
                                    <span class="badge <?= e($badgeClass) ?>"><?= e($label) ?></span>
                                </td>
                                <td><?= e(implode(', ', $match['match_reasons'] ?? [])) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('/clients/' . ((int) ($match['id'] ?? 0))) ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$isEdit): ?>
        <div class="alert alert-warning mb-3 d-none" id="client_duplicate_matches_live" role="alert">
            <div class="fw-semibold mb-1">Possible matching clients</div>
            <div class="small text-muted mb-2">Check these records before saving a new client.</div>
            <div class="list-group" id="client_duplicate_matches_live_list"></div>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="first_name">First Name</label>
            <input class="form-control" id="first_name" name="first_name" type="text" value="<?= e($firstName) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="last_name">Last Name</label>
            <input class="form-control" id="last_name" name="last_name" type="text" value="<?= e($lastName) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" type="text" value="<?= e($phone) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e($email) ?>" />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="client_type">Type</label>
            <select class="form-select" id="client_type" name="client_type">
                <?php foreach ($clientTypes as $type): ?>
                    <option value="<?= e($type) ?>" <?= $clientType === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" id="can_text" name="can_text" type="checkbox" value="1" <?= $canTextChecked ? 'checked' : '' ?> />
                <label class="form-check-label" for="can_text">Can Text</label>
            </div>
        </div>

        <div class="col-md-7 position-relative">
            <label class="form-label" for="company_name">Company (Optional)</label>
            <input id="company_lookup_url" type="hidden" value="<?= e(url('/clients/lookup/companies')) ?>" />
            <input id="company_create_url" type="hidden" value="<?= e(url('/companies/quick-create')) ?>" />
            <input id="company_id" name="company_id" type="hidden" value="<?= e($companyId) ?>" />
            <input
                class="form-control"
                id="company_name"
                name="company_name"
                type="text"
                value="<?= e($companyName) ?>"
                placeholder="Search company name..."
                autocomplete="off"
            />
            <div id="company_name_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1100;"></div>
            <div class="form-text">Select one company for this client. Leave blank for no company.</div>
        </div>
        <!-- <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-outline-primary w-100 mb-2" id="add_company_btn" type="button" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                <i class="fas fa-plus me-1"></i>
                Add New
            </button>
        </div> -->
        <!-- <div class="col-md-3 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" id="active" name="active" type="checkbox" value="1" <?= $activeChecked ? 'checked' : '' ?> />
                <label class="form-check-label" for="active">Active</label>
            </div>
        </div> -->

        <div class="col-md-4">
            <label class="form-label" for="address_1">Address 1</label>
            <input class="form-control" id="address_1" name="address_1" type="text" value="<?= e($address1) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="address_2">Address 2</label>
            <input class="form-control" id="address_2" name="address_2" type="text" value="<?= e($address2) ?>" />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="city">City</label>
            <input class="form-control" id="city" name="city" type="text" value="<?= e($city) ?>" />
        </div>
        <div class="col-md-1">
            <label class="form-label" for="state">State</label>
            <input class="form-control" id="state" name="state" type="text" maxlength="2" value="<?= e($state) ?>" />
        </div>
        <div class="col-md-1">
            <label class="form-label" for="zip">Zip</label>
            <input class="form-control" id="zip" name="zip" type="text" value="<?= e($zip) ?>" />
        </div>

        <div class="col-12">
            <label class="form-label" for="note">Notes</label>
            <textarea class="form-control" id="note" name="note" rows="4"><?= e($note) ?></textarea>
        </div>
        <?php if ($isEdit): ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" id="ignore_duplicate_warning" name="ignore_duplicate_warning" type="checkbox" value="1" <?= old('ignore_duplicate_warning') === '1' ? 'checked' : '' ?> />
                    <label class="form-check-label" for="ignore_duplicate_warning">
                        Ignore duplicate warning and update anyway
                    </label>
                </div>
            </div>
        <?php elseif ($canCreateContact): ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" id="create_contact_now" name="create_contact_now" type="checkbox" value="1" <?= $createAsContactNow ? 'checked' : '' ?> />
                    <label class="form-check-label" for="create_contact_now">
                        Also save this client as a network client
                    </label>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <?php if (!$isEdit): ?>
            <button class="btn btn-primary" name="force_create" type="submit" value="0">Save Client</button>
        <?php else: ?>
            <button class="btn btn-primary" type="submit">Update Client</button>
        <?php endif; ?>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/clients/' . ($client['id'] ?? '')) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/clients') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>

<div class="modal fade" id="addCompanyModal" tabindex="-1" aria-labelledby="addCompanyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCompanyModalLabel">Add Company</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="company_create_error" class="alert alert-danger d-none" role="alert"></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="new_company_name">Company Name</label>
                        <input class="form-control" id="new_company_name" type="text" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="new_company_phone">Phone</label>
                        <input class="form-control" id="new_company_phone" type="text" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="new_company_web_address">Website</label>
                        <input class="form-control" id="new_company_web_address" type="text" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="new_company_city">City</label>
                        <input class="form-control" id="new_company_city" type="text" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="new_company_state">State</label>
                        <input class="form-control" id="new_company_state" maxlength="2" type="text" />
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="new_company_note">Note</label>
                        <textarea class="form-control" id="new_company_note" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="save_new_company_btn" type="button">Save Company</button>
            </div>
        </div>
    </div>
</div>
