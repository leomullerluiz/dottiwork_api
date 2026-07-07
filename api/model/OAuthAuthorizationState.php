<?php

class OAuthAuthorizationState
{
    public static function create($plainState, $returnTo, $ipHash = null, $userAgent = null, $inviteCode = null, $inviteLinkId = null)
    {
        $db = Database::getInstance()->getConnection();
        $expiresAt = (new DateTime())->modify('+10 minutes')->format('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO oauth_authorization_states (
                state_hash, return_to, ip_hash, user_agent, invite_code, invite_link_id, expires_at, created_at
            ) VALUES (
                :state_hash, :return_to, :ip_hash, :user_agent, :invite_code, :invite_link_id, :expires_at, NOW()
            )
        ");

        $stmt->execute([
            'state_hash' => Crypto::hashState($plainState),
            'return_to' => $returnTo,
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'invite_code' => $inviteCode,
            'invite_link_id' => $inviteLinkId,
            'expires_at' => $expiresAt,
        ]);

        return self::findByStateHash(Crypto::hashState($plainState));
    }

    public static function findByStateHash($stateHash)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM oauth_authorization_states
            WHERE state_hash = :state_hash
            LIMIT 1
        ");
        $stmt->execute(['state_hash' => $stateHash]);
        return $stmt->fetch();
    }

    public static function markUsed($id)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE oauth_authorization_states
            SET used_at = NOW()
            WHERE id = :id AND used_at IS NULL
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public static function isValid($state)
    {
        $record = self::findByStateHash(Crypto::hashState($state));
        if (!$record || !empty($record['used_at'])) {
            return false;
        }

        return new DateTime() <= new DateTime($record['expires_at']);
    }
}
