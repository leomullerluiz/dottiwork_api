<?php

use PHPUnit\Framework\TestCase;

class PublicUserProfileServiceTest extends TestCase
{
    public function testFindByLoginNormalizesPublicIdentifier(): void
    {
        $calls = [];
        $service = new PublicUserProfileService([
            'find_public_user' => function ($identifier) use (&$calls) {
                $calls[] = $identifier;
                return ['id' => 7, 'login' => 'Ana-Dev'];
            },
        ]);

        $user = $service->findByLogin('Ana-Dev');

        $this->assertSame(7, $user['id']);
        $this->assertSame(['ana-dev'], $calls);
        $this->assertNull($service->findByLogin('../ana'));
    }

    public function testBuildForUserReturnsPublicPayloadWithoutPrivateFields(): void
    {
        $_ENV['FRONTEND_URL'] = 'https://dotti.work';
        $_ENV['API_BASE_URL'] = 'https://api.dotti.work';

        $service = new PublicUserProfileService($this->deps([
            'profile_settings' => function () {
                return [
                    'public_profile_enabled' => true,
                    'public_profile_slug' => 'ana-dev',
                ];
            },
            'repository_state_counts' => function () {
                return [
                    'saved' => 8,
                    'pull_request_sent' => 1,
                    'contributed' => 2,
                ];
            },
            'activity_event_counts' => function () {
                return [
                    'opened_github' => 12,
                    'sent_pull_request' => 3,
                ];
            },
        ]));

        $payload = $service->buildForUser($this->user());
        $encoded = json_encode($payload);

        $this->assertSame('ana-dev', $payload['profile']['login']);
        $this->assertSame('frontend', $payload['profile']['role']);
        $this->assertSame('https://dotti.work/u/ana-dev', $payload['share']['canonical_url']);
        $this->assertSame('https://api.dotti.work/api/v1/public/profiles/ana-dev', $payload['share']['api_url']);
        $this->assertSame(3, $payload['metrics']['pull_requests_sent_count']);
        $this->assertSame(1, count($payload['badges']));
        $this->assertSame('public_badge', $payload['badges'][0]['slug']);
        $this->assertSame('https://github.com/open-source-org/project', $payload['featured_repositories'][0]['public_url']);
        $this->assertArrayNotHasKey('notes', $payload['featured_repositories'][0]);
        $this->assertStringNotContainsString('email', $encoded);
        $this->assertStringNotContainsString('access_token', $encoded);
        $this->assertStringNotContainsString('scope', $encoded);
        $this->assertStringNotContainsString('criteria_config', $encoded);
        $this->assertStringNotContainsString('secret_badge', $encoded);
        $this->assertStringNotContainsString('private note', $encoded);
    }

    public function testPreviewIncludesPublicFlagShareUrlAndSameProfileShape(): void
    {
        $_ENV['FRONTEND_URL'] = 'https://dotti.work';

        $service = new PublicUserProfileService($this->deps([
            'profile_settings' => function () {
                return [
                    'public_profile_enabled' => false,
                    'public_profile_slug' => null,
                ];
            },
        ]));

        $preview = $service->previewForUser($this->user());

        $this->assertFalse($preview['is_public']);
        $this->assertSame('https://dotti.work/u/ana-dev', $preview['share_url']);
        $this->assertSame('ana-dev', $preview['profile']['profile']['login']);
        $this->assertSame('PROFILE_PRIVATE', $preview['warnings'][0]['code']);
    }

