<?php

class UserActivityEvent
{
    public static function create($userId, $eventType, $githubRepositoryId = null, array $metadata = [])
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO user_activity_events (
                user_id, github_repository_id, event_type, metadata, created_at
            ) VALUES (
                :user_id, :github_repository_id, :event_type, :metadata, NOW()
            )
        ");

        $stmt->execute([
            'user_id' => $userId,
            'github_repository_id' => $githubRepositoryId,
            'event_type' => $eventType,
            'metadata' => json_encode($metadata),
        ]);

        return self::findById($db->lastInsertId(), $userId);
    }

    public static function findById($id, $userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM user_activity_events
            WHERE id = :id AND user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return self::decode($row);
    }

    public static function listByUser($userId, array $filters = [])
    {
        $db = Database::getInstance()->getConnection();
        $conditions = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        if (!empty($filters['event_type'])) {
            $conditions[] = 'event_type = :event_type';
            $params['event_type'] = $filters['event_type'];
        }

        if (!empty($filters['github_repository_id'])) {
            $conditions[] = 'github_repository_id = :github_repository_id';
            $params['github_repository_id'] = (int) $filters['github_repository_id'];
        }

        if (!empty($filters['cursor'])) {
            $conditions[] = 'id < :cursor';
            $params['cursor'] = (int) $filters['cursor'];
        }

        $limit = isset($filters['limit']) ? min(max((int) $filters['limit'], 1), 100) : 50;
        $sql = 'SELECT * FROM user_activity_events WHERE ' . implode(' AND ', $conditions) . ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return array_map([self::class, 'decode'], $stmt->fetchAll());
    }

    public static function deleteByUser($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM user_activity_events WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    private static function decode($row)
    {
        if (!$row) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['github_repository_id'] = $row['github_repository_id'] !== null ? (int) $row['github_repository_id'] : null;
        $row['metadata'] = $row['metadata'] ? json_decode($row['metadata'], true) : [];
        return $row;
    }
}
