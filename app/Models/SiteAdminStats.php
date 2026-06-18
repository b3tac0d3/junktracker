<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class SiteAdminStats
{
    /**
     * @return array<string, mixed>
     */
    public static function dashboardSnapshot(): array
    {
        return [
            'companies' => self::companyCounts(),
            'users' => self::userCounts(),
            'engagement' => self::engagementCounts(),
            'dev_queue' => self::devQueueCounts(),
            'growth' => self::growthCounts(),
            'recent_logins' => self::recentLogins(8),
            'daily_trend' => self::dailyEngagementTrend(7),
            'users_by_business' => self::activeUsersByBusiness(),
            'pending_review_items' => self::pendingReviewItems(6),
        ];
    }

    /**
     * @return array{all: int, active: int, inactive: int}
     */
    public static function companyCounts(): array
    {
        return [
            'all' => Business::countForSiteAdmin('all'),
            'active' => Business::countForSiteAdmin('active'),
            'inactive' => Business::countForSiteAdmin('inactive'),
        ];
    }

    /**
     * @return array{company_users: int, site_admins: int, total_accounts: int}
     */
    public static function userCounts(): array
    {
        if (!SchemaInspector::hasTable('users')) {
            return ['company_users' => 0, 'site_admins' => 0, 'total_accounts' => 0];
        }

        $siteAdmins = (int) Database::connection()->query(
            "SELECT COUNT(*)
             FROM users
             WHERE deleted_at IS NULL
               AND COALESCE(is_active, 1) = 1
               AND role = 'site_admin'"
        )->fetchColumn();

        $companyUsers = 0;
        if (SchemaInspector::hasTable('business_user_memberships') && SchemaInspector::hasTable('businesses')) {
            $companyUsers = (int) Database::connection()->query(
                "SELECT COUNT(DISTINCT u.id)
                 FROM users u
                 INNER JOIN business_user_memberships m
                    ON m.user_id = u.id
                   AND m.deleted_at IS NULL
                   AND COALESCE(m.is_active, 1) = 1
                 INNER JOIN businesses b
                    ON b.id = m.business_id
                   AND b.deleted_at IS NULL
                   AND COALESCE(b.is_active, 1) = 1
                 WHERE u.deleted_at IS NULL
                   AND COALESCE(u.is_active, 1) = 1
                   AND u.role <> 'site_admin'"
            )->fetchColumn();
        }

        return [
            'company_users' => $companyUsers,
            'site_admins' => $siteAdmins,
            'total_accounts' => $companyUsers + $siteAdmins,
        ];
    }

    /**
     * @return array{
     *     logins_today: int,
     *     active_users_today: int,
     *     logins_7d: int,
     *     active_users_7d: int,
     *     activity_events_today: int,
     *     activity_events_7d: int
     * }
     */
    public static function engagementCounts(): array
    {
        if (!SchemaInspector::hasTable('activity_logs')) {
            return [
                'logins_today' => 0,
                'active_users_today' => 0,
                'logins_7d' => 0,
                'active_users_7d' => 0,
                'activity_events_today' => 0,
                'activity_events_7d' => 0,
            ];
        }

        $pdo = Database::connection();
        $todayStart = date('Y-m-d') . ' 00:00:00';
        $tomorrowStart = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
        $weekStart = date('Y-m-d', strtotime('-6 days')) . ' 00:00:00';

        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT user_id)
             FROM activity_logs
             WHERE action = 'user_login'
               AND user_id IS NOT NULL
               AND created_at >= :today_start
               AND created_at < :tomorrow_start"
        );
        $stmt->execute(['today_start' => $todayStart, 'tomorrow_start' => $tomorrowStart]);
        $loginsToday = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT user_id)
             FROM activity_logs
             WHERE user_id IS NOT NULL
               AND action <> 'user_logout'
               AND created_at >= :today_start
               AND created_at < :tomorrow_start"
        );
        $stmt->execute(['today_start' => $todayStart, 'tomorrow_start' => $tomorrowStart]);
        $activeToday = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT user_id)
             FROM activity_logs
             WHERE action = 'user_login'
               AND user_id IS NOT NULL
               AND created_at >= :week_start"
        );
        $stmt->execute(['week_start' => $weekStart]);
        $logins7d = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT user_id)
             FROM activity_logs
             WHERE user_id IS NOT NULL
               AND action <> 'user_logout'
               AND created_at >= :week_start"
        );
        $stmt->execute(['week_start' => $weekStart]);
        $active7d = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM activity_logs
             WHERE created_at >= :today_start
               AND created_at < :tomorrow_start"
        );
        $stmt->execute(['today_start' => $todayStart, 'tomorrow_start' => $tomorrowStart]);
        $eventsToday = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM activity_logs
             WHERE created_at >= :week_start"
        );
        $stmt->execute(['week_start' => $weekStart]);
        $events7d = (int) $stmt->fetchColumn();

        return [
            'logins_today' => $loginsToday,
            'active_users_today' => $activeToday,
            'logins_7d' => $logins7d,
            'active_users_7d' => $active7d,
            'activity_events_today' => $eventsToday,
            'activity_events_7d' => $events7d,
        ];
    }

    /**
     * @return array{pending_total: int, pending_bugs: int, pending_updates: int}
     */
    public static function devQueueCounts(): array
    {
        if (!DevTrackerItem::hasSubmissionColumns()) {
            return ['pending_total' => 0, 'pending_bugs' => 0, 'pending_updates' => 0];
        }

        $pendingBugs = DevTrackerItem::pendingReviewCount('bug');
        $pendingUpdates = DevTrackerItem::pendingReviewCount('update');

        return [
            'pending_total' => $pendingBugs + $pendingUpdates,
            'pending_bugs' => $pendingBugs,
            'pending_updates' => $pendingUpdates,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pendingReviewItems(int $limit = 6): array
    {
        if (!DevTrackerItem::hasSubmissionColumns() || !SchemaInspector::hasTable('dev_tracker_items')) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            "SELECT
                i.id,
                i.item_type,
                i.title,
                i.status,
                i.review_status,
                i.priority,
                i.area,
                i.created_at,
                i.updated_at,
                b.name AS business_name,
                u.first_name,
                u.last_name,
                u.email
             FROM dev_tracker_items i
             LEFT JOIN businesses b ON b.id = i.business_id
             LEFT JOIN users u ON u.id = i.submitted_by
             WHERE i.deleted_at IS NULL
               AND LOWER(i.status) = 'pending_review'
             ORDER BY i.created_at DESC, i.id DESC
             LIMIT :row_limit"
        );
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{new_users_30d: int, new_memberships_30d: int}
     */
    public static function growthCounts(): array
    {
        $since = date('Y-m-d H:i:s', strtotime('-30 days'));
        $newUsers = 0;
        $newMemberships = 0;

        if (SchemaInspector::hasTable('users')) {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*)
                 FROM users
                 WHERE deleted_at IS NULL
                   AND created_at >= :since"
            );
            $stmt->execute(['since' => $since]);
            $newUsers = (int) $stmt->fetchColumn();
        }

        if (SchemaInspector::hasTable('business_user_memberships')) {
            $createdCol = SchemaInspector::hasColumn('business_user_memberships', 'created_at') ? 'created_at' : null;
            if ($createdCol !== null) {
                $stmt = Database::connection()->prepare(
                    "SELECT COUNT(*)
                     FROM business_user_memberships
                     WHERE deleted_at IS NULL
                       AND created_at >= :since"
                );
                $stmt->execute(['since' => $since]);
                $newMemberships = (int) $stmt->fetchColumn();
            }
        }

        return [
            'new_users_30d' => $newUsers,
            'new_memberships_30d' => $newMemberships,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recentLogins(int $limit = 8): array
    {
        if (!SchemaInspector::hasTable('activity_logs') || !SchemaInspector::hasTable('users')) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            "SELECT
                al.id,
                al.user_id,
                al.business_id,
                al.created_at,
                u.first_name,
                u.last_name,
                u.email,
                u.role,
                b.name AS business_name
             FROM activity_logs al
             INNER JOIN users u ON u.id = al.user_id
             LEFT JOIN businesses b ON b.id = al.business_id
             WHERE al.action = 'user_login'
               AND al.user_id IS NOT NULL
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT :row_limit"
        );
        $stmt->bindValue(':row_limit', max(1, min($limit, 25)), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array{date: string, label: string, logins: int, active_users: int, events: int}>
     */
    public static function dailyEngagementTrend(int $days = 7): array
    {
        if (!SchemaInspector::hasTable('activity_logs') || $days <= 0) {
            return [];
        }

        $days = min($days, 14);
        $start = date('Y-m-d', strtotime('-' . (string) ($days - 1) . ' days')) . ' 00:00:00';

        $stmt = Database::connection()->prepare(
            "SELECT
                DATE(created_at) AS day_key,
                SUM(CASE WHEN action = 'user_login' THEN 1 ELSE 0 END) AS login_events,
                COUNT(DISTINCT CASE WHEN action = 'user_login' THEN user_id END) AS unique_logins,
                COUNT(DISTINCT CASE WHEN user_id IS NOT NULL AND action <> 'user_logout' THEN user_id END) AS active_users,
                COUNT(*) AS total_events
             FROM activity_logs
             WHERE created_at >= :start_date
             GROUP BY DATE(created_at)
             ORDER BY day_key ASC"
        );
        $stmt->execute(['start_date' => $start]);
        $rows = $stmt->fetchAll();

        $byDay = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $dayKey = (string) ($row['day_key'] ?? '');
                if ($dayKey === '') {
                    continue;
                }
                $byDay[$dayKey] = $row;
            }
        }

        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . (string) $i . ' days'));
            $row = is_array($byDay[$date] ?? null) ? $byDay[$date] : [];
            $trend[] = [
                'date' => $date,
                'label' => date('D n/j', strtotime($date)),
                'logins' => (int) ($row['unique_logins'] ?? 0),
                'active_users' => (int) ($row['active_users'] ?? 0),
                'events' => (int) ($row['total_events'] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * @return array<int, int>
     */
    public static function activeUsersByBusiness(): array
    {
        if (!SchemaInspector::hasTable('business_user_memberships') || !SchemaInspector::hasTable('users')) {
            return [];
        }

        $stmt = Database::connection()->query(
            "SELECT m.business_id, COUNT(DISTINCT u.id) AS user_count
             FROM business_user_memberships m
             INNER JOIN users u
                ON u.id = m.user_id
               AND u.deleted_at IS NULL
               AND COALESCE(u.is_active, 1) = 1
               AND u.role <> 'site_admin'
             WHERE m.deleted_at IS NULL
               AND COALESCE(m.is_active, 1) = 1
             GROUP BY m.business_id"
        );
        $rows = $stmt !== false ? $stmt->fetchAll() : [];
        if (!is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $businessId = (int) ($row['business_id'] ?? 0);
            if ($businessId <= 0) {
                continue;
            }
            $map[$businessId] = (int) ($row['user_count'] ?? 0);
        }

        return $map;
    }
}
