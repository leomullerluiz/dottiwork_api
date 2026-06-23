<?php

class OAuthAccount
{
    public static function findByProviderAccount($provider, $providerAccountId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM oauth_accounts
            WHERE provider = :provider AND provider_account_id = :provider_account_id
            LIMIT 1
        ");
        $stmt->execute([
            'provider' => $provider,
            'provider_account_id' => $providerAccountId,
        ]);
        return $stmt->fetch();
    }

    public static function findByUserAndProvider($userId, $provider)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM oauth_accounts
            WHERE user_id = :user_id AND provider = :provider
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId, 'provider' => $provider]);
        return $stmt->fetch();
    }

    public static function upsertGitHub($userId, array $githubUser, array $tokenResponse)
    {
        $existing = self::findByProviderAccount('github', (string) $githubUser['id']);
        $params = [
            'user_id' => $userId,
            'provider' => 'github',
            'provider_account_id' => (string) $githubUser['id'],
            'provider_login' => $githubUser['login'] ?? null,
            'access_token_encrypted' => Crypto::encrypt($tokenResponse['access_token']),
            'refresh_token_encrypted' => isset($tokenResponse['refresh_token']) ? Crypto::encrypt($tokenResponse['refresh_token']) : null,
            'token_type' => $tokenResponse['token_type'] ?? null,
            'scope' => $tokenResponse['scope'] ?? null,
            'token_expires_at' => null,
        ];

        $db = Database::getInstance()->getConnection();

        if ($existing) {
            $updateParams = [
                'id' => $existing['id'],
                'user_id' => $params['user_id'],
                'provider_login' => $params['provider_login'],
                'access_token_encrypted' => $params['access_token_encrypted'],
                'refresh_token_encrypted' => $params['refresh_token_encrypted'],
                'token_type' => $params['token_type'],
                'scope' => $params['scope'],
                'token_expires_at' => $params['token_expires_at'],
            ];
            $stmt = $db->prepare("
                UPDATE oauth_accounts
                SET user_id = :user_id,
                    provider_login = :provider_login,
                    access_token_encrypted = :access_token_encrypted,
                    refresh_token_encrypted = :refresh_token_encrypted,
                    token_type = :token_type,
                    scope = :scope,
                    token_expires_at = :token_expires_at,
                    token_last_verified_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute($updateParams);
            return self::findByProviderAccount('github', (string) $githubUser['id']);
        }

        $stmt = $db->prepare("
            INSERT INTO oauth_accounts (
                user_id, provider, provider_account_id, provider_login,
                access_token_encrypted, refresh_token_encrypted, token_type, scope,
                token_expires_at, token_last_verified_at, created_at, updated_at
            ) VALUES (
                :user_id, :provider, :provider_account_id, :provider_login,
                :access_token_encrypted, :refresh_token_encrypted, :token_type, :scope,
                :token_expires_at, NOW(), NOW(), NOW()
            )
        ");
        $stmt->execute($params);

        return self::findByProviderAccount('github', (string) $githubUser['id']);
    }

    public static function decryptAccessToken($account)
    {
        if (!$account || empty($account['access_token_encrypted'])) {
            return null;
        }

        return Crypto::decrypt($account['access_token_encrypted']);
    }

    public static function toPublic($account)
    {
        return [
            'connected' => (bool) $account,
            'login' => $account['provider_login'] ?? null,
            'provider' => $account['provider'] ?? 'github',
            'scope' => $account['scope'] ?? null,
            'token_last_verified_at' => $account['token_last_verified_at'] ?? null,
        ];
    }
}
