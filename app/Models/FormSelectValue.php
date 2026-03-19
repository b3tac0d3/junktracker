<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class FormSelectValue
{
    /** @var array<int, bool> */
    private static array $defaultsEnsured = [];

    /**
     * @return array<string, array{label: string, sections: array<string, array{label: string, hint: string, defaults: array<int, string>}>}>
     */
    public static function definitions(): array
    {
        return [
            'clients' => [
                'label' => 'Clients',
                'sections' => [
                    'client_type' => [
                        'label' => 'Client Type',
                        'hint' => 'Used on client create/edit forms.',
                        'defaults' => ['client', 'company', 'realtor', 'other'],
                    ],
                ],
            ],
            'sales' => [
                'label' => 'Sales',
                'sections' => [
                    'sale_type' => [
                        'label' => 'Sale Type',
                        'hint' => 'Used on sales filters and sale forms.',
                        'defaults' => ['shop', 'ebay', 'scrap', 'b2b'],
                    ],
                ],
            ],
            'expenses' => [
                'label' => 'Expenses',
                'sections' => [
                    'expense_category' => [
                        'label' => 'Expense Category',
                        'hint' => 'Used on job expense and general expense forms.',
                        'defaults' => ['Fuel', 'Disposal', 'Materials', 'Labor', 'Payroll', 'Supplies', 'Rent', 'Utilities', 'Other'],
                    ],
                ],
            ],
            'jobs' => [
                'label' => 'Jobs',
                'sections' => [
                    'job_type' => [
                        'label' => 'Job Type',
                        'hint' => 'Used on job create/edit forms and job details.',
                        'defaults' => [],
                    ],
                    'job_status' => [
                        'label' => 'Job Status',
                        'hint' => 'Used on job forms and job filters.',
                        'defaults' => ['prospect', 'pending', 'active', 'complete', 'cancelled'],
                    ],
                ],
            ],
            'purchases' => [
                'label' => 'Purchases',
                'sections' => [
                    'purchase_status' => [
                        'label' => 'Purchase Status',
                        'hint' => 'Used on purchase order forms and filters.',
                        'defaults' => ['prospect', 'pending', 'active', 'complete', 'cancelled'],
                    ],
                ],
            ],
            'tasks' => [
                'label' => 'Tasks',
                'sections' => [
                    'task_status' => [
                        'label' => 'Task Status',
                        'hint' => 'Used on task forms and task filters.',
                        'defaults' => ['open', 'in_progress', 'closed'],
                    ],
                ],
            ],
            'billing' => [
                'label' => 'Billing',
                'sections' => [
                    'estimate_status' => [
                        'label' => 'Estimate Status',
                        'hint' => 'Used on estimate forms and quick status updates.',
                        'defaults' => ['draft', 'sent', 'approved', 'declined'],
                    ],
                    'invoice_status' => [
                        'label' => 'Invoice Status',
                        'hint' => 'Used on invoice forms, quick status updates, and billing filters.',
                        'defaults' => ['unsent', 'sent', 'partially_paid', 'paid_in_full'],
                    ],
                    'payment_method' => [
                        'label' => 'Payment Method',
                        'hint' => 'Used when recording payments.',
                        'defaults' => ['check', 'cc', 'cash', 'venmo', 'cashapp', 'other'],
                    ],
                    'payment_type' => [
                        'label' => 'Payment Type',
                        'hint' => 'Used when recording payments.',
                        'defaults' => ['deposit', 'payment'],
                    ],
                ],
            ],
        ];
    }

    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('form_select_values');
    }

    public static function isValidScope(string $formKey, string $sectionKey): bool
    {
        $formKey = self::normalizeKey($formKey);
        $sectionKey = self::normalizeKey($sectionKey);
        $definitions = self::definitions();
        return isset($definitions[$formKey], $definitions[$formKey]['sections'][$sectionKey]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function sectionDefinition(string $sectionKey): ?array
    {
        $sectionKey = self::normalizeKey($sectionKey);
        foreach (self::definitions() as $form) {
            $sections = is_array($form['sections'] ?? null) ? $form['sections'] : [];
            if (isset($sections[$sectionKey]) && is_array($sections[$sectionKey])) {
                return $sections[$sectionKey];
            }
        }
        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForSection(int $businessId, string $sectionKey): array
    {
        $sectionKey = self::normalizeKey($sectionKey);
        $defaults = self::defaultOptions($sectionKey);
        if ($businessId <= 0 || $sectionKey === '' || !self::isAvailable()) {
            return $defaults;
        }
        self::ensureDefaultsForBusiness($businessId);

        $stmt = Database::connection()->prepare(
            'SELECT option_value
             FROM form_select_values
             WHERE business_id = :business_id
               AND section_key = :section_key
               AND deleted_at IS NULL
               AND is_active = 1
             ORDER BY sort_order ASC, option_value ASC, id ASC'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'section_key' => $sectionKey,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (!is_array($rows)) {
            return $defaults;
        }

        $values = [];
        $seen = [];
        foreach ($rows as $raw) {
            $value = self::normalizeValue((string) $raw);
            if ($value === '') {
                continue;
            }
            $key = mb_strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $values[] = $value;
        }

        return $values !== [] ? $values : $defaults;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function catalogForBusiness(int $businessId): array
    {
        self::ensureDefaultsForBusiness($businessId);
        $definitions = self::definitions();
        $rowsBySection = self::rowsBySection($businessId);

        $result = [];
        foreach ($definitions as $formKey => $form) {
            $sections = [];
            $sectionDefs = is_array($form['sections'] ?? null) ? $form['sections'] : [];
            foreach ($sectionDefs as $sectionKey => $sectionDef) {
                $sections[] = [
                    'section_key' => $sectionKey,
                    'section_label' => (string) ($sectionDef['label'] ?? $sectionKey),
                    'section_hint' => (string) ($sectionDef['hint'] ?? ''),
                    'options' => $rowsBySection[$sectionKey] ?? [],
                ];
            }

            $result[] = [
                'form_key' => $formKey,
                'form_label' => (string) ($form['label'] ?? $formKey),
                'sections' => $sections,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function formSummariesForBusiness(int $businessId): array
    {
        self::ensureDefaultsForBusiness($businessId);
        $definitions = self::definitions();
        $rowsBySection = self::rowsBySection($businessId);

        $result = [];
        foreach ($definitions as $formKey => $form) {
            $sectionDefs = is_array($form['sections'] ?? null) ? $form['sections'] : [];
            $sectionCount = count($sectionDefs);
            $optionCount = 0;
            foreach ($sectionDefs as $sectionKey => $_sectionDef) {
                $optionCount += count($rowsBySection[$sectionKey] ?? []);
            }

            $result[] = [
                'form_key' => $formKey,
                'form_label' => (string) ($form['label'] ?? $formKey),
                'section_count' => $sectionCount,
                'option_count' => $optionCount,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function formCatalogForBusiness(int $businessId, string $formKey): ?array
    {
        $formKey = self::normalizeKey($formKey);
        self::ensureDefaultsForBusiness($businessId);
        $definitions = self::definitions();
        if (!isset($definitions[$formKey]) || !is_array($definitions[$formKey])) {
            return null;
        }

        $form = $definitions[$formKey];
        $rowsBySection = self::rowsBySection($businessId);
        $sections = [];
        $sectionDefs = is_array($form['sections'] ?? null) ? $form['sections'] : [];
        foreach ($sectionDefs as $sectionKey => $sectionDef) {
            $sections[] = [
                'section_key' => $sectionKey,
                'section_label' => (string) ($sectionDef['label'] ?? $sectionKey),
                'section_hint' => (string) ($sectionDef['hint'] ?? ''),
                'options' => $rowsBySection[$sectionKey] ?? [],
            ];
        }

        return [
            'form_key' => $formKey,
            'form_label' => (string) ($form['label'] ?? $formKey),
            'sections' => $sections,
        ];
    }

    public static function ensureDefaults(int $businessId, int $actorUserId): void
    {
        if ($businessId <= 0 || !self::isAvailable()) {
            return;
        }

        $definitions = self::definitions();
        foreach ($definitions as $formKey => $form) {
            $sections = is_array($form['sections'] ?? null) ? $form['sections'] : [];
            foreach ($sections as $sectionKey => $section) {
                $defaults = is_array($section['defaults'] ?? null) ? $section['defaults'] : [];
                $sortOrder = 10;
                foreach ($defaults as $defaultValueRaw) {
                    $defaultValue = self::normalizeValue((string) $defaultValueRaw);
                    if ($defaultValue === '') {
                        continue;
                    }

                    if (self::exists($businessId, $formKey, $sectionKey, $defaultValue)) {
                        $sortOrder += 10;
                        continue;
                    }

                    $stmt = Database::connection()->prepare(
                        'INSERT INTO form_select_values (
                            business_id,
                            form_key,
                            section_key,
                            option_value,
                            sort_order,
                            is_active,
                            created_by,
                            updated_by,
                            created_at,
                            updated_at
                        ) VALUES (
                            :business_id,
                            :form_key,
                            :section_key,
                            :option_value,
                            :sort_order,
                            1,
                            :created_by,
                            :updated_by,
                            NOW(),
                            NOW()
                        )'
                    );
                    $stmt->execute([
                        'business_id' => $businessId,
                        'form_key' => $formKey,
                        'section_key' => $sectionKey,
                        'option_value' => $defaultValue,
                        'sort_order' => $sortOrder,
                        'created_by' => $actorUserId > 0 ? $actorUserId : null,
                        'updated_by' => $actorUserId > 0 ? $actorUserId : null,
                    ]);

                    $sortOrder += 10;
                }
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForBusiness(int $businessId, int $id): ?array
    {
        if ($businessId <= 0 || $id <= 0 || !self::isAvailable()) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, business_id, form_key, section_key, option_value, sort_order, is_active
             FROM form_select_values
             WHERE business_id = :business_id
               AND id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'id' => $id,
        ]);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function create(int $businessId, string $formKey, string $sectionKey, string $optionValue, int $actorUserId): int
    {
        $formKey = self::normalizeKey($formKey);
        $sectionKey = self::normalizeKey($sectionKey);
        $optionValue = self::normalizeValue($optionValue);

        if (!self::isValidScope($formKey, $sectionKey)) {
            throw new \RuntimeException('Invalid form/section scope.');
        }
        if ($optionValue === '') {
            throw new \RuntimeException('Option value is required.');
        }
        if (self::exists($businessId, $formKey, $sectionKey, $optionValue)) {
            throw new \RuntimeException('Option already exists.');
        }

        $nextSort = self::nextSortOrder($businessId, $formKey, $sectionKey);

        $stmt = Database::connection()->prepare(
            'INSERT INTO form_select_values (
                business_id,
                form_key,
                section_key,
                option_value,
                sort_order,
                is_active,
                created_by,
                updated_by,
                created_at,
                updated_at
            ) VALUES (
                :business_id,
                :form_key,
                :section_key,
                :option_value,
                :sort_order,
                1,
                :created_by,
                :updated_by,
                NOW(),
                NOW()
            )'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'form_key' => $formKey,
            'section_key' => $sectionKey,
            'option_value' => $optionValue,
            'sort_order' => $nextSort,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updateValue(int $businessId, int $id, string $optionValue, int $actorUserId): bool
    {
        $row = self::findForBusiness($businessId, $id);
        if ($row === null) {
            return false;
        }

        $optionValue = self::normalizeValue($optionValue);
        if ($optionValue === '') {
            throw new \RuntimeException('Option value is required.');
        }

        $formKey = (string) ($row['form_key'] ?? '');
        $sectionKey = (string) ($row['section_key'] ?? '');
        if (self::exists($businessId, $formKey, $sectionKey, $optionValue, $id)) {
            throw new \RuntimeException('Option already exists.');
        }

        $stmt = Database::connection()->prepare(
            'UPDATE form_select_values
             SET option_value = :option_value,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'option_value' => $optionValue,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function softDelete(int $businessId, int $id, int $actorUserId): bool
    {
        if ($businessId <= 0 || $id <= 0 || !self::isAvailable()) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE form_select_values
             SET is_active = 0,
                 deleted_at = NOW(),
                 deleted_by = :deleted_by,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<int, string>
     */
    public static function defaultOptions(string $sectionKey): array
    {
        $definition = self::sectionDefinition($sectionKey);
        if ($definition === null) {
            return [];
        }

        $defaults = is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [];
        return array_values(array_filter(array_map(static fn ($raw): string => self::normalizeValue((string) $raw), $defaults), static fn (string $value): bool => $value !== ''));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function rowsBySection(int $businessId): array
    {
        if ($businessId <= 0 || !self::isAvailable()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, form_key, section_key, option_value, sort_order, is_active
             FROM form_select_values
             WHERE business_id = :business_id
               AND deleted_at IS NULL
               AND is_active = 1
             ORDER BY form_key ASC, section_key ASC, sort_order ASC, option_value ASC, id ASC'
        );
        $stmt->execute(['business_id' => $businessId]);

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sectionKey = self::normalizeKey((string) ($row['section_key'] ?? ''));
            if ($sectionKey === '') {
                continue;
            }

            $result[$sectionKey][] = [
                'id' => (int) ($row['id'] ?? 0),
                'form_key' => self::normalizeKey((string) ($row['form_key'] ?? '')),
                'section_key' => $sectionKey,
                'option_value' => self::normalizeValue((string) ($row['option_value'] ?? '')),
                'sort_order' => (int) ($row['sort_order'] ?? 100),
            ];
        }

        return $result;
    }

    private static function exists(int $businessId, string $formKey, string $sectionKey, string $optionValue, ?int $excludeId = null): bool
    {
        if (!self::isAvailable()) {
            return false;
        }

        $sql = 'SELECT 1
                FROM form_select_values
                WHERE business_id = :business_id
                  AND form_key = :form_key
                  AND section_key = :section_key
                  AND deleted_at IS NULL
                  AND LOWER(option_value) = :option_value';
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':form_key', self::normalizeKey($formKey));
        $stmt->bindValue(':section_key', self::normalizeKey($sectionKey));
        $stmt->bindValue(':option_value', mb_strtolower(self::normalizeValue($optionValue)));
        if ($excludeId !== null && $excludeId > 0) {
            $stmt->bindValue(':exclude_id', $excludeId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        return is_array($stmt->fetch());
    }

    private static function nextSortOrder(int $businessId, string $formKey, string $sectionKey): int
    {
        if (!self::isAvailable()) {
            return 100;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0)
             FROM form_select_values
             WHERE business_id = :business_id
               AND form_key = :form_key
               AND section_key = :section_key
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'form_key' => self::normalizeKey($formKey),
            'section_key' => self::normalizeKey($sectionKey),
        ]);

        $maxSort = (int) $stmt->fetchColumn();
        return $maxSort + 10;
    }

    private static function normalizeKey(string $value): string
    {
        return strtolower(trim($value));
    }

    private static function normalizeValue(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private static function ensureDefaultsForBusiness(int $businessId): void
    {
        if ($businessId <= 0 || !self::isAvailable()) {
            return;
        }

        if (isset(self::$defaultsEnsured[$businessId])) {
            return;
        }

        self::ensureDefaults($businessId, 0);
        self::$defaultsEnsured[$businessId] = true;
    }
}
