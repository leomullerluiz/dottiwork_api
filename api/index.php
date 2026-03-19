<?php

require __DIR__ . '/../vendor/autoload.php';

\Sentry\init([
    'dsn' => 'https://7356870e3b9efc8546edb728d150e94e@o4510988945391616.ingest.us.sentry.io/4510988947161088',
]);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

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

$config = require __DIR__ . '/config/database.php';

$router->get('/db_connection_test', function () use ($config) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query('SELECT 1');
        $stmt->fetch();
        Response::json(['message' => 'CONEXAO EFETUADA']);
    } catch (Exception $e) {
        Response::error('Error conn:' . $e->getMessage(), 500);
    }
});

//auth routes
//todo: criar um endpoint para oAuth do google
$router->post('/auth/login', 'AuthController@login');
$router->post('/auth/signup', 'AuthController@signup');
$router->post('/auth/logout', 'AuthController@logout');
$router->get('/auth/me', 'AuthController@me');

// password reset routes
$router->post('/auth/password_reset', 'AuthController@requestPasswordReset');
$router->get('/auth/password_reset/:resetToken/:email', 'AuthController@validateResetToken');
$router->post('/auth/password_reset/credentials', 'AuthController@resetPassword');


//tasks routes
$router->get('/task/filter/category/:category_id', 'TasksController@listByCategory');
$router->get('/task/filter', 'TasksController@filter');
$router->get('/task/:id', 'TasksController@listById');
$router->get('/task/list', 'TasksController@listAll');
$router->post('/task/create', 'TasksController@create');
$router->post('/task/update', 'TasksController@update');
$router->delete('/task/delete', 'TasksController@delete');

//task category routes
$router->get('/task/category/', 'TaskCategoryController@findAllByUserId');
$router->get('/task/category/:id', 'TaskCategoryController@findByIdAndUserId');
$router->post('/task/category/create', 'TaskCategoryController@create');
$router->post('/task/category/update', 'TaskCategoryController@update');
$router->post('/task/category/delete', 'TaskCategoryController@delete');
$router->dispatch($request);