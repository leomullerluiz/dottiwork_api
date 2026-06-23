<?php

class UserProfileService
{
    public function export($userId)
    {
        return [
            'user' => User::toPublic(User::findById($userId)),
            'profile' => UserProfile::getComplete($userId),
            'technologies' => UserTechnology::findByUserId($userId),
            'preferences' => UserPreference::findByUserId($userId),
            'repository_states' => UserRepositoryState::listByUser($userId, ['limit' => 100]),
            'history' => UserActivityEvent::listByUser($userId, ['limit' => 100]),
        ];
    }

    public function importLocalData($userId, array $payload)
    {
        if (isset($payload['profile']) && is_array($payload['profile'])) {
            $profile = $payload['profile'];
            UserProfile::upsertWithGoals(
                $userId,
                $profile['role'] ?? null,
                $profile['seniority'] ?? null,
                isset($profile['goals']) && is_array($profile['goals']) ? array_values(array_unique($profile['goals'])) : [],
                !empty($profile['onboarding_completed'])
            );
        }

        if (isset($payload['preferences']) && is_array($payload['preferences'])) {
            UserPreference::upsert($userId, $payload['preferences']);
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
            $active = Technology::findActiveByIds($ids);
            if (count($active) === count($ids)) {
                UserTechnology::replaceAll($userId, $normalized);
            }
        }

        if (isset($payload['repository_states']) && is_array($payload['repository_states'])) {
            foreach (array_slice($payload['repository_states'], 0, 200) as $state) {
                if (empty($state['github_repository_id']) || empty($state['state'])) {
                    continue;
                }
                UserRepositoryState::upsert(
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
                UserActivityEvent::create(
                    $userId,
                    $event['event_type'],
                    isset($event['github_repository_id']) ? (int) $event['github_repository_id'] : null,
                    ['source' => 'local_storage_import']
                );
            }
        }

        UserActivityEvent::create($userId, 'restored_project', null, ['type' => 'local_storage_import']);
        return $this->export($userId);
    }
}
