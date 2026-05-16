<?php

/**
 * Admin helper - centralized admin UID resolution
 * Admin UIDs are stored in app_settings (key: admin_uids) as JSON array.
 * Bootstrap UID 1290033 is always included to prevent lockout.
 */
class AdminHelper
{
    private const BOOTSTRAP_UID = 1290033;

    /** @var int[]|null Per-request cache */
    private static ?array $cachedUids = null;

    /**
     * Get list of admin UIDs. Bootstrap UID is always included.
     * Reads from app_settings if available; otherwise returns [1290033].
     */
    public static function getAdminUids(?PDO $pdo = null): array
    {
        if (self::$cachedUids !== null) {
            return self::$cachedUids;
        }

        $uids = [self::BOOTSTRAP_UID];

        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT v FROM app_settings WHERE k = 'admin_uids'");
                if ($stmt && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $decoded = json_decode($row['v'], true);
                    if (is_array($decoded)) {
                        $extra = array_map('intval', array_filter($decoded, 'is_numeric'));
                        $uids = array_values(array_unique(array_merge($uids, $extra)));
                    }
                }
            } catch (Throwable $e) {
                /* fallback to bootstrap only */
            }
        }

        self::$cachedUids = $uids;
        return $uids;
    }

    /** Check if a UID is admin */
    public static function isAdmin(int $uid, ?PDO $pdo = null): bool
    {
        return in_array($uid, self::getAdminUids($pdo), true);
    }

    /** Reset cached UIDs (e.g. after adding/removing in same request) */
    public static function clearCache(): void
    {
        self::$cachedUids = null;
    }
}
