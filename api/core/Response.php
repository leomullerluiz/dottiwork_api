<?php

class Response
{
    public static function json($data, $statusCode = 200)
    {
        self::send($data, $statusCode);
    }

    public static function success($data = [], $statusCode = 200)
    {
        self::send([
            'success' => true,
            'data' => $data,
        ], $statusCode);
    }

    public static function created($data = [])
    {
        self::success($data, 201);
    }

    public static function noContent()
    {
        http_response_code(204);
        exit;
    }

    public static function error($message, $statusCode = 400, $code = 'BAD_REQUEST', $details = [])
    {
        self::send([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $statusCode);
    }

    public static function validationError($details = [], $message = 'Dados invalidos.')
    {
        self::error($message, 422, 'VALIDATION_ERROR', $details);
    }

    public static function unauthorized($message = 'Nao autenticado.')
    {
        self::error($message, 401, 'UNAUTHORIZED');
    }

    public static function forbidden($message = 'Acesso negado.')
    {
        self::error($message, 403, 'FORBIDDEN');
    }

    public static function notFound($message = 'Recurso nao encontrado.')
    {
        self::error($message, 404, 'NOT_FOUND');
    }

    public static function conflict($message = 'Conflito de dados.')
    {
        self::error($message, 409, 'CONFLICT');
    }

    public static function tooManyRequests($message = 'Muitas requisicoes.')
    {
        self::error($message, 429, 'RATE_LIMITED');
    }

    public static function badGateway($message = 'Servico externo indisponivel.')
    {
        self::error($message, 502, 'BAD_GATEWAY');
    }

    public static function serviceUnavailable($message = 'Servico temporariamente indisponivel.')
    {
        self::error($message, 503, 'SERVICE_UNAVAILABLE');
    }

    public static function redirect($url, $statusCode = 302)
    {
        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }

    private static function send($payload, $statusCode)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
