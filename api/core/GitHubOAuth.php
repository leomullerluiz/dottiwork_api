<?php

class GitHubOAuth
{
    private const DEBUG_STATE_PREFIX = 'debug.';

    private static $callbackDebugLogs = [];

    public static function start(Request $request)
    {
        $returnTo = self::sanitizeReturnTo($request->getQuery('return_to', '/matches'));
        $isDebugMode = self::requestDebugEnabled($request);
        $state = ($isDebugMode ? self::DEBUG_STATE_PREFIX : '') . Crypto::randomBase64Url(32);

        OAuthAuthorizationState::create(
            $state,
            $returnTo,
            Crypto::optionalIpHash($request->getClientIp()),
            $request->getHeader('User-Agent')
        );

        $config = require __DIR__ . '/../config/github.php';
        if (empty($config['client_id']) || empty($config['redirect_uri'])) {
            Response::error('OAuth GitHub nao configurado.', 500, 'OAUTH_NOT_CONFIGURED');
        }

        $url = 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'scope' => $config['scopes'],
            'state' => $state,
            'allow_signup' => 'true',
        ]);

        Response::redirect($url);
    }

    public static function callback(Request $request)
    {
        $isDebugMode = self::callbackDebugEnabled($request);
        self::$callbackDebugLogs = [];

        $code = $request->getQuery('code');
        $state = $request->getQuery('state');

        self::debugCallback($isDebugMode, 'callback:start', [
            'code_present' => (bool) $code,
            'code_length' => $code ? strlen($code) : 0,
            'state_present' => (bool) $state,
            'state_length' => $state ? strlen($state) : 0,
            'state_preview' => self::preview($state),
            'state_debug_flag' => self::stateCarriesDebugFlag($state),
            'origin' => $request->getOrigin(),
            'client_ip' => $request->getClientIp(),
        ]);

        if (!$code || !$state) {
            self::redirectOrDebug($isDebugMode, self::frontendCallbackUrl('error', 'github_authorization_failed'), 'missing_code_or_state');
        }

        $stateRecord = OAuthAuthorizationState::findByStateHash(Crypto::hashState($state));
        $stateIsValid = $stateRecord ? OAuthAuthorizationState::isValid($state) : false;

        self::debugCallback($isDebugMode, 'state:loaded', [
            'state_record_found' => (bool) $stateRecord,
            'state_is_valid' => $stateIsValid,
            'state_record' => self::safeStateRecord($stateRecord),
        ]);

        if (!$stateRecord || !$stateIsValid) {
            self::redirectOrDebug($isDebugMode, self::frontendCallbackUrl('error', 'invalid_state'), 'invalid_state');
        }

        if (!OAuthAuthorizationState::markUsed($stateRecord['id'])) {
            self::redirectOrDebug($isDebugMode, self::frontendCallbackUrl('error', 'state_already_used'), 'state_already_used', [
                'state_id' => $stateRecord['id'],
            ]);
        }

        self::debugCallback($isDebugMode, 'state:marked_used', [
            'state_id' => $stateRecord['id'],
            'return_to' => $stateRecord['return_to'] ?? null,
        ]);

        try {
            self::debugCallback($isDebugMode, 'github:exchange_code:before', [
                'redirect_uri_configured' => (bool) self::githubConfigValue('redirect_uri'),
                'client_id_configured' => (bool) self::githubConfigValue('client_id'),
                'client_secret_configured' => (bool) self::githubConfigValue('client_secret'),
            ]);

            $publicClient = new GitHubClient();
            $tokenResponse = $publicClient->exchangeOAuthCode($code);

            self::debugCallback($isDebugMode, 'github:exchange_code:after', [
                'token_response' => self::safeTokenResponse($tokenResponse),
            ]);

            if (empty($tokenResponse['access_token'])) {
                self::redirectOrDebug($isDebugMode, self::frontendCallbackUrl('error', 'github_authorization_failed'), 'missing_access_token', [
                    'token_response' => self::safeTokenResponse($tokenResponse),
                ]);
            }

            $client = new GitHubClient($tokenResponse['access_token']);
            $githubUser = $client->getAuthenticatedUser();
            $email = $githubUser['email'] ?? null;

            self::debugCallback($isDebugMode, 'github:user:loaded', [
                'github_user' => self::safeGitHubUser($githubUser),
                'profile_email_present' => (bool) $email,
            ]);

            if (!$email) {
                self::debugCallback($isDebugMode, 'github:emails:before', [
                    'reason' => 'github profile email is empty',
                ]);

                $emails = $client->getAuthenticatedUserEmails();

                self::debugCallback($isDebugMode, 'github:emails:after', [
                    'emails' => self::safeEmailList($emails),
                ]);

                foreach ($emails as $candidate) {
                    if (!empty($candidate['primary']) && !empty($candidate['verified'])) {
                        $email = $candidate['email'];
                        break;
                    }
                }
            }

            $account = OAuthAccount::findByProviderAccount('github', (string) $githubUser['id']);
            self::debugCallback($isDebugMode, 'local_user:resolve:before', [
                'oauth_account_found' => (bool) $account,
                'resolved_email' => self::maskEmail($email),
            ]);

            if ($account) {
                $user = User::updateFromGitHub($account['user_id'], $githubUser, $email);
                $resolution = 'updated_by_oauth_account';
            } elseif ($email && ($existingUser = User::findByEmail($email))) {
                $user = User::updateFromGitHub($existingUser['id'], $githubUser, $email);
                $resolution = 'updated_by_existing_email';
            } else {
                $user = User::createFromGitHub($githubUser, $email);
                $resolution = 'created_from_github';
            }

            self::debugCallback($isDebugMode, 'local_user:resolve:after', [
                'resolution' => $resolution,
                'user_id' => $user['id'] ?? null,
                'user_login' => $user['login'] ?? null,
                'user_email' => self::maskEmail($user['email'] ?? null),
            ]);

            OAuthAccount::upsertGitHub($user['id'], $githubUser, $tokenResponse);
            self::debugCallback($isDebugMode, 'oauth_account:upserted', [
                'user_id' => $user['id'] ?? null,
                'provider' => 'github',
                'provider_account_id' => $githubUser['id'] ?? null,
                'provider_login' => $githubUser['login'] ?? null,
            ]);

            $session = Auth::createSession($user['id'], $request);
            self::debugCallback($isDebugMode, 'session:created', [
                'expires_at' => $session['expires_at'] ?? null,
                'expires_in' => $session['expires_in'] ?? null,
                'session_token_preview' => self::preview($session['token'] ?? null),
            ]);

            Auth::setSessionCookie($session['token']);
            self::debugCallback($isDebugMode, 'session:cookie_set', [
                'cookie_name' => Auth::cookieName(),
            ]);

            self::redirectOrDebug(
                $isDebugMode,
                self::frontendCallbackUrl('success', null, $stateRecord['return_to']),
                'success_redirect',
                ['return_to' => $stateRecord['return_to'] ?? null]
            );
        } catch (Throwable $e) {
            self::debugCallback($isDebugMode, 'exception', [
                'class' => get_class($e),
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            self::redirectOrDebug(
                $isDebugMode,
                self::frontendCallbackUrl('error', 'github_authorization_failed'),
                'exception_redirect'
            );
        }
    }

    public static function sanitizeReturnTo($returnTo)
    {
        if (!$returnTo || !is_string($returnTo)) {
            return '/matches';
        }

        $trimmed = trim($returnTo);
        $lower = strtolower($trimmed);
        $unsafePrefixes = ['http:', 'https:', '//', 'javascript:', 'data:'];

        foreach ($unsafePrefixes as $prefix) {
            if (strpos($lower, $prefix) === 0) {
                return '/matches';
            }
        }

        if (strpos($trimmed, '/') !== 0) {
            return '/matches';
        }

        return substr($trimmed, 0, 255);
    }

    private static function frontendCallbackUrl($status, $reason = null, $returnTo = null)
    {
        $frontendUrl = rtrim(self::env('FRONTEND_URL') ?: 'https://dotti.work', '/');
        $query = ['status' => $status];

        if ($reason) {
            $query['reason'] = $reason;
        }

        if ($returnTo) {
            $query['return_to'] = self::sanitizeReturnTo($returnTo);
        }

        return $frontendUrl . '/auth/callback?' . http_build_query($query);
    }

    private static function callbackDebugEnabled(Request $request)
    {
        return self::requestDebugEnabled($request) || self::stateCarriesDebugFlag($request->getQuery('state'));
    }

    private static function debugCallback($enabled, $step, array $data = [])
    {
        if (!$enabled) {
            return;
        }

        $entry = [
            'step' => $step,
            'data' => self::sanitizeDebugData($data),
        ];

        error_log('[GitHubOAuth callback] ' . json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        self::$callbackDebugLogs[] = $entry;
    }

    private static function redirectOrDebug($isDebugMode, $url, $step, array $data = [])
    {
        self::debugCallback($isDebugMode, $step, array_merge($data, ['redirect_to' => $url]));

        if ($isDebugMode) {
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            echo "GitHub OAuth callback debug\n";
            echo "Redirect que seria executado:\n";
            echo $url . "\n\n";
            echo "Logs:\n";
            print_r(self::$callbackDebugLogs);
            exit;
        }

        Response::redirect($url);
    }

    private static function requestDebugEnabled(Request $request)
    {
        $debug = $request->getQuery('debug');

        return self::truthy($debug) || filter_var(self::env('OAUTH_GITHUB_DEBUG_CALLBACK'), FILTER_VALIDATE_BOOLEAN);
    }

    private static function stateCarriesDebugFlag($state)
    {
        return is_string($state) && strpos($state, self::DEBUG_STATE_PREFIX) === 0;
    }

    private static function githubConfigValue($key)
    {
        $config = require __DIR__ . '/../config/github.php';
        return $config[$key] ?? null;
    }

    private static function safeStateRecord($stateRecord)
    {
        if (!$stateRecord) {
            return null;
        }

        return [
            'id' => $stateRecord['id'] ?? null,
            'return_to' => $stateRecord['return_to'] ?? null,
            'expires_at' => $stateRecord['expires_at'] ?? null,
            'used_at' => $stateRecord['used_at'] ?? null,
            'created_at' => $stateRecord['created_at'] ?? null,
            'user_agent_preview' => self::preview($stateRecord['user_agent'] ?? null, 24),
            'ip_hash_preview' => self::preview($stateRecord['ip_hash'] ?? null, 12),
            'state_hash_preview' => self::preview($stateRecord['state_hash'] ?? null, 12),
        ];
    }

    private static function safeTokenResponse(array $tokenResponse)
    {
        $safe = $tokenResponse;

        foreach (['access_token', 'refresh_token'] as $key) {
            if (isset($safe[$key])) {
                $safe[$key] = self::preview($safe[$key]);
            }
        }

        return $safe;
    }

    private static function safeGitHubUser(array $githubUser)
    {
        return [
            'id' => $githubUser['id'] ?? null,
            'login' => $githubUser['login'] ?? null,
            'name' => $githubUser['name'] ?? null,
            'email' => self::maskEmail($githubUser['email'] ?? null),
            'html_url' => $githubUser['html_url'] ?? null,
            'avatar_url_present' => !empty($githubUser['avatar_url']),
        ];
    }

    private static function safeEmailList(array $emails)
    {
        return array_map(function ($email) {
            return [
                'email' => self::maskEmail($email['email'] ?? null),
                'primary' => !empty($email['primary']),
                'verified' => !empty($email['verified']),
                'visibility' => $email['visibility'] ?? null,
            ];
        }, $emails);
    }

    private static function sanitizeDebugData($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitiveKeys = ['token', 'access_token', 'refresh_token', 'client_secret', 'code', 'state'];
        $sanitized = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, $sensitiveKeys, true)) {
                $sanitized[$key] = self::preview($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeDebugData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private static function preview($value, $visible = 8)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;
        return substr($value, 0, $visible) . '...len=' . strlen($value);
    }

    private static function maskEmail($email)
    {
        if (!$email || strpos($email, '@') === false) {
            return $email;
        }

        list($name, $domain) = explode('@', $email, 2);
        return substr($name, 0, 1) . '***@' . $domain;
    }

    private static function env($key)
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        $value = getenv($key);
        return $value === false ? null : $value;
    }

    private static function truthy($value)
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
