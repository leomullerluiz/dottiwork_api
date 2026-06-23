<?php

class PreferencesController extends BaseController
{
    private $allowed = [
        'contribution_types' => ['bug_fix', 'feature', 'documentation', 'tests', 'performance', 'refactor', 'accessibility', 'translation'],
        'difficulty_levels' => ['beginner', 'intermediate', 'advanced'],
        'project_sizes' => ['small', 'medium', 'large'],
        'documentation_languages' => ['en', 'pt', 'es', 'any'],
        'organization_types' => ['independent', 'startup', 'company', 'community', 'foundation', 'any'],
        'default_sort_by' => ['best_match', 'most_active', 'most_stars', 'beginner_friendly', 'recently_updated'],
    ];

    public function show(Request $request)
    {
        $user = $this->requireToken($request);
        Response::success(['preferences' => UserPreference::findByUserId($user['id'])]);
    }

    public function update(Request $request)
    {
        $user = $this->requireToken($request);
        $body = $this->jsonBody($request);
        $errors = [];

        foreach (['contribution_types', 'difficulty_levels', 'project_sizes', 'documentation_languages', 'organization_types'] as $field) {
            if (!isset($body[$field]) || !is_array($body[$field])) {
                $errors[] = ['field' => $field, 'message' => 'Campo obrigatorio e deve ser lista.'];
                continue;
            }
            if (count($body[$field]) > 20 || !Validator::arrayOfEnum($body[$field], $this->allowed[$field])) {
                $errors[] = ['field' => $field, 'message' => 'Lista contem valores invalidos.'];
            }
        }

        if (isset($body['activity_window_days']) && ((int) $body['activity_window_days'] < 1 || (int) $body['activity_window_days'] > 3650)) {
            $errors[] = ['field' => 'activity_window_days', 'message' => 'Deve estar entre 1 e 3650.'];
        }

        if (isset($body['minimum_stars']) && (int) $body['minimum_stars'] < 0) {
            $errors[] = ['field' => 'minimum_stars', 'message' => 'Nao pode ser negativo.'];
        }

        if (isset($body['default_sort_by']) && !Validator::enum($body['default_sort_by'], $this->allowed['default_sort_by'])) {
            $errors[] = ['field' => 'default_sort_by', 'message' => 'Ordenacao invalida.'];
        }

        if ($errors) {
            Response::validationError($errors);
        }

        Response::success(['preferences' => UserPreference::upsert($user['id'], $body)]);
    }
}
