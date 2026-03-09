<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\FormSelectValue;
use Core\Controller;

final class AdminFormSelectValuesController extends Controller
{
    public function index(): void
    {
        require_business_role(['admin']);

        $businessId = current_business_id();
        $actorUserId = (int) (auth_user_id() ?? 0);
        FormSelectValue::ensureDefaults($businessId, $actorUserId);

        $this->render('admin/form_select_values/index', [
            'pageTitle' => 'Form Select Values',
            'forms' => FormSelectValue::formSummariesForBusiness($businessId),
        ]);
    }

    public function show(array $params): void
    {
        require_business_role(['admin']);

        $businessId = current_business_id();
        $actorUserId = (int) (auth_user_id() ?? 0);
        FormSelectValue::ensureDefaults($businessId, $actorUserId);

        $formKey = strtolower(trim((string) ($params['formKey'] ?? '')));
        $formCatalog = FormSelectValue::formCatalogForBusiness($businessId, $formKey);
        if ($formCatalog === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('admin/form_select_values/show', [
            'pageTitle' => 'Form Select Values',
            'formCatalog' => $formCatalog,
        ]);
    }

    public function quickCreate(): void
    {
        require_business_role(['admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $businessId = current_business_id();
        $actorUserId = (int) (auth_user_id() ?? 0);
        $formKey = strtolower(trim((string) ($_POST['form_key'] ?? '')));
        $sectionKey = strtolower(trim((string) ($_POST['section_key'] ?? '')));
        $optionValue = trim((string) ($_POST['option_value'] ?? ''));

        if (!FormSelectValue::isValidScope($formKey, $sectionKey)) {
            $this->json(['ok' => false, 'error' => 'Invalid form/section selection.'], 422);
        }
        if ($optionValue === '') {
            $this->json(['ok' => false, 'error' => 'Option value is required.'], 422);
        }

        try {
            $id = FormSelectValue::create($businessId, $formKey, $sectionKey, $optionValue, $actorUserId);
        } catch (\RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        }

        $row = FormSelectValue::findForBusiness($businessId, $id);
        if ($row === null) {
            $this->json(['ok' => false, 'error' => 'Option was created but could not be loaded.'], 500);
        }

        $this->json([
            'ok' => true,
            'option' => [
                'id' => (int) ($row['id'] ?? 0),
                'form_key' => (string) ($row['form_key'] ?? ''),
                'section_key' => (string) ($row['section_key'] ?? ''),
                'option_value' => (string) ($row['option_value'] ?? ''),
            ],
        ], 201);
    }

    public function quickUpdate(array $params): void
    {
        require_business_role(['admin']);

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'Invalid option id.'], 422);
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $businessId = current_business_id();
        $actorUserId = (int) (auth_user_id() ?? 0);
        $optionValue = trim((string) ($_POST['option_value'] ?? ''));
        if ($optionValue === '') {
            $this->json(['ok' => false, 'error' => 'Option value is required.'], 422);
        }

        try {
            $updated = FormSelectValue::updateValue($businessId, $id, $optionValue, $actorUserId);
        } catch (\RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        }

        if (!$updated) {
            $this->json(['ok' => false, 'error' => 'Unable to update option.'], 404);
        }

        $row = FormSelectValue::findForBusiness($businessId, $id);
        if ($row === null) {
            $this->json(['ok' => false, 'error' => 'Updated option was not found.'], 404);
        }

        $this->json([
            'ok' => true,
            'option' => [
                'id' => (int) ($row['id'] ?? 0),
                'option_value' => (string) ($row['option_value'] ?? ''),
            ],
        ]);
    }

    public function quickDelete(array $params): void
    {
        require_business_role(['admin']);

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'Invalid option id.'], 422);
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $deleted = FormSelectValue::softDelete(current_business_id(), $id, (int) (auth_user_id() ?? 0));
        if (!$deleted) {
            $this->json(['ok' => false, 'error' => 'Unable to delete option.'], 404);
        }

        $this->json(['ok' => true]);
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
