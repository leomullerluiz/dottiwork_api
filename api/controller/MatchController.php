<?php

class MatchController extends BaseController
{
    public function index(Request $request)
    {
        $user = $this->requireToken($request);
        $service = new MatchService();
        $filters = MatchFilterService::fromRequest($request);
        $errors = MatchFilterService::validate($filters);
        if ($errors) {
            Response::validationError($errors);
        }

        $items = $service->listMatches($user['id'], [
            'state' => $request->getQuery('state'),
            'minimum_score' => $request->getQuery('minimum_score'),
            'sort_by' => $request->getQuery('sort_by', 'score'),
            'limit' => 100,
        ]);
        $page = MatchFilterService::paginate($items, $filters);

        Response::success([
            'items' => $page['items'],
            'next_cursor' => $page['next_cursor'],
            'pagination' => [
                'next_cursor' => $page['next_cursor'],
            ],
            'metadata' => [
                'cached' => true,
                'sort_by' => $filters['sort_by'] ?: 'score',
                'default_sort_by' => 'score',
                'total_filtered' => $page['total_filtered'],
            ],
        ]);
    }

    public function refresh(Request $request)
    {
        $user = $this->requireToken($request);
        $service = new MatchService();

        try {
            $result = $service->refresh($user);
            if (!$result['refreshed']) {
                Response::tooManyRequests('Atualizacao de matches em cooldown.');
            }
            Response::success($result);
        } catch (Exception $e) {
            $status = $e->getCode();
            if ($status === 403 || $status === 429) {
                Response::tooManyRequests('Limite da API do GitHub atingido. Tente novamente mais tarde.');
            }
            Response::badGateway('Nao foi possivel atualizar matches pelo GitHub.');
        }
    }

    public function show(Request $request, $params)
    {
        $user = $this->requireToken($request);
        $service = new MatchService();
        $match = $service->getMatch($user['id'], (int) $params['githubRepositoryId']);

        if (!$match) {
            Response::notFound('Match nao encontrado.');
        }

        Response::success($match);
    }
}
