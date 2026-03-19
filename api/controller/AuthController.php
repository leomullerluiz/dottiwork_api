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

        $email = trim($body['email']);
        $senha = $body['password'];
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

    /**
     * POST /auth/password_reset
     * Body: { "email": "..." }
     *
     * Gera um código de 5 dígitos, armazena-o (criptografado) no banco
     * e envia para o e-mail informado. O código expira em 1 hora.
     */
    public function requestPasswordReset(Request $request)
    {
        $body = $request->getBody();

        if (empty($body['email'])) {
            Response::error('E-mail é obrigatório', 400);
        }

        $email = trim($body['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('E-mail inválido', 400);
        }

        $user = User::findByEmail($email);

        // Resposta genérica para não revelar se o e-mail existe
        if (!$user) {
            Response::json(['message' => 'Se este e-mail estiver cadastrado, você receberá o código em instantes.'], 200);
            return;
        }

        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        PasswordResetToken::create($user['id'], $email, $code);

        try {
            Mailer::sendPasswordReset($email, $code);
        } catch (RuntimeException $e) {
            Response::error('Não foi possível enviar o e-mail. Tente novamente mais tarde.', 500);
        }

        Response::json(['message' => 'Se este e-mail estiver cadastrado, você receberá o código em instantes.'], 200);
    }

    /**
     * GET /auth/password_reset/:resetToken/:email
     *
     * Valida se o código de reset é válido (não expirado, não usado)
     * e está vinculado ao e-mail informado.
     * Retorna { "valid": true } ou { "valid": false }.
     */
    public function validateResetToken(Request $request, $params)
    {
        $resetToken = $params['resetToken'] ?? null;
        $email = $params['email'] ?? null;

        if (!$resetToken || !$email) {
            Response::error('Token e e-mail são obrigatórios', 400);
        }

        $email = urldecode($email);

        $record = PasswordResetToken::findValid($email, $resetToken);

        Response::json(['valid' => $record !== null]);
    }

    /**
     * POST /auth/password_reset/credentials
     * Body: { "email": "...", "token": "...", "password": "...", "password_confirm": "..." }
     *
     * Valida o token + e-mail, verifica se as senhas coincidem e atualiza a senha.
     */
    public function resetPassword(Request $request)
    {
        $body = $request->getBody();

        if (
            empty($body['email']) ||
            empty($body['token']) ||
            empty($body['password']) ||
            empty($body['password_confirm'])
        ) {
            Response::error('E-mail, token, senha e confirmação de senha são obrigatórios', 400);
        }

        $email = trim($body['email']);
        $token = $body['token'];
        $password = $body['password'];
        $passwordConfirm = $body['password_confirm'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('E-mail inválido', 400);
        }

        if ($password !== $passwordConfirm) {
            Response::error('As senhas não coincidem', 400);
        }

        $record = PasswordResetToken::findValid($email, $token);

        if (!$record) {
            Response::error('Token inválido ou expirado', 400);
        }

        // Atualiza a senha do usuário
        User::updatePassword($record['user_id'], $password);

        // Invalida o token após uso
        PasswordResetToken::markAsUsed($record['id']);

        Response::json(['message' => 'Senha alterada com sucesso'], 200);
    }
}