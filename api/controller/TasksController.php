<?php

class TasksController
{
    public function listAll(Request $request, $params)
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
        $tasks = Task::create($tokenInfo['user_id'], $request->getBody()['category_id'], $request->getBody()['title'], $request->getBody()['description'], $request->getBody()['is_completed'], $request->getBody()['priority'], $request->getBody()['display_order'], $request->getBody()['due_date']);
        Response::json($tasks, 200);
    }

}
