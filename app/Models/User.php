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
            'failed_login_count' => 'ALTER TABLE users ADD COLUMN failed_login_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_2fa_at',
            'last_failed_login_at' => 'ALTER TABLE users ADD COLUMN last_failed_login_at DATETIME NULL AFTER failed_login_count',
            'last_failed_login_ip' => 'ALTER TABLE users ADD COLUMN last_failed_login_ip VARCHAR(45) NULL AFTER last_failed_login_at',
            'locked_until' => 'ALTER TABLE users ADD COLUMN locked_until DATETIME NULL AFTER last_failed_login_ip',
            'last_login_at' => 'ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER locked_until',
            'two_factor_enabled' => 'ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER last_login_at',
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
                       last_2fa_at,
                       failed_login_count,
                       last_failed_login_at,
                       last_failed_login_ip,
                       locked_until,
                       last_login_at,
                       two_factor_enabled
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
                       password_hash,
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
                       last_2fa_at,
                       failed_login_count,
                       last_failed_login_at,
                       last_failed_login_ip,
                       locked_until,
                       last_login_at,
                       two_factor_enabled
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
                       last_2fa_at,
                       failed_login_count,
                       last_failed_login_at,
                       last_failed_login_ip,
                       locked_until,
                       last_login_at,
                       two_factor_enabled
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
                       password_hash,
                       is_active,
                       created_at,
                       updated_at,
                       COALESCE(last_login_at, updated_at, created_at) AS last_activity_at,
                       password_setup_sent_at,
                       password_setup_expires_at,
                       password_setup_used_at,
                       last_2fa_at,
                       failed_login_count,
                       last_failed_login_at,
                       last_failed_login_ip,
                       locked_until,
                       last_login_at,
                       two_factor_enabled
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
        if (Schema::hasColumn('users', 'two_factor_enabled')) {
            $columns[] = 'two_factor_enabled';
            $values[] = ':two_factor_enabled';
            $params['two_factor_enabled'] = array_key_exists('two_factor_enabled', $data)
                ? (int) ((int) $data['two_factor_enabled'] === 1)
                : 1;
        }

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

    public static function registerFailedLogin(int $id, ?string $ipAddress = null): void
    {
        self::ensureAuthColumns();

        if ($id <= 0) {
            return;
        }

        $sql = 'UPDATE users
                SET failed_login_count = CASE
                        WHEN last_failed_login_at IS NULL OR last_failed_login_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                            THEN 1
                        ELSE COALESCE(failed_login_count, 0) + 1
                    END,
                    last_failed_login_at = NOW(),
                    last_failed_login_ip = :ip_address,
                    locked_until = CASE
                        WHEN (
                            CASE
                                WHEN last_failed_login_at IS NULL OR last_failed_login_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                                    THEN 1
                                ELSE COALESCE(failed_login_count, 0) + 1
                            END
                        ) >= 5
                        THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                        ELSE locked_until
                    END,
                    updated_at = NOW()
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'ip_address' => $ipAddress,
        ]);
    }

    public static function clearFailedLogin(int $id): void
    {
        self::ensureAuthColumns();

        if ($id <= 0) {
            return;
        }

        $sql = 'UPDATE users
                SET failed_login_count = 0,
                    locked_until = NULL,
                    last_login_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
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
        if (Schema::hasColumn('users', 'two_factor_enabled') && array_key_exists('two_factor_enabled', $data)) {
            $sql .= ', two_factor_enabled = :two_factor_enabled';
            $fields['two_factor_enabled'] = (int) ((int) $data['two_factor_enabled'] === 1);
        }

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

    public static function isTwoFactorEnabledForUser(array $user): bool
    {
        self::ensureAuthColumns();

        if (!is_two_factor_enabled()) {
            return false;
        }

        if (!Schema::hasColumn('users', 'two_factor_enabled')) {
            return true;
        }

        $raw = $user['two_factor_enabled'] ?? null;
        if ($raw === null || $raw === '') {
            return true;
        }

        return (int) $raw === 1;
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

    public static function inviteStatus(array $user): array
    {
        self::ensureAuthColumns();

        $sentAt = trim((string) ($user['password_setup_sent_at'] ?? ''));
        $expiresAt = trim((string) ($user['password_setup_expires_at'] ?? ''));
        $usedAt = trim((string) ($user['password_setup_used_at'] ?? ''));
        $passwordHash = trim((string) ($user['password_hash'] ?? ''));

        if ($sentAt === '') {
            return [
                'status' => 'none',
                'label' => 'N/A',
                'badge_class' => 'bg-secondary',
                'sent_at' => null,
                'expires_at' => null,
                'accepted_at' => null,
                'is_outstanding' => false,
            ];
        }

        $acceptedAt = $usedAt !== '' ? $usedAt : null;
        if ($acceptedAt === null && $passwordHash !== '') {
            $acceptedAt = trim((string) ($user['last_login_at'] ?? '')) !== ''
                ? trim((string) ($user['last_login_at'] ?? ''))
                : trim((string) ($user['updated_at'] ?? ''));
            $acceptedAt = $acceptedAt !== '' ? $acceptedAt : null;
        }

        if ($acceptedAt !== null) {
            return [
                'status' => 'accepted',
                'label' => 'Accepted',
                'badge_class' => 'bg-success',
                'sent_at' => $sentAt,
                'expires_at' => $expiresAt !== '' ? $expiresAt : null,
                'accepted_at' => $acceptedAt,
                'is_outstanding' => false,
            ];
        }

        $expired = false;
        if ($expiresAt !== '') {
            $expiresTs = strtotime($expiresAt);
            $expired = $expiresTs !== false && $expiresTs < time();
        }

        return [
            'status' => $expired ? 'expired' : 'invited',
            'label' => $expired ? 'Invite Expired' : 'Invited',
            'badge_class' => $expired ? 'bg-danger' : 'bg-warning text-dark',
            'sent_at' => $sentAt,
            'expires_at' => $expiresAt !== '' ? $expiresAt : null,
            'accepted_at' => null,
            'is_outstanding' => true,
        ];
    }

    public static function outstandingInvites(int $limit = 10): array
    {
        self::ensureAuthColumns();

        $capped = max(1, min($limit, 100));
        $sql = 'SELECT id,
                       first_name,
                       last_name,
                       email,
                       role,
                       is_active,
                       password_hash,
                       password_setup_sent_at,
                       password_setup_expires_at,
                       password_setup_used_at,
                       created_at,
                       updated_at
                FROM users
                WHERE is_active = 1
                  AND password_setup_sent_at IS NOT NULL
                  AND COALESCE(password_setup_used_at, \'\') = \'\'
                  AND COALESCE(password_hash, \'\') = \'\'
                ORDER BY
                  CASE
                    WHEN password_setup_expires_at IS NULL THEN 1
                    WHEN password_setup_expires_at < NOW() THEN 0
                    ELSE 1
                  END ASC,
                  password_setup_expires_at ASC,
                  id DESC
                LIMIT ' . $capped;

        $rows = Database::connection()->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['invite'] = self::inviteStatus($row);
        }
        unset($row);

        return $rows;
    }

    public static function outstandingInviteSummary(): array
    {
        self::ensureAuthColumns();

        $sql = 'SELECT
                    COALESCE(SUM(
                        CASE
                            WHEN is_active = 1
                             AND password_setup_sent_at IS NOT NULL
                             AND COALESCE(password_setup_used_at, \'\') = \'\'
                             AND COALESCE(password_hash, \'\') = \'\'
                             AND (
                                 password_setup_expires_at IS NULL
                                 OR password_setup_expires_at >= NOW()
                             )
                            THEN 1
                            ELSE 0
                        END
                    ), 0) AS invited_count,
                    COALESCE(SUM(
                        CASE
                            WHEN is_active = 1
                             AND password_setup_sent_at IS NOT NULL
                             AND COALESCE(password_setup_used_at, \'\') = \'\'
                             AND COALESCE(password_hash, \'\') = \'\'
                             AND password_setup_expires_at IS NOT NULL
                             AND password_setup_expires_at < NOW()
                            THEN 1
                            ELSE 0
                        END
                    ), 0) AS expired_count
                FROM users';
        $row = Database::connection()->query($sql)->fetch();

        $invited = (int) ($row['invited_count'] ?? 0);
        $expired = (int) ($row['expired_count'] ?? 0);

        return [
            'invited_count' => $invited,
            'expired_count' => $expired,
            'outstanding_count' => $invited + $expired,
        ];
    }

    public static function autoAcceptInvite(int $id, string $temporaryPassword, ?int $actorId = null): bool
    {
        self::ensureAuthColumns();

        if ($id <= 0 || trim($temporaryPassword) === '') {
            return false;
        }

        $sql = 'UPDATE users
                SET password_hash = :password_hash,
                    password_setup_token_hash = NULL,
                    password_setup_expires_at = NULL,
                    password_setup_used_at = NOW(),
                    failed_login_count = 0,
                    locked_until = NULL,
                    updated_at = NOW()';
        $params = [
            'id' => $id,
            'password_hash' => password_hash($temporaryPassword, PASSWORD_BCRYPT),
        ];

        if ($actorId !== null && Schema::hasColumn('users', 'updated_by')) {
            $sql .= ', updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql .= ' WHERE id = :id
                  AND is_active = 1
                  AND password_setup_sent_at IS NOT NULL
                  AND COALESCE(password_setup_used_at, \'\') = \'\'
                  AND COALESCE(password_hash, \'\') = \'\'';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
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
