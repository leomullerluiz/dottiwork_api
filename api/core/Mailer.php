<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    public static function sendTemplate($toEmail, $slug, $variables = [], $subject = 'dotti.work')
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

            $body = EmailTemplateRenderer::renderHtml($slug, $variables);
            $textBody = EmailTemplateRenderer::renderText($slug, $variables);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $textBody ?: trim(html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8'));
            $mail->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('Falha ao enviar e-mail.');
        }
    }
}
