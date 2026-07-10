<?php

class RepositorySummary
{
    public static function fromCacheRow(array $cacheRow, array $issueStats = null)
    {
        $repository = $cacheRow['repository_data'] ?? [];
        $health = $cacheRow['health_data'] ?? null;

        if (isset($cacheRow['github_repository_id']) && !isset($repository['id'])) {
            $repository['id'] = $cacheRow['github_repository_id'];
        }

        if (isset($cacheRow['owner_login']) && !isset($repository['owner_login'])) {
            $repository['owner_login'] = $cacheRow['owner_login'];
        }

        if (isset($cacheRow['repository_name']) && !isset($repository['name'])) {
            $repository['name'] = $cacheRow['repository_name'];
        }

        return self::fromGitHubRepository($repository, $health, $issueStats);
    }

    public static function fromGitHubRepository(array $repository, array $health = null, array $issueStats = null)
    {
        [$owner, $name] = self::resolveOwnerAndName($repository);
        $fullName = $owner && $name ? $owner . '/' . $name : self::stringOrNull($repository['full_name'] ?? null);
        $url = self::stringOrNull($repository['html_url'] ?? null);
        if ($owner && $name) {
            $url = $url ?: 'https://github.com/' . rawurlencode($owner) . '/' . rawurlencode($name);
        }
        if (!$url) {
            $url = self::stringOrNull($repository['url'] ?? null);
        }

        $languages = self::normalizeStringList($repository['languages'] ?? []);
        $primaryLanguage = self::stringOrNull($repository['language'] ?? null) ?: ($languages[0] ?? null);
        if ($primaryLanguage && !in_array($primaryLanguage, $languages, true)) {
            array_unshift($languages, $primaryLanguage);
        }

        $topics = self::normalizeStringList($repository['topics'] ?? []);
        $stars = self::intValue($repository['stargazers_count'] ?? $repository['stars'] ?? 0);
        $forks = self::intValue($repository['forks_count'] ?? $repository['forks'] ?? 0);
        $openIssues = self::intValue($repository['open_issues_count'] ?? $repository['open_issues'] ?? 0);
        $contributors = self::intValue($repository['contributors_count'] ?? $repository['contributors'] ?? 0);
        $lastPushedAt = self::stringOrNull($repository['pushed_at'] ?? $repository['last_pushed_at'] ?? null);
        $lastUpdatedAt = self::stringOrNull($repository['updated_at'] ?? $repository['last_updated_at'] ?? null);
        $activityDate = $lastPushedAt ?: $lastUpdatedAt;
        $activityScore = self::activityScore($activityDate);
        $homepageUrl = self::stringOrNull($repository['homepage'] ?? $repository['homepage_url'] ?? null);

        $summary = [
            'github_repository_id' => isset($repository['id']) ? (int) $repository['id'] : null,
            'owner' => $owner,
            'name' => $name,
            'full_name' => $fullName,
            'description' => self::stringOrNull($repository['description'] ?? null),
            'url' => $url,
            'homepage_url' => $homepageUrl,
            'avatar_url' => self::stringOrNull($repository['owner']['avatar_url'] ?? $repository['avatar_url'] ?? null),
            'primary_language' => $primaryLanguage,
            'languages' => $languages,
            'topics' => $topics,
            'stars' => $stars,
            'forks' => $forks,
            'watchers' => self::intValue($repository['watchers_count'] ?? $repository['watchers'] ?? 0),
            'open_issues' => $openIssues,
            'contributors' => $contributors,
            'good_first_issues' => self::issueCount($repository, $issueStats, 'good_first_issues'),
            'help_wanted_issues' => self::issueCount($repository, $issueStats, 'help_wanted_issues'),
            'license' => self::license($repository['license'] ?? null),
            'last_pushed_at' => $lastPushedAt,
            'last_updated_at' => $lastUpdatedAt,
            'project_size' => self::projectSize($repository, $stars, $forks, $openIssues),
            'activity_score' => $activityScore,
            'activity_label' => self::activityLabel($activityScore),
            'health_score' => self::healthScore($health, $repository),
        ];

        return array_merge($summary, [
            'html_url' => $summary['url'],
            'homepage' => $summary['homepage_url'],
            'updated_at' => $summary['last_updated_at'],
        ]);
    }

    private static function resolveOwnerAndName(array $repository)
    {
        $owner = self::stringOrNull($repository['owner']['login'] ?? $repository['owner_login'] ?? null);
        $name = self::stringOrNull($repository['name'] ?? $repository['repository_name'] ?? null);
        $fullName = self::stringOrNull($repository['full_name'] ?? null);

        if ((!$owner || !$name) && $fullName && strpos($fullName, '/') !== false) {
            [$parsedOwner, $parsedName] = explode('/', $fullName, 2);
            $owner = $owner ?: self::stringOrNull($parsedOwner);
            $name = $name ?: self::stringOrNull($parsedName);
        }

        return [$owner, $name];
    }

    private static function normalizeStringList($value)
    {
        if (!is_array($value)) {
            $item = self::stringOrNull($value);
            return $item ? [$item] : [];
        }

        $items = self::isList($value) ? $value : array_keys($value);
        $normalized = [];
        foreach ($items as $item) {
            $item = self::stringOrNull($item);
            if ($item && !in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    private static function stringOrNull($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function intValue($value)
    {
        return max(0, (int) $value);
    }

    private static function issueCount(array $repository, array $issueStats = null, $field)
    {
        return self::intValue($issueStats[$field] ?? $repository[$field] ?? 0);
    }

    private static function license($license)
    {
        if (is_array($license)) {
            foreach (['spdx_id', 'key', 'name'] as $field) {
                $value = self::stringOrNull($license[$field] ?? null);
                if ($value && strtoupper($value) !== 'NOASSERTION') {
                    return $value;
                }
            }

            return null;
        }

        return self::stringOrNull($license);
    }

    private static function projectSize(array $repository, $stars, $forks, $openIssues)
    {
        $configured = self::stringOrNull($repository['project_size'] ?? null);
        if ($configured && in_array($configured, ['small', 'medium', 'large'], true)) {
            return $configured;
        }

        if (isset($repository['size']) && is_numeric($repository['size'])) {
            $sizeKb = (int) $repository['size'];
            if ($sizeKb >= 500000) {
                return 'large';
            }
            if ($sizeKb >= 50000) {
                return 'medium';
            }
            return 'small';
        }

        if ($stars >= 10000 || $forks >= 2000 || $openIssues >= 1000) {
            return 'large';
        }

        if ($stars >= 1000 || $forks >= 200 || $openIssues >= 100) {
            return 'medium';
        }

        return 'small';
    }

    private static function activityScore($date)
    {
        if (!$date) {
            return 0;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return 0;
        }

        $days = max(0, (time() - $timestamp) / 86400);
        if ($days <= 7) {
            return 100;
        }
        if ($days <= 30) {
            return 85;
        }
        if ($days <= 90) {
            return 65;
        }
        if ($days <= 180) {
            return 45;
        }
        if ($days <= 365) {
            return 25;
        }
        return 10;
    }

    private static function activityLabel($score)
    {
        if ($score >= 90) {
            return 'very_active';
        }
        if ($score >= 60) {
            return 'active';
        }
        if ($score >= 30) {
            return 'moderate';
        }
        return 'low';
    }

    private static function healthScore(array $health = null, array $repository = [])
    {
        $score = $health['score'] ?? $repository['health_score'] ?? null;
        if ($score === null || !is_numeric($score)) {
            return null;
        }

        return min(100, max(0, (int) round((float) $score)));
    }

    private static function isList(array $value)
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
