<?php

class Technology
{
    public static function list(array $filters = [])
    {
        $db = Database::getInstance()->getConnection();
        $conditions = [];
        $params = [];

        if (isset($filters['category']) && $filters['category'] !== '') {
            $conditions[] = 'category = :category';
            $params['category'] = $filters['category'];
        }

        if (isset($filters['active']) && $filters['active'] !== '') {
            $conditions[] = 'is_active = :active';
            $params['active'] = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $conditions[] = '(name LIKE :search OR slug LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['cursor']) && $filters['cursor'] !== '') {
            $conditions[] = 'id > :cursor';
            $params['cursor'] = (int) $filters['cursor'];
        }

        $limit = isset($filters['limit']) ? min(max((int) $filters['limit'], 1), 100) : 100;
        $sql = 'SELECT * FROM technologies';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY display_order ASC, name ASC LIMIT ' . $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_map([self::class, 'decode'], $stmt->fetchAll());
    }

    public static function findBySlug($slug)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM technologies WHERE slug = :slug LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ? self::decode($row) : null;
    }

    public static function findActiveByIds(array $ids)
    {
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM technologies WHERE is_active = 1 AND id IN ({$placeholders})");
        $stmt->execute(array_values($ids));
        return array_map([self::class, 'decode'], $stmt->fetchAll());
    }

    public static function decode($row)
    {
        if (!$row) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['is_active'] = (bool) $row['is_active'];
        $row['display_order'] = (int) $row['display_order'];
        $row['github_topics'] = $row['github_topics'] ? json_decode($row['github_topics'], true) : [];
        return $row;
    }
}
