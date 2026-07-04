<?php

class UserRepositoryState
{
    public static function findByUserAndRepository($userId, $githubRepositoryId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM user_repository_states
            WHERE user_id = :user_id AND github_repository_id = :github_repository_id
            LIMIT 1
        ");
        $stmt->execute([
            'user_id' => $userId,
            'github_repository_id' => $githubRepositoryId,
        ]);
        return $stmt->fetch();
    }

    public static function listByUser($userId, array $filters = [])
    {
        $db = Database::getInstance()->getConnection();
        $conditions = ['s.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if (!empty($filters['state'])) {
            $conditions[] = 's.state = :state';
            $params['state'] = $filters['state'];
        }

        if (!empty($filters['cursor'])) {
            $conditions[] = 's.id < :cursor';
            $params['cursor'] = (int) $filters['cursor'];
        }

        $limit = isset($filters['limit']) ? min(max((int) $filters['limit'], 1), 100) : 50;
        $sql = '
            SELECT s.*, r.repository_data, r.health_data
            FROM user_repository_states s
            LEFT JOIN repository_cache r ON r.github_repository_id = s.github_repository_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY s.updated_at DESC, s.id DESC
            LIMIT ' . $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_map([self::class, 'decodeListRow'], $stmt->fetchAll());
    }

    public static function upsert($userId, $githubRepositoryId, $ownerLogin, $repositoryName, $state, $notes = null)
    {
        $existing = self::findByUserAndRepository($userId, $githubRepositoryId);
        $db = Database::getInstance()->getConnection();

        $timestampFields = [
            'saved_at' => $state === 'saved' ? 'NOW()' : 'saved_at',
            'ignored_at' => $state === 'ignored' ? 'NOW()' : 'ignored_at',
            'contributed_at' => $state === 'contributed' ? 'NOW()' : 'contributed_at',
        ];

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE user_repository_states
                SET owner_login = :owner_login,
                    repository_name = :repository_name,
                    state = :state,
                    notes = :notes,
                    saved_at = {$timestampFields['saved_at']},
                    ignored_at = {$timestampFields['ignored_at']},
                    contributed_at = {$timestampFields['contributed_at']},
                    updated_at = NOW()
                WHERE user_id = :user_id AND github_repository_id = :github_repository_id
            ");
        } else {
            $stmt = $db->prepare("
                INSERT INTO user_repository_states (
                    user_id, github_repository_id, owner_login, repository_name, state, notes,
                    saved_at, ignored_at, contributed_at, created_at, updated_at
                ) VALUES (
                    :user_id, :github_repository_id, :owner_login, :repository_name, :state, :notes,
                    " . ($state === 'saved' ? 'NOW()' : 'NULL') . ",
                    " . ($state === 'ignored' ? 'NOW()' : 'NULL') . ",
                    " . ($state === 'contributed' ? 'NOW()' : 'NULL') . ",
                    NOW(), NOW()
                )
            ");
        }

        $stmt->execute([
            'user_id' => $userId,
            'github_repository_id' => $githubRepositoryId,
            'owner_login' => $ownerLogin ?: '',
            'repository_name' => $repositoryName ?: '',
            'state' => $state,
            'notes' => $notes,
        ]);

        return self::findByUserAndRepository($userId, $githubRepositoryId);
    }

    public static function delete($userId, $githubRepositoryId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            DELETE FROM user_repository_states
            WHERE user_id = :user_id AND github_repository_id = :github_repository_id
        ");
        $stmt->execute([
            'user_id' => $userId,
            'github_repository_id' => $githubRepositoryId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public static function deleteByUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM user_repository_states WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
    }

    private static function decodeListRow($row)
    {
        if (!$row) {
            return null;
        }

        $repositoryData = $row['repository_data'] ? json_decode($row['repository_data'], true) : null;
        $healthData = $row['health_data'] ? json_decode($row['health_data'], true) : null;
        unset($row['repository_data'], $row['health_data']);

        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['github_repository_id'] = (int) $row['github_repository_id'];
        $issueStats = RepositoryIssueCache::statsByRepositoryId($row['github_repository_id']);
        $row['repository'] = RepositorySummary::fromGitHubRepository($repositoryData ?: [
            'id' => $row['github_repository_id'],
            'owner_login' => $row['owner_login'] ?? null,
            'name' => $row['repository_name'] ?? null,
        ], $healthData, $issueStats);

        return $row;
    }
}
