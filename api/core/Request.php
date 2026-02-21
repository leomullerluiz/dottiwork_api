<?php
/**
 * Classe para manipular requisições HTTP
 */
class Request
{
    private $method;
    private $uri;
    private $body;
    private $headers;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = $this->parseUri();
        $this->body = $this->parseBody();
        $this->headers = $this->parseHeaders();
    }

    /**
     * Retorna o método HTTP (GET, POST, etc)
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Retorna a URI limpa
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Retorna o corpo da requisição (parsed JSON)
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Retorna um header específico
     */
    public function getHeader($name)
    {
        $name = strtoupper(str_replace('-', '_', $name));
        return $this->headers[$name] ?? null;
    }

    /**
     * Retorna o token Bearer do Authorization header
     */
    public function getBearerToken()
    {
        return $this->getHeader('key');
    }

    /**
     * Parse da URI removendo query strings
     */
    private function parseUri()
    {
        $uri = $_SERVER['REQUEST_URI'];

        // Remove a base path se estiver em subpasta
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/') {
            $uri = str_replace($scriptName, '', $uri);
        }

        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        return '/' . trim($uri, '/');
    }

    /**
     * Parse do corpo da requisição (JSON)
     */
    private function parseBody()
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        return $data ?? [];
    }

    /**
     * Parse dos headers
     */
    private function parseHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[substr($key, 5)] = $value;
            }
        }
        return $headers;
    }
}