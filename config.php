<?php
// config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'supplies');

// Default values (can be overridden or fetched from a settings table)
define('DEFAULT_VAT_PERCENTAGE', 16.50);
define('DEFAULT_PPDA_LEVY_PERCENTAGE', 1.00);
define('DEFAULT_MRA_WHT_NOTE_TEMPLATE', "MRA Withholding Tax Exemption Note. Please provide necessary documentation if applicable.");

function getDBConnection() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // In a real app, log this error and show a user-friendly message
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}

// Start session on every page that needs it
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Assume a logged-in user ID (replace with your actual auth system)
// For testing, you can hardcode it:
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Example user ID
}
$current_user_id = $_SESSION['user_id'];
?>