<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $conn = null;
    private $error = null;
    private $db_exists = false;

    private function __construct() {
        try {
            // We connect to host first. We'll use database after.
            $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->conn = $pdo;
            
            // Check if database exists by attempting to switch to it
            try {
                $pdo->exec("USE `" . DB_NAME . "`");
                $this->db_exists = true;
            } catch (PDOException $e) {
                $this->db_exists = false;
                $this->error = "Database '" . DB_NAME . "' does not exist: " . $e->getMessage();
            }
        } catch (PDOException $e) {
            $this->error = "Connection failed: " . $e->getMessage();
            $this->conn = null;
            $this->db_exists = false;
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->db_exists ? $this->conn : null;
    }

    public function getHostConnection() {
        return $this->conn;
    }

    public function doesDbExist() {
        return $this->db_exists;
    }

    public function attemptCreateDatabase() {
        if ($this->conn && !$this->db_exists) {
            try {
                $this->conn->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $this->conn->exec("USE `" . DB_NAME . "`");
                $this->db_exists = true;
                return true;
            } catch (PDOException $e) {
                $this->error = "Failed to create database: " . $e->getMessage();
                return false;
            }
        }
        return false;
    }


    public function getError() {
        return $this->error;
    }

    /**
     * Execute a prepared SQL query safely
     */
    public function query($sql, $params = []) {
        if ($this->conn === null) {
            return false;
        }
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            return false;
        }
    }
}
