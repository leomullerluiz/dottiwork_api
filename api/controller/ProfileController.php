<?php

class ProfileController extends BaseController
{
    private $goalValues = [
        'first_contribution',
        'build_portfolio',
        'practical_experience',
        'join_communities',
        'long_term_projects',
    ];

    public function show(Request $request)
    {
        $user = $this->requireToken($request);
        Response::success([
            'user' => User::toPublic($user),
            'profile' => UserProfile::getComplete($user['id']),
        ]);
    }

    public function update(Request $request)
    {
        $user = $this->requireToken($request);
        $body = $this->jsonBody($request);
        $goals = isset($body['goals']) && is_array($body['goals']) ? array_values(array_unique($body['goals'])) : [];

        $errors = Validator::collectErrors([
            'display_name' => [
                'display_name deve ser texto com ate 150 caracteres.' => !isset($body['display_name']) || Validator::maxLength($body['display_name'], 150),
            ],
            'role' => [
                'role deve ter ate 100 caracteres.' => !isset($body['role']) || Validator::maxLength($body['role'], 100),
            ],
            'seniority' => [
                'seniority deve ser junior, mid ou senior.' => !isset($body['seniority']) || $body['seniority'] === null || Validator::enum($body['seniority'], ['junior', 'mid', 'senior']),
            ],
            'goals' => [
                'goals possui valores invalidos.' => Validator::arrayOfEnum($goals, $this->goalValues),
            ],
            'onboarding_completed' => [
                'onboarding_completed deve ser booleano.' => !isset($body['onboarding_completed']) || Validator::boolean($body['onboarding_completed']),
            ],
        ]);

        if ($errors) {
            Response::validationError($errors);
        }

        if (isset($body['display_name'])) {
            User::updateDisplayName($user['id'], $body['display_name']);
        }

        $profile = UserProfile::upsertWithGoals(
            $user['id'],
            $body['role'] ?? null,
            $body['seniority'] ?? null,
            $goals,
            !empty($body['onboarding_completed'])
        );

        Response::success([
            'user' => User::toPublic(User::findById($user['id'])),
            'profile' => $profile,
        ]);
    }

    public function importLocalData(Request $request)
    {
        $user = $this->requireToken($request);
        $body = $this->jsonBody($request);

        foreach (['profile', 'technologies', 'preferences', 'repository_states', 'history'] as $key) {
            if (isset($body[$key]) && !is_array($body[$key])) {
                Response::validationError([['field' => $key, 'message' => 'Campo deve ser objeto ou lista.']]);
            }
        }

        $service = new UserProfileService();
        Response::success($service->importLocalData($user['id'], $body));
    }

    public function export(Request $request)
    {
        $user = $this->requireToken($request);
        $service = new UserProfileService();
        Response::success($service->export($user['id']));
    }
}
