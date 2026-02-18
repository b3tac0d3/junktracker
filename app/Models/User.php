<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class User
{
    public static function ensureAuthColumns(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $pdo = Database::connection();

        $columns = [
            'password_setup_token_hash' => 'ALTER TABLE users ADD COLUMN password_setup_token_hash VARCHAR(255) NULL AFTER password_hash',
            'password_setup_expires_at' => 'ALTER TABLE users ADD COLUMN password_setup_expires_at DATETIME NULL AFTER password_setup_token_hash',
            'password_setup_sent_at' => 'ALTER TABLE users ADD COLUMN password_setup_sent_at DATETIME NULL AFTER password_setup_expires_at',
            'password_setup_used_at' => 'ALTER TABLE users ADD COLUMN password_setup_used_at DATETIME NULL AFTER password_setup_sent_at',
            'two_factor_code_hash' => 'ALTER TABLE users ADD COLUMN two_factor_code_hash VARCHAR(255) NULL AFTER password_setup_used_at',
            'two_factor_expires_at' => 'ALTER TABLE users ADD COLUMN two_factor_expires_at DATETIME NULL AFTER two_factor_code_hash',
            'two_factor_sent_at' => 'ALTER TABLE users ADD COLUMN two_factor_sent_at DATETIME NULL AFTER two_factor_expires_at',
            'last_2fa_at' => 'ALTER TABLE users ADD COLUMN last_2fa_at DATETIME NULL AFTER two_factor_sent_at',
        ];

        foreach ($columns as $column => $sql) {
            if (!Schema::hasColumn('users', $column)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable) {
                    // handled by migration on environments with restricted DDL
                }
            }
        }

        try {
            $pdo->exec('CREATE INDEX idx_users_password_setup_expires ON users (password_setup_expires_at)');
        } catch (Throwable) {
            // index exists
        }

        $ensured = true;
    }

    public static function findByEmail(string $email): ?array
    {
        self::ensureAuthColumns();

        $sql = 'SELECT id,
                       email,
                       first_name,
                       last_name,
                       role,
                       password_hash,
                       is_active,
                       password_setup_token_hash,
                       password_setup_expires_at,
                       password_setup_sent_at,
                       password_setup_used_at,
                       two_factor_code_hash,
                       two_factor_expires_at,
                       two_factor_sent_at,
                       last_2fa_at
                FROM users
                WHERE email = :email
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findById(int $id): ?array
    {
        self::ensureAuthColumns();

        $createdBySelect = Schema::hasColumn('users', 'created_by') ? 'created_by' : 'NULL';
        $updatedBySelect = Schema::hasColumn('users', 'updated_by') ? 'updated_by' : 'NULL';
        $deletedAtSelect = Schema::hasColumn('users', 'deleted_at') ? 'deleted_at' : 'NULL';
        $deletedBySelect = Schema::hasColumn('users', 'deleted_by') ? 'deleted_by' : 'NULL';

        $sql = 'SELECT id,
                       email,
                       first_name,
                       last_name,
                       role,
                       is_active,
                       created_at,
                       ' . $createdBySelect . ' AS created_by,
                       updated_at,
                       ' . $updatedBySelect . ' AS updated_by,
                       ' . $deletedAtSelect . ' AS deleted_at,
                       ' . $deletedBySelect . ' AS deleted_by,
                       password_setup_sent_at,
                       password_setup_expires_at,
                       password_setup_used_at,
                       last_2fa_at
                FROM users
                WHERE id = :id
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByIdWithPassword(int $id): ?array
    {
        self::ensureAuthColumns();

        $sql = 'SELECT id,
                       email,
                       first_name,
                       last_name,
                       role,
                       password_hash,
                       is_active,
                       password_setup_token_hash,
                       password_setup_expires_at,
                       password_setup_sent_at,
                       password_setup_used_at,
                       two_factor_code_hash,
                       two_factor_expires_at,
                       two_factor_sent_at,
                       last_2fa_at
                FROM users
                WHERE id = :id
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function search(string $term = '', string $status = 'active'): array
    {
        self::ensureAuthColumns();

        $sql = 'SELECT id,
                       first_name,
                       last_name,
                       email,
                       role,
                       is_active,
                       created_at,
                       password_setup_sent_at,
                       password_setup_expires_at,
                       password_setup_used_at,
                       last_2fa_at
                FROM users';
        $params = [];
        $where = [];

        if ($status === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'is_active = 0';
        }

        if ($term !== '') {
            $where[] = '(email LIKE :term OR first_name LIKE :term OR last_name LIKE :term)';
            $params['term'] = '%' . $term . '%';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY last_name, first_name';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function emailInUse(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id
                FROM users
                WHERE LOWER(email) = LOWER(:email)';
        $params = ['email' => trim($email)];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        self::ensureAuthColumns();

        $columns = ['email', 'first_name', 'last_name', 'role', 'password_hash', 'is_active', 'created_at', 'updated_at'];
        $values = [':email', ':first_name', ':last_name', ':role', ':password_hash', ':is_active', 'NOW()', 'NOW()'];

        $password = trim((string) ($data['password'] ?? ''));
        $passwordHash = $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : null;

        $params = [
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'],
            'password_hash' => $passwordHash,
            'is_active' => $data['is_active'],
        ];

        if ($actorId !== null && Schema::hasColumn('users', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('users', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO users (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function issuePasswordSetupToken(int $userId, string $rawToken, int $hours = 72, ?int $actorId = null): void
    {
        self::ensureAuthColumns();

        if ($userId <= 0 || trim($rawToken) === '') {
            return;
        }

        $tokenHash = self::tokenHash($rawToken);
        $hours = max(1, min($hours, 168));

        $sql = 'UPDATE users
                SET password_setup_token_hash = :token_hash,
                    password_setup_expires_at = DATE_ADD(NOW(), INTERVAL ' . $hours . ' HOUR),
                    password_setup_sent_at = NOW(),
                    password_setup_used_at = NULL,
                    updated_at = NOW()';
        $params = [
            'token_hash' => $tokenHash,
            'id' => $userId,
        ];

        if ($actorId !== null && Schema::hasColumn('users', 'updated_by')) {
            $sql .= ', updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function findByPasswordSetupToken(string $rawToken): ?array
    {
        self::ensureAuthColumns();

        if (trim($rawToken) === '') {
            return null;
        }

        $tokenHash = self::tokenHash($rawToken);
        $sql = 'SELECT id,
                       email,
                       first_name,
                       last_name,
                       role,
                       is_active,
                       password_setup_expires_at,
                       password_setup_used_at
                FROM users
                WHERE password_setup_token_hash = :token_hash
                  AND password_setup_expires_at IS NOT NULL
                  AND password_setup_expires_at >= NOW()
                  AND password_setup_used_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['token_hash' => $tokenHash]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function completePasswordSetup(int $id, string $password, ?int $actorId = null): void
    {
        self::ensureAuthColumns();

        if ($id <= 0 || trim($password) === '') {
            return;
        }

        $sql = 'UPDATE users
                SET password_hash = :password_hash,
                    password_setup_token_hash = NULL,
                    password_setup_expires_at = NULL,
                    password_setup_used_at = NOW(),
                    updated_at = NOW()';
        $params = [
            'id' => $id,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ];

        if ($actorId !== null && Schema::hasColumn('users', 'updated_by')) {
            $sql .= ', updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function saveTwoFactorCode(int $userId, string $code, int $minutes = 15): void
    {
        self::ensureAuthColumns();

        if ($userId <= 0 || trim($code) === '') {
            return;
        }

        $minutes = max(5, min($minutes, 60));
        $codeHash = self::twoFactorHash($userId, $code);

        $sql = 'UPDATE users
                SET two_factor_code_hash = :code_hash,
                    two_factor_expires_at = DATE_ADD(NOW(), INTERVAL ' . $minutes . ' MINUTE),
                    two_factor_sent_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'code_hash' => $codeHash,
            'id' => $userId,
        ]);
    }

    public static function verifyTwoFactorCode(int $userId, string $code): bool
    {
        self::ensureAuthColumns();

        if ($userId <= 0) {
            return false;
        }

        $user = self::findByIdWithPassword($userId);
        if (!$user) {
            return false;
        }

        $storedHash = (string) ($user['two_factor_code_hash'] ?? '');
        $expiresAt = trim((string) ($user['two_factor_expires_at'] ?? ''));
        if ($storedHash === '' || $expiresAt === '') {
            return false;
        }

        $expiresTs = strtotime($expiresAt);
        if ($expiresTs === false || $expiresTs < time()) {
            return false;
        }

        $candidate = self::twoFactorHash($userId, $code);
        return hash_equals($storedHash, $candidate);
    }

    public static function clearTwoFactorCode(int $userId, bool $markVerified): void
    {
        self::ensureAuthColumns();

        if ($userId <= 0) {
            return;
        }

        $sql = 'UPDATE users
                SET two_factor_code_hash = NULL,
                    two_factor_expires_at = NULL,
                    updated_at = NOW()';

        if ($markVerified) {
            $sql .= ', last_2fa_at = NOW()';
        }

        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $userId]);
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        self::ensureAuthColumns();

        $fields = [
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ];

        $sql = 'UPDATE users SET email = :email, first_name = :first_name, last_name = :last_name, role = :role, is_active = :is_active';

        if (!empty($data['password'])) {
            $sql .= ', password_hash = :password_hash';
            $fields['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
            $sql .= ', password_setup_token_hash = NULL, password_setup_expires_at = NULL';
        }

        $sql .= ', updated_at = NOW()';
        if ($actorId !== null && Schema::hasColumn('users', 'updated_by')) {
            $sql .= ', updated_by = :updated_by';
            $fields['updated_by'] = $actorId;
        }

        $sql .= ' WHERE id = :id';
        $fields['id'] = $id;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($fields);
    }

    public static function updateProfile(int $id, array $data, ?int $actorId = null): void
    {
        self::ensureAuthColumns();

        $fields = [
            'id' => $id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
        ];

        $sql = 'UPDATE users
                SET first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    updated_at = NOW()';

        if (!empty($data['password'])) {
            $sql .= ', password_hash = :password_hash';
            $fields['password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }

        if ($actorId !== null && Schema::hasColumn('users', 'updated_by')) {
            $sql .= ', updated_by = :updated_by';
            $fields['updated_by'] = $actorId;
        }

        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($fields);
    }

    public static function deactivate(int $id, ?int $actorId = null): void
    {
        self::ensureAuthColumns();

        $sets = [
            'is_active = 0',
            'updated_at = NOW()',
        ];
        $params = ['id' => $id];

        if ($actorId !== null && Schema::hasColumn('users', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if (Schema::hasColumn('users', 'deleted_at')) {
            $sets[] = 'deleted_at = COALESCE(deleted_at, NOW())';
        }
        if ($actorId !== null && Schema::hasColumn('users', 'deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    private static function tokenHash(string $raw): string
    {
        return hash('sha256', $raw . '|' . app_key());
    }

    private static function twoFactorHash(int $userId, string $code): string
    {
        return hash('sha256', $userId . '|' . trim($code) . '|' . app_key());
    }
}
