<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class User
{
    public static function findByEmail(string $email): ?array
    {
        $sql = 'SELECT id, email, password_hash, first_name, last_name, role
                FROM users
                WHERE email = :email
                  AND deleted_at IS NULL
                  AND COALESCE(is_active, 1) = 1
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['email' => trim(strtolower($email))]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, email, first_name, last_name, role
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function displayName(array $user): string
    {
        $full = trim(((string) ($user['first_name'] ?? '')) . ' ' . ((string) ($user['last_name'] ?? '')));
        if ($full !== '') {
            return $full;
        }

        return (string) ($user['email'] ?? 'User');
    }
}
