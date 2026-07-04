<?php

class AccountDeletionService
{
    private $revokeAllTokens;
    private $softDeleteUser;
    private $clearSessionCookie;

    public function __construct(callable $revokeAllTokens = null, callable $softDeleteUser = null, callable $clearSessionCookie = null)
    {
        $this->revokeAllTokens = $revokeAllTokens ?: ['Auth', 'revokeAllUserTokens'];
        $this->softDeleteUser = $softDeleteUser ?: ['User', 'softDelete'];
        $this->clearSessionCookie = $clearSessionCookie ?: ['Auth', 'clearSessionCookie'];
    }

    public function delete($userId)
    {
        call_user_func($this->revokeAllTokens, $userId);
        call_user_func($this->softDeleteUser, $userId);
        call_user_func($this->clearSessionCookie);

        return ['deleted' => true];
    }
}
