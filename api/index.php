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

$router->get('/', function (Request $request) {
    Response::json([
        'message' => 'API online',
        'version' => '1.0.0',
    ]);
});

//auth routes
$router->post('/auth/login', 'AuthController@login');
$router->post('/auth/signup', 'AuthController@signup');
$router->post('/auth/logout', 'AuthController@logout');
$router->get('/auth/me', 'AuthController@me');

//tasks routes
$router->get('/tasks/category/:category_id', 'TasksController@listByCategory');
$router->get('/tasks/', 'TasksController@listAll');
$router->get('/tasks/:id', 'TasksController@listById');
$router->post('/tasks/create', 'TasksController@create');


$router->dispatch($request);