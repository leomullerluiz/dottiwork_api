<?php

class RateLimiter
{
    public static function enforce(Request $request, $action, $limit, $windowSeconds, $identity = null)
    {
        if (!self::enabled()) {
            return true;
        }

        $keyHashes = self::keyHashes($request, $action, $identity);

        try {
            foreach ($keyHashes as $keyHash) {
                if (!self::consume($keyHash, $limit, $windowSeconds)) {
                    Response::tooManyRequests('Muitas requisicoes. Tente novamente em alguns minutos.');
                }
            }
        } catch (Throwable $e) {
            if (self::env('APP_ENV') === 'production') {
                return true;
            }

            throw $e;
        }

        return true;
    }

    public static function keyHash(Request $request, $action, $identity = null)
    {
        $source = self::identitySource($request, $identity);
        return hash('sha256', $action . '|' . $source);
    }

    public static function keyHashes(Request $request, $action, $identity = null)
    {
        $sources = [self::ipSource($request)];

        if ($identity !== null && $identity !== '') {
            $sources[] = (string) $identity;
        }

        $hashes = [];
        foreach (array_unique($sources) as $source) {
            $hashes[] = hash('sha256', $action . '|' . $source);
        }

        return $hashes;
    }

    private static function identitySource(Request $request, $identity = null)
    {
        if ($identity !== null && $identity !== '') {
            return (string) $identity;
        }

        return self::ipSource($request);
    }

    private static function ipSource(Request $request)
    {
        return $request->getClientIp() ?: 'unknown';
    }

    private static function consume($keyHash, $limit, $windowSeconds)
    {
        $limit = max(1, (int) $limit);
        $windowSeconds = max(1, (int) $windowSeconds);
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT key_hash, attempts, reset_at
            FROM rate_limit_buckets
            WHERE key_hash = :key_hash
            LIMIT 1
        ");
        $stmt->execute(['key_hash' => $keyHash]);
        $row = $stmt->fetch();

        if (!$row || strtotime($row['reset_at']) <= time()) {
            $resetAt = (new DateTime())->modify('+' . $windowSeconds . ' seconds')->format('Y-m-d H:i:s');
            $stmt = $db->prepare("
                INSERT INTO rate_limit_buckets (key_hash, attempts, reset_at, created_at, updated_at)
                VALUES (:key_hash, 1, :reset_at, NOW(), NOW())
                ON DUPLICATE KEY UPDATE attempts = 1, reset_at = VALUES(reset_at), updated_at = NOW()
            ");
            $stmt->execute([
                'key_hash' => $keyHash,
                'reset_at' => $resetAt,
            ]);

            return true;
        }

        if ((int) $row['attempts'] >= $limit) {
            return false;
        }

        $stmt = $db->prepare("
            UPDATE rate_limit_buckets
            SET attempts = attempts + 1, updated_at = NOW()
            WHERE key_hash = :key_hash
        ");
        $stmt->execute(['key_hash' => $keyHash]);

        return true;
    }

    private static function enabled()
    {
        $configured = self::env('RATE_LIMIT_ENABLED');
        if ($configured === null || $configured === '') {
            return true;
        }

        return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
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
