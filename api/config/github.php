<?php

return [
    'client_id' => $_ENV['GITHUB_CLIENT_ID'] ?? '',
    'client_secret' => $_ENV['GITHUB_CLIENT_SECRET'] ?? '',
    'redirect_uri' => $_ENV['GITHUB_REDIRECT_URI'] ?? '',
    'scopes' => $_ENV['GITHUB_OAUTH_SCOPES'] ?? 'read:user,user:email',
    'api_version' => $_ENV['GITHUB_API_VERSION'] ?? '2022-11-28',
    'user_agent' => $_ENV['GITHUB_USER_AGENT'] ?? 'dotti-work-api',
    'connect_timeout' => (int) ($_ENV['GITHUB_CONNECT_TIMEOUT'] ?? 5),
    'timeout' => (int) ($_ENV['GITHUB_TIMEOUT'] ?? 15),
    'repository_cache_ttl_seconds' => (int) ($_ENV['REPOSITORY_CACHE_TTL_SECONDS'] ?? 21600),
    'issues_cache_ttl_seconds' => (int) ($_ENV['ISSUES_CACHE_TTL_SECONDS'] ?? 3600),
    'match_cache_ttl_seconds' => (int) ($_ENV['MATCH_CACHE_TTL_SECONDS'] ?? 3600),
    'match_refresh_cooldown_seconds' => (int) ($_ENV['MATCH_REFRESH_COOLDOWN_SECONDS'] ?? 60),
];
