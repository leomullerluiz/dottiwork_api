<?php

use PHPUnit\Framework\TestCase;

class UserProfileServiceTest extends TestCase
{
    public function testExportReturnsStableDataShape(): void
    {
        $service = new UserProfileService([
            'user_public' => function ($userId) {
                return ['id' => $userId, 'login' => 'ana'];
            },
            'profile_get' => function () {
                return ['role' => 'frontend'];
            },
            'technologies_find' => function () {
                return [['technology_id' => 10]];
            },
            'preferences_find' => function () {
                return ['default_sort_by' => 'best_match'];
            },
            'repository_states_list' => function () {
                return [['state' => 'saved']];
            },
            'history_list' => function () {
                return [['type' => 'viewed_project']];
            },
        ]);

        $this->assertSame([
            'user' => ['id' => 7, 'login' => 'ana'],
            'profile' => ['role' => 'frontend'],
            'technologies' => [['technology_id' => 10]],
            'preferences' => ['default_sort_by' => 'best_match'],
            'repository_states' => [['state' => 'saved']],
            'history' => [['type' => 'viewed_project']],
        ], $service->export(7));
    }

    public function testImportLocalDataNormalizesSupportedSectionsAndReturnsExport(): void
    {
        $calls = [];
        $service = new UserProfileService([
            'user_public' => function ($userId) {
                return ['id' => $userId];
            },
            'profile_get' => function () {
                return ['role' => 'backend'];
            },
            'technologies_find' => function () {
                return [];
            },
            'preferences_find' => function () {
                return [];
            },
            'repository_states_list' => function () {
                return [];
            },
            'history_list' => function () {
                return [];
            },
            'profile_upsert' => function ($userId, $role, $seniority, $goals, $completed) use (&$calls) {
                $calls[] = ['profile_upsert', $userId, $role, $seniority, $goals, $completed];
            },
            'preferences_upsert' => function ($userId, $preferences) use (&$calls) {
                $calls[] = ['preferences_upsert', $userId, $preferences];
            },
            'technology_find_active_by_ids' => function ($ids) use (&$calls) {
                $calls[] = ['technology_find_active_by_ids', $ids];
                return array_map(function ($id) {
                    return ['id' => $id];
                }, $ids);
            },
            'technology_replace_all' => function ($userId, $items) use (&$calls) {
                $calls[] = ['technology_replace_all', $userId, $items];
            },
            'repository_state_upsert' => function ($userId, $repoId, $owner, $repo, $state, $notes) use (&$calls) {
                $calls[] = ['repository_state_upsert', $userId, $repoId, $owner, $repo, $state, $notes];
            },
            'activity_create' => function ($userId, $type, $repoId, $metadata) use (&$calls) {
                $calls[] = ['activity_create', $userId, $type, $repoId, $metadata];
            },
        ]);

        $result = $service->importLocalData(7, [
            'profile' => [
                'role' => 'backend',
                'seniority' => 'mid',
                'goals' => ['first_contribution', 'first_contribution', 'build_portfolio'],
                'onboarding_completed' => true,
            ],
            'preferences' => ['default_sort_by' => 'recently_updated'],
            'technologies' => [
                ['technology_id' => 10, 'proficiency_level' => 'daily', 'interest_level' => 'invalid'],
                ['technology_id' => 10, 'proficiency_level' => 'advanced', 'interest_level' => 'mentor'],
                ['technology_id' => 11, 'proficiency_level' => 'unknown'],
                ['technology_id' => 12, 'proficiency_level' => 'basic', 'interest_level' => 'learn'],
            ],
            'repository_states' => [
                ['github_repository_id' => 123, 'owner_login' => 'owner', 'repository_name' => 'repo', 'state' => 'saved', 'notes' => 'ok'],
                ['github_repository_id' => 456],
            ],
            'history' => [
                ['event_type' => 'viewed_project', 'github_repository_id' => 123],
                ['github_repository_id' => 456],
            ],
        ]);

        $this->assertSame(['id' => 7], $result['user']);
        $this->assertContains(['profile_upsert', 7, 'backend', 'mid', ['first_contribution', 'build_portfolio'], true], $calls);
        $this->assertContains(['preferences_upsert', 7, ['default_sort_by' => 'recently_updated']], $calls);
        $this->assertContains(['technology_find_active_by_ids', [10, 12]], $calls);
        $this->assertContains([
            'technology_replace_all',
            7,
            [
                ['technology_id' => 10, 'proficiency_level' => 'daily', 'interest_level' => 'contribute'],
                ['technology_id' => 12, 'proficiency_level' => 'basic', 'interest_level' => 'learn'],
            ],
        ], $calls);
        $this->assertContains(['repository_state_upsert', 7, 123, 'owner', 'repo', 'saved', 'ok'], $calls);
        $this->assertContains(['activity_create', 7, 'viewed_project', 123, ['source' => 'local_storage_import']], $calls);
        $this->assertContains(['activity_create', 7, 'restored_project', null, ['type' => 'local_storage_import']], $calls);
    }
}
