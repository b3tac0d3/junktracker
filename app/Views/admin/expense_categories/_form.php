<?php
    $category = $category ?? [];
    $isEdit = !empty($category['id']);

    $name = (string) old('name', $category['name'] ?? '');
    $note = (string) old('note', $category['note'] ?? '');
?>
<form method="post" action="<?= url($isEdit ? '/admin/expense-categories/' . ($category['id'] ?? '') . '/edit' : '/admin/expense-categories/new') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name">Category Name</label>
            <input class="form-control" id="name" name="name" type="text" value="<?= e($name) ?>" required />
        </div>
        <div class="col-12">
            <label class="form-label" for="note">Notes</label>
            <textarea class="form-control" id="note" name="note" rows="4"><?= e($note) ?></textarea>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Category' : 'Save Category' ?></button>
        <a class="btn btn-outline-secondary" href="<?= url('/admin/expense-categories') ?>">Cancel</a>
    </div>
</form>
