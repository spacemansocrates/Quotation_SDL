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

// Database configuration (Consider moving sensitive info to .env file)
class DatabaseConfig {
    private const HOST = 'localhost';
    private const USERNAME = 'root';
    private const PASSWORD = '';
    private const DATABASE = 'supplies';
    private const CHARSET = 'utf8mb4';
    
    // PDO connection options
    private const OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'
    ];

    /**
     * Establishes a database connection
     * 
     * @return PDO Database connection object
     * @throws PDOException If connection fails
     */
    public static function getConnection(): PDO {
        try {
            // Construct DSN (Data Source Name)
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s', 
                self::HOST, 
                self::DATABASE, 
                self::CHARSET
            );

            // Create PDO connection
            $pdo = new PDO($dsn, self::USERNAME, self::PASSWORD, self::OPTIONS);

            return $pdo;
        } catch (PDOException $e) {
            // Log the error
            self::logConnectionError($e);

            // Throw a generic error to prevent information disclosure
            throw new PDOException('Database connection failed. Please try again later.');
        }
    }

    /**
     * Logs database connection errors
     * 
     * @param PDOException $e Exception to log
     */
    private static function logConnectionError(PDOException $e): void {
        // Ensure log directory exists
        $logDir = __DIR__ . '/logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Log file path
        $logFile = $logDir . '/db_connection_errors.log';

        // Prepare log message
        $logMessage = sprintf(
            "[%s] Database Connection Error: %s\nTrace: %s\n\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getTraceAsString()
        );

        // Write to log file
        error_log($logMessage, 3, $logFile);
    }

    /**
     * Safely closes the database connection
     * 
     * @param PDO|null $pdo Database connection to close
     */
    public static function closeConnection(?PDO &$pdo = null): void {
        $pdo = null;
    }

    /**
     * Performs a safe query execution
     * 
     * @param PDO $pdo Database connection
     * @param string $query SQL query to execute
     * @param array $params Query parameters
     * @return PDOStatement Executed statement
     * @throws PDOException If query execution fails
     */
    public static function executeQuery(PDO $pdo, string $query, array $params = []): PDOStatement {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            // Log query execution error
            self::logQueryError($query, $params, $e);

            // Rethrow exception
            throw $e;
        }
    }

    /**
     * Logs query execution errors
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param PDOException $e Exception to log
     */
    private static function logQueryError(string $query, array $params, PDOException $e): void {
        // Ensure log directory exists
        $logDir = __DIR__ . '/logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Log file path
        $logFile = $logDir . '/db_query_errors.log';

        // Prepare log message
        $logMessage = sprintf(
            "[%s] Query Execution Error: %s\nQuery: %s\nParams: %s\nTrace: %s\n\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $query,
            json_encode($params),
            $e->getTraceAsString()
        );

        // Write to log file
        error_log($logMessage, 3, $logFile);
    }

    /**
     * Begins a database transaction
     * 
     * @param PDO $pdo Database connection
     */
    public static function beginTransaction(PDO $pdo): void {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
    }

    /**
     * Commits a database transaction
     * 
     * @param PDO $pdo Database connection
     */
    public static function commitTransaction(PDO $pdo): void {
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    }

    /**
     * Rolls back a database transaction
     * 
     * @param PDO $pdo Database connection
     */
    public static function rollbackTransaction(PDO $pdo): void {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

// Helper function to quickly get a database connection
function getDatabaseConnection(): PDO {
    return DatabaseConfig::getConnection();
}