<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Expense Categories</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Expense Categories</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/admin/expense-categories/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Category
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-tags me-1"></i>
            Active Categories
        </div>
        <div class="card-body">
            <table id="expenseCategoriesTable" class="js-card-list-source">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Notes</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= e((string) ($category['id'] ?? '')) ?></td>
                            <td><?= e((string) ($category['name'] ?? '')) ?></td>
                            <td><?= e((string) ($category['note'] ?? '—')) ?></td>
                            <td><?= e(format_datetime($category['updated_at'] ?? null)) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary" href="<?= url('/admin/expense-categories/' . ($category['id'] ?? '') . '/edit') ?>" title="Edit category" aria-label="Edit category">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <form class="d-inline" method="post" action="<?= url('/admin/expense-categories/' . ($category['id'] ?? '') . '/delete') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-danger" type="submit" title="Delete category" aria-label="Delete category">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
