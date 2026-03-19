<?php

class TasksController extends BaseController
{
    public function listAll(Request $request)
    {
        $user = $this->requireToken($request);
        $tasks = Task::findAllByUserId($user['id']);
        Response::json($tasks, 200);
    }
    public function listByCategory(Request $request, $params)
    {
        $user = $this->requireToken($request);
        $tasks = Task::findByCategorieIdAndUserId($params['category_id'], $user['id']);
        Response::json($tasks, 200);
    }
    public function listById(Request $request, $params)
    {
        $user = $this->requireToken($request);
        $tasks = Task::findByIdAndUserId($params['id'], $user['id']);
        Response::json($tasks, 200);
    }

    public function create(Request $request)
    {
        $user = $this->requireToken($request);
        $body = $request->getBody();
        $task = Task::create($user['id'], $body['category_id'], $body['title'], $body['description'], $body['is_completed'], $body['priority'], $body['display_order'], $body['due_date']);

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
        $user = $this->requireToken($request);
        $body = $request->getBody();
        $task = Task::update($body['task_id'], $user['id'], $body['category_id'], $body['title'], $body['description'], $body['is_completed'], $body['priority'], $body['display_order'], $body['due_date']);

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
        $user = $this->requireToken($request);
        $body = $request->getBody();
        $deleted = Task::delete($body['task_id'], $user['id']);
        if ($deleted) {
            Response::json([
                'message' => 'Tarefa excluída com sucesso'
            ], 200);
        } else {
            Response::error('Erro ao excluir tarefa', 400);
        }
    }

    public function filter(Request $request)
    {
        //todo: escrever testes unitarios
        $user = $this->requireToken($request);

        $allowed = ['category_id', 'priority', 'is_completed', 'due_date', 'search'];
        $filters = [];
        foreach ($allowed as $key) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $filters[$key] = $_GET[$key];
            }
        }

        $tasks = Task::filter($user['id'], $filters);
        Response::json(['tasks' => $tasks], 200);
    }

}
