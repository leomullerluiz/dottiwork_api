<?php

class BaseController
{
    protected function requireToken(Request $request)
    {
        $this->validateMutationOrigin($request);
        return Auth::requireAuth($request);
    }

    protected function validateMutationOrigin(Request $request)
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $origin = $request->getOrigin();
        if (!$origin && Auth::isCookieAuthRequest($request)) {
            Response::forbidden('Origem obrigatoria para mutacoes autenticadas por cookie.');
        }

        if (!$origin) {
            return;
        }

        $allowed = $this->allowedOrigins();
        if ($allowed && !in_array($origin, $allowed, true)) {
            Response::forbidden('Origem nao autorizada.');
        }
    }

    private function allowedOrigins()
    {
        $configured = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '';
        if ($configured === '') {
            $configured = ($_ENV['APP_ENV'] ?? 'local') === 'production'
                ? 'https://dotti.work,https://dottiwork.com'
                : 'https://dotti.work,https://dottiwork.com,http://localhost:3000';
        }

        return array_values(array_filter(array_map('trim', explode(',', $configured))));
    }

    protected function jsonBody(Request $request)
    {
        $body = $request->getJsonBody();
        return is_array($body) ? $body : [];
    }

    protected function limit(Request $request, $default = 30, $max = 100)
    {
        $limit = (int) $request->getQuery('limit', $default);
        return min(max($limit, 1), $max);
    }
}
