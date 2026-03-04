<?php

class TaskCategoryController
{
    public function findAllByUserId(Request $request)
    {
        $tokenInfo = AuthToken::findByToken($request->getBearerToken());
        $categories = TaskCategory::findAllByUserId($tokenInfo['user_id']);
        Response::json($categories, 200);
    }

    public function findByIdAndUserId(Request $request, $params)
    {
        $tokenInfo = AuthToken::findByToken($request->getBearerToken());
        $category = TaskCategory::findByIdAndUserId($params['id'], $tokenInfo['user_id']);
        if ($category) {
            Response::json($category, 200);
        } else {
            Response::error('Categoria não encontrada', 404);
        }
    }

    //todo: criar, atualizar e deletar categorias
}