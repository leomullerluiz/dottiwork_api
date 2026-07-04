<?php

use PHPUnit\Framework\TestCase;

class RepositoryIssueDtoTest extends TestCase
{
    public function testNormalizesGitHubIssuePayload(): void
    {
        $body = str_repeat('A', 300);
        $issue = RepositoryIssueDto::fromGitHubIssue([
            'id' => 456,
            'number' => 12,
            'title' => 'Fix broken login',
            'body' => $body,
            'html_url' => 'https://github.com/owner/repo/issues/12',
            'state' => 'open',
            'labels' => [
                ['name' => 'good first issue', 'color' => '7057ff'],
                ['name' => 'help wanted', 'color' => '008672'],
                ['name' => 'bug', 'color' => 'd73a4a'],
            ],
            'comments' => 3,
            'created_at' => '2026-07-04T12:00:00Z',
            'updated_at' => '2026-07-04T13:00:00Z',
        ], [
            'level' => 'beginner',
            'confidence' => 0.84,
            'reasons' => ['good first issue label found'],
        ], 123);

        $this->assertSame(456, $issue['github_issue_id']);
        $this->assertSame(123, $issue['github_repository_id']);
        $this->assertSame(12, $issue['number']);
        $this->assertSame('Fix broken login', $issue['title']);
        $this->assertLessThanOrEqual(240, strlen($issue['body_excerpt']));
        $this->assertStringEndsWith('...', $issue['body_excerpt']);
        $this->assertSame('https://github.com/owner/repo/issues/12', $issue['url']);
        $this->assertSame('open', $issue['state']);
        $this->assertSame('good first issue', $issue['labels'][0]['name']);
        $this->assertTrue($issue['is_good_first_issue']);
        $this->assertTrue($issue['is_help_wanted']);
        $this->assertSame('easy', $issue['difficulty']);
        $this->assertSame(0.84, $issue['confidence']);
        $this->assertSame('bugfix', $issue['contribution_type']);
        $this->assertSame(12, $issue['issue_number']);
        $this->assertSame('beginner', $issue['difficulty_estimation']['level']);
    }

    public function testMapsDifficultyAndContributionTypes(): void
    {
        $documentation = RepositoryIssueDto::fromGitHubIssue([
            'id' => 1,
            'number' => 1,
            'title' => 'Improve README documentation',
            'labels' => [['name' => 'documentation']],
        ], ['level' => 'intermediate']);

        $advanced = RepositoryIssueDto::fromGitHubIssue([
            'id' => 2,
            'number' => 2,
            'title' => 'Security migration',
            'labels' => [['name' => 'security']],
        ], ['level' => 'advanced', 'confidence' => 2]);

        $this->assertSame('medium', $documentation['difficulty']);
        $this->assertSame('documentation', $documentation['contribution_type']);
        $this->assertSame('hard', $advanced['difficulty']);
        $this->assertSame(1.0, $advanced['confidence']);
        $this->assertSame('unknown', $advanced['contribution_type']);
    }

    public function testBuildsFromCacheRowWithoutExposingRawIssueAsPrimaryContract(): void
    {
        $issue = RepositoryIssueDto::fromCacheRow([
            'github_repository_id' => 999,
            'github_issue_id' => 100,
            'issue_number' => 7,
            'issue_data' => json_encode([
                'id' => 100,
                'number' => 7,
                'title' => 'Add tests',
                'labels' => [['name' => 'test', 'color' => 'ffffff']],
                'comments' => 0,
            ]),
            'difficulty_estimation' => json_encode(['level' => 'intermediate', 'confidence' => 0.7]),
            'fetched_at' => '2026-07-04 12:00:00',
            'expires_at' => '2026-07-04 13:00:00',
            'created_at' => '2026-07-04 12:00:00',
            'updated_at' => '2026-07-04 12:00:00',
        ]);

        $this->assertSame(999, $issue['github_repository_id']);
        $this->assertSame(100, $issue['github_issue_id']);
        $this->assertSame(7, $issue['number']);
        $this->assertSame('test', $issue['contribution_type']);
        $this->assertArrayNotHasKey('issue_data', $issue);
        $this->assertSame('2026-07-04 12:00:00', $issue['fetched_at']);
        $this->assertSame('2026-07-04 12:00:00', $issue['created_cache_at']);
    }
}
