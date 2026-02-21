<?php
/**
 * Classe para manipular respostas HTTP
 */
class Response {
    /**
     * Envia resposta JSON
     */
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Envia erro padrão
     */
    public static function error($message, $statusCode = 400) {
        self::json(['error' => $message], $statusCode);
    }

    /**
     * Envia sucesso padrão
     */
    public static function success($data = [], $message = 'Sucesso') {
        self::json(array_merge(['message' => $message], $data), 200);
    }

    /**
     * Não autorizado
     */
    public static function unauthorized($message = 'Não autorizado') {
        self::json(['error' => $message], 401);
    }

    /**
     * Não encontrado
     */
    public static function notFound($message = 'Recurso não encontrado') {
        self::json(['error' => $message], 404);
    }
}