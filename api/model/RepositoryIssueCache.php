<?php

class RepositoryIssueCache
{
    public static function listFreshByRepositoryId($githubRepositoryId, array $filters = [])
    {
        $db = Database::getInstance()->getConnection();
        $conditions = ['github_repository_id = :github_repository_id', 'expires_at > NOW()'];
        $params = ['github_repository_id' => $githubRepositoryId];

        if (!empty($filters['cursor'])) {
            $conditions[] = 'issue_number > :cursor';
            $params['cursor'] = (int) $filters['cursor'];
        }

        $limit = isset($filters['limit']) ? min(max((int) $filters['limit'], 1), 100) : 30;
        $sql = 'SELECT * FROM repository_issue_cache WHERE ' . implode(' AND ', $conditions) . ' ORDER BY issue_number ASC LIMIT ' . $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return array_map([self::class, 'decode'], $stmt->fetchAll());
    }

    public static function statsByRepositoryId($githubRepositoryId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT issue_data
            FROM repository_issue_cache
            WHERE github_repository_id = :github_repository_id
              AND expires_at > NOW()
        ");
        $stmt->execute(['github_repository_id' => $githubRepositoryId]);

        $stats = [
            'total_issues' => 0,
            'good_first_issues' => 0,
            'help_wanted_issues' => 0,
        ];

        foreach ($stmt->fetchAll() as $row) {
            $issue = $row['issue_data'] ? json_decode($row['issue_data'], true) : [];
            if (!is_array($issue)) {
                continue;
            }

            $stats['total_issues']++;
            $labels = array_map(function ($label) {
                return strtolower($label['name'] ?? '');
            }, $issue['labels'] ?? []);

            if (in_array('good first issue', $labels, true)) {
                $stats['good_first_issues']++;
            }

            if (in_array('help wanted', $labels, true)) {
                $stats['help_wanted_issues']++;
            }
        }

        return $stats;
    }

    public static function upsertMany($githubRepositoryId, array $issues, $ttlSeconds, IssueDifficultyService $difficultyService)
    {
        $db = Database::getInstance()->getConnection();
        $expiresAt = (new DateTime())->modify('+' . $ttlSeconds . ' seconds')->format('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO repository_issue_cache (
                github_repository_id, github_issue_id, issue_number, issue_data,
                difficulty_estimation, fetched_at, expires_at, created_at, updated_at
            ) VALUES (
                :github_repository_id, :github_issue_id, :issue_number, :issue_data,
                :difficulty_estimation, NOW(), :expires_at, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                issue_number = VALUES(issue_number),
                issue_data = VALUES(issue_data),
                difficulty_estimation = VALUES(difficulty_estimation),
                fetched_at = NOW(),
                expires_at = VALUES(expires_at),
                updated_at = NOW()
        ");

        foreach ($issues as $issue) {
            if (isset($issue['pull_request'])) {
                continue;
            }

            $stmt->execute([
                'github_repository_id' => $githubRepositoryId,
                'github_issue_id' => $issue['id'],
                'issue_number' => $issue['number'],
                'issue_data' => json_encode($issue),
                'difficulty_estimation' => json_encode($difficultyService->estimate($issue)),
                'expires_at' => $expiresAt,
            ]);
        }
    }

    private static function decode($row)
    {
        if (!$row) {
            return null;
        }

        $row['github_repository_id'] = (int) $row['github_repository_id'];
        $row['github_issue_id'] = (int) $row['github_issue_id'];
        $row['issue_number'] = (int) $row['issue_number'];
        return RepositoryIssueDto::fromCacheRow($row);
    }
}
