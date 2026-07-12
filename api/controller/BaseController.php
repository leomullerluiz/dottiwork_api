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
            Response::forbidden('Origin is required for cookie-authenticated mutations.');
        }

        if (!$origin) {
            return;
        }

        $allowed = $this->allowedOrigins();
        if ($allowed && !in_array($origin, $allowed, true)) {
            Response::forbidden('Unauthorized origin.');
        }
    }

    private function allowedOrigins()
    {
        return CorsPolicy::allowedOrigins();
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
