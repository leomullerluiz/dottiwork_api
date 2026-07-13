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
        $this->assertSame('First contribution', $progress['badge']['name']);
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

    public function testSignupCohortFirstNCompletesOnlyForReservedUsers(): void
    {
        $service = new BadgeProgressService([
            'signup_cohort_award_position' => function ($userId, $cohort) {
                $this->assertSame('first_key_first_egg', $cohort);
                return $userId === 7 ? 2 : null;
            },
        ]);

        $definition = $this->definition([
            'slug' => 'first_key_first_egg',
            'name' => 'First to the key! First to the egg!',
            'criteria_type' => 'signup_cohort_first_n',
            'criteria_config' => ['cohort' => 'first_key_first_egg', 'limit' => 10, 'target' => 1],
            'level' => 'legendary',
        ]);

        $awarded = $service->progressForDefinition(7, $definition);
        $notAwarded = $service->progressForDefinition(8, $definition);

        $this->assertSame(1, $awarded['current_value']);
        $this->assertSame(1, $awarded['target_value']);
        $this->assertTrue($awarded['completed']);
        $this->assertSame(0, $notAwarded['current_value']);
        $this->assertFalse($notAwarded['completed']);
    }

    public function testBadgeDefinitionResponseDecodesCriteriaConfig(): void
    {
        $response = BadgeDefinition::toResponse($this->definition([
            'criteria_config' => '{"threshold":3}',
        ]));

        $this->assertSame(['threshold' => 3], $response['criteria_config']);
        $this->assertFalse($response['is_secret']);
        $this->assertSame('/uploads/media/badges/first_contribution.png', $response['image_url']);
    }

    public function testSecretBadgeDefinitionResponseIsGeneric(): void
    {
        $response = BadgeDefinition::toResponse($this->definition([
            'slug' => 'first_key_first_egg',
            'name' => 'First to the key! First to the egg!',
            'description' => 'Awarded to the first 10 new members.',
            'level' => 'legendary',
            'image_url' => '/uploads/media/badges/first_key_first_egg.png',
            'criteria_type' => 'signup_cohort_first_n',
            'criteria_config' => ['cohort' => 'first_key_first_egg', 'target' => 1],
            'is_secret' => true,
        ]));

        $this->assertSame('secret_badge', $response['slug']);
        $this->assertSame('Secret badge', $response['name']);
        $this->assertSame('This achievement is hidden.', $response['description']);
        $this->assertSame('secret', $response['level']);
        $this->assertSame('/uploads/media/badges/secret_badge.png', $response['image_url']);
        $this->assertSame('secret', $response['criteria_type']);
        $this->assertSame([], $response['criteria_config']);
    }

    public function testSecretBadgeProgressHidesCriteriaAndActualProgress(): void
    {
        $service = new BadgeProgressService([
            'activity_event_count' => function () {
                return 4;
            },
        ]);

        $progress = $service->progressForDefinition(7, $this->definition([
            'slug' => 'view_secret_projects',
            'name' => 'Secret project viewer',
            'criteria_type' => 'activity_event_count',
            'criteria_config' => [
                'event_type' => 'viewed_project',
                'threshold' => 5,
                'distinct_repositories' => true,
            ],
            'is_secret' => true,
        ]));

        $this->assertSame('secret_badge', $progress['slug']);
        $this->assertSame(0, $progress['current_value']);
        $this->assertSame(1, $progress['target_value']);
        $this->assertSame(0, $progress['percent']);
        $this->assertFalse($progress['completed']);
        $this->assertSame('secret', $progress['criteria_type']);
        $this->assertSame([], $progress['criteria_config']);
        $this->assertSame('Secret badge', $progress['badge']['name']);
    }

    private function definition(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'slug' => 'first_contribution',
            'name' => 'First contribution',
            'description' => 'Marked the first contribution as completed.',
            'category' => 'contribution',
            'level' => 'gold',
            'image_url' => '/uploads/media/badges/first_contribution.png',
            'image_alt' => 'First open source contribution badge',
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
