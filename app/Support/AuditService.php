<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Logger.php';

/**
 * Audit trail for admin actions
 */
class AuditService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Log an admin action
     *
     * @param int $adminUid Admin user ID
     * @param string $action Action type (e.g. reversal, backup, clear_sessions)
     * @param string|null $entityType Entity type (e.g. withdrawal, database)
     * @param string|int|null $entityId Entity ID
     * @param array $details Additional context (will be JSON encoded)
     * @return bool
     */
    public function log(int $adminUid, string $action, ?string $entityType = null, $entityId = null, array $details = []): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_log (admin_uid, action, entity_type, entity_id, details, ip)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $entityIdStr = $entityId !== null ? (string)$entityId : null;
            $detailsJson = empty($details) ? null : json_encode($details);
            $ip = Security::getClientIp();
            return $stmt->execute([$adminUid, $action, $entityType, $entityIdStr, $detailsJson, $ip]);
        } catch (Exception $e) {
            // Don't fail the main operation if audit fails (e.g. table doesn't exist yet)
            Logger::warning('Audit log failed', ['error' => $e->getMessage(), 'action' => $action]);
            return false;
        }
    }

    /**
     * Get audit entries with optional filters
     */
    public function getEntries(int $limit = 200, int $offset = 0, ?string $action = null, ?int $adminUid = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $where = ['1=1'];
        $params = [];

        if ($action) {
            $where[] = 'action = ?';
            $params[] = $action;
        }
        if ($adminUid) {
            $where[] = 'admin_uid = ?';
            $params[] = $adminUid;
        }
        if ($dateFrom) {
            $where[] = 'created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $where[] = 'created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT * FROM audit_log WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if audit_log table exists
     */
    public function tableExists(): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'audit_log'");
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
