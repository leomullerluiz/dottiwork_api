<?php

class PublicUserProfileService
{
    private $deps;

    public function __construct(array $deps = [])
    {
        $this->deps = array_merge([
            'find_public_user' => [$this, 'queryPublicUserByIdentifier'],
            'profile_get' => ['UserProfile', 'getComplete'],
            'profile_settings' => ['UserProfile', 'publicSettings'],
            'profile_update_settings' => ['UserProfile', 'updatePublicSettings'],
            'public_slug_exists_for_other_user' => ['UserProfile', 'publicSlugExistsForOtherUser'],
            'login_exists_for_other_user' => ['User', 'loginExistsForOtherUser'],
            'technologies_find' => ['UserTechnology', 'findByUserId'],
            'github_account_find' => function ($userId) {
                return OAuthAccount::findByUserAndProvider($userId, 'github');
            },
            'badges_list' => ['UserBadge', 'listByUser'],
            'technologies_count' => [$this, 'queryTechnologiesCount'],
            'badges_count' => [$this, 'queryPublicBadgesCount'],
            'repository_state_counts' => [$this, 'queryRepositoryStateCounts'],
            'activity_event_counts' => [$this, 'queryActivityEventCounts'],
            'activity_days_count' => [$this, 'queryActivityDaysCount'],
            'last_activity_at' => [$this, 'queryLastActivityAt'],
            'featured_repositories' => [$this, 'queryFeaturedRepositories'],
        ], $deps);
    }

    public function findByLogin(string $login)
    {
        $identifier = self::normalizeSlug($login);
        if (!$identifier) {
            return null;
        }

        return $this->call('find_public_user', $identifier);
    }

    public function buildForUser(array $user)
    {
        $userId = (int) $user['id'];
        $profile = $this->call('profile_get', $userId);
        $settings = $this->call('profile_settings', $userId);
        $slug = $this->publicSlugForUser($user, $settings);

        return [
            'profile' => $this->publicProfile($user, $profile),
            'github' => $this->githubProfile($user, $this->call('github_account_find', $userId)),
            'technologies' => $this->publicTechnologies($this->call('technologies_find', $userId)),
            'metrics' => $this->metricsForUser($userId, $user['created_at'] ?? null),
            'badges' => $this->publicBadges($this->call('badges_list', $userId)),
            'featured_repositories' => $this->featuredRepositoriesForUser($userId),
            'share' => [
                'canonical_url' => $slug ? $this->shareUrlForLogin($slug) : null,
                'api_url' => $slug ? $this->apiUrlForLogin($slug) : null,
            ],
        ];
    }

    public function previewForUser(array $user)
    {
        $settings = $this->call('profile_settings', (int) $user['id']);
        $slug = $this->publicSlugForUser($user, $settings);
        $profile = $this->buildForUser($user);
        $data = [
            'is_public' => !empty($settings['public_profile_enabled']),
            'share_url' => $slug ? $this->shareUrlForLogin($slug) : null,
            'profile' => $profile,
        ];

        $warnings = $this->previewWarnings($data['is_public'], $slug, $profile);
        if ($warnings) {
            $data['warnings'] = $warnings;
        }

        return $data;
    }

    public function updateSettings(array $user, $enabled, $slug = null, $slugProvided = false)
    {
        $userId = (int) $user['id'];
        $currentSettings = $this->call('profile_settings', $userId);
        $slugToSave = null;

        if ($slugProvided) {
            $slugToSave = self::normalizeSlug($slug);
            if (!$slugToSave) {
                throw new InvalidArgumentException('public_profile_slug_invalid');
            }
        } elseif (empty($currentSettings['public_profile_slug'])) {
            $slugToSave = $this->publicSlugForUser($user, $currentSettings);
        }

        if ($enabled && !$slugToSave && empty($currentSettings['public_profile_slug'])) {
            throw new InvalidArgumentException('public_profile_slug_required');
        }

        if ($slugToSave && $this->publicSlugInUse($slugToSave, $userId)) {
            throw new InvalidArgumentException('public_profile_slug_unavailable');
        }

        $settings = $this->call('profile_update_settings', $userId, (bool) $enabled, $slugToSave);
        $slug = $this->publicSlugForUser($user, $settings);

        return [
            'is_public' => !empty($settings['public_profile_enabled']),
            'share_url' => $slug ? $this->shareUrlForLogin($slug) : null,
            'public_profile_slug' => $slug,
        ];
    }

