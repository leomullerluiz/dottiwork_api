<?php

use PHPUnit\Framework\TestCase;

class MatchServiceTest extends TestCase
{
    public function testScoreIsDeterministicAndContainsBreakdown(): void
    {
        $service = new MatchService();
        $repository = [
            'id' => 123,
            'name' => 'sample',
            'languages' => ['TypeScript', 'JavaScript'],
            'topics' => ['react', 'nextjs'],
            'updated_at' => date('c'),
            'open_issues_count' => 12,
            'license' => ['spdx_id' => 'MIT'],
            'description' => 'A test repository',
        ];
        $issues = [
            [
                'title' => 'Add test coverage',
                'body' => 'Simple issue for test coverage',
                'comments' => 1,
                'labels' => [['name' => 'good first issue']],
            ],
        ];
        $health = [
            'score' => 90,
            'has_contributing' => true,
            'has_contribution_labels' => true,
            'has_readme' => true,
        ];
        $technologies = [
            ['name' => 'TypeScript', 'github_topics' => ['typescript'], 'proficiency_level' => 'daily'],
            ['name' => 'React', 'github_topics' => ['react'], 'proficiency_level' => 'advanced'],
        ];
        $profile = ['seniority' => 'junior'];
        $preferences = [];

        $first = $service->calculateScore($repository, $issues, $health, $technologies, $profile, $preferences);
        $second = $service->calculateScore($repository, $issues, $health, $technologies, $profile, $preferences);

        $this->assertSame($first, $second);
        $this->assertArrayHasKey('breakdown', $first);
        $this->assertArrayHasKey('stack', $first['breakdown']);
        $this->assertGreaterThan(0, $first['score']);
        $this->assertNotEmpty($first['reasons']);
    }

    public function testFetchCandidatesOnlyReturnsRepositoriesWithOpenIssues(): void
    {
        $service = new MatchService();
        $client = new MatchServiceSearchClient([
            [
                'id' => 1,
                'name' => 'without-issues',
                'open_issues_count' => 0,
            ],
            [
                'id' => 2,
                'name' => 'with-issues',
                'open_issues_count' => 3,
            ],
            [
                'id' => 3,
                'name' => 'legacy-open-issues-field',
                'open_issues' => 1,
            ],
        ]);

        $candidates = $this->invokePrivate($service, 'fetchCandidates', [
            $client,
            [
                [
                    'github_language' => 'PHP',
                    'github_topics' => [],
                ],
            ],
            ['minimum_stars' => 10],
        ]);

        $this->assertSame(['with-issues', 'legacy-open-issues-field'], array_column($candidates, 'name'));
        $this->assertSame('language:PHP archived:false is:public stars:>=10', $client->queries[0]);
        $this->assertSame(15, $client->perPages[0]);
    }

    public function testFetchCandidatesUsesConfigurableSearchAndCandidateLimits(): void
    {
        $previousSearchPerQuery = $_ENV['MATCH_SEARCH_PER_QUERY'] ?? null;
        $previousCandidateLimit = $_ENV['MATCH_CANDIDATE_LIMIT'] ?? null;
        $_ENV['MATCH_SEARCH_PER_QUERY'] = '25';
        $_ENV['MATCH_CANDIDATE_LIMIT'] = '20';

        try {
            $service = new MatchService();
            $items = [];
            for ($i = 1; $i <= 30; $i++) {
                $items[] = [
                    'id' => $i,
                    'name' => 'repo-' . $i,
                    'open_issues_count' => 1,
                ];
            }

            $client = new MatchServiceSearchClient($items);
            $candidates = $this->invokePrivate($service, 'fetchCandidates', [
                $client,
                [
                    [
                        'github_language' => 'TypeScript',
                        'github_topics' => ['react'],
                    ],
                ],
                ['minimum_stars' => 0],
            ]);

            $this->assertCount(20, $candidates);
            $this->assertSame(25, $client->perPages[0]);
        } finally {
            if ($previousSearchPerQuery === null) {
                unset($_ENV['MATCH_SEARCH_PER_QUERY']);
            } else {
                $_ENV['MATCH_SEARCH_PER_QUERY'] = $previousSearchPerQuery;
            }

            if ($previousCandidateLimit === null) {
                unset($_ENV['MATCH_CANDIDATE_LIMIT']);
            } else {
                $_ENV['MATCH_CANDIDATE_LIMIT'] = $previousCandidateLimit;
            }
        }
    }

    public function testOpenIssueItemsIgnoresPullRequestsAndClosedIssues(): void
    {
        $service = new MatchService();

        $issues = $this->invokePrivate($service, 'openIssueItems', [[
            [
                'id' => 1,
                'number' => 10,
                'state' => 'open',
                'title' => 'Open issue',
            ],
            [
                'id' => 2,
                'number' => 11,
                'state' => 'open',
                'pull_request' => ['url' => 'https://api.github.com/pulls/11'],
            ],
            [
                'id' => 3,
                'number' => 12,
                'state' => 'closed',
            ],
        ]]);

        $this->assertSame([1], array_column($issues, 'id'));
    }

    private function invokePrivate($object, $method, array $args)
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($object, $args);
    }
}

class MatchServiceSearchClient extends GitHubClient
{
    public $queries = [];
    public $perPages = [];
    private $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function searchRepositories($query, $page = 1, $perPage = 10)
    {
        $this->queries[] = $query;
        $this->perPages[] = $perPage;
        return ['items' => $this->items];
    }
}
