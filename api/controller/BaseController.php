<?php

class BaseController
{
    /**
     * Valida o token da requisição e retorna o usuário autenticado.
     * Encerra a execução com 401 se o token estiver ausente, inválido ou expirado.
     */
    protected function requireToken(Request $request): array
    {
        return Auth::requireAuth($request);
    }
}
