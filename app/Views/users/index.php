<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Users</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Users</li>
            </ol>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?= url('/users/new') ?>">
                <i class="fas fa-user-plus me-1"></i>
                Add User
            </a>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/users') ?>">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-lg-7">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input class="form-control" type="text" name="q" placeholder="Search users..." value="<?= e($query ?? '') ?>" />
                            <?php if (!empty($query)): ?>
                                <a class="btn btn-outline-secondary" href="<?= url('/users') ?>">Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <select class="form-select" name="status">
                            <option value="active" <?= ($status ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="all" <?= ($status ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2 d-grid">
                        <button class="btn btn-primary" type="submit">Apply</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            User Directory
        </div>
        <div class="card-body">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php $rowHref = url('/users/' . $user['id']); ?>
                        <tr data-href="<?= $rowHref ?>" style="cursor: pointer;">
                            <td data-href="<?= $rowHref ?>"><?= e((string) $user['id']) ?></td>
                            <td>
                                <a class="text-decoration-none" href="<?= url('/users/' . $user['id']) ?>">
                                    <?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
                                </a>
                            </td>
                            <td><?= e($user['email'] ?? '') ?></td>
                            <td><?= e(role_label(isset($user['role']) ? (int) $user['role'] : null)) ?></td>
                            <td>
                                <?php if (!empty($user['is_active'])): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(format_datetime($user['created_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
