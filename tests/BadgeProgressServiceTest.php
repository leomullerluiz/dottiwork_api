<?php

use PHPUnit\Framework\TestCase;

class BadgeProgressServiceTest extends TestCase
{
    public function testProgressForThresholdCriteriaCalculatesPercentAndCompletion(): void
    {
        $service = new BadgeProgressService([
            'activity_event_count' => function ($userId, $eventType, $distinctRepositories) {
                $this->assertSame(7, $userId);
                $this->assertSame('viewed_project', $eventType);
                $this->assertTrue($distinctRepositories);
                return 3;
            },
        ]);

        $progress = $service->progressForDefinition(7, $this->definition([
            'slug' => 'explorer',
            'criteria_type' => 'activity_event_count',
            'criteria_config' => [
                'event_type' => 'viewed_project',
                'threshold' => 5,
                'distinct_repositories' => true,
            ],
        ]));

        $this->assertSame('explorer', $progress['slug']);
        $this->assertSame(3, $progress['current_value']);
        $this->assertSame(5, $progress['target_value']);
        $this->assertSame(60, $progress['percent']);
        $this->assertFalse($progress['completed']);
    }

    public function testProgressForCompletedBooleanCriteria(): void
    {
        $service = new BadgeProgressService([
            'repository_state_exists' => function ($userId, $state) {
                return $userId === 7 && $state === 'contributed';
            },
        ]);

        $progress = $service->progressForDefinition(7, $this->definition([
            'slug' => 'first_contribution',
            'criteria_type' => 'repository_state_exists',
            'criteria_config' => ['state' => 'contributed', 'target' => 1],
        ]));

        $this->assertSame(1, $progress['current_value']);
        $this->assertSame(100, $progress['percent']);
        $this->assertTrue($progress['completed']);
        $this->assertSame('Primeira contribuicao', $progress['badge']['name']);
    }

    public function testCombinedActivityOrStateCriteriaCompletesWithEitherSignal(): void
    {
        $service = new BadgeProgressService([
            'activity_event_exists' => function ($userId, $eventType) {
                return false;
            },
            'repository_state_count' => function ($userId, array $states) {
                $this->assertSame(['working', 'researching'], $states);
                return 1;
            },
        ]);

        $progress = $service->progressForDefinition(7, $this->definition([
            'slug' => 'started_contributing',
            'criteria_type' => 'activity_event_or_repository_state_exists',
            'criteria_config' => [
                'event_type' => 'started_contributing',
                'states' => ['working', 'researching'],
                'target' => 1,
            ],
        ]));

        $this->assertSame(1, $progress['current_value']);
        $this->assertTrue($progress['completed']);
    }

    public function testAlphaUserRequiresDeadlineAndInitialObjectives(): void
    {
        $service = new BadgeProgressService([
            'alpha_user_started_before' => function ($userId, $deadline) {
                $this->assertSame('2026-10-30', $deadline);
                return $userId === 7;
            },
            'profile_onboarding_completed' => function () {
                return true;
            },
            'activity_event_count' => function ($userId, $eventType, $distinctRepositories) {
                return $eventType === 'viewed_project' && $distinctRepositories ? 5 : 0;
            },
            'repository_state_count' => function ($userId, array $states) {
                return $states === ['saved'] ? 3 : 0;
            },
            'activity_event_exists' => function ($userId, $eventType) {
                return $eventType === 'opened_github';
            },
        ]);

        $progress = $service->progressForDefinition(7, $this->definition([
            'slug' => 'alpha_user',
            'criteria_type' => 'alpha_user',
            'criteria_config' => ['deadline' => '2026-10-30', 'threshold' => 5],
            'level' => 'platinum',
        ]));

        $this->assertSame(5, $progress['current_value']);
        $this->assertSame(5, $progress['target_value']);
        $this->assertTrue($progress['completed']);
    }

    public function testBadgeDefinitionResponseDecodesCriteriaConfig(): void
    {
        $response = BadgeDefinition::toResponse($this->definition([
            'criteria_config' => '{"threshold":3}',
        ]));

        $this->assertSame(['threshold' => 3], $response['criteria_config']);
        $this->assertFalse($response['is_secret']);
        $this->assertSame('https://placehold.co/100/png', $response['image_url']);
    }

    private function definition(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'slug' => 'first_contribution',
            'name' => 'Primeira contribuicao',
            'description' => 'Marcou sua primeira contribuicao como concluida.',
            'category' => 'contribution',
            'level' => 'gold',
            'image_url' => 'https://placehold.co/100/png',
            'image_alt' => 'Insignia de primeira contribuicao open source',
            'icon' => 'award',
            'is_active' => true,
            'is_secret' => false,
            'display_order' => 10,
            'criteria_type' => 'repository_state_exists',
            'criteria_config' => ['state' => 'contributed', 'target' => 1],
            'created_at' => '2026-07-08 12:00:00',
            'updated_at' => '2026-07-08 12:00:00',
        ], $overrides);
    }
}
