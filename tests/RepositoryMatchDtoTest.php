<?php

use PHPUnit\Framework\TestCase;

class RepositoryMatchDtoTest extends TestCase
{
    public function testBuildsEnrichedMatchItemAndKeepsLegacyMatchObject(): void
    {
        $item = RepositoryMatchDto::fromComponents([
            'id' => 123,
            'owner' => ['login' => 'owner'],
            'name' => 'repo',
            'html_url' => 'https://github.com/owner/repo',
            'language' => 'TypeScript',
            'languages' => ['TypeScript', 'CSS'],
            'topics' => ['react', 'frontend'],
            'stargazers_count' => 1500,
            'forks_count' => 200,
            'open_issues_count' => 80,
            'updated_at' => date('c'),
        ], [
            'score' => 82,
            'has_readme' => true,
            'has_contributing' => true,
            'has_code_of_conduct' => false,
            'has_ci' => true,
            'has_tests' => true,
            'has_contribution_labels' => true,
        ], [
            'good_first_issues' => 2,
            'help_wanted_issues' => 1,
        ], [
            'score' => 91.0,
            'breakdown' => [
                'stack' => 35,
                'difficulty' => 18,
                'issues' => 15,
            ],
            'reasons' => ['Repository language matches your stack'],
            'generated_at' => '2026-07-04 12:00:00',
            'expires_at' => '2026-07-05 12:00:00',
        ], null, [
            [
                'name' => 'TypeScript',
                'github_language' => 'TypeScript',
                'github_topics' => ['typescript'],
            ],
            [
                'name' => 'React',
                'github_language' => null,
                'github_topics' => ['react'],
            ],
        ]);

        $this->assertSame(123, $item['github_repository_id']);
        $this->assertSame(91.0, $item['score']);
        $this->assertSame('junior', $item['recommended_seniority']);
        $this->assertSame(['Repository language matches your stack'], $item['match_reasons']);
        $this->assertSame(['TypeScript', 'React'], $item['shared_technologies']);
        $this->assertContains('Matches your stack', $item['positives']);
        $this->assertContains('Beginner-friendly issues', $item['positives']);
        $this->assertTrue($item['cached']);
        $this->assertSame('owner/repo', $item['repository']['full_name']);

        $this->assertSame($item['score'], $item['match']['score']);
        $this->assertSame($item['recommended_seniority'], $item['match']['recommended_seniority']);
        $this->assertSame($item['match_reasons'], $item['match']['match_reasons']);
        $this->assertSame($item['shared_technologies'], $item['match']['shared_technologies']);
        $this->assertSame($item['health_checklist'], $item['match']['health_checklist']);
        $this->assertSame('has_readme', $item['health_checklist'][0]['key']);
        $this->assertTrue($item['health_checklist'][0]['passed']);
    }

    public function testRecommendsSeniorForLargeProjectWithoutBeginnerSignals(): void
    {
        $item = RepositoryMatchDto::fromComponents([
            'id' => 456,
            'full_name' => 'big/project',
            'language' => 'PHP',
            'stargazers_count' => 25000,
            'forks_count' => 5000,
            'open_issues_count' => 1500,
        ], [
            'score' => 40,
            'has_readme' => true,
            'has_contributing' => false,
        ], [
            'good_first_issues' => 0,
            'help_wanted_issues' => 0,
        ], [
            'score' => 64,
            'breakdown' => [
                'difficulty' => 8,
            ],
            'reasons' => [],
        ]);

        $this->assertSame('large', $item['repository']['project_size']);
        $this->assertSame('senior', $item['recommended_seniority']);
        $this->assertContains('Large codebase', $item['challenges']);
        $this->assertContains('Few beginner-friendly issues', $item['challenges']);
        $this->assertContains('Contribution guide not detected', $item['challenges']);
    }
}
