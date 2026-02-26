<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class DataQuality
{
    public static function duplicateQueue(int $limitPerEntity = 30): array
    {
        return [
            'clients' => self::clientDuplicates($limitPerEntity),
            'companies' => self::companyDuplicates($limitPerEntity),
            'jobs' => self::jobDuplicates($limitPerEntity),
        ];
    }

    public static function mergeClients(int $sourceId, int $targetId, ?int $actorId = null): bool
    {
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            return false;
        }

        $source = Client::findById($sourceId);
        $target = Client::findById($targetId);
        if (!$source || !$target) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            self::updateForeignKey('jobs', 'client_id', $sourceId, $targetId);
            if (Schema::hasColumn('jobs', 'contact_client_id')) {
                self::updateForeignKey('jobs', 'contact_client_id', $sourceId, $targetId);
            }
            if (Schema::hasColumn('jobs', 'owner_client_id')) {
                self::updateForeignKey('jobs', 'owner_client_id', $sourceId, $targetId);
            }
            if (Schema::hasColumn('jobs', 'job_owner_type') && Schema::hasColumn('jobs', 'job_owner_id')) {
                $sql = 'UPDATE jobs
                        SET job_owner_id = :target_id,
                            updated_at = NOW()
                        WHERE job_owner_type = "client"
                          AND job_owner_id = :source_id';
                $params = [
                    'target_id' => $targetId,
                    'source_id' => $sourceId,
                ];
                if (Schema::hasColumn('jobs', 'business_id')) {
                    $sql .= ' AND business_id = :business_id';
                    $params['business_id'] = self::currentBusinessId();
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            self::updateForeignKey('prospects', 'client_id', $sourceId, $targetId);
            self::updateForeignKey('estates', 'client_id', $sourceId, $targetId);
            self::updateForeignKey('client_contacts', 'client_id', $sourceId, $targetId);

            if (self::tableExists('todos') && Schema::hasColumn('todos', 'link_type') && Schema::hasColumn('todos', 'link_id')) {
                $sql = 'UPDATE todos
                        SET link_id = :target_id,
                            updated_at = NOW()
                        WHERE link_type = "client"
                          AND link_id = :source_id';
                $params = [
                    'target_id' => $targetId,
                    'source_id' => $sourceId,
                ];
                if (Schema::hasColumn('todos', 'business_id')) {
                    $sql .= ' AND business_id = :business_id';
                    $params['business_id'] = self::currentBusinessId();
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            if (self::tableExists('attachments') && Schema::hasColumn('attachments', 'link_type') && Schema::hasColumn('attachments', 'link_id')) {
                $sql = 'UPDATE attachments
                        SET link_id = :target_id,
                            updated_at = NOW()
                        WHERE link_type = "client"
                          AND link_id = :source_id';
                $params = [
                    'target_id' => $targetId,
                    'source_id' => $sourceId,
                ];
                if (Schema::hasColumn('attachments', 'business_id')) {
                    $sql .= ' AND business_id = :business_id';
                    $params['business_id'] = self::currentBusinessId();
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            if (self::tableExists('companies_x_clients')) {
                self::updateForeignKey('companies_x_clients', 'client_id', $sourceId, $targetId);
                $pdo->exec(
                    'DELETE c1
                     FROM companies_x_clients c1
                     INNER JOIN companies_x_clients c2
                         ON c1.company_id = c2.company_id
                        AND c1.client_id = c2.client_id
                        AND c1.id > c2.id
                     WHERE c1.deleted_at IS NULL
                       AND c2.deleted_at IS NULL'
                );
            }

            $sets = [
                'active = 0',
                'deleted_at = COALESCE(deleted_at, NOW())',
                'updated_at = NOW()',
            ];
            $params = ['id' => $sourceId];
            if ($actorId !== null && Schema::hasColumn('clients', 'updated_by')) {
                $sets[] = 'updated_by = :updated_by';
                $params['updated_by'] = $actorId;
            }
            if ($actorId !== null && Schema::hasColumn('clients', 'deleted_by')) {
                $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
                $params['deleted_by'] = $actorId;
            }

            $stmt = $pdo->prepare(
                'UPDATE clients
                 SET ' . implode(', ', $sets) . '
                 WHERE id = :id' . (Schema::hasColumn('clients', 'business_id') ? ' AND business_id = :business_id' : '')
            );
            if (Schema::hasColumn('clients', 'business_id')) {
                $params['business_id'] = self::currentBusinessId();
            }
            $stmt->execute($params);

            $pdo->commit();
            return true;
        } catch (\Throwable) {
            $pdo->rollBack();
            return false;
        }
    }

    public static function mergeCompanies(int $sourceId, int $targetId, ?int $actorId = null): bool
    {
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            return false;
        }

        $source = Company::findById($sourceId);
        $target = Company::findById($targetId);
        if (!$source || !$target) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if (self::tableExists('companies_x_clients')) {
                self::updateForeignKey('companies_x_clients', 'company_id', $sourceId, $targetId);
                $pdo->exec(
                    'DELETE c1
                     FROM companies_x_clients c1
                     INNER JOIN companies_x_clients c2
                         ON c1.company_id = c2.company_id
                        AND c1.client_id = c2.client_id
                        AND c1.id > c2.id
                     WHERE c1.deleted_at IS NULL
                       AND c2.deleted_at IS NULL'
                );
            }

            if (Schema::hasColumn('jobs', 'job_owner_type') && Schema::hasColumn('jobs', 'job_owner_id')) {
                $sql = 'UPDATE jobs
                        SET job_owner_id = :target_id,
                            updated_at = NOW()
                        WHERE job_owner_type = "company"
                          AND job_owner_id = :source_id';
                $params = [
                    'target_id' => $targetId,
                    'source_id' => $sourceId,
                ];
                if (Schema::hasColumn('jobs', 'business_id')) {
                    $sql .= ' AND business_id = :business_id';
                    $params['business_id'] = self::currentBusinessId();
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            if (Schema::hasColumn('jobs', 'owner_company_id')) {
                self::updateForeignKey('jobs', 'owner_company_id', $sourceId, $targetId);
            }

            if (self::tableExists('todos') && Schema::hasColumn('todos', 'link_type') && Schema::hasColumn('todos', 'link_id')) {
                $sql = 'UPDATE todos
                        SET link_id = :target_id,
                            updated_at = NOW()
                        WHERE link_type = "company"
                          AND link_id = :source_id';
                $params = [
                    'target_id' => $targetId,
                    'source_id' => $sourceId,
                ];
                if (Schema::hasColumn('todos', 'business_id')) {
                    $sql .= ' AND business_id = :business_id';
                    $params['business_id'] = self::currentBusinessId();
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            if (self::tableExists('attachments') && Schema::hasColumn('attachments', 'link_type') && Schema::hasColumn('attachments', 'link_id')) {
                $sql = 'UPDATE attachments
                        SET link_id = :target_id,
                            updated_at = NOW()
                        WHERE link_type = "company"
                          AND link_id = :source_id';
                $params = [
                    'target_id' => $targetId,
                    'source_id' => $sourceId,
                ];
                if (Schema::hasColumn('attachments', 'business_id')) {
                    $sql .= ' AND business_id = :business_id';
                    $params['business_id'] = self::currentBusinessId();
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            $sets = [
                'active = 0',
                'deleted_at = COALESCE(deleted_at, NOW())',
                'updated_at = NOW()',
            ];
            $params = ['id' => $sourceId];
            if ($actorId !== null && Schema::hasColumn('companies', 'updated_by')) {
                $sets[] = 'updated_by = :updated_by';
                $params['updated_by'] = $actorId;
            }
            if ($actorId !== null && Schema::hasColumn('companies', 'deleted_by')) {
                $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
                $params['deleted_by'] = $actorId;
            }

            $stmt = $pdo->prepare(
                'UPDATE companies
                 SET ' . implode(', ', $sets) . '
                 WHERE id = :id' . (Schema::hasColumn('companies', 'business_id') ? ' AND business_id = :business_id' : '')
            );
            if (Schema::hasColumn('companies', 'business_id')) {
                $params['business_id'] = self::currentBusinessId();
            }
            $stmt->execute($params);

            $pdo->commit();
            return true;
        } catch (\Throwable) {
            $pdo->rollBack();
            return false;
        }
    }

    public static function mergeJobs(int $sourceId, int $targetId, ?int $actorId = null): bool
    {
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            return false;
        }

        $source = Job::findById($sourceId);
        $target = Job::findById($targetId);
        if (!$source || !$target) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            self::updateForeignKey('expenses', 'job_id', $sourceId, $targetId);
            self::updateForeignKey('sales', 'job_id', $sourceId, $targetId);
            self::updateForeignKey('employee_time_entries', 'job_id', $sourceId, $targetId);
            self::updateForeignKey('job_actions', 'job_id', $sourceId, $targetId);
            if (self::tableExists('job_disposal_events')) {
                self::updateForeignKey('job_disposal_events', 'job_id', $sourceId, $targetId);
            }
            if (self::tableExists('job_estimate_invoices')) {
                self::updateForeignKey('job_estimate_invoices', 'job_id', $sourceId, $targetId);
            }
            if (self::tableExists('job_estimate_invoice_events')) {
                self::updateForeignKey('job_estimate_invoice_events', 'job_id', $sourceId, $targetId);
            }
            if (self::tableExists('job_estimate_invoice_line_items')) {
                self::updateForeignKey('job_estimate_invoice_line_items', 'job_id', $sourceId, $targetId);
            }

            if (self::tableExists('job_crew')) {
                $stmt = $pdo->prepare(
                    'INSERT IGNORE INTO job_crew (job_id, employee_id, active, deleted_at, created_at, updated_at)
                     SELECT :target_id,
                            jc.employee_id,
                            COALESCE(jc.active, 1),
                            jc.deleted_at,
                            jc.created_at,
                            jc.updated_at
                     FROM job_crew jc
                     WHERE jc.job_id = :source_id'
                );
                $stmt->execute([
                    'target_id' => $targetId,
                    'source_id' => $sourceId,
                ]);

                $delete = $pdo->prepare('DELETE FROM job_crew WHERE job_id = :source_id');
                $delete->execute(['source_id' => $sourceId]);
            }

            if (self::tableExists('todos') && Schema::hasColumn('todos', 'link_type') && Schema::hasColumn('todos', 'link_id')) {
                $sql = 'UPDATE todos
                        SET link_id = :target_id,
                            updated_at = NOW()
                        WHERE link_type = "job"
                          AND link_id = :source_id';
                $params = [
                    'target_id' => $targetId,
                    'source_id' => $sourceId,
                ];
                if (Schema::hasColumn('todos', 'business_id')) {
                    $sql .= ' AND business_id = :business_id';
                    $params['business_id'] = self::currentBusinessId();
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            if (self::tableExists('attachments') && Schema::hasColumn('attachments', 'link_type') && Schema::hasColumn('attachments', 'link_id')) {
                $sql = 'UPDATE attachments
                        SET link_id = :target_id,
                            updated_at = NOW()
                        WHERE link_type = "job"
                          AND link_id = :source_id';
                $params = [
                    'target_id' => $targetId,
                    'source_id' => $sourceId,
                ];
                if (Schema::hasColumn('attachments', 'business_id')) {
                    $sql .= ' AND business_id = :business_id';
                    $params['business_id'] = self::currentBusinessId();
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            Job::softDelete($sourceId, $actorId);

            $pdo->commit();
            return true;
        } catch (\Throwable) {
            $pdo->rollBack();
            return false;
        }
    }

    private static function clientDuplicates(int $limit): array
    {
        $sql = 'SELECT id, first_name, last_name, business_name, email, phone, city, state, zip
                FROM clients
                WHERE deleted_at IS NULL
                  AND COALESCE(active, 1) = 1';
        $params = [];
        if (Schema::hasColumn('clients', 'business_id')) {
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $sql .= '
                ORDER BY id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return self::buildDuplicates($rows, 'client', [
            [
                'label' => 'Matching email',
                'key' => static function (array $row): ?string {
                    $email = strtolower(trim((string) ($row['email'] ?? '')));
                    return $email !== '' ? 'email:' . $email : null;
                },
            ],
            [
                'label' => 'Matching phone',
                'key' => static function (array $row): ?string {
                    $phone = self::normalizePhone((string) ($row['phone'] ?? ''));
                    return strlen($phone) >= 7 ? 'phone:' . $phone : null;
                },
            ],
            [
                'label' => 'Matching name + ZIP',
                'key' => static function (array $row): ?string {
                    $name = self::normalizeName((string) ($row['business_name'] ?? ''), (string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? ''));
                    $zip = self::normalizeZip((string) ($row['zip'] ?? ''));
                    return ($name !== '' && $zip !== '') ? 'name_zip:' . $name . ':' . $zip : null;
                },
            ],
        ], $limit, static function (array $row): array {
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['business_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            }
            if ($name === '') {
                $name = 'Client #' . $id;
            }

            $meta = [];
            if (!empty($row['email'])) {
                $meta[] = (string) $row['email'];
            }
            if (!empty($row['phone'])) {
                $meta[] = format_phone((string) $row['phone']);
            }
            $location = trim((string) ($row['city'] ?? '') . ((string) ($row['city'] ?? '') !== '' && (string) ($row['state'] ?? '') !== '' ? ', ' : '') . (string) ($row['state'] ?? ''));
            if ($location !== '') {
                $meta[] = $location;
            }

            return [
                'id' => $id,
                'label' => $name,
                'url' => '/clients/' . $id,
                'meta' => implode(' • ', $meta),
            ];
        });
    }

    private static function companyDuplicates(int $limit): array
    {
        $sql = 'SELECT id, name, phone, city, state
                FROM companies
                WHERE deleted_at IS NULL
                  AND COALESCE(active, 1) = 1';
        $params = [];
        if (Schema::hasColumn('companies', 'business_id')) {
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $sql .= '
                ORDER BY id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return self::buildDuplicates($rows, 'company', [
            [
                'label' => 'Matching company name',
                'key' => static function (array $row): ?string {
                    $name = self::normalizeText((string) ($row['name'] ?? ''));
                    return $name !== '' ? 'name:' . $name : null;
                },
            ],
            [
                'label' => 'Matching phone',
                'key' => static function (array $row): ?string {
                    $phone = self::normalizePhone((string) ($row['phone'] ?? ''));
                    return strlen($phone) >= 7 ? 'phone:' . $phone : null;
                },
            ],
        ], $limit, static function (array $row): array {
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = 'Company #' . $id;
            }

            $meta = [];
            if (!empty($row['phone'])) {
                $meta[] = format_phone((string) $row['phone']);
            }
            $location = trim((string) ($row['city'] ?? '') . ((string) ($row['city'] ?? '') !== '' && (string) ($row['state'] ?? '') !== '' ? ', ' : '') . (string) ($row['state'] ?? ''));
            if ($location !== '') {
                $meta[] = $location;
            }

            return [
                'id' => $id,
                'label' => $name,
                'url' => '/companies/' . $id,
                'meta' => implode(' • ', $meta),
            ];
        });
    }

    private static function jobDuplicates(int $limit): array
    {
        $sql = 'SELECT id, name, address_1, city, state, zip, phone, job_status
                FROM jobs
                WHERE deleted_at IS NULL
                  AND COALESCE(active, 1) = 1';
        $params = [];
        if (Schema::hasColumn('jobs', 'business_id')) {
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $sql .= '
                ORDER BY id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return self::buildDuplicates($rows, 'job', [
            [
                'label' => 'Matching name + address',
                'key' => static function (array $row): ?string {
                    $name = self::normalizeText((string) ($row['name'] ?? ''));
                    $address = self::normalizeText((string) ($row['address_1'] ?? ''));
                    $zip = self::normalizeZip((string) ($row['zip'] ?? ''));
                    return ($name !== '' && $address !== '') ? 'name_address:' . $name . ':' . $address . ':' . $zip : null;
                },
            ],
            [
                'label' => 'Matching phone + city',
                'key' => static function (array $row): ?string {
                    $phone = self::normalizePhone((string) ($row['phone'] ?? ''));
                    $city = self::normalizeText((string) ($row['city'] ?? ''));
                    return (strlen($phone) >= 7 && $city !== '') ? 'phone_city:' . $phone . ':' . $city : null;
                },
            ],
        ], $limit, static function (array $row): array {
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = 'Job #' . $id;
            }

            $meta = [];
            if (!empty($row['address_1'])) {
                $meta[] = (string) $row['address_1'];
            }
            $location = trim((string) ($row['city'] ?? '') . ((string) ($row['city'] ?? '') !== '' && (string) ($row['state'] ?? '') !== '' ? ', ' : '') . (string) ($row['state'] ?? ''));
            if ($location !== '') {
                $meta[] = $location;
            }
            if (!empty($row['job_status'])) {
                $meta[] = 'Status: ' . (string) $row['job_status'];
            }

            return [
                'id' => $id,
                'label' => $name,
                'url' => '/jobs/' . $id,
                'meta' => implode(' • ', $meta),
            ];
        });
    }

    private static function buildDuplicates(
        array $rows,
        string $entity,
        array $rules,
        int $limit,
        callable $presenter
    ): array {
        $groups = [];
        foreach ($rules as $rule) {
            $ruleLabel = (string) ($rule['label'] ?? 'Potential duplicate');
            $keyFn = $rule['key'] ?? null;
            if (!is_callable($keyFn)) {
                continue;
            }

            $bucket = [];
            foreach ($rows as $row) {
                $key = $keyFn($row);
                if ($key === null || trim($key) === '') {
                    continue;
                }
                $bucket[$key][] = $row;
            }

            foreach ($bucket as $matchKey => $members) {
                if (count($members) < 2) {
                    continue;
                }

                $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $members);
                sort($ids);
                $signature = $entity . '|' . implode('-', $ids);
                if (isset($groups[$signature])) {
                    continue;
                }

                $records = array_map($presenter, $members);
                $groups[$signature] = [
                    'entity' => $entity,
                    'reason' => $ruleLabel,
                    'match_key' => $matchKey,
                    'records' => $records,
                ];
            }
        }

        $queue = array_values($groups);
        usort($queue, static function (array $a, array $b): int {
            $countCompare = count($b['records'] ?? []) <=> count($a['records'] ?? []);
            if ($countCompare !== 0) {
                return $countCompare;
            }

            return strcmp((string) ($a['reason'] ?? ''), (string) ($b['reason'] ?? ''));
        });

        return array_slice($queue, 0, max(1, $limit));
    }

    private static function updateForeignKey(string $table, string $column, int $sourceId, int $targetId): void
    {
        if (!self::tableExists($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $sql = 'UPDATE ' . $table . '
                SET ' . $column . ' = :target_id
                WHERE ' . $column . ' = :source_id';
        $params = [
            'target_id' => $targetId,
            'source_id' => $sourceId,
        ];

        if (Schema::hasColumn($table, 'business_id')) {
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    private static function tableExists(string $table): bool
    {
        $schema = trim((string) config('database.database', ''));
        if ($schema === '') {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table
             LIMIT 1'
        );
        $stmt->execute([
            'schema' => $schema,
            'table' => $table,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private static function normalizeText(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';
        return $normalized;
    }

    private static function normalizeName(string $businessName, string $firstName, string $lastName): string
    {
        $business = self::normalizeText($businessName);
        if ($business !== '') {
            return $business;
        }

        return self::normalizeText(trim($firstName . ' ' . $lastName));
    }

    private static function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }
        return $digits;
    }

    private static function normalizeZip(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        return substr($digits, 0, 5);
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(0, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }
}
