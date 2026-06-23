<?php

class AuthController extends BaseController
{
    public function githubStart(Request $request)
    {
        GitHubOAuth::start($request);
    }

    public function githubCallback(Request $request)
    {
        GitHubOAuth::callback($request);
    }

    public function me(Request $request)
    {
        $user = $this->requireToken($request);
        $profile = UserProfile::getComplete($user['id']);
        $githubAccount = OAuthAccount::findByUserAndProvider($user['id'], 'github');

        Response::success([
            'user' => User::toPublic($user),
            'profile' => [
                'role' => $profile['role'] ?? null,
                'seniority' => $profile['seniority'] ?? null,
                'onboarding_completed' => (bool) ($profile['onboarding_completed'] ?? false),
                'goals' => $profile['goals'] ?? [],
            ],
            'github' => OAuthAccount::toPublic($githubAccount),
        ]);
    }

    public function logout(Request $request)
    {
        $this->requireToken($request);
        Auth::revokeCurrentToken($request);
        Response::success(['logged_out' => true]);
    }

    public function logoutAll(Request $request)
    {
        $user = $this->requireToken($request);
        Auth::revokeAllUserTokens($user['id']);
        Auth::clearSessionCookie();
        Response::success(['revoked' => true]);
    }

    public function session(Request $request)
    {
        $user = $this->requireToken($request);
        Response::success([
            'authenticated' => true,
            'user' => User::toPublic($user),
        ]);
    }

    public function githubStatus(Request $request)
    {
        $user = $this->requireToken($request);
        $account = OAuthAccount::findByUserAndProvider($user['id'], 'github');
        Response::success(['github' => OAuthAccount::toPublic($account)]);
    }

    public function githubSync(Request $request)
    {
        $user = $this->requireToken($request);
        $account = OAuthAccount::findByUserAndProvider($user['id'], 'github');

        if (!$account) {
            Response::notFound('Conta GitHub nao vinculada.');
        }

        try {
            $client = new GitHubClient(OAuthAccount::decryptAccessToken($account));
            $githubUser = $client->getAuthenticatedUser();
            $updated = User::updateFromGitHub($user['id'], $githubUser, $user['email'] ?? null);
            Response::success(['user' => User::toPublic($updated)]);
        } catch (Exception $e) {
            Response::badGateway('Nao foi possivel sincronizar com o GitHub.');
        }
    }
}
