<?php
/**
 * Simple API router.
 */
class Router {
    private $routes = [];

    /**
     * Registers a POST route.
     */
    public function post($uri, $callback) {
        $this->addRoute('POST', $uri, $callback);
    }

    /**
     * Registers a GET route.
     */
    public function get($uri, $callback) {
        $this->addRoute('GET', $uri, $callback);
    }

    /**
     * Registers a PUT route.
     */
    public function put($uri, $callback) {
        $this->addRoute('PUT', $uri, $callback);
    }

    /**
     * Registers a DELETE route.
     */
    public function delete($uri, $callback) {
        $this->addRoute('DELETE', $uri, $callback);
    }

    /**
     * Registers a PATCH route.
     */
    public function patch($uri, $callback) {
        $this->addRoute('PATCH', $uri, $callback);
    }

    /**
     * Adds a route to the internal map.
     */
    private function addRoute($method, $uri, $callback) {
        $uri = '/' . trim($uri, '/');
        $this->routes[$method][$uri] = $callback;
    }

    /**
     * Processes the request and executes the matching route.
     */
    public function dispatch(Request $request) {
        $method = $request->getMethod();
        $uri = $request->getUri();

        // Checks for an exact route.
        if (isset($this->routes[$method][$uri])) {
            return $this->executeCallback($this->routes[$method][$uri], $request);
        }

        // Checks routes with parameters.
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route => $callback) {
                $pattern = $this->convertToRegex($route);
                if (preg_match($pattern, $uri, $matches)) {
                    array_shift($matches); // Removes the full-match element.
                    return $this->executeCallback($callback, $request, $matches);
                }
            }
        }

        Response::notFound('Endpoint not found.');
    }

    /**
     * Converts a route to regex syntax and supports :param segments.
     */
    private function convertToRegex($route) {
        $route = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $route);
        return '#^' . $route . '$#';
    }

    /**
     * Executes the route callback.
     */
    private function executeCallback($callback, $request, $params = []) {
        if (is_callable($callback)) {
            return call_user_func_array($callback, [$request, $params]);
        }

        // Supports Controller@method.
        if (is_string($callback) && strpos($callback, '@') !== false) {
            list($controller, $method) = explode('@', $callback);

            if (class_exists($controller) && method_exists($controller, $method)) {
                $instance = new $controller();
                return call_user_func_array([$instance, $method], [$request, $params]);
            }
        }

        Response::error('Invalid callback.', 500);
    }
}
