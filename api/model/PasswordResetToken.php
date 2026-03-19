<?php
/**
 * Model de Token para Reset de Senha
 */
class PasswordResetToken
{
    /**
     * Cria (ou substitui) um token de reset para o e-mail informado.
     * Tokens anteriores do mesmo e-mail são removidos antes de criar o novo.
     *
     * @param int    $userId
     * @param string $email
     * @param string $plainCode  Código de 5 dígitos em texto plano
     * @return array             Token criado (com o código em texto plano)
     */
    public static function create($userId, $email, $plainCode)
    {
        $db = Database::getInstance()->getConnection();

        // Invalida tokens anteriores do mesmo e-mail
        $stmt = $db->prepare("DELETE FROM password_reset_tokens WHERE email = :email");
        $stmt->execute(['email' => $email]);

        $tokenHash = password_hash($plainCode, PASSWORD_BCRYPT, ['cost' => 10]);
        $expiresAt = (new DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');

        $stmt = $db->prepare("
            INSERT INTO password_reset_tokens (user_id, email, token_hash, expires_at, created_at)
            VALUES (:user_id, :email, :token_hash, :expires_at, NOW())
        ");

        $stmt->execute([
            'user_id' => $userId,
            'email' => $email,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        return [
            'id' => $db->lastInsertId(),
            'user_id' => $userId,
            'email' => $email,
            'expires_at' => $expiresAt,
            'plain_code' => $plainCode,
        ];
    }

    /**
     * Verifica se existe um token válido (não expirado e não utilizado) para
     * o e-mail e o código informados.
     *
     * @param string $email
     * @param string $plainCode  Código de 5 dígitos em texto plano
     * @return array|null        Registro do token ou null se inválido
     */
    public static function findValid($email, $plainCode)
    {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT * FROM password_reset_tokens
            WHERE email      = :email
              AND expires_at > NOW()
              AND used_at    IS NULL
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute(['email' => $email]);
        $record = $stmt->fetch();

        if (!$record) {
            return null;
        }

        if (!password_verify($plainCode, $record['token_hash'])) {
            return null;
        }

        return $record;
    }

    /**
     * Marca um token como utilizado (invalida-o para novos usos).
     *
     * @param int $id  ID do registro na tabela
     */
    public static function markAsUsed($id)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
}
