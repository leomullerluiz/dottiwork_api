<?php

class UserActivityEvent
{
    public static $allowedTypes = [
        'viewed_project',
        'saved_project',
        'ignored_project',
        'opened_github',
        'started_contributing',
        'sent_pull_request',
        'marked_contributed',
        'restored_project',
    ];

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
        $conditions = ['e.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if (!empty($filters['event_type'])) {
            $conditions[] = 'e.event_type = :event_type';
            $params['event_type'] = $filters['event_type'];
        }

        if (!empty($filters['github_repository_id'])) {
            $conditions[] = 'e.github_repository_id = :github_repository_id';
            $params['github_repository_id'] = (int) $filters['github_repository_id'];
        }

        if (!empty($filters['cursor'])) {
            $conditions[] = 'e.id < :cursor';
            $params['cursor'] = (int) $filters['cursor'];
        }

        $limit = isset($filters['limit']) ? min(max((int) $filters['limit'], 1), 100) : 50;
        $sql = '
            SELECT e.*, r.repository_data, r.health_data
            FROM user_activity_events e
            LEFT JOIN repository_cache r ON r.github_repository_id = e.github_repository_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY e.id DESC
            LIMIT ' . $limit;
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

        $repositoryData = !empty($row['repository_data']) ? json_decode($row['repository_data'], true) : null;
        $healthData = !empty($row['health_data']) ? json_decode($row['health_data'], true) : null;
        unset($row['repository_data'], $row['health_data']);

        return self::toResponse($row, $repositoryData, $healthData);
    }

    public static function toResponse(array $row, array $repositoryData = null, array $healthData = null, array $issueStats = null)
    {
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['github_repository_id'] = $row['github_repository_id'] !== null ? (int) $row['github_repository_id'] : null;
        $row['event_type'] = (string) $row['event_type'];
        $row['type'] = $row['event_type'];
        $row['metadata'] = self::normalizeMetadata($row['metadata'] ?? []);

        $row['repository'] = null;
        if ($row['github_repository_id'] !== null) {
            if ($issueStats === null) {
                $issueStats = RepositoryIssueCache::statsByRepositoryId($row['github_repository_id']);
            }
            $row['repository'] = RepositorySummary::fromGitHubRepository($repositoryData ?: [
                'id' => $row['github_repository_id'],
                'owner_login' => $row['metadata']['owner'] ?? null,
                'name' => $row['metadata']['repo'] ?? null,
            ], $healthData, $issueStats);
        }

        return $row;
    }

    private static function normalizeMetadata($metadata)
    {
        if (is_string($metadata)) {
            $decoded = $metadata ? json_decode($metadata, true) : [];
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($metadata) ? $metadata : [];
    }
}
