<?php
/**
 * Model de Usuário
 */
class User
{
    /**
     * Busca usuário por login
     */
    public static function findByLogin($login)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE login = :login LIMIT 1");
        $stmt->execute(['login' => $login]);
        return $stmt->fetch();
    }

    /**
     * Busca usuário por ID
     */
    public static function findById($id)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Busca usuário por email
     */
    public static function findByEmail($email)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    /**
     * Cria novo usuário
     */
    public static function create($login, $email, $senha)
    {
        $db = Database::getInstance()->getConnection();
        $hashedPassword = Auth::hashPassword($senha);
        $stmt = $db->prepare("INSERT INTO users (email, login, senha, created_at) VALUES (:email, :login, :senha, NOW())");
        $stmt->execute([
            'email' => $email,
            'login' => $login,
            'senha' => $hashedPassword
        ]);
        $userId = $db->lastInsertId();
        return self::findById($userId);
    }

    /**
     * Atualiza último login do usuário
     */
    public static function updateLastLogin($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET ultimo_login = NOW() WHERE id = :id");
        $stmt->execute(['id' => $userId]);
    }

    /**
     * Retorna dados públicos do usuário (sem senha)
     */
    public static function toPublic($user)
    {
        if (!$user)
            return null;

        unset($user['senha']);
        return $user;
    }
}