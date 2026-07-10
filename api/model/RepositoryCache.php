<?php

class RepositoryCache
{
    public static function findByGitHubRepositoryId($githubRepositoryId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM repository_cache
            WHERE github_repository_id = :github_repository_id
            LIMIT 1
        ");
        $stmt->execute(['github_repository_id' => $githubRepositoryId]);
        return self::decode($stmt->fetch());
    }

    public static function findByOwnerRepo($owner, $repo, $freshOnly = false)
    {
        $db = Database::getInstance()->getConnection();
        $sql = "
            SELECT *
            FROM repository_cache
            WHERE owner_login = :owner_login AND repository_name = :repository_name
        ";
        if ($freshOnly) {
            $sql .= " AND expires_at > NOW()";
        }
        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'owner_login' => $owner,
            'repository_name' => $repo,
        ]);
        return self::decode($stmt->fetch());
    }

    public static function upsert(array $repositoryData, array $healthData = null, $ttlSeconds = 21600)
    {
        $githubRepositoryId = $repositoryData['id'];
        $ownerLogin = $repositoryData['owner']['login'] ?? ($repositoryData['owner_login'] ?? '');
        $repositoryName = $repositoryData['name'];
        $existing = self::findByGitHubRepositoryId($githubRepositoryId);
        $expiresAt = (new DateTime())->modify('+' . $ttlSeconds . ' seconds')->format('Y-m-d H:i:s');
        $db = Database::getInstance()->getConnection();
        $params = [
            'github_repository_id' => $githubRepositoryId,
            'owner_login' => $ownerLogin,
            'repository_name' => $repositoryName,
            'repository_data' => json_encode($repositoryData),
            'health_data' => $healthData ? json_encode($healthData) : null,
            'expires_at' => $expiresAt,
        ];

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE repository_cache
                SET owner_login = :owner_login,
                    repository_name = :repository_name,
                    repository_data = :repository_data,
                    health_data = :health_data,
                    fetched_at = NOW(),
                    expires_at = :expires_at,
                    updated_at = NOW()
                WHERE github_repository_id = :github_repository_id
            ");
        } else {
            $stmt = $db->prepare("
                INSERT INTO repository_cache (
                    github_repository_id, owner_login, repository_name, repository_data,
                    health_data, fetched_at, expires_at, created_at, updated_at
                ) VALUES (
                    :github_repository_id, :owner_login, :repository_name, :repository_data,
                    :health_data, NOW(), :expires_at, NOW(), NOW()
                )
            ");
        }

        $stmt->execute($params);
        return self::findByGitHubRepositoryId($githubRepositoryId);
    }

    public static function listAll($freshOnly = false)
    {
        $db = Database::getInstance()->getConnection();
        $sql = "
            SELECT *
            FROM repository_cache
        ";

        if ($freshOnly) {
            $sql .= " WHERE expires_at > NOW()";
        }

        $sql .= " ORDER BY fetched_at DESC, updated_at DESC, github_repository_id ASC";

        $stmt = $db->query($sql);
        return array_map([self::class, 'decode'], $stmt->fetchAll());
    }

    public static function decode($row)
    {
        if (!$row) {
            return null;
        }

        $row['github_repository_id'] = (int) $row['github_repository_id'];
        $row['repository_data'] = $row['repository_data'] ? json_decode($row['repository_data'], true) : [];
        $row['health_data'] = $row['health_data'] ? json_decode($row['health_data'], true) : null;
        return $row;
    }
}
