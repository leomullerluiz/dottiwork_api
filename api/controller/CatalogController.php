<?php

class CatalogController extends BaseController
{
    public function technologies(Request $request)
    {
        $items = Technology::list([
            'category' => $request->getQuery('category'),
            'search' => $request->getQuery('search'),
            'active' => $request->getQuery('active', '1'),
            'limit' => $request->getQuery('limit', 100),
            'cursor' => $request->getQuery('cursor'),
        ]);

        $nextCursor = count($items) > 0 ? end($items)['id'] : null;
        Response::success([
            'items' => $items,
            'pagination' => ['next_cursor' => $nextCursor],
        ]);
    }

    public function technology(Request $request, $params)
    {
        $technology = Technology::findBySlug($params['slug']);
        if (!$technology || !$technology['is_active']) {
            Response::notFound('Tecnologia nao encontrada.');
        }

        Response::success(['technology' => $technology]);
    }
}
