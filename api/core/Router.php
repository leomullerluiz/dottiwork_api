<?php
/**
 * Roteador simples para a API
 */
class Router {
    private $routes = [];

    /**
     * Registra uma rota POST
     */
    public function post($uri, $callback) {
        $this->addRoute('POST', $uri, $callback);
    }

    /**
     * Registra uma rota GET
     */
    public function get($uri, $callback) {
        $this->addRoute('GET', $uri, $callback);
    }

    /**
     * Registra uma rota PUT
     */
    public function put($uri, $callback) {
        $this->addRoute('PUT', $uri, $callback);
    }

    /**
     * Registra uma rota DELETE
     */
    public function delete($uri, $callback) {
        $this->addRoute('DELETE', $uri, $callback);
    }

    /**
     * Adiciona rota ao array
     */
    private function addRoute($method, $uri, $callback) {
        $uri = '/' . trim($uri, '/');
        $this->routes[$method][$uri] = $callback;
    }

    /**
     * Processa a requisição e executa a rota correspondente
     */
    public function dispatch(Request $request) {
        $method = $request->getMethod();
        $uri = $request->getUri();

        // Verifica se existe rota exata
        if (isset($this->routes[$method][$uri])) {
            return $this->executeCallback($this->routes[$method][$uri], $request);
        }

        // Verifica rotas com parâmetros
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route => $callback) {
                $pattern = $this->convertToRegex($route);
                if (preg_match($pattern, $uri, $matches)) {
                    array_shift($matches); // Remove o primeiro elemento (match completo)
                    return $this->executeCallback($callback, $request, $matches);
                }
            }
        }

        Response::notFound('Endpoint não encontrado');
    }

    /**
     * Converte rota para regex (suporta :param)
     */
    private function convertToRegex($route) {
        $route = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $route);
        return '#^' . $route . '$#';
    }

    /**
     * Executa o callback da rota
     */
    private function executeCallback($callback, $request, $params = []) {
        if (is_callable($callback)) {
            return call_user_func_array($callback, [$request, $params]);
        }

        // Suporte para Controller@method
        if (is_string($callback) && strpos($callback, '@') !== false) {
            list($controller, $method) = explode('@', $callback);
            
            if (class_exists($controller) && method_exists($controller, $method)) {
                $instance = new $controller();
                return call_user_func_array([$instance, $method], [$request, $params]);
            }
        }

        Response::error('Callback inválido', 500);
    }
}