<?php

class GitHubDisconnectService
{
    private $findAccount;
    private $deleteAccount;
    private $decryptToken;
    private $revokeToken;
    private $sendDisconnectEmail;

    public function __construct(
        callable $findAccount = null,
        callable $deleteAccount = null,
        callable $decryptToken = null,
        callable $revokeToken = null,
        callable $sendDisconnectEmail = null
    ) {
        $this->findAccount = $findAccount ?: ['OAuthAccount', 'findByUserAndProvider'];
        $this->deleteAccount = $deleteAccount ?: ['OAuthAccount', 'deleteByUserAndProvider'];
        $this->decryptToken = $decryptToken ?: ['OAuthAccount', 'decryptAccessToken'];
        $this->revokeToken = $revokeToken ?: function ($accessToken) {
            $client = new GitHubClient();
            return $client->revokeOAuthToken($accessToken);
        };
        $this->sendDisconnectEmail = $sendDisconnectEmail ?: function (array $user, array $account) {
            return (new GitHubIntegrationEmailService())->sendDisconnectAlert($user, $account);
        };
    }

    public function disconnect($user, $provider = 'github')
    {
        $userId = is_array($user) ? ($user['id'] ?? null) : $user;
        $account = call_user_func($this->findAccount, $userId, $provider);

        if (!$account) {
            return [
                'found' => false,
                'data' => null,
            ];
        }

        $this->tryRevokeRemoteToken($account);
        call_user_func($this->deleteAccount, $userId, $provider);
        $this->trySendDisconnectEmail($user, $account);

        return [
            'found' => true,
            'data' => self::disconnectedPayload(),
        ];
    }

    public static function disconnectedPayload()
    {
        return [
            'connected' => false,
        ];
    }

    private function tryRevokeRemoteToken(array $account)
    {
        try {
            $accessToken = call_user_func($this->decryptToken, $account);

            if ($accessToken) {
                call_user_func($this->revokeToken, $accessToken);
            }
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }

    private function trySendDisconnectEmail($user, array $account)
    {
        if (!is_array($user)) {
            return false;
        }

        try {
            call_user_func($this->sendDisconnectEmail, $user, $account);
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }
}
