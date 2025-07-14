<?php
// config.php
// Database configuration
define('DB_HOST', '127.0.0.1'); // Or your host
define('DB_NAME', 'supplies');
define('DB_USER', 'root'); // Your DB username
define('DB_PASS', '');     // Your DB password
define('DB_CHARSET', 'utf8mb4');

// Data Source Name (DSN)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// PDO Options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Create a PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // In a real app, you would log this error and show a generic message
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>