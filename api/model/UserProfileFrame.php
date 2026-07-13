<?php

class UserProfileFrame
{
    public static function grant($userId, $slug, $name, $imageUrl = null, array $styleConfig = [], $sourceBadgeSlug = null)
    {
        $existing = self::findByUserAndSlug($userId, $slug);
        if ($existing) {
            return $existing;
        }

        $db = Database::getInstance()->getConnection();
        try {
            $stmt = $db->prepare("
                INSERT INTO user_profile_frames (
                    user_id, slug, name, image_url, style_config, source_badge_slug,
                    awarded_at, created_at, updated_at
                ) VALUES (
                    :user_id, :slug, :name, :image_url, :style_config, :source_badge_slug,
                    NOW(), NOW(), NOW()
                )
            ");
            $stmt->execute([
                'user_id' => $userId,
                'slug' => $slug,
                'name' => $name,
                'image_url' => $imageUrl,
                'style_config' => json_encode($styleConfig),
                'source_badge_slug' => $sourceBadgeSlug,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }

        return self::findByUserAndSlug($userId, $slug);
    }

    public static function featuredForUser($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM user_profile_frames
            WHERE user_id = :user_id
            ORDER BY awarded_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ? self::toResponse($row) : null;
    }

    public static function findByUserAndSlug($userId, $slug)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM user_profile_frames
            WHERE user_id = :user_id AND slug = :slug
            LIMIT 1
        ");
        $stmt->execute([
            'user_id' => $userId,
            'slug' => $slug,
        ]);
        $row = $stmt->fetch();
        return $row ? self::toResponse($row) : null;
    }

    public static function toResponse(array $row)
    {
        return [
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'image_url' => $row['image_url'] ?? null,
            'style_config' => self::normalizeJson($row['style_config'] ?? []),
            'source_badge_slug' => $row['source_badge_slug'] ?? null,
            'awarded_at' => $row['awarded_at'] ?? null,
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
}
