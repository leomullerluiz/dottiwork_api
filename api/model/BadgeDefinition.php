<?php

class BadgeDefinition
{
    private const SECRET_SLUG = 'secret_badge';
    private const SECRET_NAME = 'Secret badge';
    private const SECRET_DESCRIPTION = 'This achievement is hidden.';
    private const SECRET_CATEGORY = 'secret';
    private const SECRET_LEVEL = 'secret';
    private const SECRET_IMAGE_URL = '/uploads/media/badges/secret_badge.png';
    private const SECRET_IMAGE_ALT = 'Hidden secret badge';
    private const SECRET_ICON = 'lock';

    public static function allActive()
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("
            SELECT *
            FROM badge_definitions
            WHERE is_active = 1
            ORDER BY display_order ASC, id ASC
        ");

        return array_map([self::class, 'decode'], $stmt->fetchAll());
    }

    public static function findBySlug($slug)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM badge_definitions
            WHERE slug = :slug
            LIMIT 1
        ");
        $stmt->execute(['slug' => $slug]);
        return self::decode($stmt->fetch());
    }

    public static function publicCatalog()
    {
        return array_map([self::class, 'toResponse'], self::allActive());
    }

    public static function toResponse(array $row)
    {
        if (!empty($row['is_secret'])) {
            return self::secretResponse(true);
        }

        return [
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'description' => (string) $row['description'],
            'category' => (string) $row['category'],
            'level' => (string) $row['level'],
            'image_url' => (string) $row['image_url'],
            'image_alt' => (string) $row['image_alt'],
            'icon' => $row['icon'] ?? null,
            'is_secret' => !empty($row['is_secret']),
            'display_order' => (int) $row['display_order'],
            'criteria_type' => (string) $row['criteria_type'],
            'criteria_config' => self::normalizeCriteriaConfig($row['criteria_config'] ?? []),
        ];
    }

    public static function compactResponse(array $row)
    {
        $badge = self::toResponse($row);
        unset($badge['criteria_type'], $badge['criteria_config']);
        return $badge;
    }

    public static function isSecret(array $row)
    {
        return !empty($row['is_secret']);
    }

    public static function secretSlug()
    {
        return self::SECRET_SLUG;
    }

    private static function decode($row)
    {
        if (!$row) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['is_active'] = (bool) $row['is_active'];
        $row['is_secret'] = (bool) $row['is_secret'];
        $row['display_order'] = (int) $row['display_order'];
        $row['criteria_config'] = self::normalizeCriteriaConfig($row['criteria_config'] ?? []);
        return $row;
    }

    private static function normalizeCriteriaConfig($value)
    {
        if (is_string($value)) {
            $decoded = $value ? json_decode($value, true) : [];
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    private static function secretResponse($includeCriteria)
    {
        $response = [
            'slug' => self::SECRET_SLUG,
            'name' => self::SECRET_NAME,
            'description' => self::SECRET_DESCRIPTION,
            'category' => self::SECRET_CATEGORY,
            'level' => self::SECRET_LEVEL,
            'image_url' => self::SECRET_IMAGE_URL,
            'image_alt' => self::SECRET_IMAGE_ALT,
            'icon' => self::SECRET_ICON,
            'is_secret' => true,
            'display_order' => 0,
        ];

        if ($includeCriteria) {
            $response['criteria_type'] = 'secret';
            $response['criteria_config'] = [];
        }

        return $response;
    }
}
