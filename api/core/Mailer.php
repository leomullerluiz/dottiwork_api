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
            $mail->Subject = 'Código de redefinição de senha - DottiWork';
            $mail->Body = self::buildResetEmailBody($code);
            $mail->AltBody = "Seu código de redefinição de senha é: {$code}\nEle expira em 1 hora.";

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
        return "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <h2>Redefinição de Senha - DottiWork</h2>
            <p>Recebemos uma solicitação para redefinir a senha da sua conta.</p>
            <p>Use o código abaixo para concluir o processo. Ele é válido por <strong>1 hora</strong>.</p>
            <div style='font-size: 32px; font-weight: bold; letter-spacing: 8px; margin: 24px 0; color: #4F46E5;'>
                {$code}
            </div>
            <p>Se você não solicitou esta redefinição, ignore este e-mail.</p>
            <hr/>
            <p style='font-size: 12px; color: #999;'>DottiWork &mdash; não responda este e-mail.</p>
        </body>
        </html>
        ";
    }
}
