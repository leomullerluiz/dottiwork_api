<?php

class UserConsent
{
    public static function allowedTypes()
    {
        return ['essential', 'analytics', 'sentry_replay', 'marketing', 'github_oauth_notice'];
    }

    public static function revocableTypes()
    {
        return ['analytics', 'sentry_replay', 'marketing', 'github_oauth_notice'];
    }

    public static function allowedSources()
    {
        return ['cookie_banner', 'settings', 'login_notice', 'onboarding'];
    }

    public static function validateGrantPayload(array $payload)
    {
        return Validator::collectErrors([
            'type' => [
                'Invalid consent type.' => isset($payload['type']) && Validator::enum($payload['type'], self::allowedTypes()),
            ],
            'status' => [
                'Status must be granted.' => !isset($payload['status']) || $payload['status'] === 'granted',
            ],
            'policy_version' => [
                'Policy version is required and must be up to 50 characters.' => isset($payload['policy_version']) && Validator::maxLength($payload['policy_version'], 50),
            ],
            'source' => [
                'Invalid consent source.' => isset($payload['source']) && Validator::enum($payload['source'], self::allowedSources()),
            ],
        ]);
    }

    public static function validateConsentType($type)
    {
        if (!Validator::enum($type, self::allowedTypes())) {
            return [
                [
                    'field' => 'type',
                    'message' => 'Invalid consent type.',
                ],
            ];
        }

        return [];
    }

    public static function canRevoke($type)
    {
        return Validator::enum($type, self::revocableTypes());
    }

    public static function normalizeGrantPayload(array $payload)
    {
        return [
            'type' => $payload['type'],
            'status' => 'granted',
            'policy_version' => trim((string) $payload['policy_version']),
            'source' => $payload['source'],
        ];
    }

    public static function listByUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM user_consents
            WHERE user_id = :user_id
            ORDER BY type ASC
        ");
        $stmt->execute(['user_id' => $userId]);

        return array_map([self::class, 'decode'], $stmt->fetchAll());
    }

    public static function findByUserAndType($userId, $type)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM user_consents
            WHERE user_id = :user_id AND type = :type
            LIMIT 1
        ");
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
        ]);
        $row = $stmt->fetch();

        return $row ? self::decode($row) : null;
    }

    public static function grant($userId, array $payload)
    {
        $payload = self::normalizeGrantPayload($payload);
        $existing = self::findByUserAndType($userId, $payload['type']);
        $db = Database::getInstance()->getConnection();

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE user_consents
                SET status = 'granted',
                    policy_version = :policy_version,
                    source = :source,
                    revoked_at = NULL,
                    updated_at = NOW()
                WHERE user_id = :user_id AND type = :type
            ");
        } else {
            $stmt = $db->prepare("
                INSERT INTO user_consents (
                    user_id, type, status, policy_version, source, created_at, updated_at, revoked_at
                ) VALUES (
                    :user_id, :type, 'granted', :policy_version, :source, NOW(), NOW(), NULL
                )
            ");
        }

        $stmt->execute([
            'user_id' => $userId,
            'type' => $payload['type'],
            'policy_version' => $payload['policy_version'],
            'source' => $payload['source'],
        ]);

        return self::findByUserAndType($userId, $payload['type']);
    }

    public static function revoke($userId, $type)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE user_consents
            SET status = 'revoked',
                revoked_at = NOW(),
                updated_at = NOW()
            WHERE user_id = :user_id AND type = :type
        ");
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
        ]);

        return self::findByUserAndType($userId, $type);
    }

    public static function decode($row)
    {
        return [
            'type' => $row['type'] ?? null,
            'status' => $row['status'] ?? null,
            'policy_version' => $row['policy_version'] ?? null,
            'source' => $row['source'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'revoked_at' => $row['revoked_at'] ?? null,
        ];
    }
}
