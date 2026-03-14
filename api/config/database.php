<?php
/**
 * Configurações do Banco de Dados
 */

return [
    'host' => $_ENV['DB_HOST'] ?: 'localhost',
    'dbname' => $_ENV['DB_NAME'] ?: 'dottiwork_db',
    'username' => $_ENV['DB_USER'] ?: 'root',
    'password' => $_ENV['DB_PASSWORD'] ?: '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];