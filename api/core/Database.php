<?php
/**
 * Classe de Conexão com Banco de Dados usando PDO
 */
class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $config = require __DIR__ . '/../config/database.php';

        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            $this->connection = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (PDOException $e) {
            Response::json(['error' => 'DB CONN ERROR | DB_HOST: ' . $config['host']] . $e->getMessage(), 500);
            exit;
        }
    }

    /**
     * Singleton: retorna uma única instância da conexão
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retorna a conexão PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Previne clonagem
     */
    private function __clone()
    {
    }

    /**
     * Previne unserialize
     */
    public function __wakeup()
    {
        throw new Exception("Não é possível unserialize singleton");
    }
}