<?php

use PHPUnit\Framework\TestCase;

class MatchFilterServiceTest extends TestCase
{
    public function testValidatesInvalidFilters(): void
    {
        $errors = MatchFilterService::validate([
            'state' => 'unknown',
            'minimum_score' => 101,
            'sort_by' => 'random',
            'difficulty' => 'impossible',
            'project_size' => 'tiny',
            'activity' => 'sleeping',
            'has_good_first_issue' => 'maybe',
            'has_help_wanted' => 'nope',
            'minimum_health_score' => -1,
            'limit' => 500,
            'cursor' => 'not-a-valid-cursor',
        ]);

        $fields = array_map(function ($error) {
            return $error['field'];
        }, $errors);

        $this->assertContains('state', $fields);
        $this->assertContains('minimum_score', $fields);
        $this->assertContains('sort_by', $fields);
        $this->assertContains('difficulty', $fields);
        $this->assertContains('project_size', $fields);
        $this->assertContains('activity', $fields);
        $this->assertContains('has_good_first_issue', $fields);
        $this->assertContains('has_help_wanted', $fields);
        $this->assertContains('minimum_health_score', $fields);
        $this->assertContains('limit', $fields);
        $this->assertContains('cursor', $fields);
    }

    public function testAppliesAdvancedMatchFilters(): void
    {
        $items = MatchFilterService::apply($this->items(), [
            'q' => 'react',
            'technology' => 'TypeScript',
            'language' => 'TypeScript',
            'difficulty' => 'beginner',
            'project_size' => 'medium',
            'activity' => 'active',
            'has_good_first_issue' => 'true',
            'has_help_wanted' => 'true',
            'minimum_health_score' => 70,
            'sort_by' => 'score',
            'limit' => 10,
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('owner/react-app', $items[0]['repository']['full_name']);
    }

    public function testSortsByStarsAndBeginnerFriendly(): void
    {
        $byStars = MatchFilterService::apply($this->items(), [
            'sort_by' => 'most_stars',
            'limit' => 10,
        ]);

        $this->assertSame('owner/php-tool', $byStars[0]['repository']['full_name']);

        $beginner = MatchFilterService::apply($this->items(), [
            'sort_by' => 'beginner_friendly',
            'limit' => 10,
        ]);

        $this->assertSame('owner/react-app', $beginner[0]['repository']['full_name']);
    }

    public function testPaginatesWithOpaqueCursor(): void
    {
        $page = MatchFilterService::paginate($this->items(), [
            'sort_by' => 'score',
            'limit' => 1,
        ]);

        $this->assertCount(1, $page['items']);
        $this->assertSame('owner/react-app', $page['items'][0]['repository']['full_name']);
        $this->assertNotNull($page['next_cursor']);
        $this->assertSame(2, $page['total_filtered']);

        $secondPage = MatchFilterService::paginate($this->items(), [
            'sort_by' => 'score',
            'limit' => 1,
            'cursor' => $page['next_cursor'],
        ]);

        $this->assertCount(1, $secondPage['items']);
        $this->assertSame('owner/php-tool', $secondPage['items'][0]['repository']['full_name']);
        $this->assertNull($secondPage['next_cursor']);
    }

    private function items()
    {
        return [
            [
                'score' => 90,
                'recommended_seniority' => 'junior',
                'shared_technologies' => ['TypeScript', 'React'],
                'repository' => [
                    'full_name' => 'owner/react-app',
                    'owner' => 'owner',
                    'name' => 'react-app',
                    'description' => 'React frontend',
                    'primary_language' => 'TypeScript',
                    'languages' => ['TypeScript', 'CSS'],
                    'topics' => ['react'],
                    'project_size' => 'medium',
                    'activity_label' => 'active',
                    'activity_score' => 85,
                    'stars' => 100,
                    'good_first_issues' => 3,
                    'help_wanted_issues' => 2,
                    'health_score' => 80,
                    'last_updated_at' => '2026-07-04T12:00:00Z',
                ],
            ],
            [
                'score' => 75,
                'recommended_seniority' => 'senior',
                'shared_technologies' => ['PHP'],
                'repository' => [
                    'full_name' => 'owner/php-tool',
                    'owner' => 'owner',
                    'name' => 'php-tool',
                    'description' => 'Backend tool',
                    'primary_language' => 'PHP',
                    'languages' => ['PHP'],
                    'topics' => ['api'],
                    'project_size' => 'large',
                    'activity_label' => 'moderate',
                    'activity_score' => 45,
                    'stars' => 5000,
                    'good_first_issues' => 0,
                    'help_wanted_issues' => 0,
                    'health_score' => 60,
                    'last_updated_at' => '2026-06-01T12:00:00Z',
                ],
            ],
        ];
    }
}