    public function metricsForUser($userId, $memberSince = null)
    {
        $stateCounts = $this->call('repository_state_counts', $userId);
        $eventCounts = $this->call('activity_event_counts', $userId);
        $pullRequestsByState = (int) ($stateCounts['pull_request_sent'] ?? 0);
        $pullRequestsByEvent = (int) ($eventCounts['sent_pull_request'] ?? 0);

        return [
            'technologies_count' => (int) $this->call('technologies_count', $userId),
            'badges_count' => (int) $this->call('badges_count', $userId),
            'repositories_saved_count' => (int) ($stateCounts['saved'] ?? 0),
            'repositories_contributed_count' => (int) ($stateCounts['contributed'] ?? 0),
            'pull_requests_sent_count' => max($pullRequestsByState, $pullRequestsByEvent),
            'opened_github_count' => (int) ($eventCounts['opened_github'] ?? 0),
            'activity_days_count' => (int) $this->call('activity_days_count', $userId),
            'member_since' => $memberSince,
            'last_activity_at' => $this->call('last_activity_at', $userId),
        ];
    }

    public function featuredRepositoriesForUser($userId, $limit = 6)
    {
        return array_map([$this, 'publicRepository'], $this->call('featured_repositories', $userId, $limit));
    }

    public function shareUrlForLogin(string $login)
    {
        $base = rtrim($this->env('PUBLIC_PROFILE_BASE_URL') ?: $this->env('FRONTEND_URL') ?: 'https://dotti.work', '/');
        return $base . '/u/' . rawurlencode($login);
    }

    public function apiUrlForLogin(string $login)
    {
        $base = rtrim($this->env('PUBLIC_API_BASE_URL') ?: $this->env('API_BASE_URL') ?: 'https://api.dottiwork.com', '/');
        return $base . '/api/v1/public/profiles/' . rawurlencode($login);
    }

    public function publicSlugInUse($slug, $userId)
    {
        return $this->call('public_slug_exists_for_other_user', $slug, $userId)
            || $this->call('login_exists_for_other_user', $slug, $userId);
    }

