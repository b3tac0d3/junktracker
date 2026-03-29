<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\FormSelectValue;
use App\Models\Sale;
use App\Models\SaleFeeDefault;
use Core\Controller;

final class AdminSaleFeeDefaultsController extends Controller
{
    public function index(): void
    {
        require_business_role(['admin']);

        $businessId = current_business_id();
        $types = $this->saleTypesForBusiness($businessId);
        $defaults = SaleFeeDefault::mapForBusiness($businessId);

        $this->render('admin/sale_fee_defaults/index', [
            'pageTitle' => 'Sales default fees',
            'types' => $types,
            'defaults' => $defaults,
        ]);
    }

    public function update(): void
    {
        require_business_role(['admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/sale-fee-defaults');
        }

        $businessId = current_business_id();
        $types = $this->saleTypesForBusiness($businessId);

        $kindMap = $_POST['fee_kind'] ?? [];
        $valueMap = $_POST['fee_value'] ?? [];
        if (!is_array($kindMap)) {
            $kindMap = [];
        }
        if (!is_array($valueMap)) {
            $valueMap = [];
        }

        $rows = [];
        foreach ($types as $type) {
            $kind = strtolower(trim((string) ($kindMap[$type] ?? 'none')));
            if ($kind !== 'percent' && $kind !== 'amount') {
                continue;
            }
            $raw = trim((string) ($valueMap[$type] ?? ''));
            if ($raw === '' || !is_numeric($raw)) {
                continue;
            }
            $val = (float) $raw;
            if ($kind === 'percent' && ($val < 0 || $val > 100)) {
                continue;
            }
            if ($kind === 'amount' && $val < 0) {
                continue;
            }
            $rows[] = [
                'sale_type' => $type,
                'fee_kind' => $kind,
                'fee_value' => $val,
            ];
        }

        SaleFeeDefault::replaceForBusiness($businessId, $rows);
        Sale::recalculateNetsForBusinessDefaults($businessId);
        flash('success', 'Sales default fees saved.');
        redirect('/admin/sale-fee-defaults');
    }

    /**
     * @return array<int, string>
     */
    private function saleTypesForBusiness(int $businessId): array
    {
        $fromForm = FormSelectValue::optionsForSection($businessId, 'sale_type');
        $fromForm = array_map(static fn (string $v): string => strtolower(trim($v)), $fromForm);
        $fromForm = array_values(array_unique(array_filter($fromForm, static fn (string $v): bool => $v !== '')));

        $merged = array_unique(array_merge(Sale::baseTypeOptions(), $fromForm));
        sort($merged);

        return array_values($merged);
    }
}
