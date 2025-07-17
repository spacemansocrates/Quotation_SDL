<?php
// includes/auth_check.php
// This script must be included at the very top of any protected page.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check if user is logged in.
// If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    // Note: The base path might need adjustment depending on your server setup.
    // '/Quotation_SDL/' is the root of your project.
    header('Location: /Quotation_SDL/index.php?error=not_logged_in');
    exit();
}

// 2. Check if the `$allowed_roles` variable has been set on the page that included this script.
// This is a safeguard against developer error.
if (!isset($allowed_roles) || !is_array($allowed_roles)) {
    // This is a configuration error on the page itself.
    die('<strong>Configuration Error:</strong> The variable $allowed_roles is not set correctly on this page.');
}

// 3. Check if the logged-in user's role is in the list of allowed roles.
// If not, they are not authorized.
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    // Redirect to a '403 Forbidden' error page.
    header('Location: /Quotation_SDL/errors/403.php');
    exit();
}

// If the script reaches this point, the user is authenticated and authorized to view the page.