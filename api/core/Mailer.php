<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Helper para envio de e-mails via PHPMailer / SMTP
 */
class Mailer
{
    /**
     * Envia um e-mail de reset de senha com o código de 5 dígitos.
     *
     * @param string $toEmail   Endereço de destino
     * @param string $code      Código de 5 dígitos em texto plano
     * @throws RuntimeException Se o envio falhar
     */
    public static function sendPasswordReset($toEmail, $code)
    {
        $config = require __DIR__ . '/../config/mail.php';

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->SMTPSecure = $config['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($toEmail);

            $mail->isHTML(true);
            $mail->Subject = 'Código de redefinição de senha - Dotti.Work';
            $mail->Body = self::buildResetEmailBody($code);
            $mail->AltBody = "Seu código de redefinição de senha é: {$code}\nEle expira em 1 hora.";

            $mail->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('Falha ao enviar e-mail: ' . $mail->ErrorInfo);
        }
    }

    /**
     * Envia um e-mail usando um template genérico
     *
     * @param string $toEmail       Endereço de destino
     * @param string $slug          Slug do template (ex: password_reset, new_user)
     * @param array $variables      Variáveis para substituir no template (ex: {{ url }}, {{ code }})
     * @throws RuntimeException Se o envio falhar
     */
    public static function sendTemplate($toEmail, $slug, $variables = [])
    {
        $config = require __DIR__ . '/../config/mail.php';
        $templatePath = __DIR__ . '/../templates/' . $slug . '.html';

        // Verifica se o template existe
        if (!file_exists($templatePath)) {
            throw new RuntimeException('Template não encontrado: ' . $slug);
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->SMTPSecure = $config['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($toEmail);

            // Carrega o template
            $body = file_get_contents($templatePath);

            // Substitui variáveis no template
            foreach ($variables as $key => $value) {
                $body = str_replace('{{ ' . $key . ' }}', $value, $body);
            }

            $mail->isHTML(true);
            $mail->Subject = 'Teste de Template - Dotti.Work';
            $mail->Body = $body;
            $mail->AltBody = 'Este é um e-mail de teste do template: ' . $slug;

            $mail->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('Falha ao enviar e-mail: ' . $mail->ErrorInfo);
        }
    }

    /**
     * Monta o HTML do e-mail de reset de senha.
     */
    private static function buildResetEmailBody($code)
    {
        $templatePath = __DIR__ . '/../templates/password_reset.html';
        $template = file_get_contents($templatePath);
        return str_replace('{{CODE}}', $code, $template);
    }
}
