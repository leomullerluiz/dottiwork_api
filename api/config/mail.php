<?php
/**
 * Configurações de e-mail (SMTP via PHPMailer)
 */
return [
    'host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
    'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
    'username' => $_ENV['MAIL_USERNAME'] ?? '',
    'password' => $_ENV['MAIL_PASSWORD'] ?? '',
    'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',  // 'tls' ou 'ssl'
    'from_email' => $_ENV['MAIL_FROM_EMAIL'] ?? '',
    'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'DottiWork',
];