    public function testUpdateSettingsGeneratesSlugFromLoginAndRejectsDuplicates(): void
    {
        $_ENV['FRONTEND_URL'] = 'https://dotti.work';

        $updates = [];
        $service = new PublicUserProfileService($this->deps([
            'profile_settings' => function () {
                return [
                    'public_profile_enabled' => false,
                    'public_profile_slug' => null,
                ];
            },
            'public_slug_exists_for_other_user' => function () {
                return false;
            },
            'login_exists_for_other_user' => function () {
                return false;
            },
            'profile_update_settings' => function ($userId, $enabled, $slug) use (&$updates) {
                $updates[] = [$userId, $enabled, $slug];
                return [
                    'public_profile_enabled' => $enabled,
                    'public_profile_slug' => $slug,
                ];
            },
        ]));

        $result = $service->updateSettings($this->user(), true);

        $this->assertSame([[7, true, 'ana-dev']], $updates);
        $this->assertTrue($result['is_public']);
        $this->assertSame('ana-dev', $result['public_profile_slug']);
        $this->assertSame('https://dotti.work/u/ana-dev', $result['share_url']);

        $duplicate = new PublicUserProfileService($this->deps([
            'profile_settings' => function () {
                return [
                    'public_profile_enabled' => false,
                    'public_profile_slug' => null,
                ];
            },
            'public_slug_exists_for_other_user' => function () {
                return true;
            },
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('public_profile_slug_unavailable');
        $duplicate->updateSettings($this->user(), true, 'ana-dev', true);
    }

    private function user(): array
    {
        return [
            'id' => 7,
            'login' => 'ana-dev',
            'display_name' => 'Ana Silva',
            'email' => 'ana@example.test',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/123',
            'bio' => 'Frontend developer.',
            'location' => 'Sao Paulo, BR',
            'company' => 'Dotti',
            'website_url' => 'https://ana.dev',
            'github_profile_url' => 'https://github.com/ana-dev',
            'created_at' => '2026-06-23 10:00:00',
            'last_login_at' => '2026-07-08 09:00:00',
        ];
    }

    private function deps(array $overrides = []): array
    {
        return array_merge([
            'profile_get' => function () {
                return [
                    'role' => 'frontend',
                    'seniority' => 'junior',
                    'goals' => ['first_contribution', 'build_portfolio'],
                ];
            },
            'profile_settings' => function () {
                return [
                    'public_profile_enabled' => true,
                    'public_profile_slug' => 'ana-dev',
                ];
            },
            'github_account_find' => function () {
                return [
                    'provider_login' => 'ana-dev',
                    'scope' => 'repo,user',
                    'access_token_encrypted' => 'secret',
                ];
            },
            'technologies_find' => function () {
                return [
                    [
                        'id' => 10,
                        'user_id' => 7,
                        'technology_id' => 1,
                        'slug' => 'javascript',
                        'name' => 'JavaScript',
                        'category' => 'language',
                        'proficiency_level' => 'daily',
                        'interest_level' => 'contribute',
                    ],
                ];
            },
            'badges_list' => function () {
                return [
                    [
                        'slug' => 'public_badge',
                        'awarded_at' => '2026-07-01 19:20:00',
                        'source_event_id' => 99,
                        'progress_snapshot' => ['raw' => true],
                        'badge' => [
                            'slug' => 'public_badge',
                            'name' => 'Badge publica',
                            'description' => 'Conquista publica.',
                            'category' => 'discovery',
                            'level' => 'bronze',
                            'image_url' => '/uploads/media/badges/first_contribution.png',
                            'image_alt' => 'Badge publica',
                            'icon' => 'bookmark',
                            'is_secret' => false,
                            'display_order' => 20,
                            'criteria_config' => ['threshold' => 1],
                        ],
                    ],
                    [
                        'slug' => 'secret_badge',
                        'awarded_at' => '2026-07-02 10:00:00',
                        'badge' => [
                            'slug' => 'secret_badge',
                            'is_secret' => true,
                        ],
                    ],
                ];
            },
            'technologies_count' => function () {
                return 1;
            },
            'badges_count' => function () {
                return 1;
            },
            'repository_state_counts' => function () {
                return ['saved' => 1];
            },
            'activity_event_counts' => function () {
                return [];
            },
            'activity_days_count' => function () {
                return 5;
            },
            'last_activity_at' => function () {
                return '2026-07-08 08:12:00';
            },
            'featured_repositories' => function () {
                return [
                    [
                        'github_repository_id' => 123456,
                        'owner_login' => 'open-source-org',
                        'repository_name' => 'project',
                        'state' => 'contributed',
                        'updated_at' => '2026-07-07 18:00:00',
                        'notes' => 'private note',
                    ],
                ];
            },
            'profile_update_settings' => function ($userId, $enabled, $slug) {
                return [
                    'public_profile_enabled' => $enabled,
                    'public_profile_slug' => $slug,
                ];
            },
            'public_slug_exists_for_other_user' => function () {
                return false;
            },
            'login_exists_for_other_user' => function () {
                return false;
            },
        ], $overrides);
    }
}
