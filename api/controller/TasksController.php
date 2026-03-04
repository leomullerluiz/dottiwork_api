<?php

class TasksController
{
    public function listAll(Request $request)
    {
        $tokenInfo = AuthToken::findByToken($request->getBearerToken());
        $tasks = Task::findAllByUserId($tokenInfo['user_id']);
        Response::json($tasks, 200);
    }
    public function listByCategory(Request $request, $params)
    {
        $tokenInfo = AuthToken::findByToken($request->getBearerToken());
        $tasks = Task::findByCategorieIdAndUserId($params['category_id'], $tokenInfo['user_id']);
        Response::json($tasks, 200);
    }
    public function listById(Request $request, $params)
    {
        $tokenInfo = AuthToken::findByToken($request->getBearerToken());
        $tasks = Task::findByIdAndUserId($params['id'], $tokenInfo['user_id']);
        Response::json($tasks, 200);
    }

    public function create(Request $request)
    {
        $tokenInfo = AuthToken::findByToken($request->getBearerToken());
        $task = Task::create($tokenInfo['user_id'], $request->getBody()['category_id'], $request->getBody()['title'], $request->getBody()['description'], $request->getBody()['is_completed'], $request->getBody()['priority'], $request->getBody()['display_order'], $request->getBody()['due_date']);

        if ($task) {
            Response::json([
                'message' => 'Tarefa criada com sucesso',
                'task' => $task
            ], 201);
        } else {
            Response::error('Erro ao criar tarefa', 400);
        }
    }

    public function update(Request $request)
    {
        $tokenInfo = AuthToken::findByToken($request->getBearerToken());
        $task = Task::update($request->getBody()['task_id'], $tokenInfo['user_id'], $request->getBody()['category_id'], $request->getBody()['title'], $request->getBody()['description'], $request->getBody()['is_completed'], $request->getBody()['priority'], $request->getBody()['display_order'], $request->getBody()['due_date']);

        if ($task) {
            Response::json([
                'message' => 'Tarefa atualizada com sucesso',
                'task' => $task
            ], 200);
        } else {
            Response::error('Erro ao atualizar tarefa', 400);
        }
    }

    public function delete(Request $request)
    {
        $tokenInfo = AuthToken::findByToken($request->getBearerToken());
        $deleted = Task::delete($request->getBody()['task_id'], $tokenInfo['user_id']);
        if ($deleted) {
            Response::json([
                'message' => 'Tarefa excluída com sucesso'
            ], 200);
        } else {
            Response::error('Erro ao excluir tarefa', 400);
        }
    }

}
