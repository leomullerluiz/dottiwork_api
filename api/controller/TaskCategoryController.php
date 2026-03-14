<?php

class TaskCategoryController extends BaseController
{
    public function findAllByUserId(Request $request)
    {
        $user = $this->requireToken($request);
        $categories = TaskCategory::findAllByUserId($user['id']);
        Response::json($categories, 200);
    }

    public function findByIdAndUserId(Request $request, $params)
    {
        $user = $this->requireToken($request);
        $category = TaskCategory::findByIdAndUserId($params['id'], $user['id']);
        if ($category) {
            Response::json($category, 200);
        } else {
            Response::error('Categoria não encontrada', 404);
        }
    }

    public function create(Request $request)
    {
        $user = $this->requireToken($request);
        $body = $request->getBody();
        $category = TaskCategory::create($user['id'], $body['name'], $body['color'], 1, $body['icon'] ?? null);
        Response::json($category, 200);
    }

    public function update(Request $request, $id)
    {
        $user = $this->requireToken($request);
        $body = $request->getBody();
        $category = TaskCategory::update($id, $body['name'], $body['color'], $body['display_order'], $user['id']);
        if ($category) {
            Response::json($category, 200);
        } else {
            Response::error('Categoria não encontrada ou erro ao atualizar', 404);
        }
    }

    public function delete(Request $request)
    {
        $user = $this->requireToken($request);
        $body = $request->getBody();

        $deleted = TaskCategory::delete($body['id'], userId: $user['id']);
        if ($deleted) {
            Response::json(['message' => 'Categoria deletada com sucesso'], 200);
        } else {
            Response::error('Categoria não encontrada ou erro ao deletar', 404);
        }
    }

    public function test(Request $request)
    {
        Response::Json(['body' => $request->getBody()], 200);
    }


}