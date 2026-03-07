<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Expense
{
    /**
     * @return array<int, string>
     */
    public static function categoryOptions(int $businessId): array
    {
        $defaults = ['Fuel', 'Disposal', 'Materials', 'Labor', 'Payroll', 'Supplies', 'Rent', 'Utilities', 'Other'];
        if (!SchemaInspector::hasTable('expenses')) {
            return $defaults;
        }

        $categorySql = SchemaInspector::hasColumn('expenses', 'category')
            ? 'category'
            : (SchemaInspector::hasColumn('expenses', 'expense_type')
                ? 'expense_type'
                : (SchemaInspector::hasColumn('expenses', 'type') ? 'type' : ''));
        if ($categorySql === '') {
            return $defaults;
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('expenses', 'business_id') ? 'business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'deleted_at IS NULL' : '1=1';
        $where[] = "COALESCE({$categorySql}, '') <> ''";

        $sql = "SELECT DISTINCT {$categorySql}
                FROM expenses
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$categorySql} ASC";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $dbOptions = is_array($rows)
            ? array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $rows), static fn ($value): bool => $value !== ''))
            : [];

        $merged = array_values(array_unique(array_merge($defaults, $dbOptions)));
        natcasesort($merged);
        return array_values($merged);
    }

    public static function indexList(int $businessId, string $search = '', string $scope = 'all', int $limit = 25, int $offset = 0): array
    {
        if (!SchemaInspector::hasTable('expenses')) {
            return [];
        }

        $query = trim($search);
        $scope = strtolower(trim($scope));
        if (!in_array($scope, ['all', 'general', 'job'], true)) {
            $scope = 'all';
        }

        $dateSql = SchemaInspector::hasColumn('expenses', 'expense_date')
            ? 'e.expense_date'
            : (SchemaInspector::hasColumn('expenses', 'date') ? 'e.date' : 'NULL');
        $amountSql = SchemaInspector::hasColumn('expenses', 'amount') ? 'e.amount' : '0';
        $nameSql = SchemaInspector::hasColumn('expenses', 'name') ? 'e.name' : "CONCAT('Expense #', e.id)";
        $categorySql = SchemaInspector::hasColumn('expenses', 'category')
            ? 'e.category'
            : (SchemaInspector::hasColumn('expenses', 'expense_type')
                ? 'e.expense_type'
                : (SchemaInspector::hasColumn('expenses', 'type') ? 'e.type' : 'NULL'));
        $paymentMethodSql = SchemaInspector::hasColumn('expenses', 'payment_method') ? 'e.payment_method' : 'NULL';
        $noteSql = SchemaInspector::hasColumn('expenses', 'note')
            ? 'e.note'
            : (SchemaInspector::hasColumn('expenses', 'notes') ? 'e.notes' : 'NULL');
        $referenceSql = SchemaInspector::hasColumn('expenses', 'reference_number') ? 'e.reference_number' : 'NULL';
        $createdSql = SchemaInspector::hasColumn('expenses', 'created_at') ? 'e.created_at' : 'NULL';
        $jobIdSql = SchemaInspector::hasColumn('expenses', 'job_id') ? 'e.job_id' : 'NULL';

        $jobTitleSql = 'NULL';
        $clientNameSql = 'NULL';
        $joinSql = '';
        if (SchemaInspector::hasColumn('expenses', 'job_id') && SchemaInspector::hasTable('jobs')) {
            $jobTitleSql = SchemaInspector::hasColumn('jobs', 'title')
                ? 'j.title'
                : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
            $joinSql .= ' LEFT JOIN jobs j ON j.id = e.job_id';
            if (SchemaInspector::hasColumn('jobs', 'business_id') && SchemaInspector::hasColumn('expenses', 'business_id')) {
                $joinSql .= ' AND j.business_id = e.business_id';
            }
            if (SchemaInspector::hasColumn('jobs', 'deleted_at')) {
                $joinSql .= ' AND j.deleted_at IS NULL';
            }

            if (SchemaInspector::hasColumn('jobs', 'client_id') && SchemaInspector::hasTable('clients')) {
                $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
                $joinSql .= ' LEFT JOIN clients c ON c.id = j.client_id';
                if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('expenses', 'business_id')) {
                    $joinSql .= ' AND c.business_id = e.business_id';
                }
                if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                    $joinSql .= ' AND c.deleted_at IS NULL';
                }
            }
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('expenses', 'business_id') ? 'e.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';
        if ($scope === 'general' && SchemaInspector::hasColumn('expenses', 'job_id')) {
            $where[] = 'e.job_id IS NULL';
        } elseif ($scope === 'job' && SchemaInspector::hasColumn('expenses', 'job_id')) {
            $where[] = 'e.job_id IS NOT NULL';
        }
        $where[] = "(
            :query = ''
            OR COALESCE({$nameSql}, '') LIKE :query_like_0
            OR COALESCE({$categorySql}, '') LIKE :query_like_1
            OR COALESCE({$noteSql}, '') LIKE :query_like_2
            OR CAST(e.id AS CHAR) LIKE :query_like_3
            OR COALESCE({$referenceSql}, '') LIKE :query_like_4
            OR COALESCE({$jobTitleSql}, '') LIKE :query_like_5
            OR COALESCE({$clientNameSql}, '') LIKE :query_like_6
            OR COALESCE({$paymentMethodSql}, '') LIKE :query_like_7
        )";

        $sql = "SELECT
                    e.id,
                    {$jobIdSql} AS job_id,
                    {$dateSql} AS expense_date,
                    {$amountSql} AS amount,
                    {$nameSql} AS name,
                    {$categorySql} AS category,
                    {$paymentMethodSql} AS payment_method,
                    {$noteSql} AS note,
                    {$referenceSql} AS reference_number,
                    {$createdSql} AS created_at,
                    {$jobTitleSql} AS job_title,
                    {$clientNameSql} AS client_name
                FROM expenses e
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE({$dateSql}, {$createdSql}) DESC, e.id DESC
                LIMIT :row_limit
                OFFSET :row_offset";

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_0', $queryLike);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->bindValue(':query_like_6', $queryLike);
        $stmt->bindValue(':query_like_7', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(int $businessId, string $search = '', string $scope = 'all'): int
    {
        if (!SchemaInspector::hasTable('expenses')) {
            return 0;
        }

        $query = trim($search);
        $scope = strtolower(trim($scope));
        if (!in_array($scope, ['all', 'general', 'job'], true)) {
            $scope = 'all';
        }

        $categorySql = SchemaInspector::hasColumn('expenses', 'category')
            ? 'e.category'
            : (SchemaInspector::hasColumn('expenses', 'expense_type')
                ? 'e.expense_type'
                : (SchemaInspector::hasColumn('expenses', 'type') ? 'e.type' : 'NULL'));
        $nameSql = SchemaInspector::hasColumn('expenses', 'name') ? 'e.name' : "CONCAT('Expense #', e.id)";
        $paymentMethodSql = SchemaInspector::hasColumn('expenses', 'payment_method') ? 'e.payment_method' : 'NULL';
        $noteSql = SchemaInspector::hasColumn('expenses', 'note')
            ? 'e.note'
            : (SchemaInspector::hasColumn('expenses', 'notes') ? 'e.notes' : 'NULL');
        $referenceSql = SchemaInspector::hasColumn('expenses', 'reference_number') ? 'e.reference_number' : 'NULL';

        $jobTitleSql = 'NULL';
        $clientNameSql = 'NULL';
        $joinSql = '';
        if (SchemaInspector::hasColumn('expenses', 'job_id') && SchemaInspector::hasTable('jobs')) {
            $jobTitleSql = SchemaInspector::hasColumn('jobs', 'title')
                ? 'j.title'
                : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
            $joinSql .= ' LEFT JOIN jobs j ON j.id = e.job_id';
            if (SchemaInspector::hasColumn('jobs', 'business_id') && SchemaInspector::hasColumn('expenses', 'business_id')) {
                $joinSql .= ' AND j.business_id = e.business_id';
            }
            if (SchemaInspector::hasColumn('jobs', 'deleted_at')) {
                $joinSql .= ' AND j.deleted_at IS NULL';
            }

            if (SchemaInspector::hasColumn('jobs', 'client_id') && SchemaInspector::hasTable('clients')) {
                $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
                $joinSql .= ' LEFT JOIN clients c ON c.id = j.client_id';
                if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('expenses', 'business_id')) {
                    $joinSql .= ' AND c.business_id = e.business_id';
                }
                if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                    $joinSql .= ' AND c.deleted_at IS NULL';
                }
            }
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('expenses', 'business_id') ? 'e.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';
        if ($scope === 'general' && SchemaInspector::hasColumn('expenses', 'job_id')) {
            $where[] = 'e.job_id IS NULL';
        } elseif ($scope === 'job' && SchemaInspector::hasColumn('expenses', 'job_id')) {
            $where[] = 'e.job_id IS NOT NULL';
        }
        $where[] = "(
            :query = ''
            OR COALESCE({$nameSql}, '') LIKE :query_like_0
            OR COALESCE({$categorySql}, '') LIKE :query_like_1
            OR COALESCE({$noteSql}, '') LIKE :query_like_2
            OR CAST(e.id AS CHAR) LIKE :query_like_3
            OR COALESCE({$referenceSql}, '') LIKE :query_like_4
            OR COALESCE({$jobTitleSql}, '') LIKE :query_like_5
            OR COALESCE({$clientNameSql}, '') LIKE :query_like_6
            OR COALESCE({$paymentMethodSql}, '') LIKE :query_like_7
        )";

        $sql = 'SELECT COUNT(*)
                FROM expenses e
                ' . $joinSql . '
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_0', $queryLike);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->bindValue(':query_like_6', $queryLike);
        $stmt->bindValue(':query_like_7', $queryLike);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function create(int $businessId, array $data, int $actorUserId, ?int $jobId = null): int
    {
        if (!SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'amount')) {
            return 0;
        }

        $columns = [];
        $placeholders = [];
        $params = [];

        $append = static function (string $column, string $placeholder, mixed $value) use (&$columns, &$placeholders, &$params): void {
            $columns[] = $column;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        };

        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $append('business_id', ':business_id', $businessId);
        }
        if (SchemaInspector::hasColumn('expenses', 'job_id')) {
            $append('job_id', ':job_id', ($jobId !== null && $jobId > 0) ? $jobId : null);
        }
        if (SchemaInspector::hasColumn('expenses', 'expense_date')) {
            $append('expense_date', ':expense_date', $data['expense_date'] ?? null);
        } elseif (SchemaInspector::hasColumn('expenses', 'date')) {
            $append('date', ':expense_date', $data['expense_date'] ?? null);
        }
        $append('amount', ':amount', (float) ($data['amount'] ?? 0));

        if (SchemaInspector::hasColumn('expenses', 'category')) {
            $append('category', ':category', trim((string) ($data['category'] ?? '')));
        } elseif (SchemaInspector::hasColumn('expenses', 'expense_type')) {
            $append('expense_type', ':category', trim((string) ($data['category'] ?? '')));
        } elseif (SchemaInspector::hasColumn('expenses', 'type')) {
            $append('type', ':category', trim((string) ($data['category'] ?? '')));
        }
        if (SchemaInspector::hasColumn('expenses', 'name')) {
            $append('name', ':name', trim((string) ($data['name'] ?? '')));
        }
        if (SchemaInspector::hasColumn('expenses', 'payment_method')) {
            $append('payment_method', ':payment_method', trim((string) ($data['payment_method'] ?? '')));
        }

        if (SchemaInspector::hasColumn('expenses', 'reference_number')) {
            $append('reference_number', ':reference_number', trim((string) ($data['reference_number'] ?? '')));
        }

        if (SchemaInspector::hasColumn('expenses', 'note')) {
            $append('note', ':note', trim((string) ($data['note'] ?? '')));
        } elseif (SchemaInspector::hasColumn('expenses', 'notes')) {
            $append('notes', ':note', trim((string) ($data['note'] ?? '')));
        }

        if (SchemaInspector::hasColumn('expenses', 'created_by')) {
            $append('created_by', ':created_by', $actorUserId > 0 ? $actorUserId : null);
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_by')) {
            $append('updated_by', ':updated_by', $actorUserId > 0 ? $actorUserId : null);
        }

        if (SchemaInspector::hasColumn('expenses', 'created_at')) {
            $columns[] = 'created_at';
            $placeholders[] = 'NOW()';
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_at')) {
            $columns[] = 'updated_at';
            $placeholders[] = 'NOW()';
        }

        $sql = 'INSERT INTO expenses (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function findForBusiness(int $businessId, int $expenseId): ?array
    {
        if ($expenseId <= 0 || !SchemaInspector::hasTable('expenses')) {
            return null;
        }

        $dateSql = SchemaInspector::hasColumn('expenses', 'expense_date')
            ? 'e.expense_date'
            : (SchemaInspector::hasColumn('expenses', 'date') ? 'e.date' : 'NULL');
        $amountSql = SchemaInspector::hasColumn('expenses', 'amount') ? 'e.amount' : '0';
        $nameSql = SchemaInspector::hasColumn('expenses', 'name') ? 'e.name' : "CONCAT('Expense #', e.id)";
        $categorySql = SchemaInspector::hasColumn('expenses', 'category')
            ? 'e.category'
            : (SchemaInspector::hasColumn('expenses', 'expense_type')
                ? 'e.expense_type'
                : (SchemaInspector::hasColumn('expenses', 'type') ? 'e.type' : 'NULL'));
        $paymentMethodSql = SchemaInspector::hasColumn('expenses', 'payment_method') ? 'e.payment_method' : 'NULL';
        $noteSql = SchemaInspector::hasColumn('expenses', 'note')
            ? 'e.note'
            : (SchemaInspector::hasColumn('expenses', 'notes') ? 'e.notes' : 'NULL');
        $referenceSql = SchemaInspector::hasColumn('expenses', 'reference_number') ? 'e.reference_number' : 'NULL';
        $createdSql = SchemaInspector::hasColumn('expenses', 'created_at') ? 'e.created_at' : 'NULL';
        $jobIdSql = SchemaInspector::hasColumn('expenses', 'job_id') ? 'e.job_id' : 'NULL';

        $jobTitleSql = 'NULL';
        $clientNameSql = 'NULL';
        $joinSql = '';
        if (SchemaInspector::hasColumn('expenses', 'job_id') && SchemaInspector::hasTable('jobs')) {
            $jobTitleSql = SchemaInspector::hasColumn('jobs', 'title')
                ? 'j.title'
                : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
            $joinSql .= ' LEFT JOIN jobs j ON j.id = e.job_id';
            if (SchemaInspector::hasColumn('jobs', 'business_id') && SchemaInspector::hasColumn('expenses', 'business_id')) {
                $joinSql .= ' AND j.business_id = e.business_id';
            }
            if (SchemaInspector::hasColumn('jobs', 'deleted_at')) {
                $joinSql .= ' AND j.deleted_at IS NULL';
            }

            if (SchemaInspector::hasColumn('jobs', 'client_id') && SchemaInspector::hasTable('clients')) {
                $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
                $joinSql .= ' LEFT JOIN clients c ON c.id = j.client_id';
                if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('expenses', 'business_id')) {
                    $joinSql .= ' AND c.business_id = e.business_id';
                }
                if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                    $joinSql .= ' AND c.deleted_at IS NULL';
                }
            }
        }

        $where = [];
        $where[] = 'e.id = :expense_id';
        $where[] = SchemaInspector::hasColumn('expenses', 'business_id') ? 'e.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    e.id,
                    {$jobIdSql} AS job_id,
                    {$dateSql} AS expense_date,
                    {$amountSql} AS amount,
                    {$nameSql} AS name,
                    {$categorySql} AS category,
                    {$paymentMethodSql} AS payment_method,
                    {$noteSql} AS note,
                    {$referenceSql} AS reference_number,
                    {$createdSql} AS created_at,
                    {$jobTitleSql} AS job_title,
                    {$clientNameSql} AS client_name
                FROM expenses e
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':expense_id', $expenseId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function updateForBusiness(int $businessId, int $expenseId, array $data, int $actorUserId): bool
    {
        if ($expenseId <= 0 || !SchemaInspector::hasTable('expenses')) {
            return false;
        }

        $setParts = [];
        $params = [
            'expense_id' => $expenseId,
        ];

        if (SchemaInspector::hasColumn('expenses', 'expense_date')) {
            $setParts[] = 'expense_date = :expense_date';
            $params['expense_date'] = $data['expense_date'] ?? null;
        } elseif (SchemaInspector::hasColumn('expenses', 'date')) {
            $setParts[] = '`date` = :expense_date';
            $params['expense_date'] = $data['expense_date'] ?? null;
        }

        if (SchemaInspector::hasColumn('expenses', 'amount')) {
            $setParts[] = 'amount = :amount';
            $params['amount'] = (float) ($data['amount'] ?? 0);
        }

        if (SchemaInspector::hasColumn('expenses', 'category')) {
            $setParts[] = 'category = :category';
            $params['category'] = trim((string) ($data['category'] ?? ''));
        } elseif (SchemaInspector::hasColumn('expenses', 'expense_type')) {
            $setParts[] = 'expense_type = :category';
            $params['category'] = trim((string) ($data['category'] ?? ''));
        } elseif (SchemaInspector::hasColumn('expenses', 'type')) {
            $setParts[] = '`type` = :category';
            $params['category'] = trim((string) ($data['category'] ?? ''));
        }

        if (SchemaInspector::hasColumn('expenses', 'payment_method')) {
            $setParts[] = 'payment_method = :payment_method';
            $params['payment_method'] = trim((string) ($data['payment_method'] ?? ''));
        }

        if (SchemaInspector::hasColumn('expenses', 'note')) {
            $setParts[] = 'note = :note';
            $params['note'] = trim((string) ($data['note'] ?? ''));
        } elseif (SchemaInspector::hasColumn('expenses', 'notes')) {
            $setParts[] = 'notes = :note';
            $params['note'] = trim((string) ($data['note'] ?? ''));
        }

        if (SchemaInspector::hasColumn('expenses', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }

        if ($setParts === []) {
            return false;
        }

        $whereParts = ['id = :expense_id'];
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $whereParts[] = 'business_id = :business_id';
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('expenses', 'deleted_at')) {
            $whereParts[] = 'deleted_at IS NULL';
        }

        $sql = 'UPDATE expenses
                SET ' . implode(', ', $setParts) . '
                WHERE ' . implode(' AND ', $whereParts) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public static function softDeleteForBusiness(int $businessId, int $expenseId, int $actorUserId): bool
    {
        if ($expenseId <= 0 || !SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'deleted_at')) {
            return false;
        }

        $setParts = ['deleted_at = NOW()'];
        $params = [
            'expense_id' => $expenseId,
        ];

        if (SchemaInspector::hasColumn('expenses', 'deleted_by')) {
            $setParts[] = 'deleted_by = :deleted_by';
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }

        $whereParts = ['id = :expense_id', 'deleted_at IS NULL'];
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $whereParts[] = 'business_id = :business_id';
            $params['business_id'] = $businessId;
        }

        $sql = 'UPDATE expenses
                SET ' . implode(', ', $setParts) . '
                WHERE ' . implode(' AND ', $whereParts) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}
