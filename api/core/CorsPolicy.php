<?php

class CorsPolicy
{
    public static function allowedOrigins($configured = null, $appEnv = null)
    {
        if ($configured === null) {
            $configured = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '';
        }

        if ($appEnv === null) {
            $appEnv = $_ENV['APP_ENV'] ?? 'local';
        }

        if (trim((string) $configured) === '') {
            $configured = $appEnv === 'production'
                ? 'https://dotti.work,https://dottiwork.com,https://www.dottiwork.com'
                : 'https://dotti.work,https://dottiwork.com,https://www.dottiwork.com,http://localhost:3000';
        }

        $origins = array_values(array_filter(array_map('trim', explode(',', $configured))));
        return self::withDottiworkAliases($origins);
    }

    private static function withDottiworkAliases(array $origins)
    {
        $allowed = [];
        foreach ($origins as $origin) {
            $normalized = rtrim($origin, '/');
            if ($normalized === '') {
                continue;
            }

            $allowed[$normalized] = true;

            if ($normalized === 'https://dottiwork.com') {
                $allowed['https://www.dottiwork.com'] = true;
            }

            if ($normalized === 'https://www.dottiwork.com') {
                $allowed['https://dottiwork.com'] = true;
            }
        }

        return array_keys($allowed);
    }
}
