<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
    <div>
        <h1>Business Admin</h1>
        <p class="muted mb-0">Your business setup</p>
    </div>
</div>

<div class="admin-nav-grid row g-3">
    <div class="col-12 col-sm-6 col-lg-3">
        <a class="admin-nav-tile" href="<?= e(url('/admin/users')) ?>">
            <div class="admin-nav-tile-icon admin-nav-tile-icon--users" aria-hidden="true">
                <i class="fas fa-users"></i>
            </div>
            <div class="admin-nav-tile-title">Users</div>
            <p class="admin-nav-tile-desc">Invite and manage workspace users, roles, and access.</p>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <a class="admin-nav-tile" href="<?= e(url('/admin/employees')) ?>">
            <div class="admin-nav-tile-icon admin-nav-tile-icon--employees" aria-hidden="true">
                <i class="fas fa-id-badge"></i>
            </div>
            <div class="admin-nav-tile-title">Employees</div>
            <p class="admin-nav-tile-desc">Employee profiles and user links for punch and time tracking.</p>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <a class="admin-nav-tile" href="<?= e(url('/admin/business-details')) ?>">
            <div class="admin-nav-tile-icon admin-nav-tile-icon--business" aria-hidden="true">
                <i class="fas fa-building"></i>
            </div>
            <div class="admin-nav-tile-title">Business Details</div>
            <p class="admin-nav-tile-desc">Company name, address, logo, and document numbering.</p>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <a class="admin-nav-tile" href="<?= e(url('/admin/invoice-item-types')) ?>">
            <div class="admin-nav-tile-icon admin-nav-tile-icon--invoice-types" aria-hidden="true">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="admin-nav-tile-title">Invoice Item Types</div>
            <p class="admin-nav-tile-desc">Reusable line items with default rates for estimates and invoices.</p>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <a class="admin-nav-tile" href="<?= e(url('/admin/form-select-values')) ?>">
            <div class="admin-nav-tile-icon admin-nav-tile-icon--form-values" aria-hidden="true">
                <i class="fas fa-list-ul"></i>
            </div>
            <div class="admin-nav-tile-title">Form Select Values</div>
            <p class="admin-nav-tile-desc">Centralize dropdown and pick-list values used across forms.</p>
        </a>
    </div>
</div>
