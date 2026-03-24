<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ClientPortalAccess
{
    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('client_portal_access');
    }

    /**
     * Create or rotate portal token for an invoice. Returns raw token (show once).
     */
    public static function issueForInvoice(int $businessId, int $invoiceId, int $ttlDays = 90): string
    {
        if (!self::isAvailable()) {
            return '';
        }

        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expires = (new \DateTimeImmutable())->modify('+' . max(1, $ttlDays) . ' days')->format('Y-m-d H:i:s');

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO client_portal_access (business_id, invoice_id, token_hash, expires_at)
             VALUES (:business_id, :invoice_id, :token_hash, :expires_at)
             ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'invoice_id' => $invoiceId,
            'token_hash' => $hash,
            'expires_at' => $expires,
        ]);

        return $raw;
    }

    /**
     * @return array{business_id:int, invoice_id:int}|null
     */
    public static function validateToken(string $rawToken): ?array
    {
        if (!self::isAvailable() || strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) {
            return null;
        }

        $hash = hash('sha256', $rawToken);
        $stmt = Database::connection()->prepare(
            'SELECT business_id, invoice_id
             FROM client_portal_access
             WHERE token_hash = :h
               AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['h' => $hash]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'business_id' => (int) ($row['business_id'] ?? 0),
            'invoice_id' => (int) ($row['invoice_id'] ?? 0),
        ];
    }
}
