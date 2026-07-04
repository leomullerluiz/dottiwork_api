<?php

class AccountController extends BaseController
{
    public function delete(Request $request)
    {
        $user = $this->requireToken($request);
        RateLimiter::enforce($request, 'account.delete', 3, 3600, 'user:' . $user['id']);
        Auth::revokeAllUserTokens($user['id']);
        User::softDelete($user['id']);
        Auth::clearSessionCookie();
        Response::success(['deleted' => true]);
    }
}
