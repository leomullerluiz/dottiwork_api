<?php

class AuthToken
{
    public static function create($userId, $tokenHash, $expiresAt, $ipHash = null, $userAgent = null)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO auth_tokens (user_id, token_hash, expires_at, ip_hash, user_agent, created_at)
            VALUES (:user_id, :token_hash, :expires_at, :ip_hash, :user_agent, NOW())
        ");

        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent,
        ]);

        return self::findByTokenHash($tokenHash);
    }

    public static function findByTokenHash($tokenHash)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM auth_tokens
            WHERE token_hash = :token_hash
            LIMIT 1
        ");
        $stmt->execute(['token_hash' => $tokenHash]);
        return $stmt->fetch();
    }

    public static function touchLastUsed($id)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE auth_tokens SET last_used_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public static function revokeByTokenHash($tokenHash)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE auth_tokens
            SET revoked_at = NOW()
            WHERE token_hash = :token_hash AND revoked_at IS NULL
        ");
        $stmt->execute(['token_hash' => $tokenHash]);
        return $stmt->rowCount();
    }

    public static function revokeAllByUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE auth_tokens
            SET revoked_at = NOW()
            WHERE user_id = :user_id AND revoked_at IS NULL
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    public static function deleteExpired()
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM auth_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
