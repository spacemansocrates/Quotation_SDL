<?php
class Database {
    private $host = 'localhost'; // Or just '127.0.0.1' if default port 3306
    private $db_name = 'supplies';
    private $username = 'root'; // <-- IMPORTANT: Replace with your actual DB username
    private $password = ''; // <-- IMPORTANT: Replace with your actual DB password
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                                  $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Optional: Set charset for the connection, good practice if your tables use utf8mb4
            $this->conn->exec("SET NAMES 'utf8mb4'");
        } catch(PDOException $e) {
            // In a production environment, you might want to log this error instead of echoing
            // and perhaps throw a custom exception or return false/null.
            error_log("Database Connection Error: " . $e->getMessage()); // Log error to PHP error log
            // For the user/developer during debugging, echoing is fine:
            echo "Connection error: " . $e->getMessage(); // Or die("Connection error: " . $e->getMessage());
        }
        return $this->conn;
    }
}
?>