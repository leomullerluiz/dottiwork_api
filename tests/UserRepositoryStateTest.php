<?php

use PHPUnit\Framework\TestCase;

class UserRepositoryStateTest extends TestCase
{
    public function testStateResponseIncludesRepositorySummaryFromCacheData(): void
    {
        $response = UserRepositoryState::toResponse([
            'id' => '10',
            'user_id' => '7',
            'github_repository_id' => '123',
            'owner_login' => 'owner',
            'repository_name' => 'repo',
            'state' => 'saved',
            'notes' => null,
            'saved_at' => '2026-07-04 12:00:00',
            'ignored_at' => null,
            'contributed_at' => null,
            'created_at' => '2026-07-04 12:00:00',
            'updated_at' => '2026-07-04 12:00:00',
        ], [
            'id' => 123,
            'owner' => ['login' => 'owner'],
            'name' => 'repo',
            'html_url' => 'https://github.com/owner/repo',
            'language' => 'TypeScript',
            'topics' => ['react'],
            'stargazers_count' => 1200,
            'forks_count' => 100,
            'open_issues_count' => 25,
        ], [
            'score' => 88,
        ], [
            'good_first_issues' => 1,
            'help_wanted_issues' => 2,
        ]);

        $this->assertSame(10, $response['id']);
        $this->assertSame(7, $response['user_id']);
        $this->assertSame(123, $response['github_repository_id']);
        $this->assertSame('saved', $response['state']);
        $this->assertSame('owner/repo', $response['repository']['full_name']);
        $this->assertSame('https://github.com/owner/repo', $response['repository']['url']);
        $this->assertSame(1, $response['repository']['good_first_issues']);
        $this->assertSame(2, $response['repository']['help_wanted_issues']);
        $this->assertSame(88, $response['repository']['health_score']);
    }

    public function testStateResponseReturnsMinimalRepositorySummaryWithoutCache(): void
    {
        $response = UserRepositoryState::toResponse([
            'id' => 11,
            'user_id' => 7,
            'github_repository_id' => 456,
            'owner_login' => 'fallback-owner',
            'repository_name' => 'fallback-repo',
            'state' => 'researching',
            'notes' => 'Look later',
            'saved_at' => null,
            'ignored_at' => null,
            'contributed_at' => null,
            'created_at' => '2026-07-04 12:00:00',
            'updated_at' => '2026-07-04 12:00:00',
        ]);

        $this->assertSame('researching', $response['state']);
        $this->assertSame('Look later', $response['notes']);
        $this->assertSame(456, $response['repository']['github_repository_id']);
        $this->assertSame('fallback-owner', $response['repository']['owner']);
        $this->assertSame('fallback-repo', $response['repository']['name']);
        $this->assertSame('fallback-owner/fallback-repo', $response['repository']['full_name']);
        $this->assertSame('https://github.com/fallback-owner/fallback-repo', $response['repository']['url']);
        $this->assertSame([], $response['repository']['languages']);
        $this->assertSame([], $response['repository']['topics']);
        $this->assertSame(0, $response['repository']['good_first_issues']);
        $this->assertSame(0, $response['repository']['help_wanted_issues']);
    }
}
