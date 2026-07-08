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
            Response::notFound('Perfil publico nao encontrado.');
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
                'message' => 'is_public deve ser booleano.',
            ];
        }

        $slugProvided = array_key_exists('public_profile_slug', $body) || array_key_exists('slug', $body);
        $slug = $body['public_profile_slug'] ?? ($body['slug'] ?? null);

        if ($slugProvided && (!is_string($slug) || !PublicUserProfileService::normalizeSlug($slug))) {
            $errors[] = [
                'field' => 'public_profile_slug',
                'message' => 'public_profile_slug deve conter apenas letras, numeros e hifens, com ate 120 caracteres.',
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
            'public_profile_slug_invalid' => 'public_profile_slug deve conter apenas letras, numeros e hifens, com ate 120 caracteres.',
            'public_profile_slug_required' => 'Nao foi possivel gerar um slug publico para este usuario.',
            'public_profile_slug_unavailable' => 'public_profile_slug ja esta em uso.',
        ];

        return $messages[$code] ?? 'public_profile_slug invalido.';
    }
}
