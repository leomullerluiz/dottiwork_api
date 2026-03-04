<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);


header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/core/' . $class . '.php',
        __DIR__ . '/controller/' . $class . '.php',
        __DIR__ . '/model/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

set_exception_handler(function ($exception) {
    Response::error('Erro interno do servidor: ' . $exception->getMessage(), 500);
});


$request = new Request();
$router = new Router();

$router->get('/', function () {
    Response::json([
        'message' => 'API online',
        'version' => '1.0.0',
    ]);
});

//auth routes
//todo: criar um endpoint para oAuth do google
$router->post('/auth/login', 'AuthController@login');
$router->post('/auth/signup', 'AuthController@signup');
$router->post('/auth/logout', 'AuthController@logout');
$router->get('/auth/me', 'AuthController@me');

//tasks routes
//todo: criar um endpoint para filtrar por data de vencimento, prioridade, status (concluída ou não concluída), categoria e campo de texto livre
$router->get('/task/filter_by_category/:category_id', 'TasksController@listByCategory');
$router->get('/task/:id', 'TasksController@listById');
$router->get('/task/list', 'TasksController@listAll');
$router->post('/task/create', 'TasksController@create');
$router->post('/task/update', 'TasksController@update');
$router->delete('/task/delete', 'TasksController@delete');

//task category routes
//todo: criar, atualizar e deletar categorias
$router->get('/task/category/', 'TaskCategoryController@findAllByUserId');
$router->get('/task/category/:id', 'TaskCategoryController@findByIdAndUserId');




$router->dispatch($request);