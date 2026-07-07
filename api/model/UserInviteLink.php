<?php

class UserInviteLink
{
    public static function findById($id)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT l.*, u.display_name AS inviter_display_name, u.avatar_url AS inviter_avatar_url
            FROM user_invite_links l
            INNER JOIN users u ON u.id = l.user_id AND u.deleted_at IS NULL
            WHERE l.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function findPrimaryActiveByUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM user_invite_links
            WHERE user_id = :user_id
              AND status = 'active'
              AND revoked_at IS NULL
              AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch();
    }

    public static function listByUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM user_invite_links
            WHERE user_id = :user_id
            ORDER BY id DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function findByCode($code)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT l.*, u.display_name AS inviter_display_name, u.avatar_url AS inviter_avatar_url
            FROM user_invite_links l
            INNER JOIN users u ON u.id = l.user_id AND u.deleted_at IS NULL
            WHERE l.code = :code
            LIMIT 1
        ");
        $stmt->execute(['code' => $code]);
        return $stmt->fetch();
    }

    public static function create($userId, array $data = [])
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO user_invite_links (
                user_id, code, label, status, max_uses, uses_count, expires_at, created_at, updated_at
            ) VALUES (
                :user_id, :code, :label, 'active', :max_uses, 0, :expires_at, NOW(), NOW()
            )
        ");

        $stmt->execute([
            'user_id' => $userId,
            'code' => $data['code'],
            'label' => $data['label'] ?? null,
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return self::findById($db->lastInsertId());
    }

    public static function revoke($userId, $linkId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE user_invite_links
            SET status = 'revoked',
                revoked_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
              AND user_id = :user_id
              AND status = 'active'
        ");
        $stmt->execute([
            'id' => $linkId,
            'user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function incrementUsage($id)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE user_invite_links
            SET uses_count = uses_count + 1,
                last_used_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }

    public static function isActive($link)
    {
        if (!$link || ($link['status'] ?? null) !== 'active' || !empty($link['revoked_at'])) {
            return false;
        }

        if (!empty($link['expires_at']) && new DateTime() > new DateTime($link['expires_at'])) {
            return false;
        }

        if (($link['max_uses'] ?? null) !== null && (int) $link['uses_count'] >= (int) $link['max_uses']) {
            return false;
        }

        return true;
    }

    public static function toResponse($link)
    {
        if (!$link) {
            return null;
        }

        return [
            'id' => (int) $link['id'],
            'code' => $link['code'],
            'url' => InviteLinkService::publicUrl($link['code']),
            'status' => $link['status'],
            'uses_count' => (int) ($link['uses_count'] ?? 0),
            'expires_at' => $link['expires_at'] ?? null,
            'created_at' => $link['created_at'] ?? null,
        ];
    }

    public static function toPublicResponse($link)
    {
        if (!$link) {
            return null;
        }

        return [
            'code' => $link['code'],
            'valid' => self::isActive($link),
            'inviter' => [
                'display_name' => $link['inviter_display_name'] ?? null,
                'avatar_url' => $link['inviter_avatar_url'] ?? null,
            ],
        ];
    }
}
