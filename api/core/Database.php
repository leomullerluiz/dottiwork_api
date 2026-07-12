<?php
/**
 * PDO database connection singleton.
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
            if (($_ENV['APP_ENV'] ?? 'local') !== 'production') {
                error_log('DB CONN ERROR: ' . $e->getMessage());
            }

            Response::serviceUnavailable('Database unavailable.');
        }
    }

    /**
     * Returns the single connection instance.
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Returns the PDO connection.
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Prevents cloning.
     */
    private function __clone()
    {
    }

    /**
     * Prevents unserialization.
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton.");
    }
}
