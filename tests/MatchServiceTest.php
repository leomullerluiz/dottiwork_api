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
}
