<?php
require_once 'db_connect.php';

$pdo = getDatabaseConnection();

// Use the $pdo connection here
try {
    $pdo = getDatabaseConnection();
    echo "✅ Database connection successful.";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage();
}