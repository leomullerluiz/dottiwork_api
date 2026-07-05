<?php

class AccountDeletionService
{
    private $revokeAllTokens;
    private $softDeleteUser;
    private $clearSessionCookie;
    private $sendDeletionEmail;

    public function __construct(
        callable $revokeAllTokens = null,
        callable $softDeleteUser = null,
        callable $clearSessionCookie = null,
        callable $sendDeletionEmail = null
    )
    {
        $this->revokeAllTokens = $revokeAllTokens ?: ['Auth', 'revokeAllUserTokens'];
        $this->softDeleteUser = $softDeleteUser ?: ['User', 'softDelete'];
        $this->clearSessionCookie = $clearSessionCookie ?: ['Auth', 'clearSessionCookie'];
        $this->sendDeletionEmail = $sendDeletionEmail ?: function (array $user) {
            return (new AccountEmailService())->sendDeletionConfirmation($user);
        };
    }

    public function delete($user)
    {
        $userId = is_array($user) ? ($user['id'] ?? null) : $user;

        call_user_func($this->revokeAllTokens, $userId);
        call_user_func($this->softDeleteUser, $userId);
        call_user_func($this->clearSessionCookie);

        if (is_array($user)) {
            call_user_func($this->sendDeletionEmail, $user);
        }

        return ['deleted' => true];
    }
}
