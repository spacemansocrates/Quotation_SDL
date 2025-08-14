<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'supplies';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        // Check if already connected
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            // Success message
            // echo "✅ Database connection successful!<br>";
            
        } catch(PDOException $e) {
            // Error logging
            error_log("Database connection error: " . $e->getMessage());
            
            // User-friendly error message
            // echo "❌ Database connection failed. Please check your configuration.<br>";
            
            // Set connection to null on failure
            $this->conn = null;
        }
        
        return $this->conn;
    }

    // Method to check if connected
    public function isConnected() {
        return $this->conn !== null;
    }

    // Method to get connection status
    public function getConnectionStatus() {
        if ($this->isConnected()) {
            // echo "✅ Database is currently connected<br>";
            return true;
        } else {
            // echo "❌ No active database connection<br>";
            return false;
        }
    }

    // Method to close connection
    public function disconnect() {
        if ($this->conn !== null) {
            $this->conn = null;
            // echo "🔌 Database connection closed<br>";
        }
    }

    // Method to get the PDO connection object
    public function getConnection() {
        return $this->conn;
    }
}
// $database = new Database();
// $connection = $database->connect();
// $database->getConnectionStatus();
// This will show you if the connection was successful


// Example usage:
/*
$database = new Database();
$connection = $database->connect();

if ($database->isConnected()) {
    echo "Ready to perform database operations!<br>";
    // Your database operations here
} else {
    echo "Cannot proceed without database connection.<br>";
}

// Check status anytime
$database->getConnectionStatus();

// Close when done
$database->disconnect();
*/
?>