    public static function normalizeSlug($value)
    {
        $value = strtolower(trim(rawurldecode((string) $value)));

        if ($value === '' || strlen($value) > 120) {
            return null;
        }

        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $value)) {
            return null;
        }

        return $value;
    }

    public function queryPublicUserByIdentifier($identifier)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT u.*
            FROM users u
            INNER JOIN user_profiles p ON p.user_id = u.id
            WHERE u.deleted_at IS NULL
              AND p.public_profile_enabled = 1
              AND (
                LOWER(u.login) = :login_identifier
                OR LOWER(p.public_profile_slug) = :slug_identifier
              )
            ORDER BY CASE WHEN LOWER(u.login) = :order_identifier THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $stmt->execute([
            'login_identifier' => $identifier,
            'slug_identifier' => $identifier,
            'order_identifier' => $identifier,
        ]);
        return $stmt->fetch() ?: null;
    }

    public function queryTechnologiesCount($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_technologies WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function queryPublicBadgesCount($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM user_badges ub
            INNER JOIN badge_definitions bd ON bd.id = ub.badge_id
            WHERE ub.user_id = :user_id AND bd.is_secret = 0
        ");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function queryRepositoryStateCounts($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT state, COUNT(DISTINCT github_repository_id) AS total
            FROM user_repository_states
            WHERE user_id = :user_id
            GROUP BY state
        ");
        $stmt->execute(['user_id' => $userId]);

        return $this->keyedCounts($stmt->fetchAll(), 'state');
    }

    public function queryActivityEventCounts($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT event_type, COUNT(*) AS total
            FROM user_activity_events
            WHERE user_id = :user_id
            GROUP BY event_type
        ");
        $stmt->execute(['user_id' => $userId]);

        return $this->keyedCounts($stmt->fetchAll(), 'event_type');
    }

    public function queryActivityDaysCount($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT DATE(created_at))
            FROM user_activity_events
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function queryLastActivityAt($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT MAX(created_at)
            FROM user_activity_events
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $lastActivityAt = $stmt->fetchColumn();
        return $lastActivityAt ?: null;
    }

    public function queryFeaturedRepositories($userId, $limit = 6)
    {
        $limit = min(max((int) $limit, 1), 20);
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT s.github_repository_id, s.owner_login, s.repository_name, s.state,
                   s.updated_at, r.repository_data, r.health_data
            FROM user_repository_states s
            LEFT JOIN repository_cache r ON r.github_repository_id = s.github_repository_id
            WHERE s.user_id = :user_id
              AND s.state IN ('contributed', 'pull_request_sent', 'working', 'researching', 'saved')
            ORDER BY FIELD(s.state, 'contributed', 'pull_request_sent', 'working', 'researching', 'saved'),
                     s.updated_at DESC,
                     s.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    private function publicProfile(array $user, array $profile)
    {
        return [
            'login' => $user['login'] ?? null,
            'display_name' => $user['display_name'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
            'bio' => $user['bio'] ?? null,
            'location' => $user['location'] ?? null,
            'company' => $user['company'] ?? null,
            'website_url' => $user['website_url'] ?? null,
            'github_profile_url' => $user['github_profile_url'] ?? null,
            'role' => $profile['role'] ?? null,
            'seniority' => $profile['seniority'] ?? null,
            'goals' => $profile['goals'] ?? [],
            'joined_at' => $user['created_at'] ?? null,
        ];
    }

    private function githubProfile(array $user, $account)
    {
        $login = $account['provider_login'] ?? ($user['login'] ?? null);
        $url = $user['github_profile_url'] ?? null;
        if (!$url && $login) {
            $url = 'https://github.com/' . rawurlencode($login);
        }

        return [
            'login' => $login,
            'url' => $url,
            'connected' => (bool) $account,
        ];
    }

    private function publicTechnologies(array $technologies)
    {
        return array_map(function ($technology) {
            return [
                'slug' => $technology['slug'] ?? null,
                'name' => $technology['name'] ?? null,
                'category' => $technology['category'] ?? null,
                'proficiency_level' => $technology['proficiency_level'] ?? null,
                'interest_level' => $technology['interest_level'] ?? null,
            ];
        }, $technologies);
    }

    private function publicBadges(array $badges)
    {
        $public = [];
        foreach ($badges as $badge) {
            $definition = $badge['badge'] ?? [];
            if (!empty($definition['is_secret'])) {
                continue;
            }

            $public[] = [
                'slug' => $badge['slug'] ?? ($definition['slug'] ?? null),
                'awarded_at' => $badge['awarded_at'] ?? null,
                'badge' => [
                    'slug' => $definition['slug'] ?? ($badge['slug'] ?? null),
                    'name' => $definition['name'] ?? null,
                    'description' => $definition['description'] ?? null,
                    'category' => $definition['category'] ?? null,
                    'level' => $definition['level'] ?? null,
                    'image_url' => $definition['image_url'] ?? null,
                    'image_alt' => $definition['image_alt'] ?? null,
                    'icon' => $definition['icon'] ?? null,
                    'is_secret' => false,
                    'display_order' => isset($definition['display_order']) ? (int) $definition['display_order'] : 0,
                ],
            ];
        }

        return $public;
    }

    private function publicRepository(array $row)
    {
        $repositoryData = $this->decodeJson($row['repository_data'] ?? null);
        $healthData = $this->decodeJson($row['health_data'] ?? null);
        $summary = null;

        if ($repositoryData) {
            $summary = RepositorySummary::fromGitHubRepository($repositoryData, $healthData ?: null);
        }

        $owner = ($row['owner_login'] ?? null) ?: ($summary['owner'] ?? null);
        $name = ($row['repository_name'] ?? null) ?: ($summary['name'] ?? null);
        $url = $summary['url'] ?? $this->githubRepositoryUrl($owner, $name);

        $repository = [
            'github_repository_id' => isset($row['github_repository_id']) ? (int) $row['github_repository_id'] : null,
            'owner_login' => $owner,
            'repository_name' => $name,
            'state' => $row['state'] ?? null,
            'public_url' => $url,
            'updated_at' => $row['updated_at'] ?? null,
        ];

        if ($summary) {
            $repository['repository'] = $summary;
        }

        return $repository;
    }

    private function publicSlugForUser(array $user, array $settings = null)
    {
        $settings = $settings ?: $this->call('profile_settings', (int) $user['id']);

        if (!empty($settings['public_profile_slug'])) {
            $slug = self::normalizeSlug($settings['public_profile_slug']);
            if ($slug) {
                return $slug;
            }
        }

        return self::normalizeSlug($user['login'] ?? '');
    }

    private function previewWarnings($isPublic, $slug, array $profile)
    {
        $warnings = [];
        if (!$isPublic) {
            $warnings[] = [
                'code' => 'PROFILE_PRIVATE',
                'message' => 'Public profile disabled.',
            ];
        }

        if (!$slug) {
            $warnings[] = [
                'code' => 'MISSING_PUBLIC_SLUG',
                'message' => 'Set a public login or slug to share the profile.',
            ];
        }

        if (empty($profile['technologies'])) {
            $warnings[] = [
                'code' => 'EMPTY_STACK',
                'message' => 'Add technologies to enrich the public profile.',
            ];
        }

        return $warnings;
    }

    private function keyedCounts(array $rows, $key)
    {
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row[$key]] = (int) $row['total'];
        }
        return $counts;
    }

    private function decodeJson($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function githubRepositoryUrl($owner, $name)
    {
        if (!$owner || !$name) {
            return null;
        }

        return 'https://github.com/' . rawurlencode($owner) . '/' . rawurlencode($name);
    }

    private function env($key)
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        $value = getenv($key);
        return $value === false ? null : $value;
    }

    private function call($key)
    {
        $args = array_slice(func_get_args(), 1);
        return call_user_func_array($this->deps[$key], $args);
    }
}
