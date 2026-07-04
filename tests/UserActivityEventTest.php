<?php

use PHPUnit\Framework\TestCase;

class UserActivityEventTest extends TestCase
{
    public function testActivityEventResponseIncludesTypeMetadataAndRepositorySummary(): void
    {
        $response = UserActivityEvent::toResponse([
            'id' => '25',
            'user_id' => '7',
            'github_repository_id' => '123',
            'event_type' => 'saved_project',
            'metadata' => json_encode(['owner' => 'owner', 'repo' => 'repo']),
            'created_at' => '2026-07-04 12:00:00',
        ], [
            'id' => 123,
            'owner' => ['login' => 'owner'],
            'name' => 'repo',
            'html_url' => 'https://github.com/owner/repo',
            'language' => 'TypeScript',
            'topics' => ['react'],
            'stargazers_count' => 1000,
        ], [
            'score' => 77,
        ], [
            'good_first_issues' => 1,
            'help_wanted_issues' => 2,
        ]);

        $this->assertSame(25, $response['id']);
        $this->assertSame(7, $response['user_id']);
        $this->assertSame(123, $response['github_repository_id']);
        $this->assertSame('saved_project', $response['type']);
        $this->assertSame('saved_project', $response['event_type']);
        $this->assertSame(['owner' => 'owner', 'repo' => 'repo'], $response['metadata']);
        $this->assertSame('owner/repo', $response['repository']['full_name']);
        $this->assertSame(1, $response['repository']['good_first_issues']);
        $this->assertSame(2, $response['repository']['help_wanted_issues']);
        $this->assertSame(77, $response['repository']['health_score']);
    }

    public function testActivityEventWithoutRepositoryReturnsNullRepository(): void
    {
        $response = UserActivityEvent::toResponse([
            'id' => 26,
            'user_id' => 7,
            'github_repository_id' => null,
            'event_type' => 'restored_project',
            'metadata' => [],
            'created_at' => '2026-07-04 12:00:00',
        ]);

        $this->assertSame('restored_project', $response['type']);
        $this->assertSame([], $response['metadata']);
        $this->assertNull($response['repository']);
    }

    public function testActivityEventUsesMetadataAsRepositoryFallback(): void
    {
        $response = UserActivityEvent::toResponse([
            'id' => 27,
            'user_id' => 7,
            'github_repository_id' => 456,
            'event_type' => 'opened_github',
            'metadata' => ['owner' => 'fallback-owner', 'repo' => 'fallback-repo'],
            'created_at' => '2026-07-04 12:00:00',
        ], null, null, []);

        $this->assertSame('opened_github', $response['type']);
        $this->assertSame('fallback-owner', $response['repository']['owner']);
        $this->assertSame('fallback-repo', $response['repository']['name']);
        $this->assertSame('fallback-owner/fallback-repo', $response['repository']['full_name']);
        $this->assertSame('https://github.com/fallback-owner/fallback-repo', $response['repository']['url']);
    }
}
