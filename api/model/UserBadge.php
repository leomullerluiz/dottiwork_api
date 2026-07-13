<?php

class UserBadge
{
    public static function listByUser($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT ub.*, bd.name, bd.description, bd.category, bd.level, bd.image_url,
                   bd.image_alt, bd.icon, bd.is_secret, bd.display_order, bd.criteria_type,
                   bd.criteria_config
            FROM user_badges ub
            INNER JOIN badge_definitions bd ON bd.id = ub.badge_id
            WHERE ub.user_id = :user_id
            ORDER BY ub.awarded_at DESC, ub.id DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        return array_map([self::class, 'toResponse'], $stmt->fetchAll());
    }

    public static function grant($userId, $badgeSlug, $sourceEventId = null, array $snapshot = [])
    {
        $definition = BadgeDefinition::findBySlug($badgeSlug);
        if (!$definition || empty($definition['is_active'])) {
            return null;
        }

        $existing = self::findByUserAndBadgeId($userId, $definition['id']);
        if ($existing) {
            return $existing;
        }

        $db = Database::getInstance()->getConnection();
        try {
            $stmt = $db->prepare("
                INSERT INTO user_badges (
                    user_id, badge_id, slug, awarded_at, source_event_id,
                    progress_snapshot, created_at, updated_at
                ) VALUES (
                    :user_id, :badge_id, :slug, NOW(), :source_event_id,
                    :progress_snapshot, NOW(), NOW()
                )
            ");
            $stmt->execute([
                'user_id' => $userId,
                'badge_id' => $definition['id'],
                'slug' => $definition['slug'],
                'source_event_id' => $sourceEventId,
                'progress_snapshot' => json_encode($snapshot),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }

        return self::findByUserAndBadgeId($userId, $definition['id']);
    }

    public static function hasBadge($userId, $badgeSlug)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT 1
            FROM user_badges
            WHERE user_id = :user_id AND slug = :slug
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId, 'slug' => $badgeSlug]);
        return (bool) $stmt->fetchColumn();
    }

    public static function recentlyAwarded($userId, $since)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT ub.*, bd.name, bd.description, bd.category, bd.level, bd.image_url,
                   bd.image_alt, bd.icon, bd.is_secret, bd.display_order, bd.criteria_type,
                   bd.criteria_config
            FROM user_badges ub
            INNER JOIN badge_definitions bd ON bd.id = ub.badge_id
            WHERE ub.user_id = :user_id AND ub.awarded_at >= :since
            ORDER BY ub.awarded_at DESC, ub.id DESC
        ");
        $stmt->execute(['user_id' => $userId, 'since' => $since]);

