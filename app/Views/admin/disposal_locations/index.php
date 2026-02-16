<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Disposal Locations</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Disposal Locations</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/admin/disposal-locations/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Location
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
            <i class="fas fa-recycle me-1"></i>
            Active Locations
        </div>
        <div class="card-body">
            <table id="disposalLocationsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Address</th>
                        <th>City</th>
                        <th>State</th>
                        <th>Phone</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $location): ?>
                        <tr>
                            <td><?= e((string) ($location['id'] ?? '')) ?></td>
                            <td><?= e((string) ($location['name'] ?? '')) ?></td>
                            <td class="text-uppercase"><?= e((string) ($location['type'] ?? '')) ?></td>
                            <td>
                                <?= e((string) ($location['address_1'] ?? '')) ?>
                                <?php if (!empty($location['address_2'])): ?>
                                    <br /><?= e((string) $location['address_2']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($location['city'] ?? '')) ?></td>
                            <td><?= e((string) ($location['state'] ?? '')) ?></td>
                            <td><?= e(format_phone($location['phone'] ?? null)) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary" href="<?= url('/admin/disposal-locations/' . ($location['id'] ?? '') . '/edit') ?>" title="Edit location" aria-label="Edit location">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <form class="d-inline" method="post" action="<?= url('/admin/disposal-locations/' . ($location['id'] ?? '') . '/delete') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-danger" type="submit" title="Delete location" aria-label="Delete location">
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
