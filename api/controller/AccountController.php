<?php

class AccountController extends BaseController
{
    public function delete(Request $request)
    {
        $user = $this->requireToken($request);
        Auth::revokeAllUserTokens($user['id']);
        User::softDelete($user['id']);
        Auth::clearSessionCookie();
        Response::success(['deleted' => true]);
    }
}
