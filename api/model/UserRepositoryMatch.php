<?php

class UserRepositoryMatch
{
    public static function listByUser($userId, array $filters = [])
    {
        $db = Database::getInstance()->getConnection();
        $conditions = ['m.user_id = :user_id', 'm.expires_at > NOW()'];
        $params = ['user_id' => $userId];

        if (!empty($filters['minimum_score'])) {
            $conditions[] = 'm.match_score >= :minimum_score';
            $params['minimum_score'] = (float) $filters['minimum_score'];
        }

        if (!empty($filters['cursor'])) {
            $conditions[] = 'm.id < :cursor';
            $params['cursor'] = (int) $filters['cursor'];
        }

        if (empty($filters['state'])) {
            $conditions[] = "(s.state IS NULL OR s.state <> 'ignored')";
        } else {
            $conditions[] = 's.state = :state';
            $params['state'] = $filters['state'];
        }

        $sort = $filters['sort_by'] ?? 'best_match';
        $order = $sort === 'recently_updated'
            ? 'r.updated_at DESC, m.match_score DESC'
            : 'm.match_score DESC, m.id DESC';

        $limit = isset($filters['limit']) ? min(max((int) $filters['limit'], 1), 100) : 30;

        $sql = "
            SELECT m.*, r.repository_data, r.health_data, s.state AS user_state
            FROM user_repository_matches m
            INNER JOIN repository_cache r ON r.github_repository_id = m.github_repository_id
            LEFT JOIN user_repository_states s
              ON s.user_id = m.user_id AND s.github_repository_id = m.github_repository_id
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY {$order}
            LIMIT {$limit}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_map([self::class, 'decodeJoined'], $stmt->fetchAll());
    }

    public static function findByUserAndRepository($userId, $githubRepositoryId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT m.*, r.repository_data, r.health_data, s.state AS user_state
            FROM user_repository_matches m
            INNER JOIN repository_cache r ON r.github_repository_id = m.github_repository_id
            LEFT JOIN user_repository_states s
              ON s.user_id = m.user_id AND s.github_repository_id = m.github_repository_id
            WHERE m.user_id = :user_id AND m.github_repository_id = :github_repository_id
            LIMIT 1
        ");
        $stmt->execute([
            'user_id' => $userId,
            'github_repository_id' => $githubRepositoryId,
        ]);
        return self::decodeJoined($stmt->fetch());
    }

    public static function upsertMany($userId, array $matches, $ttlSeconds)
    {
        $db = Database::getInstance()->getConnection();
        $expiresAt = (new DateTime())->modify('+' . $ttlSeconds . ' seconds')->format('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO user_repository_matches (
                user_id, github_repository_id, match_score, score_breakdown,
                reasons, generated_at, expires_at, created_at, updated_at
            ) VALUES (
                :user_id, :github_repository_id, :match_score, :score_breakdown,
                :reasons, NOW(), :expires_at, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                match_score = VALUES(match_score),
                score_breakdown = VALUES(score_breakdown),
                reasons = VALUES(reasons),
                generated_at = NOW(),
                expires_at = VALUES(expires_at),
                updated_at = NOW()
        ");

        foreach ($matches as $match) {
            $stmt->execute([
                'user_id' => $userId,
                'github_repository_id' => $match['github_repository_id'],
                'match_score' => $match['score'],
                'score_breakdown' => json_encode($match['breakdown']),
                'reasons' => json_encode($match['reasons']),
                'expires_at' => $expiresAt,
            ]);
        }
    }

    public static function invalidateByUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE user_repository_matches
            SET expires_at = NOW(), updated_at = NOW()
            WHERE user_id = :user_id AND expires_at > NOW()
        ");
        $stmt->execute(['user_id' => $userId]);
    }

    public static function lastGeneratedAt($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT MAX(generated_at) AS generated_at FROM user_repository_matches WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ? $row['generated_at'] : null;
    }

    private static function decodeJoined($row)
    {
        if (!$row) {
            return null;
        }

        $repository = $row['repository_data'] ? json_decode($row['repository_data'], true) : [];
        $health = $row['health_data'] ? json_decode($row['health_data'], true) : null;
        $issueStats = RepositoryIssueCache::statsByRepositoryId($row['github_repository_id']);
        $breakdown = $row['score_breakdown'] ? json_decode($row['score_breakdown'], true) : [];
        $reasons = $row['reasons'] ? json_decode($row['reasons'], true) : [];

        return [
            'repository' => self::normalizeRepository($repository, $health, $issueStats),
            'match' => [
                'score' => (float) $row['match_score'],
                'recommended_seniority' => self::recommendedSeniority((float) $row['match_score']),
                'breakdown' => $breakdown,
                'reasons' => $reasons,
                'generated_at' => $row['generated_at'],
                'expires_at' => $row['expires_at'],
            ],
            'user_state' => $row['user_state'] ?? null,
        ];
    }

    public static function normalizeRepository(array $repository, array $health = null, array $issueStats = null)
    {
        return RepositorySummary::fromGitHubRepository($repository, $health, $issueStats);
    }

    private static function recommendedSeniority($score)
    {
        if ($score >= 85) {
            return 'mid';
        }

        if ($score >= 70) {
            return 'junior';
        }

        return 'senior';
    }
}
