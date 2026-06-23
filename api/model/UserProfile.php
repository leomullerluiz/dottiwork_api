<?php

class UserProfile
{
    public static function findByUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM user_profiles WHERE user_id = :user_id LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch();
    }

    public static function getGoals($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT goal FROM user_profile_goals WHERE user_id = :user_id ORDER BY goal");
        $stmt->execute(['user_id' => $userId]);
        return array_map(function ($row) {
            return $row['goal'];
        }, $stmt->fetchAll());
    }

    public static function getComplete($userId)
    {
        $profile = self::findByUserId($userId);
        if (!$profile) {
            $profile = self::createDefault($userId);
        }

        $profile['onboarding_completed'] = (bool) $profile['onboarding_completed'];
        $profile['goals'] = self::getGoals($userId);
        return $profile;
    }

    public static function createDefault($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO user_profiles (user_id, onboarding_completed, created_at, updated_at)
            VALUES (:user_id, 0, NOW(), NOW())
        ");
        $stmt->execute(['user_id' => $userId]);
        return self::findByUserId($userId);
    }

    public static function upsertWithGoals($userId, $role, $seniority, array $goals, $onboardingCompleted)
    {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            $current = self::findByUserId($userId);
            $completedAtSql = '';
            if ($onboardingCompleted && (!$current || !(bool) $current['onboarding_completed'])) {
                $completedAtSql = ', onboarding_completed_at = NOW()';
            }

            if ($current) {
                $stmt = $db->prepare("
                    UPDATE user_profiles
                    SET role = :role,
                        seniority = :seniority,
                        onboarding_completed = :onboarding_completed,
                        updated_at = NOW()
                        {$completedAtSql}
                    WHERE user_id = :user_id
                ");
            } else {
                $stmt = $db->prepare("
                    INSERT INTO user_profiles (
                        user_id, role, seniority, onboarding_completed,
                        onboarding_completed_at, created_at, updated_at
                    ) VALUES (
                        :user_id, :role, :seniority, :onboarding_completed,
                        " . ($onboardingCompleted ? 'NOW()' : 'NULL') . ", NOW(), NOW()
                    )
                ");
            }

            $stmt->execute([
                'user_id' => $userId,
                'role' => $role,
                'seniority' => $seniority,
                'onboarding_completed' => $onboardingCompleted ? 1 : 0,
            ]);

            $delete = $db->prepare("DELETE FROM user_profile_goals WHERE user_id = :user_id");
            $delete->execute(['user_id' => $userId]);

            $insert = $db->prepare("
                INSERT INTO user_profile_goals (user_id, goal, created_at)
                VALUES (:user_id, :goal, NOW())
            ");

            foreach ($goals as $goal) {
                $insert->execute(['user_id' => $userId, 'goal' => $goal]);
            }

            $db->commit();
            return self::getComplete($userId);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
