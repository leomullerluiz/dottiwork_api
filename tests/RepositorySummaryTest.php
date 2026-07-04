<?php

use PHPUnit\Framework\TestCase;

class RepositorySummaryTest extends TestCase
{
    public function testBuildsRepositorySummaryFromGitHubRepositoryData(): void
    {
        $summary = RepositorySummary::fromGitHubRepository([
            'id' => 123,
            'owner' => [
                'login' => 'owner',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
            ],
            'name' => 'repo',
            'description' => 'Repository description.',
            'html_url' => 'https://github.com/owner/repo',
            'homepage' => 'https://example.test',
            'language' => 'TypeScript',
            'languages' => ['TypeScript' => 100, 'CSS' => 20],
            'topics' => ['nextjs', 'react'],
            'stargazers_count' => 1000,
            'forks_count' => 120,
            'watchers_count' => 80,
            'open_issues_count' => 32,
            'license' => ['spdx_id' => 'MIT'],
            'pushed_at' => date('c'),
            'updated_at' => date('c'),
            'size' => 80000,
        ], [
            'score' => 76,
        ], [
            'good_first_issues' => 5,
            'help_wanted_issues' => 3,
        ]);

        $this->assertSame(123, $summary['github_repository_id']);
        $this->assertSame('owner', $summary['owner']);
        $this->assertSame('repo', $summary['name']);
        $this->assertSame('owner/repo', $summary['full_name']);
        $this->assertSame('https://github.com/owner/repo', $summary['url']);
        $this->assertSame('https://github.com/owner/repo', $summary['html_url']);
        $this->assertSame('https://example.test', $summary['homepage_url']);
        $this->assertSame('https://example.test', $summary['homepage']);
        $this->assertSame('TypeScript', $summary['primary_language']);
        $this->assertSame(['TypeScript', 'CSS'], $summary['languages']);
        $this->assertSame(['nextjs', 'react'], $summary['topics']);
        $this->assertSame(1000, $summary['stars']);
        $this->assertSame(120, $summary['forks']);
        $this->assertSame(80, $summary['watchers']);
        $this->assertSame(32, $summary['open_issues']);
        $this->assertSame(5, $summary['good_first_issues']);
        $this->assertSame(3, $summary['help_wanted_issues']);
        $this->assertSame('MIT', $summary['license']);
        $this->assertSame('medium', $summary['project_size']);
        $this->assertSame(100, $summary['activity_score']);
        $this->assertSame('very_active', $summary['activity_label']);
        $this->assertSame(76, $summary['health_score']);
    }

    public function testUsesStableDefaultsForMissingOptionalData(): void
    {
        $summary = RepositorySummary::fromGitHubRepository([
            'id' => 456,
            'full_name' => 'fallback/project',
            'url' => 'https://api.github.com/repos/fallback/project',
        ]);

        $this->assertSame(456, $summary['github_repository_id']);
        $this->assertSame('fallback', $summary['owner']);
        $this->assertSame('project', $summary['name']);
        $this->assertSame('fallback/project', $summary['full_name']);
        $this->assertSame('https://github.com/fallback/project', $summary['url']);
        $this->assertSame([], $summary['languages']);
        $this->assertSame([], $summary['topics']);
        $this->assertSame(0, $summary['stars']);
        $this->assertSame(0, $summary['good_first_issues']);
        $this->assertSame(0, $summary['help_wanted_issues']);
        $this->assertSame('small', $summary['project_size']);
        $this->assertSame(0, $summary['activity_score']);
        $this->assertSame('low', $summary['activity_label']);
        $this->assertNull($summary['health_score']);
    }

    public function testBuildsRepositorySummaryFromCacheRow(): void
    {
        $summary = RepositorySummary::fromCacheRow([
            'github_repository_id' => 789,
            'owner_login' => 'cached-owner',
            'repository_name' => 'cached-repo',
            'repository_data' => [
                'description' => 'Cached repository.',
                'stars' => 42,
            ],
            'health_data' => [
                'score' => 99,
            ],
        ]);

        $this->assertSame(789, $summary['github_repository_id']);
        $this->assertSame('cached-owner', $summary['owner']);
        $this->assertSame('cached-repo', $summary['name']);
        $this->assertSame('cached-owner/cached-repo', $summary['full_name']);
        $this->assertSame('https://github.com/cached-owner/cached-repo', $summary['url']);
        $this->assertSame(99, $summary['health_score']);
    }
}
