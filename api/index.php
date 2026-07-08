<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

if (!empty($_ENV['SENTRY_DSN'])) {
    \Sentry\init(['dsn' => $_ENV['SENTRY_DSN']]);
}

error_reporting(E_ALL);
ini_set('display_errors', ($_ENV['APP_ENV'] ?? 'local') === 'production' ? 0 : 1);

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/core/' . $class . '.php',
        __DIR__ . '/controller/' . $class . '.php',
        __DIR__ . '/model/' . $class . '.php',
        __DIR__ . '/service/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

configureCors();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

set_exception_handler(function ($exception) {
    if (($_ENV['APP_ENV'] ?? 'local') !== 'production') {
        error_log($exception->getMessage());
    }

    Response::error('Erro interno do servidor.', 500, 'INTERNAL_SERVER_ERROR');
});

$request = new Request();
$router = new Router();

registerRoutes($router);
$router->dispatch($request);

function registerRoutes(Router $router)
{
    $route = function ($method, $path, $callback) use ($router) {
        foreach (['', '/api/v1'] as $prefix) {
            $fullPath = $prefix . $path;
            $router->{$method}($fullPath, $callback);
        }
    };

    $route('get', '/', 'HealthController@health');
    $route('get', '/health', 'HealthController@health');
    $route('get', '/health/database', 'HealthController@database');
    $route('get', '/health/sentry', 'HealthController@sentry');

    $route('get', '/auth/github/start', 'AuthController@githubStart');
    $route('get', '/auth/github/callback', 'AuthController@githubCallback');
    $route('get', '/auth/me', 'AuthController@me');
    $route('post', '/auth/logout', 'AuthController@logout');
    $route('post', '/auth/logout-all', 'AuthController@logoutAll');
    $route('get', '/auth/session', 'AuthController@session');

    $route('get', '/integrations/github/status', 'AuthController@githubStatus');
    $route('post', '/integrations/github/sync', 'AuthController@githubSync');
    $route('delete', '/integrations/github', 'AuthController@githubDisconnect');

    $route('get', '/me/profile', 'ProfileController@show');
    $route('put', '/me/profile', 'ProfileController@update');
    $route('post', '/me/import-local-data', 'ProfileController@importLocalData');
    $route('get', '/me/export', 'ProfileController@export');
    $route('delete', '/me/account', 'AccountController@delete');
    $route('post', '/me/invite-links', 'InviteController@store');
    $route('get', '/me/invite-links', 'InviteController@index');
    $route('post', '/me/invite-links/:id/revoke', 'InviteController@revoke');
    $route('get', '/me/referrals', 'ReferralController@index');
    $route('get', '/invites/:code', 'InviteController@publicShow');

    $route('get', '/catalog/technologies', 'CatalogController@technologies');
    $route('get', '/catalog/technologies/:slug', 'CatalogController@technology');
    $route('get', '/me/technologies', 'TechnologyController@mine');
    $route('put', '/me/technologies', 'TechnologyController@replace');

    $route('get', '/me/preferences', 'PreferencesController@show');
    $route('put', '/me/preferences', 'PreferencesController@update');
    $route('get', '/me/consents', 'ConsentController@index');
    $route('post', '/me/consents', 'ConsentController@store');
    $route('delete', '/me/consents/:type', 'ConsentController@revoke');

    $route('get', '/badges', 'BadgeController@index');
    $route('get', '/me/badges', 'BadgeController@mine');
    $route('post', '/me/badges/evaluate', 'BadgeController@evaluate');

    $route('get', '/matches', 'MatchController@index');
    $route('post', '/matches/refresh', 'MatchController@refresh');
    $route('get', '/matches/:githubRepositoryId', 'MatchController@show');

    $route('get', '/repositories/:owner/:repo', 'RepositoryController@show');
    $route('get', '/repositories/:owner/:repo/issues', 'RepositoryController@issues');
    $route('post', '/repositories/:owner/:repo/activity', 'RepositoryController@activity');

    $route('get', '/me/repositories', 'UserRepositoryStateController@index');
    $route('put', '/me/repositories/:githubRepositoryId/state', 'UserRepositoryStateController@setState');
    $route('delete', '/me/repositories/:githubRepositoryId/state', 'UserRepositoryStateController@deleteState');
    $route('post', '/me/repositories/:githubRepositoryId/restore', 'UserRepositoryStateController@restore');

    $route('get', '/me/history', 'ActivityController@history');
    $route('delete', '/me/history', 'ActivityController@clearHistory');
}

function configureCors()
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $allowedOrigins = allowedCorsOrigins();

    if ($origin && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
}

function allowedCorsOrigins()
{
    return CorsPolicy::allowedOrigins();
}
