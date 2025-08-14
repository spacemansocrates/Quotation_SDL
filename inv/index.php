<?php
// Your main project index.php (e.g., /your_project_folder/index.php)
session_start(); // Start session at the very beginning

// Include the database connection
require_once __DIR__ . '/db_connect.php';

// Include the main controller
require_once __DIR__ . '/controllers/InvoiceController.php';

// Instantiate the controller and handle the request
$controller = new InvoiceController($conn);
$controller->handleRequest();

// Close the database connection when done (optional, PHP closes automatically)
$conn->close();
?>