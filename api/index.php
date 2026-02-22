<?php
/**
 * Ponto de Entrada da API
 */

// Configurações de erro (desabilitar em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Headers CORS (ajustar em produção)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responde OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autoload manual das classes
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

// Captura erros não tratados
set_exception_handler(function ($exception) {
    Response::error('Erro interno do servidor: ' . $exception->getMessage(), 500);
});

// Inicializa objetos principais
$request = new Request();
$router = new Router();

// ========================================
// ROTAS DA API
// ========================================

// Rota de teste
$router->get('/', function (Request $request) {
    Response::json([
        'message' => 'API online',
        'version' => '1.0.0',
    ]);
});

// Rotas de autenticação
$router->post('/auth/login', 'AuthController@login');
$router->post('/auth/signup', 'AuthController@signup');
$router->post('/auth/logout', 'AuthController@logout');
$router->get('/auth/me', 'AuthController@me');


// Exemplo de rota com parâmetro
$router->get('/users/:id', function (Request $request, $params) {
    $user = Auth::requireAuth($request);

    $userId = $params['id'];
    $targetUser = User::findById($userId);

    if (!$targetUser) {
        Response::notFound('Usuário não encontrado');
    }

    Response::json([
        'user' => User::toPublic($targetUser)
    ]);
});

// Processa a requisição
$router->dispatch($request);