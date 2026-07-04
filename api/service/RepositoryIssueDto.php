<?php

class RepositoryIssueDto
{
    public static function fromCacheRow(array $row)
    {
        $issue = $row['issue_data'] ?? [];
        $difficulty = $row['difficulty_estimation'] ?? null;

        if (is_string($issue)) {
            $decoded = json_decode($issue, true);
            $issue = is_array($decoded) ? $decoded : [];
        }

        if (is_string($difficulty)) {
            $decoded = json_decode($difficulty, true);
            $difficulty = is_array($decoded) ? $decoded : null;
        }

        return self::fromGitHubIssue(
            $issue,
            is_array($difficulty) ? $difficulty : null,
            isset($row['github_repository_id']) ? (int) $row['github_repository_id'] : null,
            $row
        );
    }

    public static function fromGitHubIssue(array $issue, array $difficultyEstimation = null, $githubRepositoryId = null, array $cacheMeta = [])
    {
        $labels = self::labels($issue['labels'] ?? []);
        $labelNames = array_map(function ($label) {
            return strtolower($label['name']);
        }, $labels);

        $difficulty = self::difficulty($difficultyEstimation['level'] ?? null);
        $confidence = isset($difficultyEstimation['confidence']) && is_numeric($difficultyEstimation['confidence'])
            ? (float) min(1, max(0, (float) $difficultyEstimation['confidence']))
            : null;

        return [
            'github_issue_id' => isset($issue['id']) ? (int) $issue['id'] : (isset($cacheMeta['github_issue_id']) ? (int) $cacheMeta['github_issue_id'] : null),
            'github_repository_id' => $githubRepositoryId,
            'number' => isset($issue['number']) ? (int) $issue['number'] : (isset($cacheMeta['issue_number']) ? (int) $cacheMeta['issue_number'] : null),
            'title' => self::stringOrNull($issue['title'] ?? null),
            'body_excerpt' => self::excerpt($issue['body'] ?? null),
            'url' => self::stringOrNull($issue['html_url'] ?? $issue['url'] ?? null),
            'state' => self::stringOrNull($issue['state'] ?? null) ?: 'open',
            'labels' => $labels,
            'comments' => max(0, (int) ($issue['comments'] ?? 0)),
            'created_at' => self::stringOrNull($issue['created_at'] ?? null),
            'updated_at' => self::stringOrNull($issue['updated_at'] ?? null),
            'is_good_first_issue' => in_array('good first issue', $labelNames, true),
            'is_help_wanted' => in_array('help wanted', $labelNames, true),
            'difficulty' => $difficulty,
            'confidence' => $confidence,
            'contribution_type' => self::contributionType($issue, $labelNames),
            'difficulty_estimation' => $difficultyEstimation,
            'issue_number' => isset($issue['number']) ? (int) $issue['number'] : (isset($cacheMeta['issue_number']) ? (int) $cacheMeta['issue_number'] : null),
            'fetched_at' => $cacheMeta['fetched_at'] ?? null,
            'expires_at' => $cacheMeta['expires_at'] ?? null,
            'created_cache_at' => $cacheMeta['created_at'] ?? null,
            'updated_cache_at' => $cacheMeta['updated_at'] ?? null,
        ];
    }

    private static function labels(array $labels)
    {
        return array_values(array_map(function ($label) {
            return [
                'name' => self::stringOrNull($label['name'] ?? null) ?: '',
                'color' => self::stringOrNull($label['color'] ?? null),
            ];
        }, array_filter($labels, function ($label) {
            return is_array($label) && self::stringOrNull($label['name'] ?? null) !== null;
        })));
    }

    private static function difficulty($level)
    {
        $map = [
            'beginner' => 'easy',
            'easy' => 'easy',
            'intermediate' => 'medium',
            'medium' => 'medium',
            'advanced' => 'hard',
            'hard' => 'hard',
        ];

        $key = strtolower((string) $level);
        return $map[$key] ?? 'unknown';
    }

    private static function contributionType(array $issue, array $labelNames)
    {
        $title = strtolower((string) ($issue['title'] ?? ''));
        $body = strtolower((string) ($issue['body'] ?? ''));
        $text = $title . ' ' . $body . ' ' . implode(' ', $labelNames);

        if (self::containsAny($text, ['doc', 'readme', 'documentation', 'translation'])) {
            return 'documentation';
        }
        if (self::containsAny($text, ['test', 'spec', 'coverage'])) {
            return 'test';
        }
        if (self::containsAny($text, ['refactor', 'cleanup', 'clean up'])) {
            return 'refactor';
        }
        if (self::containsAny($text, ['feature', 'enhancement', 'add support'])) {
            return 'feature';
        }
        if (self::containsAny($text, ['bug', 'fix', 'error', 'crash', 'broken'])) {
            return 'bugfix';
        }

        return 'unknown';
    }

    private static function containsAny($text, array $needles)
    {
        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function excerpt($body)
    {
        $body = self::stringOrNull($body);
        if ($body === null) {
            return null;
        }

        $body = preg_replace('/\s+/', ' ', trim($body));
        if (mb_strlen($body) <= 240) {
            return $body;
        }

        return rtrim(mb_substr($body, 0, 237)) . '...';
    }

    private static function stringOrNull($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
