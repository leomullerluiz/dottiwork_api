<?php

class Response
{
    public static function json($data, $statusCode = 200)
    {
        if (self::isEnvelope($data)) {
            self::send($data, $statusCode);
        }

        if ($statusCode >= 400) {
            $message = is_array($data) && isset($data['error']) ? (string) $data['error'] : 'Erro na requisicao.';
            self::send(self::errorPayload($message, self::codeForStatus($statusCode)), $statusCode);
        }

        self::send(self::successPayload($data), $statusCode);
    }

    public static function success($data = null, $statusCode = 200)
    {
        self::send(self::successPayload($data), $statusCode);
    }

    public static function created($data = null)
    {
        self::success($data, 201);
    }

    public static function noContent()
    {
        http_response_code(204);
        exit;
    }

    public static function error($message, $statusCode = 400, $code = 'BAD_REQUEST', $details = null)
    {
        self::send(self::errorPayload($message, $code, $details), $statusCode);
    }

    public static function validationError($details = [], $message = 'Dados invalidos.')
    {
        self::send(self::validationErrorPayload($details, $message), 422);
    }

    public static function successPayload($data = null)
    {
        return [
            'success' => true,
            'data' => $data === null ? self::emptyObject() : $data,
        ];
    }

    public static function errorPayload($message, $code = 'BAD_REQUEST', $details = null)
    {
        return [
            'success' => false,
            'error' => [
                'code' => $code ?: 'BAD_REQUEST',
                'message' => $message ?: 'Erro na requisicao.',
                'details' => self::normalizeErrorDetails($details),
            ],
        ];
    }

    public static function validationErrorPayload($details = [], $message = 'Dados invalidos.')
    {
        return self::errorPayload($message, 'VALIDATION_ERROR', self::normalizeValidationDetails($details));
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

    private static function normalizeErrorDetails($details)
    {
        if ($details === null) {
            return self::emptyObject();
        }

        if (!is_array($details)) {
            return ['message' => (string) $details];
        }

        return $details;
    }

    private static function normalizeValidationDetails($details)
    {
        if (!is_array($details) || $details === []) {
            return [];
        }

        if (self::isList($details)) {
            return array_map(function ($item) {
                if (is_array($item)) {
                    return [
                        'field' => isset($item['field']) ? (string) $item['field'] : null,
                        'message' => isset($item['message']) ? (string) $item['message'] : 'Campo invalido.',
                    ];
                }

                return [
                    'field' => null,
                    'message' => (string) $item,
                ];
            }, $details);
        }

        $normalized = [];
        foreach ($details as $field => $messages) {
            $messages = is_array($messages) ? $messages : [$messages];
            foreach ($messages as $message) {
                $normalized[] = [
                    'field' => (string) $field,
                    'message' => (string) $message,
                ];
            }
        }

        return $normalized;
    }

    private static function isEnvelope($data)
    {
        return is_array($data)
            && array_key_exists('success', $data)
            && (array_key_exists('data', $data) || array_key_exists('error', $data));
    }

    private static function isList(array $value)
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private static function emptyObject()
    {
        return new stdClass();
    }

    private static function codeForStatus($statusCode)
    {
        $map = [
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMITED',
            500 => 'INTERNAL_SERVER_ERROR',
            502 => 'BAD_GATEWAY',
            503 => 'SERVICE_UNAVAILABLE',
        ];

        return $map[$statusCode] ?? 'ERROR';
    }
}
