<?php
/**
 * Model de Token de Autenticação
 */
class AuthToken {
    /**
     * Cria novo token de autenticação
     */
    public static function create($userId, $token, $expiresInSeconds = 3600) {
        $db = Database::getInstance()->getConnection();
        
        $expiresAt = (new DateTime())->modify("+{$expiresInSeconds} seconds")->format('Y-m-d H:i:s');
        
        $stmt = $db->prepare("
            INSERT INTO auth_tokens (user_id, token, expires_at, created_at) 
            VALUES (:user_id, :token, :expires_at, NOW())
        ");
        
        $stmt->execute([
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt
        ]);
        
        return [
            'id' => $db->lastInsertId(),
            'token' => $token,
            'expires_at' => $expiresAt,
            'expires_in' => $expiresInSeconds
        ];
    }

    /**
     * Busca token por valor
     */
    public static function findByToken($token) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM auth_tokens WHERE token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        return $stmt->fetch();
    }

    /**
     * Remove tokens expirados
     */
    public static function deleteExpired() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM auth_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Remove tokens de um usuário
     */
    public static function deleteByUserId($userId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM auth_tokens WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Remove token específico
     */
    public static function deleteByToken($token) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM auth_tokens WHERE token = :token");
        $stmt->execute(['token' => $token]);
        return $stmt->rowCount();
    }
}