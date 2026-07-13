<?php

class BadgeProgressService
{
    private $deps;

    public function __construct(array $deps = [])
    {
        $this->deps = array_merge([
            'profile_onboarding_completed' => function ($userId) {
                $profile = UserProfile::findByUserId($userId);
                return $profile && !empty($profile['onboarding_completed']);
            },
            'technology_count' => function ($userId) {
                return count(UserTechnology::findByUserId($userId));
            },
            'preferences_exists' => function ($userId) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT 1 FROM user_preferences WHERE user_id = :user_id LIMIT 1");
                $stmt->execute(['user_id' => $userId]);
                return (bool) $stmt->fetchColumn();
            },
            'activity_event_count' => [$this, 'countActivityEvents'],
            'activity_event_exists' => function ($userId, $eventType) {
                return $this->countActivityEvents($userId, $eventType, false) > 0;
            },
            'repository_state_count' => [$this, 'countRepositoryStates'],
            'repository_state_exists' => function ($userId, $state) {
                return $this->countRepositoryStates($userId, [$state]) > 0;
            },
            'repository_language_saved_count' => [$this, 'countSavedRepositoryLanguages'],
            'issue_label_interaction_count' => [$this, 'countIssueLabelInteractions'],
            'activity_distinct_days' => [$this, 'countActivityDistinctDays'],
            'referral_count' => ['UserReferral', 'countByReferrerUserId'],
            'alpha_user_started_before' => [$this, 'isAlphaUserStartedBefore'],
            'signup_cohort_award_position' => ['SignupCohortAward', 'positionForUser'],
        ], $deps);
    }

    public function progressForUser($userId)
    {
        $earned = UserBadge::awardedMapByUser($userId);
        $items = [];

        foreach (BadgeDefinition::allActive() as $definition) {
            $progress = $this->progressForDefinition($userId, $definition);
            if (isset($earned[$definition['slug']])) {
                $progress['completed'] = true;
                $progress['current_value'] = $progress['target_value'];
                $progress['percent'] = 100;
                $progress['awarded_at'] = $earned[$definition['slug']]['awarded_at'];
            }
            $items[] = $progress;
        }

        return $items;
    }

    public function progressForBadge($userId, $badgeSlug)
    {
        $definition = BadgeDefinition::findBySlug($badgeSlug);
        if (!$definition || empty($definition['is_active'])) {
            return null;
        }

        return $this->progressForDefinition($userId, $definition);
    }

    public function progressForDefinition($userId, array $definition)
    {
        $criteriaType = $definition['criteria_type'];
        $config = $definition['criteria_config'] ?? [];
        $target = $this->targetValue($criteriaType, $config);
        $current = $this->currentValue($userId, $criteriaType, $config);
        $percent = $target > 0 ? min(100, (int) floor(($current / $target) * 100)) : 0;
        $completed = $target > 0 && $current >= $target;
        $isSecret = BadgeDefinition::isSecret($definition);

        return [
            'slug' => $isSecret ? BadgeDefinition::secretSlug() : $definition['slug'],
            'current_value' => $isSecret ? ($completed ? 1 : 0) : min($current, $target),
            'target_value' => $isSecret ? 1 : $target,
            'percent' => $isSecret ? ($completed ? 100 : 0) : $percent,
            'completed' => $completed,
            'awarded_at' => null,
            'criteria_type' => $isSecret ? 'secret' : $criteriaType,
            'criteria_config' => $isSecret ? [] : $config,
            'badge' => BadgeDefinition::compactResponse($definition),
        ];
    }

    public function countActivityEvents($userId, $eventType, $distinctRepositories = false)
    {
        $db = Database::getInstance()->getConnection();
        $select = $distinctRepositories ? 'COUNT(DISTINCT github_repository_id)' : 'COUNT(*)';
        $extra = $distinctRepositories ? ' AND github_repository_id IS NOT NULL' : '';
        $stmt = $db->prepare("
            SELECT {$select}
            FROM user_activity_events
            WHERE user_id = :user_id AND event_type = :event_type {$extra}
        ");
        $stmt->execute(['user_id' => $userId, 'event_type' => $eventType]);
        return (int) $stmt->fetchColumn();
    }

    public function countRepositoryStates($userId, array $states)
    {
        if (!$states) {
            return 0;
        }

        $db = Database::getInstance()->getConnection();
        $placeholders = [];
        $params = ['user_id' => $userId];
        foreach (array_values($states) as $index => $state) {
            $key = 'state_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $state;
        }

        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT github_repository_id)
            FROM user_repository_states
            WHERE user_id = :user_id AND state IN (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countSavedRepositoryLanguages($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT r.repository_data
            FROM user_repository_states s
            INNER JOIN repository_cache r ON r.github_repository_id = s.github_repository_id
            WHERE s.user_id = :user_id AND s.state = 'saved'
        ");
        $stmt->execute(['user_id' => $userId]);

        $languages = [];
        foreach ($stmt->fetchAll() as $row) {
            $repository = $row['repository_data'] ? json_decode($row['repository_data'], true) : [];
            if (!is_array($repository)) {
                continue;
            }

            foreach (($repository['languages'] ?? []) as $language) {
                if ($language) {
                    $languages[strtolower((string) $language)] = true;
                }
            }

            if (!empty($repository['language'])) {
                $languages[strtolower((string) $repository['language'])] = true;
            }
        }

        return count($languages);
    }

    public function countIssueLabelInteractions($userId, $label)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT DISTINCT github_repository_id
            FROM user_repository_states
            WHERE user_id = :user_id
              AND state IN ('saved', 'researching', 'working', 'pull_request_sent', 'contributed')
        ");
        $stmt->execute(['user_id' => $userId]);

        $count = 0;
        foreach ($stmt->fetchAll() as $row) {
            $stats = RepositoryIssueCache::statsByRepositoryId($row['github_repository_id']);
            if ($label === 'good first issue' && ($stats['good_first_issues'] ?? 0) > 0) {
                $count++;
            } elseif ($label === 'help wanted' && ($stats['help_wanted_issues'] ?? 0) > 0) {
                $count++;
            }
        }

        return $count;
    }

    public function countActivityDistinctDays($userId, $withinDays = null)
    {
        $db = Database::getInstance()->getConnection();
        $params = ['user_id' => $userId];
        $where = 'user_id = :user_id';

        if ($withinDays) {
            $withinDays = min(max((int) $withinDays, 1), 3650);
            $where .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $withinDays . ' DAY)';
        }

        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT DATE(created_at))
            FROM user_activity_events
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function isAlphaUserStartedBefore($userId, $deadline)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT created_at
            FROM users
            WHERE id = :user_id AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $createdAt = $stmt->fetchColumn();

        return $createdAt && substr($createdAt, 0, 10) <= $deadline;
    }

    private function currentValue($userId, $criteriaType, array $config)
    {
        switch ($criteriaType) {
            case 'profile_onboarding_completed':
                return $this->call('profile_onboarding_completed', $userId) ? 1 : 0;
            case 'technology_count':
                return $this->call('technology_count', $userId);
            case 'preferences_defined':
                return $this->call('preferences_exists', $userId) ? 1 : 0;
            case 'activity_event_count':
                return $this->call(
                    'activity_event_count',
                    $userId,
                    $config['event_type'] ?? '',
                    !empty($config['distinct_repositories'])
                );
            case 'activity_event_exists':
                return $this->call('activity_event_exists', $userId, $config['event_type'] ?? '') ? 1 : 0;
            case 'activity_event_or_repository_state_exists':
                if ($this->call('activity_event_exists', $userId, $config['event_type'] ?? '')) {
                    return 1;
                }
                return $this->call('repository_state_count', $userId, $config['states'] ?? [$config['state'] ?? '']) > 0 ? 1 : 0;
            case 'repository_state_count':
                return $this->call('repository_state_count', $userId, $config['states'] ?? [$config['state'] ?? '']);
            case 'repository_state_exists':
                return $this->call('repository_state_exists', $userId, $config['state'] ?? '') ? 1 : 0;
            case 'repository_language_saved_count':
                return $this->call('repository_language_saved_count', $userId);
            case 'issue_label_interaction_count':
                return $this->call('issue_label_interaction_count', $userId, $config['label'] ?? '');
            case 'activity_distinct_days':
                return $this->call('activity_distinct_days', $userId, $config['within_days'] ?? null);
            case 'referral_count':
                return $this->call('referral_count', $userId);
            case 'alpha_user':
                return $this->alphaUserCurrentValue($userId, $config);
            case 'signup_cohort_first_n':
                return $this->signupCohortFirstNCurrentValue($userId, $config);
            default:
                return 0;
        }
    }

    private function signupCohortFirstNCurrentValue($userId, array $config)
    {
        $cohort = $config['cohort'] ?? SignupCohortAwardService::COHORT_SLUG;
        return $this->call('signup_cohort_award_position', $userId, $cohort) ? 1 : 0;
    }

    private function alphaUserCurrentValue($userId, array $config)
    {
        $deadline = $config['deadline'] ?? '2026-10-30';
        if (!$this->call('alpha_user_started_before', $userId, $deadline)) {
            return 0;
        }

        $checks = 1;
        $checks += $this->call('profile_onboarding_completed', $userId) ? 1 : 0;
        $checks += $this->call('activity_event_count', $userId, 'viewed_project', true) >= 5 ? 1 : 0;
        $checks += $this->call('repository_state_count', $userId, ['saved']) >= 3 ? 1 : 0;
        $checks += $this->call('activity_event_exists', $userId, 'opened_github') ? 1 : 0;
        return $checks;
    }

    private function targetValue($criteriaType, array $config)
    {
        if ($criteriaType === 'signup_cohort_first_n') {
            return 1;
        }

        return max(1, (int) ($config['threshold'] ?? $config['target'] ?? 1));
    }

    private function call($key)
    {
        $args = array_slice(func_get_args(), 1);
        return call_user_func_array($this->deps[$key], $args);
    }
}
