<?php

class UserTechnology
{
    public static function findByUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT ut.*, t.slug, t.name, t.category, t.github_language, t.github_topics
            FROM user_technologies ut
            INNER JOIN technologies t ON t.id = ut.technology_id
            WHERE ut.user_id = :user_id
            ORDER BY t.display_order ASC, t.name ASC
        ");
        $stmt->execute(['user_id' => $userId]);

        return array_map(function ($row) {
            $row['id'] = (int) $row['id'];
            $row['technology_id'] = (int) $row['technology_id'];
            $row['github_topics'] = $row['github_topics'] ? json_decode($row['github_topics'], true) : [];
            return $row;
        }, $stmt->fetchAll());
    }

    public static function replaceAll($userId, array $items)
    {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            $delete = $db->prepare("DELETE FROM user_technologies WHERE user_id = :user_id");
            $delete->execute(['user_id' => $userId]);

            $insert = $db->prepare("
                INSERT INTO user_technologies (
                    user_id, technology_id, proficiency_level, interest_level, created_at, updated_at
                ) VALUES (
                    :user_id, :technology_id, :proficiency_level, :interest_level, NOW(), NOW()
                )
            ");

            foreach ($items as $item) {
                $insert->execute([
                    'user_id' => $userId,
                    'technology_id' => $item['technology_id'],
                    'proficiency_level' => $item['proficiency_level'],
                    'interest_level' => $item['interest_level'],
                ]);
            }

            UserRepositoryMatch::invalidateByUserId($userId);
            $db->commit();
            return self::findByUserId($userId);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
