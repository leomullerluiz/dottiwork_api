<?php

class User
{
    public static function findById($id)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function findByEmail($email)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public static function findByLogin($login)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE login = :login AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['login' => $login]);
        return $stmt->fetch();
    }

    public static function loginExistsForOtherUser($login, $userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT 1
            FROM users
            WHERE login = :login AND id <> :user_id AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([
            'login' => $login,
            'user_id' => $userId,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public static function createFromGitHub(array $githubUser, $email = null)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO users (
                login, email, display_name, avatar_url, bio, location, company,
                website_url, github_profile_url, last_login_at, created_at, updated_at
            ) VALUES (
                :login, :email, :display_name, :avatar_url, :bio, :location, :company,
                :website_url, :github_profile_url, NOW(), NOW(), NOW()
            )
        ");

        $stmt->execute(self::githubParams($githubUser, $email));
        return self::findById($db->lastInsertId());
    }

    public static function updateFromGitHub($userId, array $githubUser, $email = null)
    {
        $db = Database::getInstance()->getConnection();
        $params = self::githubParams($githubUser, $email);
        $params['id'] = $userId;

        $stmt = $db->prepare("
            UPDATE users
            SET login = :login,
                email = :email,
                display_name = :display_name,
                avatar_url = :avatar_url,
                bio = :bio,
                location = :location,
                company = :company,
                website_url = :website_url,
                github_profile_url = :github_profile_url,
                last_login_at = NOW(),
                updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");

        $stmt->execute($params);
        return self::findById($userId);
    }

    public static function updateDisplayName($userId, $displayName)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE users
            SET display_name = :display_name, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute(['id' => $userId, 'display_name' => $displayName]);
        return self::findById($userId);
    }

    public static function softDelete($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE users
            SET deleted_at = NOW(), updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute(['id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function toPublic($user)
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'login' => $user['login'] ?? null,
            'display_name' => $user['display_name'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
            'bio' => $user['bio'] ?? null,
            'location' => $user['location'] ?? null,
            'company' => $user['company'] ?? null,
            'website_url' => $user['website_url'] ?? null,
            'github_profile_url' => $user['github_profile_url'] ?? null,
            'created_at' => $user['created_at'] ?? null,
            'updated_at' => $user['updated_at'] ?? null,
        ];
    }

    private static function githubParams(array $githubUser, $email)
    {
        return [
            'login' => $githubUser['login'] ?? null,
            'email' => $email,
            'display_name' => $githubUser['name'] ?? ($githubUser['login'] ?? null),
            'avatar_url' => $githubUser['avatar_url'] ?? null,
            'bio' => $githubUser['bio'] ?? null,
            'location' => $githubUser['location'] ?? null,
            'company' => $githubUser['company'] ?? null,
            'website_url' => $githubUser['blog'] ?? null,
            'github_profile_url' => $githubUser['html_url'] ?? null,
        ];
    }
}