        return array_map([self::class, 'toResponse'], $stmt->fetchAll());
    }

    public static function unseenAwarded($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT ub.*, bd.name, bd.description, bd.category, bd.level, bd.image_url,
                   bd.image_alt, bd.icon, bd.is_secret, bd.display_order, bd.criteria_type,
                   bd.criteria_config
            FROM user_badges ub
            INNER JOIN badge_definitions bd ON bd.id = ub.badge_id
            WHERE ub.user_id = :user_id AND ub.notification_seen_at IS NULL
            ORDER BY ub.awarded_at DESC, ub.id DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        return array_map([self::class, 'toResponse'], $stmt->fetchAll());
    }

    public static function unseenAwardedCount($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM user_badges
            WHERE user_id = :user_id AND notification_seen_at IS NULL
        ");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function markNotificationsSeen($userId, array $slugs = [], array $ids = [])
    {
        $slugs = self::normalizeSlugs($slugs);
        $ids = self::normalizeIds($ids);

        if (!$slugs && !$ids) {
            return 0;
        }

        $conditions = [];
        $params = ['user_id' => $userId];

        if ($slugs) {
            $slugPlaceholders = [];
            foreach ($slugs as $index => $slug) {
                $key = 'slug_' . $index;
                $slugPlaceholders[] = ':' . $key;
                $params[$key] = $slug;
            }
            $conditions[] = 'slug IN (' . implode(', ', $slugPlaceholders) . ')';
        }

        if ($ids) {
            $idPlaceholders = [];
            foreach ($ids as $index => $id) {
                $key = 'id_' . $index;
                $idPlaceholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $conditions[] = 'id IN (' . implode(', ', $idPlaceholders) . ')';
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE user_badges
            SET notification_seen_at = COALESCE(notification_seen_at, NOW()),
                updated_at = NOW()
            WHERE user_id = :user_id
              AND notification_seen_at IS NULL
              AND (" . implode(' OR ', $conditions) . ")
        ");
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public static function awardedMapByUser($userId)
    {
        $map = [];
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT ub.*, bd.name, bd.description, bd.category, bd.level, bd.image_url,
                   bd.image_alt, bd.icon, bd.is_secret, bd.display_order, bd.criteria_type,
                   bd.criteria_config
            FROM user_badges ub
            INNER JOIN badge_definitions bd ON bd.id = ub.badge_id
            WHERE ub.user_id = :user_id
            ORDER BY ub.awarded_at DESC, ub.id DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        foreach ($stmt->fetchAll() as $row) {
            $map[$row['slug']] = self::toResponse($row);
        }
        return $map;
    }

    private static function findByUserAndBadgeId($userId, $badgeId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT ub.*, bd.name, bd.description, bd.category, bd.level, bd.image_url,
                   bd.image_alt, bd.icon, bd.is_secret, bd.display_order, bd.criteria_type,
                   bd.criteria_config
            FROM user_badges ub
            INNER JOIN badge_definitions bd ON bd.id = ub.badge_id
            WHERE ub.user_id = :user_id AND ub.badge_id = :badge_id
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId, 'badge_id' => $badgeId]);
        $row = $stmt->fetch();
        return $row ? self::toResponse($row) : null;
    }

    public static function toResponse(array $row)
    {
        $isSecret = !empty($row['is_secret']);
        $definition = [
            'id' => $row['badge_id'] ?? ($row['id'] ?? null),
            'slug' => $row['slug'],
            'name' => $row['name'],
            'description' => $row['description'],
            'category' => $row['category'],
            'level' => $row['level'],
            'image_url' => $row['image_url'],
            'image_alt' => $row['image_alt'],
            'icon' => $row['icon'] ?? null,
            'is_secret' => $row['is_secret'] ?? false,
            'display_order' => $row['display_order'] ?? 0,
            'criteria_type' => $row['criteria_type'] ?? '',
            'criteria_config' => $row['criteria_config'] ?? [],
        ];

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'slug' => $isSecret ? BadgeDefinition::secretSlug() : (string) $row['slug'],
            'awarded_at' => $row['awarded_at'] ?? null,
            'notification_seen' => !empty($row['notification_seen_at']),
            'notification_seen_at' => $row['notification_seen_at'] ?? null,
            'source_event_id' => $isSecret ? null : (isset($row['source_event_id']) ? (int) $row['source_event_id'] : null),
            'progress_snapshot' => $isSecret ? [] : self::normalizeJson($row['progress_snapshot'] ?? []),
            'badge' => BadgeDefinition::compactResponse($definition),
        ];
    }

    private static function normalizeJson($value)
    {
        if (is_string($value)) {
            $decoded = $value ? json_decode($value, true) : [];
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    private static function normalizeSlugs(array $slugs)
    {
        $normalized = [];
        foreach ($slugs as $slug) {
            if (!is_string($slug)) {
                continue;
            }

            $slug = trim($slug);
            if ($slug !== '') {
                $normalized[$slug] = true;
            }
        }

        return array_keys($normalized);
    }

    private static function normalizeIds(array $ids)
    {
        $normalized = [];
        foreach ($ids as $id) {
            if (!Validator::integer($id)) {
                continue;
            }

            $id = (int) $id;
            if ($id > 0) {
                $normalized[$id] = true;
            }
        }

        return array_keys($normalized);
    }
}
