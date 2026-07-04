<?php

class GitHubDisconnectService
{
    private $findAccount;
    private $deleteAccount;
    private $decryptToken;
    private $revokeToken;

    public function __construct(
        callable $findAccount = null,
        callable $deleteAccount = null,
        callable $decryptToken = null,
        callable $revokeToken = null
    ) {
        $this->findAccount = $findAccount ?: ['OAuthAccount', 'findByUserAndProvider'];
        $this->deleteAccount = $deleteAccount ?: ['OAuthAccount', 'deleteByUserAndProvider'];
        $this->decryptToken = $decryptToken ?: ['OAuthAccount', 'decryptAccessToken'];
        $this->revokeToken = $revokeToken ?: function ($accessToken) {
            $client = new GitHubClient();
            return $client->revokeOAuthToken($accessToken);
        };
    }

    public function disconnect($userId, $provider = 'github')
    {
        $account = call_user_func($this->findAccount, $userId, $provider);

        if (!$account) {
            return [
                'found' => false,
                'data' => null,
            ];
        }

        $this->tryRevokeRemoteToken($account);
        call_user_func($this->deleteAccount, $userId, $provider);

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
}
