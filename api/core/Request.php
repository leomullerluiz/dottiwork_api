<?php

class Request
{
    private $method;
    private $uri;
    private $body;
    private $headers;
    private $query;
    private $cookies;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $this->parseUri();
        $this->query = $_GET ?? [];
        $this->cookies = $_COOKIE ?? [];
        $this->headers = $this->parseHeaders();
        $this->body = $this->parseBody();
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getJsonBody()
    {
        return $this->body;
    }

    public function getQuery($key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }

        return array_key_exists($key, $this->query) ? $this->query[$key] : $default;
    }

    public function getHeader($name)
    {
        $normalized = strtoupper(str_replace('-', '_', $name));
        return $this->headers[$normalized] ?? null;
    }

    public function getAuthorizationBearerToken()
    {
        $authorization = $this->getHeader('Authorization');

        if (!$authorization) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    public function getCookie($name)
    {
        return $this->cookies[$name] ?? null;
    }

    public function getClientIp()
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['HTTP_X_REAL_IP'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }

            $parts = explode(',', $candidate);
            $ip = trim($parts[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }

    public function getOrigin()
    {
        return $this->getHeader('Origin');
    }

    private function parseUri()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        $basePaths = array_values(array_unique(array_filter([
            $scriptName,
            dirname($scriptName),
        ], function ($path) {
            return $path && $path !== '.' && $path !== '/';
        })));

        usort($basePaths, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        foreach ($basePaths as $basePath) {
            if (strpos($uri, $basePath) === 0) {
                $uri = substr($uri, strlen($basePath));
                break;
            }
        }

        if ($uri === '/v1' || strpos($uri, '/v1/') === 0) {
            $uri = '/api' . $uri;
        }

        $queryPosition = strpos($uri, '?');
        if ($queryPosition !== false) {
            $uri = substr($uri, 0, $queryPosition);
        }

        return '/' . trim($uri, '/');
    }

    private function parseBody()
    {
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || trim($rawBody) === '') {
            return [];
        }

        $data = json_decode($rawBody, true);
        return is_array($data) ? $data : [];
    }

    private function parseHeaders()
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[substr($key, 5)] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['CONTENT_TYPE'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['CONTENT_LENGTH'] = $_SERVER['CONTENT_LENGTH'];
        }

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['AUTHORIZATION'] = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return $headers;
    }
}
