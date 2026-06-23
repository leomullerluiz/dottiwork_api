<?php

class Auth
{
    public static function generateSessionToken()
    {
        return Crypto::randomBase64Url(32);
    }

    public static function hashSessionToken($token)
    {
        return Crypto::hashToken($token);
    }

    public static function createSession($userId, Request $request)
    {
        $token = self::generateSessionToken();
        $ttl = self::sessionTtlSeconds();
        $expiresAt = (new DateTime())->modify('+' . $ttl . ' seconds')->format('Y-m-d H:i:s');
        $userAgent = substr((string) $request->getHeader('User-Agent'), 0, 500);

        AuthToken::create(
            $userId,
            self::hashSessionToken($token),
            $expiresAt,
            Crypto::optionalIpHash($request->getClientIp()),
            $userAgent ?: null
        );

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'expires_in' => $ttl,
        ];
    }

    public static function validateSessionToken($token)
    {
        if (!$token) {
            return null;
        }

        $authToken = AuthToken::findByTokenHash(self::hashSessionToken($token));
        if (!$authToken || !empty($authToken['revoked_at'])) {
            return null;
        }

        $now = new DateTime();
        $expiresAt = new DateTime($authToken['expires_at']);
        if ($now > $expiresAt) {
            return null;
        }

        AuthToken::touchLastUsed($authToken['id']);
        return $authToken;
    }

    public static function getAuthenticatedUser(Request $request)
    {
        $token = $request->getAuthorizationBearerToken();
        if (!$token) {
            $token = $request->getCookie(self::cookieName());
        }

        $authToken = self::validateSessionToken($token);
        if (!$authToken) {
            return null;
        }

        return User::findById($authToken['user_id']);
    }

    public static function requireAuth(Request $request)
    {
        $user = self::getAuthenticatedUser($request);
        if (!$user) {
            Response::unauthorized('Sessao invalida ou expirada.');
        }

        return $user;
    }

    public static function revokeCurrentToken(Request $request)
    {
        $token = $request->getAuthorizationBearerToken();
        if (!$token) {
            $token = $request->getCookie(self::cookieName());
        }

        if ($token) {
            AuthToken::revokeByTokenHash(self::hashSessionToken($token));
        }

        self::clearSessionCookie();
    }

    public static function revokeAllUserTokens($userId)
    {
        return AuthToken::revokeAllByUserId($userId);
    }

    public static function cleanupExpiredTokens()
    {
        return AuthToken::deleteExpired();
    }

    public static function setSessionCookie($token)
    {
        $params = [
            'expires' => time() + self::sessionTtlSeconds(),
            'path' => '/',
            'secure' => self::cookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        $domain = self::env('SESSION_COOKIE_DOMAIN');
        if ($domain) {
            $params['domain'] = $domain;
        }

        setcookie(self::cookieName(), $token, $params);
    }

    public static function clearSessionCookie()
    {
        $params = [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => self::cookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        $domain = self::env('SESSION_COOKIE_DOMAIN');
        if ($domain) {
            $params['domain'] = $domain;
        }

        setcookie(self::cookieName(), '', $params);
    }

    public static function cookieName()
    {
        return self::env('SESSION_COOKIE_NAME') ?: 'dotti_session';
    }

    private static function sessionTtlSeconds()
    {
        return (int) (self::env('SESSION_TOKEN_TTL_SECONDS') ?: 2592000);
    }

    private static function cookieSecure()
    {
        $configured = self::env('SESSION_COOKIE_SECURE');
        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return self::env('APP_ENV') === 'production';
    }

    private static function env($key)
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        $value = getenv($key);
        return $value === false ? null : $value;
    }
}
