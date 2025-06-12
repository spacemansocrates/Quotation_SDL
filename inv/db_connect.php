<?php
// db_connect.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // No password as specified
define('DB_NAME', 'supplies'); // Your database name

// Establish database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to UTF-8
$conn->set_charset("utf8mb4");

// You can optionally include this file at the top of any script that needs database access.
// Example: require_once 'path/to/db_connect.php';
?>