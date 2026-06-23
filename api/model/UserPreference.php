<?php

class UserPreference
{
    public static function defaults()
    {
        return [
            'contribution_types' => ['bug_fix', 'documentation', 'tests'],
            'difficulty_levels' => ['beginner', 'intermediate'],
            'project_sizes' => ['small', 'medium'],
            'documentation_languages' => ['en', 'pt', 'any'],
            'organization_types' => ['community', 'foundation', 'any'],
            'activity_window_days' => 90,
            'minimum_stars' => 0,
            'require_good_first_issue' => false,
            'require_help_wanted' => false,
            'default_sort_by' => 'best_match',
        ];
    }

    public static function findByUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM user_preferences WHERE user_id = :user_id LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            return self::createDefaults($userId);
        }

        return self::decode($row);
    }

    public static function createDefaults($userId)
    {
        return self::upsert($userId, self::defaults(), false);
    }

    public static function upsert($userId, array $data, $invalidateMatches = true)
    {
        $existing = self::rawByUserId($userId);
        $payload = array_merge(self::defaults(), $data);
        $params = self::params($userId, $payload);
        $db = Database::getInstance()->getConnection();

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE user_preferences
                SET contribution_types = :contribution_types,
                    difficulty_levels = :difficulty_levels,
                    project_sizes = :project_sizes,
                    documentation_languages = :documentation_languages,
                    organization_types = :organization_types,
                    activity_window_days = :activity_window_days,
                    minimum_stars = :minimum_stars,
                    require_good_first_issue = :require_good_first_issue,
                    require_help_wanted = :require_help_wanted,
                    default_sort_by = :default_sort_by,
                    updated_at = NOW()
                WHERE user_id = :user_id
            ");
        } else {
            $stmt = $db->prepare("
                INSERT INTO user_preferences (
                    user_id, contribution_types, difficulty_levels, project_sizes,
                    documentation_languages, organization_types, activity_window_days,
                    minimum_stars, require_good_first_issue, require_help_wanted,
                    default_sort_by, created_at, updated_at
                ) VALUES (
                    :user_id, :contribution_types, :difficulty_levels, :project_sizes,
                    :documentation_languages, :organization_types, :activity_window_days,
                    :minimum_stars, :require_good_first_issue, :require_help_wanted,
                    :default_sort_by, NOW(), NOW()
                )
            ");
        }

        $stmt->execute($params);

        if ($invalidateMatches) {
            UserRepositoryMatch::invalidateByUserId($userId);
        }

        return self::findByUserId($userId);
    }

    private static function rawByUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM user_preferences WHERE user_id = :user_id LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch();
    }

    private static function params($userId, array $data)
    {
        return [
            'user_id' => $userId,
            'contribution_types' => json_encode(array_values($data['contribution_types'])),
            'difficulty_levels' => json_encode(array_values($data['difficulty_levels'])),
            'project_sizes' => json_encode(array_values($data['project_sizes'])),
            'documentation_languages' => json_encode(array_values($data['documentation_languages'])),
            'organization_types' => json_encode(array_values($data['organization_types'])),
            'activity_window_days' => (int) $data['activity_window_days'],
            'minimum_stars' => (int) $data['minimum_stars'],
            'require_good_first_issue' => !empty($data['require_good_first_issue']) ? 1 : 0,
            'require_help_wanted' => !empty($data['require_help_wanted']) ? 1 : 0,
            'default_sort_by' => $data['default_sort_by'],
        ];
    }

    private static function decode($row)
    {
        foreach (['contribution_types', 'difficulty_levels', 'project_sizes', 'documentation_languages', 'organization_types'] as $field) {
            $row[$field] = $row[$field] ? json_decode($row[$field], true) : [];
        }

        $row['activity_window_days'] = (int) $row['activity_window_days'];
        $row['minimum_stars'] = (int) $row['minimum_stars'];
        $row['require_good_first_issue'] = (bool) $row['require_good_first_issue'];
        $row['require_help_wanted'] = (bool) $row['require_help_wanted'];
        return $row;
    }
}
