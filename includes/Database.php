<?php
class Database {
    private static $instance = null;
    private $conn;
    private $usePDO = true;

    private function __construct() {
        if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers())) {
            try {
                $this->conn = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                $this->usePDO = true;
            } catch (PDOException $e) {
                die("PDO Database connection failed: " . $e->getMessage() .
                    "<br><br>Please check your database credentials in config.php");
            }
        }
        elseif (extension_loaded('mysqli')) {
            try {
                $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

                if ($this->conn->connect_error) {
                    die("MySQLi Database connection failed: " . $this->conn->connect_error);
                }

                $this->conn->set_charset("utf8mb4");
                $this->usePDO = false;
            } catch (Exception $e) {
                die("MySQLi Database connection failed: " . $e->getMessage());
            }
        }
        // No MySQL extension available
        else {
            throw new Exception("Database Error: Neither PDO_MySQL nor MySQLi extension is available. " .
                "Please enable one of these extensions in your php.ini file: " .
                "extension=pdo_mysql (recommended) or extension=mysqli. " .
                "After enabling, restart your web server.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function isPDO() {
        return $this->usePDO;
    }

    private function __clone() {}
    public function __wakeup() {}
}
