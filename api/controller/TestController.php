<?php

/**
 * Controller para endpoints de teste
 */
class TestController extends BaseController
{
    /**
     * Testa a conexão com o banco de dados
     */
    public function dbConnectionTest($request, $params = [])
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query('SELECT 1');
            $stmt->fetch();
            Response::json(['message' => 'CONEXAO EFETUADA']);
        } catch (Exception $e) {
            Response::error('Error conn: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Envia um e-mail de teste usando um template
     * 
     * Parâmetros esperados (JSON):
     * - slug: Slug do template (ex: password_reset, new_user)
     * - email: Endereço de e-mail para envio
     */
    public function sendEmailTemplate($request, $params = [])
    {
        try {
            $body = $request->getBody();

            // Valida os parâmetros obrigatórios
            if (!isset($body['slug']) || empty($body['slug'])) {
                Response::error('Parâmetro "slug" é obrigatório', 400);
                return;
            }

            if (!isset($body['email']) || empty($body['email'])) {
                Response::error('Parâmetro "email" é obrigatório', 400);
                return;
            }

            $slug = $body['slug'];
            $email = $body['email'];

            // Valida o formato do email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('O endereço de e-mail fornecido é inválido', 400);
                return;
            }

            // Envia o template
            Mailer::sendTemplate($email, $slug);

            Response::json([
                'message' => 'E-mail de teste enviado com sucesso',
                'slug' => $slug,
                'email' => $email
            ]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 400);
        } catch (Exception $e) {
            Response::error('Erro ao enviar e-mail: ' . $e->getMessage(), 500);
        }
    }
}
