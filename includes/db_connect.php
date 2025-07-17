<?php
/**
 * Database Connection File
 *
 * Provides a secure and reusable way to connect to the MySQL database
 */

// Prevent direct script access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    die('Direct access not allowed');
}

/**
 * Database Configuration and Connection Management
 *
 * This class handles the database connection, query execution,
 * transaction management, and error logging.
 * It uses environment variables for sensitive credentials and
 * implements a basic singleton-like pattern for the PDO connection
 * to ensure only one connection is established per request.
 */
class DatabaseConfig {
    // Static property to hold the single PDO connection instance
    private static ?PDO $pdoInstance = null;

    // PDO connection options
    private const OPTIONS = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,      // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,            // Default fetch mode to associative array
        PDO::ATTR_EMULATE_PREPARES   => false,                       // Disable emulation for true prepared statements
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci' // Ensure UTF-8mb4
    ];

    /**
     * Establishes and returns a database connection.
     *
     * This method ensures that only one PDO connection is created per script execution.
     * It retrieves database credentials from environment variables for security.
     *
     * @return PDO Database connection object
     * @throws PDOException If connection fails
     */
    public static function getConnection(): PDO {
        // If an instance already exists, return it
        if (self::$pdoInstance instanceof PDO) {
            return self::$pdoInstance;
        }

        // Retrieve sensitive credentials from environment variables
        // IMPORTANT: You need to set these environment variables on your Hostinger server.
        // For example, in your Hostinger panel or via .htaccess (if allowed) or a server config.
        // On Hostinger, you might set them in the "Environment Variables" section or similar.
        $host     = getenv('DB_HOST') ?: 'srv582.hstgr.io';
        $username = getenv('DB_USERNAME') ?: 'u789944046_socrates';
        $password = getenv('DB_PASSWORD') ?: 'Naho1386';
        $database = getenv('DB_DATABASE') ?: 'u789944046_suppliesdirect';
        $charset  = getenv('DB_CHARSET') ?: 'utf8mb4';

        try {
            // Construct DSN (Data Source Name)
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $host,
                $database,
                $charset
            );

            // Create PDO connection and store it
            self::$pdoInstance = new PDO($dsn, $username, $password, self::OPTIONS);

            return self::$pdoInstance;
        } catch (PDOException $e) {
            // Log the detailed error for debugging
            self::logConnectionError($e);

            // Throw a generic error to prevent sensitive information disclosure to the client
            throw new PDOException('Database connection failed. Please try again later.');
        }
    }

    /**
     * Logs database connection errors.
     *
     * @param PDOException $e Exception to log
     */
    private static function logConnectionError(PDOException $e): void {
        // Ensure log directory exists
        $logDir = __DIR__ . '/logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true); // Create directory with appropriate permissions
        }

        // Log file path
        $logFile = $logDir . '/db_connection_errors.log';

        // Prepare log message with timestamp, error message, and stack trace
        $logMessage = sprintf(
            "[%s] Database Connection Error: %s\nTrace: %s\n\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getTraceAsString()
        );

        // Write to log file (mode 3 means append to the specified file)
        error_log($logMessage, 3, $logFile);
    }

    /**
     * Safely closes the database connection.
     *
     * This method unsets the PDO instance, effectively closing the connection.
     * It's good practice to call this at the end of your script if you want to explicitly close.
     *
     * @param PDO|null $pdo Optional: The PDO connection object to close. If null, the internal instance is closed.
     */
    public static function closeConnection(?PDO &$pdo = null): void {
        // If a specific PDO object is passed, unset it
        if ($pdo instanceof PDO) {
            $pdo = null;
        }
        // Also unset the internal static instance
        self::$pdoInstance = null;
    }

    /**
     * Performs a safe query execution using prepared statements.
     *
     * @param PDO    $pdo    Database connection
     * @param string $query  SQL query to execute
     * @param array  $params Query parameters for binding
     * @return PDOStatement Executed statement object
     * @throws PDOException If query preparation or execution fails
     */
    public static function executeQuery(PDO $pdo, string $query, array $params = []): PDOStatement {
        try {
            $stmt = $pdo->prepare($query); // Prepare the SQL query
            $stmt->execute($params);       // Execute with bound parameters
            return $stmt;
        } catch (PDOException $e) {
            // Log the detailed query execution error
            self::logQueryError($query, $params, $e);

            // Rethrow the exception to be handled by the calling code
            throw $e;
        }
    }

    /**
     * Logs query execution errors.
     *
     * @param string     $query  SQL query that failed
     * @param array      $params Parameters used in the query
     * @param PDOException $e      Exception to log
     */
    private static function logQueryError(string $query, array $params, PDOException $e): void {
        // Ensure log directory exists
        $logDir = __DIR__ . '/logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Log file path
        $logFile = $logDir . '/db_query_errors.log';

        // Prepare log message with query, parameters, and trace
        $logMessage = sprintf(
            "[%s] Query Execution Error: %s\nQuery: %s\nParams: %s\nTrace: %s\n\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $query,
            json_encode($params), // Encode parameters for better readability in logs
            $e->getTraceAsString()
        );

        // Write to log file
        error_log($logMessage, 3, $logFile);
    }

    /**
     * Begins a database transaction.
     *
     * @param PDO $pdo Database connection
     */
    public static function beginTransaction(PDO $pdo): void {
        if (!$pdo->inTransaction()) { // Only begin if not already in a transaction
            $pdo->beginTransaction();
        }
    }

    /**
     * Commits a database transaction.
     *
     * @param PDO $pdo Database connection
     */
    public static function commitTransaction(PDO $pdo): void {
        if ($pdo->inTransaction()) { // Only commit if in a transaction
            $pdo->commit();
        }
    }

    /**
     * Rolls back a database transaction.
     *
     * @param PDO $pdo Database connection
     */
    public static function rollbackTransaction(PDO $pdo): void {
        if ($pdo->inTransaction()) { // Only rollback if in a transaction
            $pdo->rollBack();
        }
    }
}

// Helper function to quickly get a database connection
// This function acts as a convenient entry point for other scripts.
function getDatabaseConnection(): PDO {
    return DatabaseConfig::getConnection();
}
