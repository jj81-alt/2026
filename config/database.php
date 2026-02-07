<?php
// config/database.php
/**
 * Database Connection Configuration
 * MarketConnect - Oculam Schema
 */

class Database {
    private $host = "localhost";
    private $db_name = "postgres"; // Change this to your actual database name
    private $username = "postgres";           // Change to your PostgreSQL username
    private $password = "mypassword123";      // Change to your PostgreSQL password
    private $schema = "oculam";              // Schema name
    private $conn;

    /**
     * Get database connection
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "pgsql:host=" . $this->host . ";dbname=" . $this->db_name;
            
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Set the search path to use the oculam schema
            $this->conn->exec("SET search_path TO " . $this->schema . ", public");
            
            // Set timezone (optional)
            $this->conn->exec("SET timezone TO 'Asia/Manila'");
            
        } catch(PDOException $e) {
            error_log("Connection error: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }

        return $this->conn;
    }

    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->conn = null;
    }

    /**
     * Get schema name
     * @return string
     */
    public function getSchema() {
        return $this->schema;
    }

    /**
     * Test database connection
     * @return bool
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                $this->closeConnection();
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}