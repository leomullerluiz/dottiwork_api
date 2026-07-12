<?php

class PublicProfileController extends BaseController
{
    public function show(Request $request, $params)
    {
        RateLimiter::enforce($request, 'public.profile.show', 60, 60);

        $login = $params['login'] ?? ($params[0] ?? '');
        $service = new PublicUserProfileService();
        $user = $service->findByLogin($login);

        if (!$user) {
            Response::notFound('Public profile not found.');
        }

        Response::success($service->buildForUser($user));
    }

    public function preview(Request $request)
    {
        $user = $this->requireToken($request);
        $service = new PublicUserProfileService();

        Response::success($service->previewForUser($user));
    }

    public function updateSettings(Request $request)
    {
        $user = $this->requireToken($request);
        RateLimiter::enforce($request, 'public.profile.settings', 20, 300, 'user:' . $user['id']);

        $body = $this->jsonBody($request);
        $errors = [];

        if (!array_key_exists('is_public', $body) || !Validator::boolean($body['is_public'])) {
            $errors[] = [
                'field' => 'is_public',
                'message' => 'is_public must be boolean.',
            ];
        }

        $slugProvided = array_key_exists('public_profile_slug', $body) || array_key_exists('slug', $body);
        $slug = $body['public_profile_slug'] ?? ($body['slug'] ?? null);

        if ($slugProvided && (!is_string($slug) || !PublicUserProfileService::normalizeSlug($slug))) {
            $errors[] = [
                'field' => 'public_profile_slug',
                'message' => 'public_profile_slug may contain only letters, numbers, and hyphens, up to 120 characters.',
            ];
        }

        if ($errors) {
            Response::validationError($errors);
        }

        $enabled = filter_var($body['is_public'], FILTER_VALIDATE_BOOLEAN);
        $service = new PublicUserProfileService();

        try {
            Response::success($service->updateSettings($user, $enabled, $slug, $slugProvided));
        } catch (InvalidArgumentException $exception) {
            Response::validationError([
                [
                    'field' => 'public_profile_slug',
                    'message' => $this->slugErrorMessage($exception->getMessage()),
                ],
            ]);
        }
    }

    private function slugErrorMessage($code)
    {
        $messages = [
            'public_profile_slug_invalid' => 'public_profile_slug may contain only letters, numbers, and hyphens, up to 120 characters.',
            'public_profile_slug_required' => 'Could not generate a public slug for this user.',
            'public_profile_slug_unavailable' => 'public_profile_slug is already in use.',
        ];

        return $messages[$code] ?? 'Invalid public_profile_slug.';
    }
}
