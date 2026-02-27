<div class="page-header">
    <h1>Site Admin: Workspaces</h1>
    <p class="muted">Choose a business workspace to enter.</p>
</div>

<div class="card">
    <h2>Businesses</h2>
    <div class="list">
        <?php foreach (($businesses ?? []) as $business): ?>
            <?php $businessId = (int) ($business['id'] ?? 0); ?>
            <div class="list-item">
                <div>
                    <div class="title"><?= e((string) ($business['name'] ?? ('Business #' . $businessId))) ?></div>
                    <div class="muted"><?= e((string) ($business['legal_name'] ?? '')) ?></div>
                </div>
                <form method="post" action="<?= e(url('/site-admin/switch-business')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="business_id" value="<?= e((string) $businessId) ?>">
                    <button class="btn btn-primary" type="submit">Enter</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card mt-3">
    <form method="post" action="<?= e(url('/site-admin/exit-workspace')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-outline" type="submit">Clear Workspace Context</button>
    </form>
</div>
