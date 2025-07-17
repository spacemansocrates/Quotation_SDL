<?php
require_once 'db_connect.php';

try {
    // Get the database connection. This will now reuse the same connection
    // if called multiple times within this script's execution.
    $pdo = getDatabaseConnection();

    echo "✅ Database connection successful.";

    // You can now use the $pdo object for database operations, e.g.:
    // $stmt = DatabaseConfig::executeQuery($pdo, "SELECT * FROM your_table_name LIMIT 1");
    // $result = $stmt->fetch();
    // print_r($result);

} catch (PDOException $e) {
    // Catch specific PDO exceptions for database connection errors
    echo "❌ Database connection failed: " . $e->getMessage();
} catch (Exception $e) {
    // Catch any other unexpected exceptions
    echo "❌ An unexpected error occurred: " . $e->getMessage();
} finally {
    // It's good practice to explicitly close the connection when done,
    // especially in long-running scripts or if you want to free resources immediately.
    // For typical web requests, PHP closes connections automatically at script end.
    if (isset($pdo)) {
        DatabaseConfig::closeConnection($pdo);
    }
}
