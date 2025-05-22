<?php
// /includes/config.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set a default timezone if not already set in php.ini
date_default_timezone_set('UTC'); // Or your server's timezone

// For demonstration, let's mock a logged-in user ID
// IN A REAL APPLICATION, THIS MUST COME FROM A SECURE LOGIN SYSTEM
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Example user ID. Replace with actual login logic.
    $_SESSION['username'] = 'admin_user'; // Example username
}

// You can define other global constants or settings here
// define('BASE_URL', 'http://localhost/your_project_folder/');
?>