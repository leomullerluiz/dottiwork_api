<?php
/**
 * Sistema de Autenticação e Autorização
 */
class Auth {
    /**
     * Gera um token seguro
     */
    public static function generateToken() {
        $randomBytes = random_bytes(32);
        $timestamp = time();
        $token = hash_hmac('sha256', $randomBytes . $timestamp, 'SECRET_KEY_AQUI');
        return $token;
    }

    /**
     * Valida o token da requisição
     * Retorna o usuário se válido, ou null se inválido
     */
    public static function validateToken($token) {
        if (!$token) {
            return null;
        }

        $authToken = AuthToken::findByToken($token);
        
        if (!$authToken) {
            return null;
        }

        // Verifica se o token expirou
        $now = new DateTime();
        $expiresAt = new DateTime($authToken['expires_at']);
        
        if ($now > $expiresAt) {
            return null;
        }

        // Retorna o usuário associado
        return User::findById($authToken['user_id']);
    }

    /**
     * Middleware: Requer autenticação
     * Use antes de endpoints protegidos
     */
    public static function requireAuth(Request $request) {
        $token = $request->getBearerToken();
        $user = self::validateToken($token);

        if (!$user) {
            Response::unauthorized('Token inválido ou expirado');
        }

        return $user;
    }

    /**
     * Gera hash seguro de senha
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * Verifica senha contra hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}