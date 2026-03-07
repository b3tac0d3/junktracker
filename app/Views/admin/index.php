<div class="page-header">
    <h1>Business Admin</h1>
    <p class="muted">Your business setup</p>
</div>

<div class="card">
    <h2>Planned Admin Modules</h2>
    <div class="list">
        <a class="list-item text-decoration-none" href="<?= e(url('/admin/users')) ?>">
            <div>
                <div class="title">Users</div>
                <div class="muted">Internal users</div>
            </div>
        </a>
        <a class="list-item text-decoration-none" href="<?= e(url('/admin/employees')) ?>">
            <div>
                <div class="title">Employees</div>
                <div class="muted">Employee profiles and user links for punch access</div>
            </div>
        </a>
        <a class="list-item text-decoration-none" href="<?= e(url('/admin/business-details')) ?>">
            <div>
                <div class="title">Business Details</div>
                <div class="muted">Business profile</div>
            </div>
        </a>
        <a class="list-item text-decoration-none" href="<?= e(url('/admin/invoice-item-types')) ?>">
            <div>
                <div class="title">Invoice Item Types</div>
                <div class="muted">Reusable invoicable items with defaults</div>
            </div>
        </a>
        <div class="list-item">
            <div>
                <div class="title">Form Select Values</div>
                <div class="muted">Centralize list values used in forms.</div>
            </div>
        </div>
    </div>
</div>
