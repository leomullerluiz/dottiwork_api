<?php

$env = static function ($key, $legacyKey = null, $default = '') {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if ($legacyKey) {
        if (isset($_ENV[$legacyKey]) && $_ENV[$legacyKey] !== '') {
            return $_ENV[$legacyKey];
        }

        $legacyValue = getenv($legacyKey);
        if ($legacyValue !== false && $legacyValue !== '') {
            return $legacyValue;
        }
    }

    return $default;
};

return [
    'client_id' => $env('OAUTH_GITHUB_CLIENT_ID', 'GITHUB_CLIENT_ID'),
    'client_secret' => $env('OAUTH_GITHUB_CLIENT_SECRET', 'GITHUB_CLIENT_SECRET'),
    'redirect_uri' => $env('OAUTH_GITHUB_REDIRECT_URI', 'GITHUB_REDIRECT_URI'),
    'scopes' => $env('OAUTH_GITHUB_SCOPES', 'GITHUB_OAUTH_SCOPES', 'read:user,user:email'),
    'api_version' => $env('OAUTH_GITHUB_API_VERSION', 'GITHUB_API_VERSION', '2022-11-28'),
    'user_agent' => $env('OAUTH_GITHUB_USER_AGENT', 'GITHUB_USER_AGENT', 'dotti-work-api'),
    'connect_timeout' => (int) $env('OAUTH_GITHUB_CONNECT_TIMEOUT', 'GITHUB_CONNECT_TIMEOUT', 5),
    'timeout' => (int) $env('OAUTH_GITHUB_TIMEOUT', 'GITHUB_TIMEOUT', 15),
    'repository_cache_ttl_seconds' => (int) ($_ENV['REPOSITORY_CACHE_TTL_SECONDS'] ?? 21600),
    'issues_cache_ttl_seconds' => (int) ($_ENV['ISSUES_CACHE_TTL_SECONDS'] ?? 3600),
    'match_cache_ttl_seconds' => (int) ($_ENV['MATCH_CACHE_TTL_SECONDS'] ?? 3600),
    'match_refresh_cooldown_seconds' => (int) ($_ENV['MATCH_REFRESH_COOLDOWN_SECONDS'] ?? 60),
];
