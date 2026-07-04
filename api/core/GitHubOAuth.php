<?php

class GitHubOAuth
{
    public static function start(Request $request)
    {
        $returnTo = self::sanitizeReturnTo($request->getQuery('return_to', '/matches'));
        $state = Crypto::randomBase64Url(32);

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
        $code = $request->getQuery('code');
        $state = $request->getQuery('state');

        if (!$code || !$state) {
            Response::redirect(self::frontendCallbackUrl('error', 'github_authorization_failed'));
        }

        $stateRecord = OAuthAuthorizationState::findByStateHash(Crypto::hashState($state));
        $stateIsValid = $stateRecord ? OAuthAuthorizationState::isValid($state) : false;

        if (!$stateRecord || !$stateIsValid) {
            Response::redirect(self::frontendCallbackUrl('error', 'invalid_state'));
        }

        if (!OAuthAuthorizationState::markUsed($stateRecord['id'])) {
            Response::redirect(self::frontendCallbackUrl('error', 'state_already_used'));
        }

        try {
            $publicClient = new GitHubClient();
            $tokenResponse = $publicClient->exchangeOAuthCode($code);

            if (empty($tokenResponse['access_token'])) {
                Response::redirect(self::frontendCallbackUrl('error', 'github_authorization_failed'));
            }

            $client = new GitHubClient($tokenResponse['access_token']);
            $githubUser = $client->getAuthenticatedUser();
            $email = $githubUser['email'] ?? null;

            if (!$email) {
                $emails = $client->getAuthenticatedUserEmails();

                foreach ($emails as $candidate) {
                    if (!empty($candidate['primary']) && !empty($candidate['verified'])) {
                        $email = $candidate['email'];
                        break;
                    }
                }
            }

            $account = OAuthAccount::findByProviderAccount('github', (string) $githubUser['id']);

            if ($account) {
                $user = User::updateFromGitHub($account['user_id'], $githubUser, $email);
            } elseif ($email && ($existingUser = User::findByEmail($email))) {
                $user = User::updateFromGitHub($existingUser['id'], $githubUser, $email);
            } else {
                $user = User::createFromGitHub($githubUser, $email);
            }

            OAuthAccount::upsertGitHub($user['id'], $githubUser, $tokenResponse);

            $session = Auth::createSession($user['id'], $request);

            Auth::setSessionCookie($session['token']);

            Response::redirect(self::frontendCallbackUrl('success', null, $stateRecord['return_to']));
        } catch (Throwable $e) {
            Response::redirect(self::frontendCallbackUrl('error', 'github_authorization_failed'));
        }
    }

    public static function sanitizeReturnTo($returnTo)
    {
        if (!$returnTo || !is_string($returnTo)) {
            return '/matches';
        }

        $trimmed = trim($returnTo);
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $trimmed)) {
            return '/matches';
        }

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

}
