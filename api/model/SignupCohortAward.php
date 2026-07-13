<?php

class SignupCohortAward
{
    public static function positionForUser($userId, $cohortSlug)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT position
            FROM signup_cohort_awards
            WHERE user_id = :user_id AND cohort_slug = :cohort_slug
            LIMIT 1
        ");
        $stmt->execute([
            'user_id' => $userId,
            'cohort_slug' => $cohortSlug,
        ]);

        $position = $stmt->fetchColumn();
        return $position === false ? null : (int) $position;
    }

    public static function countByCohort($cohortSlug)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM signup_cohort_awards
            WHERE cohort_slug = :cohort_slug
        ");
        $stmt->execute(['cohort_slug' => $cohortSlug]);
        return (int) $stmt->fetchColumn();
    }
}
