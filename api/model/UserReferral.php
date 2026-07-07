<?php

class UserReferral
{
    public static function findByReferredUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM user_referrals
            WHERE referred_user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch();
    }

    public static function create($referrerUserId, $referredUserId, array $inviteLink, $source)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO user_referrals (
                referrer_user_id, referred_user_id, invite_link_id, invite_code, source, registered_at, created_at
            ) VALUES (
                :referrer_user_id, :referred_user_id, :invite_link_id, :invite_code, :source, NOW(), NOW()
            )
        ");

        $stmt->execute([
            'referrer_user_id' => $referrerUserId,
            'referred_user_id' => $referredUserId,
            'invite_link_id' => $inviteLink['id'],
            'invite_code' => $inviteLink['code'],
            'source' => $source,
        ]);

        return self::findByReferredUserId($referredUserId);
    }

    public static function countByReferrerUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) AS total
            FROM user_referrals
            WHERE referrer_user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function listByReferrerUserId($userId, $limit = 20)
    {
        $db = Database::getInstance()->getConnection();
        $limit = min(max((int) $limit, 1), 100);
        $stmt = $db->prepare("
            SELECT r.*, u.login, u.display_name, u.avatar_url
            FROM user_referrals r
            INNER JOIN users u ON u.id = r.referred_user_id AND u.deleted_at IS NULL
            WHERE r.referrer_user_id = :user_id
            ORDER BY r.registered_at DESC, r.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute(['user_id' => $userId]);
        return array_map([self::class, 'toResponse'], $stmt->fetchAll());
    }

    public static function toResponse(array $row)
    {
        return [
            'registered_at' => $row['registered_at'] ?? null,
            'referred_user' => [
                'login' => $row['login'] ?? null,
                'display_name' => $row['display_name'] ?? null,
                'avatar_url' => $row['avatar_url'] ?? null,
            ],
        ];
    }
}
