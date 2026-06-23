<?php

class MatchService
{
    private $githubConfig;
    private $difficultyService;
    private $healthService;

    public function __construct()
    {
        $this->githubConfig = require __DIR__ . '/../config/github.php';
        $this->difficultyService = new IssueDifficultyService();
        $this->healthService = new RepositoryHealthService();
    }

    public function listMatches($userId, array $filters)
    {
        return UserRepositoryMatch::listByUser($userId, $filters);
    }

    public function getMatch($userId, $githubRepositoryId)
    {
        return UserRepositoryMatch::findByUserAndRepository($userId, $githubRepositoryId);
    }

    public function refresh($user)
    {
        $lastGeneratedAt = UserRepositoryMatch::lastGeneratedAt($user['id']);
        if ($lastGeneratedAt) {
            $elapsed = time() - strtotime($lastGeneratedAt);
            if ($elapsed < $this->githubConfig['match_refresh_cooldown_seconds']) {
                return [
                    'refreshed' => false,
                    'cooldown_seconds_remaining' => $this->githubConfig['match_refresh_cooldown_seconds'] - $elapsed,
                    'items' => $this->listMatches($user['id'], []),
                ];
            }
        }

        $account = OAuthAccount::findByUserAndProvider($user['id'], 'github');
        $accessToken = $account ? OAuthAccount::decryptAccessToken($account) : null;
        $client = new GitHubClient($accessToken);
        $technologies = UserTechnology::findByUserId($user['id']);
        $profile = UserProfile::getComplete($user['id']);
        $preferences = UserPreference::findByUserId($user['id']);

        $candidates = $this->fetchCandidates($client, $technologies, $preferences);
        $matches = [];

        foreach ($candidates as $repository) {
            $owner = $repository['owner']['login'] ?? null;
            $repo = $repository['name'] ?? null;
            if (!$owner || !$repo || !empty($repository['private'])) {
                continue;
            }

            try {
                $repository['languages'] = array_keys($client->getRepositoryLanguages($owner, $repo));
                if (empty($repository['topics'])) {
                    $topicsResponse = $client->getRepositoryTopics($owner, $repo);
                    $repository['topics'] = $topicsResponse['names'] ?? [];
                }

                $labels = $client->getRepositoryLabels($owner, $repo);
                $contents = $client->getRepositoryContents($owner, $repo);
                $health = $this->healthService->analyze($repository, $labels, $contents);
                RepositoryCache::upsert($repository, $health, $this->githubConfig['repository_cache_ttl_seconds']);

                $issues = $client->getRepositoryIssues($owner, $repo, ['per_page' => 20]);
                RepositoryIssueCache::upsertMany($repository['id'], $issues, $this->githubConfig['issues_cache_ttl_seconds'], $this->difficultyService);

                $score = $this->calculateScore($repository, $issues, $health, $technologies, $profile, $preferences);
                $matches[] = [
                    'github_repository_id' => $repository['id'],
                    'score' => $score['score'],
                    'breakdown' => $score['breakdown'],
                    'reasons' => $score['reasons'],
                ];
            } catch (Exception $e) {
                if (RepositoryCache::findByGitHubRepositoryId($repository['id'] ?? 0)) {
                    continue;
                }
                throw $e;
            }
        }

        usort($matches, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $matches = array_slice($matches, 0, 30);
        UserRepositoryMatch::upsertMany($user['id'], $matches, $this->githubConfig['match_cache_ttl_seconds']);

        return [
            'refreshed' => true,
            'items' => $this->listMatches($user['id'], []),
        ];
    }

    public function calculateScore(array $repository, array $issues, array $health, array $technologies, array $profile, array $preferences)
    {
        $repoLanguages = array_map('strtolower', $repository['languages'] ?? []);
        $repoTopics = array_map('strtolower', $repository['topics'] ?? []);
        $techNames = array_map(function ($tech) {
            return strtolower($tech['name']);
        }, $technologies);
        $techTopics = [];
        foreach ($technologies as $technology) {
            foreach ($technology['github_topics'] ?? [] as $topic) {
                $techTopics[] = strtolower($topic);
            }
        }

        $languageMatches = count(array_intersect($repoLanguages, $techNames));
        $topicMatches = count(array_intersect($repoTopics, array_merge($techNames, $techTopics)));
        $stack = min(35, ($languageMatches > 0 ? 15 : 0) + min(15, $topicMatches * 5) + min(5, count($technologies)));

        $estimatedIssues = array_map([$this->difficultyService, 'estimate'], array_filter($issues, function ($issue) {
            return !isset($issue['pull_request']);
        }));

        $beginnerIssues = count(array_filter($estimatedIssues, function ($item) {
            return $item['level'] === 'beginner';
        }));
        $helpfulLabels = $this->countHelpfulIssues($issues);

        $seniority = $profile['seniority'] ?? null;
        $difficulty = 10;
        if ($seniority === 'junior') {
            $difficulty = min(20, 8 + $beginnerIssues * 4);
        } elseif ($seniority === 'mid') {
            $difficulty = min(20, 10 + $helpfulLabels * 2);
        } elseif ($seniority === 'senior') {
            $difficulty = min(20, 12 + max(0, (int) ($repository['open_issues_count'] ?? 0) > 50 ? 4 : 0));
        }

        $issuesScore = min(15, $helpfulLabels * 3 + $beginnerIssues * 2);
        $activity = $this->activityScore($repository['updated_at'] ?? null);
        $healthScore = min(10, round(($health['score'] ?? 0) / 10));
        $contributionReadiness = min(10, ($health['has_contributing'] ?? false ? 4 : 0) + ($health['has_contribution_labels'] ?? false ? 4 : 0) + ($health['has_readme'] ?? false ? 2 : 0));

        $breakdown = [
            'stack' => $stack,
            'difficulty' => $difficulty,
            'issues' => $issuesScore,
            'activity' => $activity,
            'health' => $healthScore,
            'contribution_readiness' => $contributionReadiness,
        ];

        $score = array_sum($breakdown);
        $reasons = $this->buildReasons($repository, $breakdown, $languageMatches, $topicMatches, $helpfulLabels, $health);

        return [
            'score' => min(100, round($score, 2)),
            'breakdown' => $breakdown,
            'reasons' => $reasons,
        ];
    }

    private function fetchCandidates(GitHubClient $client, array $technologies, array $preferences)
    {
        $queries = [];
        $minimumStars = (int) ($preferences['minimum_stars'] ?? 0);

        foreach (array_slice($technologies, 0, 5) as $technology) {
            if (!empty($technology['github_language'])) {
                $queries[] = 'language:' . $technology['github_language'];
            }

            foreach (array_slice($technology['github_topics'] ?? [], 0, 2) as $topic) {
                $queries[] = 'topic:' . $topic;
            }
        }

        if (!$queries) {
            $queries[] = 'topic:good-first-issue';
            $queries[] = 'topic:opensource';
        }

        $repositories = [];
        $seen = [];

        foreach (array_unique($queries) as $query) {
            $fullQuery = $query . ' archived:false is:public stars:>=' . $minimumStars;
            $result = $client->searchRepositories($fullQuery, 1, 5);

            foreach ($result['items'] ?? [] as $repository) {
                if (isset($seen[$repository['id']])) {
                    continue;
                }
                $seen[$repository['id']] = true;
                $repositories[] = $repository;
            }
        }

        return array_slice($repositories, 0, 12);
    }

    private function countHelpfulIssues(array $issues)
    {
        $count = 0;
        foreach ($issues as $issue) {
            if (isset($issue['pull_request'])) {
                continue;
            }
            $labels = array_map(function ($label) {
                return strtolower($label['name'] ?? '');
            }, $issue['labels'] ?? []);

            if (array_intersect($labels, ['good first issue', 'help wanted', 'bug', 'documentation', 'tests', 'accessibility', 'performance', 'refactor'])) {
                $count++;
            }
        }
        return $count;
    }

    private function activityScore($updatedAt)
    {
        if (!$updatedAt) {
            return 0;
        }

        $days = (time() - strtotime($updatedAt)) / 86400;
        if ($days <= 30) {
            return 10;
        }
        if ($days <= 90) {
            return 7;
        }
        if ($days <= 180) {
            return 4;
        }
        return 1;
    }

    private function buildReasons(array $repository, array $breakdown, $languageMatches, $topicMatches, $helpfulLabels, array $health)
    {
        $reasons = [];

        if ($languageMatches > 0) {
            $reasons[] = 'Repository language matches your stack';
        }

        if ($topicMatches > 0) {
            $reasons[] = 'Repository topics match your technologies';
        }

        if ($breakdown['activity'] >= 7) {
            $reasons[] = 'Repository is active recently';
        }

        if ($helpfulLabels > 0) {
            $reasons[] = 'Contribution-friendly issues are available';
        }

        if (!empty($health['has_contributing'])) {
            $reasons[] = 'Contribution guide found';
        }

        if (!$reasons) {
            $reasons[] = 'Repository has public activity and can be explored';
        }

        return $reasons;
    }
}
