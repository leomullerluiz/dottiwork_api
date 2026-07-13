<?php

class UserProfileService
{
    private $deps;

    public function __construct(array $deps = [])
    {
        $this->deps = array_merge([
            'user_public' => function ($userId) {
                return User::toPublic(User::findById($userId));
            },
            'profile_get' => ['UserProfile', 'getComplete'],
            'profile_frame' => ['UserProfileFrame', 'featuredForUser'],
            'technologies_find' => ['UserTechnology', 'findByUserId'],
            'preferences_find' => ['UserPreference', 'findByUserId'],
            'repository_states_list' => function ($userId) {
                return UserRepositoryState::listByUser($userId, ['limit' => 100]);
            },
            'history_list' => function ($userId) {
                return UserActivityEvent::listByUser($userId, ['limit' => 100]);
            },
            'profile_upsert' => ['UserProfile', 'upsertWithGoals'],
            'preferences_upsert' => ['UserPreference', 'upsert'],
            'technology_find_active_by_ids' => ['Technology', 'findActiveByIds'],
            'technology_replace_all' => ['UserTechnology', 'replaceAll'],
            'repository_state_upsert' => ['UserRepositoryState', 'upsert'],
            'activity_create' => ['UserActivityEvent', 'create'],
            'badge_evaluate' => function ($userId) {
                return (new BadgeEvaluatorService())->evaluateUser($userId);
            },
            'export_email' => function (array $user) {
                return (new UserDataExportEmailService())->sendExportRequestedAlert($user);
            },
        ], $deps);
    }

    public function export($user)
    {
        $userId = is_array($user) ? ($user['id'] ?? null) : $user;
        $profile = $this->call('profile_get', $userId);
        $profile['profile_frame'] = $this->call('profile_frame', $userId);

        $data = [
            'user' => $this->call('user_public', $userId),
            'profile' => $profile,
            'technologies' => $this->call('technologies_find', $userId),
            'preferences' => $this->call('preferences_find', $userId),
            'repository_states' => $this->call('repository_states_list', $userId),
            'history' => $this->call('history_list', $userId),
        ];

        $this->trySendExportEmail($user);

        return $data;
    }

    public function importLocalData($userId, array $payload)
    {
        if (isset($payload['profile']) && is_array($payload['profile'])) {
            $profile = $payload['profile'];
            $this->call(
                'profile_upsert',
                $userId,
                $profile['role'] ?? null,
                $profile['seniority'] ?? null,
                isset($profile['goals']) && is_array($profile['goals']) ? array_values(array_unique($profile['goals'])) : [],
                !empty($profile['onboarding_completed'])
            );
        }

        if (isset($payload['preferences']) && is_array($payload['preferences'])) {
            $this->call('preferences_upsert', $userId, $payload['preferences']);
        }

        if (isset($payload['technologies']) && is_array($payload['technologies'])) {
            $items = array_slice($payload['technologies'], 0, 50);
            $ids = [];
            $normalized = [];
            $seen = [];
            $proficiencyLevels = ['learning', 'basic', 'daily', 'advanced'];
            $interestLevels = ['learn', 'contribute', 'mentor'];
            foreach ($items as $item) {
                if (empty($item['technology_id']) || empty($item['proficiency_level'])) {
                    continue;
                }
                if (!in_array($item['proficiency_level'], $proficiencyLevels, true)) {
                    continue;
                }
                $interestLevel = $item['interest_level'] ?? 'contribute';
                if (!in_array($interestLevel, $interestLevels, true)) {
                    $interestLevel = 'contribute';
                }
                $id = (int) $item['technology_id'];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $ids[] = $id;
                $normalized[] = [
                    'technology_id' => $id,
                    'proficiency_level' => $item['proficiency_level'],
                    'interest_level' => $interestLevel,
                ];
            }

            $ids = array_values(array_unique($ids));
            $active = $this->call('technology_find_active_by_ids', $ids);
            if (count($active) === count($ids)) {
                $this->call('technology_replace_all', $userId, $normalized);
            }
        }

        if (isset($payload['repository_states']) && is_array($payload['repository_states'])) {
            foreach (array_slice($payload['repository_states'], 0, 200) as $state) {
                if (empty($state['github_repository_id']) || empty($state['state'])) {
                    continue;
                }
                $this->call(
                    'repository_state_upsert',
                    $userId,
                    (int) $state['github_repository_id'],
                    $state['owner_login'] ?? '',
                    $state['repository_name'] ?? '',
                    $state['state'],
                    $state['notes'] ?? null
                );
            }
        }

        if (isset($payload['history']) && is_array($payload['history'])) {
            foreach (array_slice($payload['history'], 0, 300) as $event) {
                if (empty($event['event_type'])) {
                    continue;
                }
                $this->call(
                    'activity_create',
                    $userId,
                    $event['event_type'],
                    isset($event['github_repository_id']) ? (int) $event['github_repository_id'] : null,
                    ['source' => 'local_storage_import']
                );
            }
        }

        $this->call('activity_create', $userId, 'restored_project', null, ['type' => 'local_storage_import']);
        $this->call('badge_evaluate', $userId);
        return $this->export($userId);
    }

    private function call($key)
    {
        $args = array_slice(func_get_args(), 1);
        return call_user_func_array($this->deps[$key], $args);
    }

    private function trySendExportEmail($user)
    {
        if (!is_array($user)) {
            return false;
        }

        try {
            $this->call('export_email', $user);
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }
}
