<?php

use PHPUnit\Framework\TestCase;

class AcceptanceCriteriaTest extends TestCase
{
    public function testRequiredIntegrationAndPrivacyRoutesAreRegistered(): void
    {
        $index = file_get_contents(__DIR__ . '/../api/index.php');

        $this->assertStringContainsString("\$route('delete', '/integrations/github', 'AuthController@githubDisconnect');", $index);
        $this->assertStringContainsString("\$route('get', '/me/consents', 'ConsentController@index');", $index);
        $this->assertStringContainsString("\$route('post', '/me/consents', 'ConsentController@store');", $index);
        $this->assertStringContainsString("\$route('delete', '/me/consents/:type', 'ConsentController@revoke');", $index);
    }

    public function testFrontFacingDtosRemainStableWithMissingData(): void
    {
        $summary = RepositorySummary::fromGitHubRepository([
            'id' => 100,
            'full_name' => 'owner/repo',
        ]);

        $this->assertRepositorySummaryContract($summary);
        $this->assertSame([], $summary['languages']);
        $this->assertSame([], $summary['topics']);
        $this->assertSame(0, $summary['good_first_issues']);
        $this->assertSame(0, $summary['help_wanted_issues']);

        $match = RepositoryMatchDto::fromComponents([
            'id' => 100,
            'full_name' => 'owner/repo',
        ], [], [], [
            'score' => 80,
            'breakdown' => [],
            'reasons' => [],
        ]);

        $this->assertArrayHasKey('repository', $match);
        $this->assertArrayHasKey('match', $match);
        $this->assertArrayHasKey('score', $match);
        $this->assertArrayHasKey('recommended_seniority', $match);
        $this->assertRepositorySummaryContract($match['repository']);
        $this->assertSame($match['score'], $match['match']['score']);

        $saved = UserRepositoryState::toResponse([
            'id' => 1,
            'user_id' => 7,
            'github_repository_id' => 100,
            'owner_login' => 'owner',
            'repository_name' => 'repo',
            'state' => 'saved',
            'notes' => null,
            'saved_at' => null,
            'ignored_at' => null,
            'contributed_at' => null,
            'created_at' => '2026-07-04 12:00:00',
            'updated_at' => '2026-07-04 12:00:00',
        ]);

        $this->assertRepositorySummaryContract($saved['repository']);
        $this->assertSame('saved', $saved['state']);

        $history = UserActivityEvent::toResponse([
            'id' => 1,
            'user_id' => 7,
            'github_repository_id' => null,
            'event_type' => 'viewed_project',
            'metadata' => [],
            'created_at' => '2026-07-04 12:00:00',
        ]);

        $this->assertSame('viewed_project', $history['type']);
        $this->assertNull($history['repository']);

        $issue = RepositoryIssueDto::fromGitHubIssue([]);
        foreach (['github_issue_id', 'number', 'title', 'body_excerpt', 'url', 'state', 'labels', 'is_good_first_issue', 'is_help_wanted', 'difficulty', 'contribution_type'] as $key) {
            $this->assertArrayHasKey($key, $issue);
        }
        $this->assertSame('open', $issue['state']);
        $this->assertSame([], $issue['labels']);
        $this->assertSame('unknown', $issue['difficulty']);
    }

    public function testMatchesSupportFiltersOrderingAndCursorPagination(): void
    {
        $filters = [
            'q' => 'api',
            'state' => 'saved',
            'minimum_score' => 70,
            'technology' => 'PHP',
            'language' => 'PHP',
            'difficulty' => 'advanced',
            'project_size' => 'large',
            'activity' => 'active',
            'has_good_first_issue' => 'true',
            'has_help_wanted' => 'true',
            'minimum_health_score' => 80,
            'sort_by' => 'beginner_friendly',
            'limit' => 1,
        ];

        $this->assertSame([], MatchFilterService::validate($filters));

        $page = MatchFilterService::paginate($this->matchItems(), $filters);
        $this->assertCount(1, $page['items']);
        $this->assertSame('owner/api-one', $page['items'][0]['repository']['full_name']);
        $this->assertNotNull($page['next_cursor']);
        $this->assertSame(2, $page['total_filtered']);

        $nextPage = MatchFilterService::paginate($this->matchItems(), array_merge($filters, [
            'cursor' => $page['next_cursor'],
        ]));

        $this->assertCount(1, $nextPage['items']);
        $this->assertSame('owner/api-two', $nextPage['items'][0]['repository']['full_name']);
        $this->assertNull($nextPage['next_cursor']);
    }

    public function testResponseEnvelopeContractRemainsConsistent(): void
    {
        $success = Response::successPayload(['items' => []]);
        $error = Response::validationErrorPayload([
            ['field' => 'state', 'message' => 'Estado invalido.'],
        ]);

        $this->assertTrue($success['success']);
        $this->assertArrayHasKey('data', $success);
        $this->assertFalse($error['success']);
        $this->assertSame('VALIDATION_ERROR', $error['error']['code']);
        $this->assertSame('state', $error['error']['details'][0]['field']);
    }

    private function assertRepositorySummaryContract(array $repository): void
    {
        foreach ([
            'github_repository_id',
            'owner',
            'name',
            'full_name',
            'description',
            'url',
            'html_url',
            'primary_language',
            'languages',
            'topics',
            'stars',
            'forks',
            'open_issues',
            'good_first_issues',
            'help_wanted_issues',
            'project_size',
            'activity_score',
            'activity_label',
            'health_score',
        ] as $key) {
            $this->assertArrayHasKey($key, $repository);
        }
    }

    private function matchItems(): array
    {
        return [
            [
                'score' => 90,
                'recommended_seniority' => 'senior',
                'shared_technologies' => ['PHP'],
                'user_state' => 'saved',
                'repository' => [
                    'full_name' => 'owner/api-one',
                    'owner' => 'owner',
                    'name' => 'api-one',
                    'description' => 'PHP API',
                    'primary_language' => 'PHP',
                    'languages' => ['PHP'],
                    'topics' => ['api'],
                    'project_size' => 'large',
                    'activity_label' => 'active',
                    'activity_score' => 95,
                    'stars' => 100,
                    'good_first_issues' => 3,
                    'help_wanted_issues' => 2,
                    'health_score' => 90,
                ],
            ],
            [
                'score' => 88,
                'recommended_seniority' => 'senior',
                'shared_technologies' => ['PHP'],
                'user_state' => 'saved',
                'repository' => [
                    'full_name' => 'owner/api-two',
                    'owner' => 'owner',
                    'name' => 'api-two',
                    'description' => 'PHP API',
                    'primary_language' => 'PHP',
                    'languages' => ['PHP'],
                    'topics' => ['api'],
                    'project_size' => 'large',
                    'activity_label' => 'active',
                    'activity_score' => 92,
                    'stars' => 80,
                    'good_first_issues' => 1,
                    'help_wanted_issues' => 1,
                    'health_score' => 82,
                ],
            ],
            [
                'score' => 50,
                'recommended_seniority' => 'junior',
                'shared_technologies' => ['JavaScript'],
                'user_state' => null,
                'repository' => [
                    'full_name' => 'owner/web',
                    'owner' => 'owner',
                    'name' => 'web',
                    'description' => 'Frontend',
                    'primary_language' => 'JavaScript',
                    'languages' => ['JavaScript'],
                    'topics' => ['frontend'],
                    'project_size' => 'small',
                    'activity_label' => 'low',
                    'activity_score' => 10,
                    'stars' => 10,
                    'good_first_issues' => 0,
                    'help_wanted_issues' => 0,
                    'health_score' => 50,
                ],
            ],
        ];
    }
}
