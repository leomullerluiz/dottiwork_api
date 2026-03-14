<?php
/**
 * Controller de Autenticação
 */
class AuthController extends BaseController
{
    /**
     * POST /auth/login
     * Realiza login e retorna token
     */
    public function login(Request $request)
    {
        $body = $request->getBody();

        // Validação de entrada
        if (empty($body['email']) || empty($body['senha'])) {
            Response::error('Email e senha são obrigatórios', 400);
        }

        $email = trim($body['email']);
        $senha = $body['senha'];

        // Busca usuário
        $user = User::findByEmail($email);

        if (!$user) {
            Response::error('Credenciais inválidas', 401);
        }

        // Verifica senha
        if (!Auth::verifyPassword($senha, $user['senha'])) {
            Response::error('Credenciais inválidas', 401);
        }

        // Gera token
        $token = Auth::generateToken();
        $expiresIn = 3600; // 1 hora

        $authToken = AuthToken::create($user['id'], $token, $expiresIn);

        // Atualiza último login
        User::updateLastLogin($user['id']);

        // Retorna token
        Response::json([
            'token' => $authToken['token'],
            'expires_in' => $authToken['expires_in'],
            'user' => User::toPublic($user)
        ], 200);
    }

    /**
     * POST /auth/logout
     * Invalida o token atual
     */
    public function logout(Request $request)
    {
        $user = $this->requireToken($request);
        $token = $request->getBearerToken();

        AuthToken::deleteByToken($token);

        Response::success([], 'Logout realizado com sucesso');
    }

    /**
     * GET /auth/me
     * Retorna informações do usuário autenticado
     */
    public function me(Request $request)
    {
        $user = $this->requireToken($request);

        Response::json([
            'user' => User::toPublic($user)
        ], 200);
    }

    /**
     * POST /auth/signup
     * Registra novo usuário
     */
    public function signup(Request $request)
    {
        $body = $request->getBody();

        // Validação
        if (empty($body['password']) || empty($body['email']) || empty($body['password_confirm'])) {
            Response::error('Email, senha e confirmação de senha são obrigatórios', 400);
        }

        $senha = $body['password'];
        $email = trim($body['email']);
        $password_confirm = $body['password_confirm'];

        // Valida formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Email inválido', 400);
        }

        if (User::findByEmail($email)) {
            Response::error('Email já está em uso', 409);
        }

        // Verifica se as senhas são iguais
        if ($senha !== $password_confirm) {
            Response::error('As senhas não coincidem', 400);
        }

        // Cria usuário
        $userId = User::create($email, $email, $senha);
        $user = User::findById($userId);

        Response::json([
            'message' => 'Usuário criado com sucesso',
            'user' => User::toPublic($user)
        ], 201);
    }
}