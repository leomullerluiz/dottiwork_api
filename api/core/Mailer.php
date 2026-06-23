<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    public static function sendTemplate($toEmail, $slug, $variables = [], $subject = 'dotti.work')
    {
        $config = require __DIR__ . '/../config/mail.php';
        $templatePath = __DIR__ . '/../templates/' . $slug . '.html';

        if (!file_exists($templatePath)) {
            throw new RuntimeException('Template nao encontrado: ' . $slug);
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

            $body = file_get_contents($templatePath);
            foreach ($variables as $key => $value) {
                $body = str_replace('{{ ' . $key . ' }}', $value, $body);
                $body = str_replace('{{' . $key . '}}', $value, $body);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            $mail->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('Falha ao enviar e-mail.');
        }
    }
}
