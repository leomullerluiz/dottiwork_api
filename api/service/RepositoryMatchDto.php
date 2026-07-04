<?php

class RepositoryMatchDto
{
    public static function fromComponents(
        array $repository,
        array $health = null,
        array $issueStats = null,
        array $matchData = [],
        $userState = null,
        array $userTechnologies = []
    ) {
        $repositorySummary = RepositorySummary::fromGitHubRepository($repository, $health, $issueStats);
        $score = (float) ($matchData['score'] ?? 0);
        $breakdown = is_array($matchData['breakdown'] ?? null) ? $matchData['breakdown'] : [];
        $reasons = self::stringList($matchData['reasons'] ?? []);
        $sharedTechnologies = self::sharedTechnologies($repositorySummary, $repository, $userTechnologies);
        $recommendedSeniority = self::recommendedSeniority($score, $repositorySummary, $breakdown);
        $healthChecklist = self::healthChecklist($health ?? []);
        $positives = self::positives($repositorySummary, $health ?? [], $sharedTechnologies);
        $challenges = self::challenges($repositorySummary, $health ?? []);

        $match = [
            'github_repository_id' => $repositorySummary['github_repository_id'],
            'score' => $score,
            'recommended_seniority' => $recommendedSeniority,
            'breakdown' => $breakdown,
            'reasons' => $reasons,
            'match_reasons' => $reasons,
            'shared_technologies' => $sharedTechnologies,
            'positives' => $positives,
            'challenges' => $challenges,
            'health_checklist' => $healthChecklist,
            'generated_at' => $matchData['generated_at'] ?? null,
            'expires_at' => $matchData['expires_at'] ?? null,
            'cached' => true,
        ];

        return [
            'github_repository_id' => $repositorySummary['github_repository_id'],
            'score' => $score,
            'recommended_seniority' => $recommendedSeniority,
            'match_reasons' => $reasons,
            'shared_technologies' => $sharedTechnologies,
            'positives' => $positives,
            'challenges' => $challenges,
            'health_checklist' => $healthChecklist,
            'generated_at' => $match['generated_at'],
            'expires_at' => $match['expires_at'],
            'cached' => true,
            'repository' => $repositorySummary,
            'match' => $match,
            'user_state' => $userState,
        ];
    }

    private static function sharedTechnologies(array $repositorySummary, array $repository, array $userTechnologies)
    {
        if (!$userTechnologies) {
            return [];
        }

        $repoLanguages = self::lowerMap($repositorySummary['languages'] ?? []);
        $repoTopics = self::lowerMap($repositorySummary['topics'] ?? []);
        $shared = [];

        foreach ($userTechnologies as $technology) {
            $name = self::stringOrNull($technology['name'] ?? null);
            if (!$name) {
                continue;
            }

            $candidates = [$name];
            if (!empty($technology['github_language'])) {
                $candidates[] = $technology['github_language'];
            }
            foreach ($technology['github_topics'] ?? [] as $topic) {
                $candidates[] = $topic;
            }

            foreach ($candidates as $candidate) {
                $key = strtolower((string) $candidate);
                if (isset($repoLanguages[$key]) || isset($repoTopics[$key])) {
                    $shared[$name] = $name;
                    break;
                }
            }
        }

        return array_values($shared);
    }

    private static function recommendedSeniority($score, array $repositorySummary, array $breakdown)
    {
        $difficultyScore = (float) ($breakdown['difficulty'] ?? 0);
        $goodFirstIssues = (int) ($repositorySummary['good_first_issues'] ?? 0);
        $projectSize = $repositorySummary['project_size'] ?? 'small';
        $healthScore = $repositorySummary['health_score'];
        $openIssues = (int) ($repositorySummary['open_issues'] ?? 0);

        if ($goodFirstIssues > 0 && $projectSize !== 'large' && $difficultyScore >= 14) {
            return 'junior';
        }

        if ($projectSize === 'large' && $goodFirstIssues === 0) {
            return 'senior';
        }

        if ($openIssues >= 500 && $goodFirstIssues === 0) {
            return 'senior';
        }

        if ($healthScore !== null && $healthScore < 45 && $goodFirstIssues === 0) {
            return 'senior';
        }

        if ($difficultyScore >= 12 || $score >= 70) {
            return 'mid';
        }

        return 'senior';
    }

    private static function positives(array $repositorySummary, array $health, array $sharedTechnologies)
    {
        $positives = [];

        if ($sharedTechnologies) {
            $positives[] = 'Matches your stack';
        }
        if (($repositorySummary['activity_score'] ?? 0) >= 60) {
            $positives[] = 'Active repository';
        }
        if (($repositorySummary['good_first_issues'] ?? 0) > 0) {
            $positives[] = 'Beginner-friendly issues';
        }
        if (!empty($health['has_contributing'])) {
            $positives[] = 'Contribution guide available';
        }
        if (($repositorySummary['health_score'] ?? 0) >= 70) {
            $positives[] = 'Healthy project signals';
        }

        return $positives ?: ['Repository can be explored'];
    }

    private static function challenges(array $repositorySummary, array $health)
    {
        $challenges = [];

        if (($repositorySummary['project_size'] ?? null) === 'large') {
            $challenges[] = 'Large codebase';
        }
        if (($repositorySummary['activity_label'] ?? 'low') === 'low') {
            $challenges[] = 'Low recent activity';
        }
        if (($repositorySummary['good_first_issues'] ?? 0) === 0) {
            $challenges[] = 'Few beginner-friendly issues';
        }
        if (($repositorySummary['open_issues'] ?? 0) >= 1000) {
            $challenges[] = 'High issue volume';
        }
        if (empty($health['has_contributing'])) {
            $challenges[] = 'Contribution guide not detected';
        }

        return $challenges;
    }

    private static function healthChecklist(array $health)
    {
        $labels = [
            'has_readme' => 'Has README',
            'has_contributing' => 'Has contributing guide',
            'has_code_of_conduct' => 'Has code of conduct',
            'has_ci' => 'Has CI configuration',
            'has_tests' => 'Has tests',
            'has_contribution_labels' => 'Has contribution labels',
        ];

        $items = [];
        foreach ($labels as $key => $label) {
            $items[] = [
                'key' => $key,
                'label' => $label,
                'passed' => (bool) ($health[$key] ?? false),
            ];
        }

        return $items;
    }

    private static function lowerMap(array $values)
    {
        $map = [];
        foreach ($values as $value) {
            $value = self::stringOrNull($value);
            if ($value) {
                $map[strtolower($value)] = $value;
            }
        }

        return $map;
    }

    private static function stringList($values)
    {
        if (!is_array($values)) {
            return [];
        }

        $list = [];
        foreach ($values as $value) {
            $value = self::stringOrNull($value);
            if ($value !== null) {
                $list[] = $value;
            }
        }

        return $list;
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
