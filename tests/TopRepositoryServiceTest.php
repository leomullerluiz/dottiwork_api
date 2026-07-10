<?php

use PHPUnit\Framework\TestCase;

class TopRepositoryServiceTest extends TestCase
{
    public function testListsRepositoriesRankedByStarsWithStableCursorAndRanks(): void
    {
        $service = new TopRepositoryService();
        $filters = [
            'sort_by' => 'stars',
            'technology' => null,
            'limit' => 2,
            'cursor' => null,
        ];

        $page = $service->list($this->cacheRows(), $filters);

        $this->assertCount(2, $page['items']);
        $this->assertSame('frontend/reactive', $page['items'][0]['repository']['full_name']);
        $this->assertSame(1, $page['items'][0]['rank']);
        $this->assertSame(400, $page['items'][0]['rank_metric']['value']);
        $this->assertSame('stars', $page['items'][0]['rank_metric']['type']);
        $this->assertNull($page['items'][0]['user_state']);
        $this->assertNotNull($page['pagination']['next_cursor']);
        $this->assertTrue($page['metadata']['cached']);

        $nextPage = $service->list($this->cacheRows(), array_merge($filters, [
            'cursor' => $page['pagination']['next_cursor'],
        ]));

        $this->assertCount(2, $nextPage['items']);
        $this->assertSame('tools/bundler', $nextPage['items'][0]['repository']['full_name']);
        $this->assertSame(3, $nextPage['items'][0]['rank']);
        $this->assertNull($nextPage['pagination']['next_cursor']);
    }

    public function testFiltersRepositoriesByTechnologySignals(): void
    {
        $service = new TopRepositoryService();

        $typescript = $service->list($this->cacheRows(), [
            'sort_by' => 'stars',
            'technology' => 'typescript',
            'limit' => 10,
            'cursor' => null,
        ], [
            'slug' => 'typescript',
            'github_language' => 'TypeScript',
            'github_topics' => ['typescript'],
        ]);

        $this->assertCount(2, $typescript['items']);
        $this->assertSame(['frontend/reactive', 'tools/bundler'], array_map(function ($item) {
            return $item['repository']['full_name'];
        }, $typescript['items']));

        $react = $service->list($this->cacheRows(), [
            'sort_by' => 'stars',
            'technology' => 'react',
            'limit' => 10,
            'cursor' => null,
        ], [
            'slug' => 'react',
            'github_language' => null,
            'github_topics' => ['react'],
        ]);

        $this->assertCount(1, $react['items']);
        $this->assertSame('frontend/reactive', $react['items'][0]['repository']['full_name']);
    }

    public function testSupportsOpenIssuesAndContributorsTieBreakers(): void
    {
        $service = new TopRepositoryService();

        $issues = $service->list($this->cacheRows(), [
            'sort_by' => 'open_issues',
            'technology' => null,
            'limit' => 10,
            'cursor' => null,
        ]);

        $this->assertSame(['backend/api-core', 'frontend/reactive', 'tools/bundler', 'legacy/cobol-kit'], array_map(function ($item) {
            return $item['repository']['full_name'];
        }, $issues['items']));

        $contributors = $service->list($this->cacheRows(), [
            'sort_by' => 'contributors',
            'technology' => null,
            'limit' => 10,
            'cursor' => null,
        ]);

        $this->assertSame(['frontend/reactive', 'backend/api-core', 'tools/bundler', 'legacy/cobol-kit'], array_map(function ($item) {
            return $item['repository']['full_name'];
        }, $contributors['items']));
    }

    public function testValidatesCursorAgainstCurrentRankingContext(): void
    {
        $cursor = TopRepositoryService::encodeCursor([
            'sort_by' => 'stars',
            'technology' => 'typescript',
            'metric_value' => 400,
            'stars' => 400,
            'updated_at' => '2026-07-10T12:00:00Z',
            'github_repository_id' => 10,
        ]);

        $errors = TopRepositoryService::validate([
            'sort_by' => 'contributors',
            'technology' => 'typescript',
            'limit' => 30,
            'cursor' => $cursor,
        ]);

        $this->assertSame('cursor', $errors[0]['field']);
    }

    private function cacheRows(): array
    {
        return [
            $this->cacheRow(10, 'frontend', 'reactive', [
                'languages' => ['TypeScript', 'JavaScript'],
                'topics' => ['react', 'ui', 'typescript'],
                'stars' => 400,
                'open_issues' => 15,
                'contributors' => 22,
                'updated_at' => '2026-07-10T12:00:00Z',
            ]),
            $this->cacheRow(20, 'backend', 'api-core', [
                'languages' => ['PHP'],
                'topics' => ['api', 'backend'],
                'stars' => 250,
                'open_issues' => 40,
                'contributors' => 18,
                'updated_at' => '2026-07-09T10:00:00Z',
            ]),
            $this->cacheRow(30, 'tools', 'bundler', [
                'languages' => ['TypeScript'],
                'topics' => ['build', 'tooling'],
                'stars' => 250,
                'open_issues' => 15,
                'contributors' => 8,
                'updated_at' => '2026-07-08T11:00:00Z',
            ]),
            $this->cacheRow(40, 'legacy', 'cobol-kit', [
                'languages' => ['COBOL'],
                'topics' => ['mainframe'],
                'stars' => 5,
                'open_issues' => 1,
                'contributors' => 1,
                'updated_at' => '2026-07-01T08:00:00Z',
            ]),
        ];
    }

    private function cacheRow(int $id, string $owner, string $name, array $overrides): array
    {
        return [
            'github_repository_id' => $id,
            'owner_login' => $owner,
            'repository_name' => $name,
            'repository_data' => array_merge([
                'id' => $id,
                'owner' => ['login' => $owner],
                'name' => $name,
                'description' => 'Repository ' . $name,
                'html_url' => 'https://github.com/' . $owner . '/' . $name,
                'stars' => 0,
                'forks' => 0,
                'open_issues' => 0,
                'contributors' => 0,
                'languages' => [],
                'topics' => [],
                'updated_at' => '2026-07-01T00:00:00Z',
            ], $overrides),
            'health_data' => null,
        ];
    }
}
