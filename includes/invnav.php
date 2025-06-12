<?php
// includes/nav.php

// Prevent direct script access for security.
if (count(get_included_files()) === 1) {
    http_response_code(403);
    die('Direct access not allowed.');
}

// --- Configuration ---
// Define which roles can access which pages (lowercase).
// This centralized array makes managing permissions straightforward.
$nav_permissions = [
    'admin_users.php'        => ['admin'],
    'admin_shops.php'        => ['admin'],
    'admin_customers.php'    => ['admin', 'manager', 'supervisor', 'viewer'],
    'admin_categories.php'   => ['admin', 'manager', 'supervisor', 'viewer'],
    'admin_products.php'     => ['admin', 'manager', 'supervisor', 'viewer'],
    'admin_units.php'        => ['admin', 'manager', 'supervisor', 'viewer'],
    'admin_invoices.php'     => ['admin', 'manager', 'supervisor', 'viewer'], // Formerly quotations
    'admin_manage_invoices.php' => ['admin'], // High-level invoice management/approval
    
    // Example for a dashboard accessible by most roles
    // 'dashboard.php' => ['admin', 'manager', 'supervisor', 'staff', 'viewer'],
];

// --- Environment Setup ---
// Get the filename of the current script to highlight the active link.
$current_page = basename($_SERVER['PHP_SELF']);

// Get the user's role from the session, converting to lowercase for consistent checks.
// The null coalescing operator provides a safe default if the session variable isn't set.
$user_role = strtolower($_SESSION['user_role'] ?? '');

// --- Helper Function ---
// A simple helper to escape output, preventing XSS vulnerabilities.
if (!function_exists('esc_nav')) {
    function esc_nav(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

?>

<nav class="admin-nav">
    <ul>
        <?php /* Example Dashboard Link
        <?php if (isset($nav_permissions['dashboard.php']) && in_array($user_role, $nav_permissions['dashboard.php'])): ?>
            <li class="<?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                <a href="dashboard.php">Dashboard</a>
            </li>
        <?php endif; ?>
        */ ?>

        <?php if (isset($nav_permissions['admin_shops.php']) && in_array($user_role, $nav_permissions['admin_shops.php'])): ?>
            <li class="<?php echo ($current_page === 'admin_shops.php') ? 'active' : ''; ?>">
                <a href="admin_shops.php">Manage Shops</a>
            </li>
        <?php endif; ?>

        <?php if (isset($nav_permissions['admin_users.php']) && in_array($user_role, $nav_permissions['admin_users.php'])): ?>
            <li class="<?php echo ($current_page === 'admin_users.php') ? 'active' : ''; ?>">
                <a href="admin_users.php">Manage Users</a>
            </li>
        <?php endif; ?>

        <?php if (isset($nav_permissions['admin_customers.php']) && in_array($user_role, $nav_permissions['admin_customers.php'])): ?>
            <li class="<?php echo ($current_page === 'admin_customers.php') ? 'active' : ''; ?>">
                <a href="admin_customers.php">Manage Customers</a>
            </li>
        <?php endif; ?>

        <?php if (isset($nav_permissions['admin_categories.php']) && in_array($user_role, $nav_permissions['admin_categories.php'])): ?>
            <li class="<?php echo ($current_page === 'admin_categories.php') ? 'active' : ''; ?>">
                <a href="admin_categories.php">Manage Categories</a>
            </li>
         <?php endif; ?>

        <?php if (isset($nav_permissions['admin_products.php']) && in_array($user_role, $nav_permissions['admin_products.php'])): ?>
            <li class="<?php echo ($current_page === 'admin_products.php') ? 'active' : ''; ?>">
                <a href="admin_products.php">Manage Products</a>
            </li>
        <?php endif; ?>

        <?php if (isset($nav_permissions['admin_units.php']) && in_array($user_role, $nav_permissions['admin_units.php'])): ?>
            <li class="<?php echo ($current_page === 'admin_units.php') ? 'active' : ''; ?>">
                <a href="admin_units.php">Manage Units</a>
            </li>
        <?php endif; ?>

        <?php // --- Invoice Management Links --- ?>
        <?php if (isset($nav_permissions['admin_invoices.php']) && in_array($user_role, $nav_permissions['admin_invoices.php'])): ?>
            <li class="<?php echo ($current_page === 'admin_invoices.php') ? 'active' : ''; ?>">
                <a href="admin_invoices.php">Manage Invoices</a>
            </li>
        <?php endif; ?>

        <?php if (isset($nav_permissions['admin_manage_invoices.php']) && in_array($user_role, $nav_permissions['admin_manage_invoices.php'])): ?>
            <li class="<?php echo ($current_page === 'admin_manage_invoices.php') ? 'active' : ''; ?>">
                <a href="admin_manage_invoices.php">Invoice Approval</a>
            </li>
        <?php endif; ?>

        <?php // --- User Session Link --- ?>
        <?php if (isset($_SESSION['user_id'])): ?>
            <li class="logout">
                <a href="logout.php">Logout (<?php echo esc_nav($_SESSION['username']); ?>)</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>

<style>
/* Nav Styling - Shadcn-inspired */

/* Main navigation container */
.admin-nav {
    background-color: var(--background);
    border-bottom: 1px solid var(--border);
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.admin-nav ul {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    flex-wrap: wrap; /* Allows items to wrap on smaller screens */
}

.admin-nav li {
    position: relative;
}

.admin-nav li a {
    display: block;
    padding: 0.75rem 1rem;
    color: var(--muted-foreground);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    transition: color 0.2s ease, background-color 0.2s ease;
}

.admin-nav li a:hover {
    color: var(--primary);
    background-color: var(--secondary);
}

/* Active link styling with animated underline */
.admin-nav li.active {
    position: relative;
}

.admin-nav li.active a {
    color: var(--primary);
    font-weight: 600;
}

.admin-nav li.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 2px;
    background-color: var(--primary);
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}

/* Logout button pushed to the right */
.admin-nav li.logout {
    margin-left: auto;
}

.admin-nav li.logout a {
    border-left: 1px solid var(--border);
}

.admin-nav li.logout a:hover {
    color: var(--destructive);
    background-color: rgba(239, 68, 68, 0.1); /* Subtle red background on hover */
}

/* Responsive styles for mobile */
@media (max-width: 768px) {
    .admin-nav ul {
        flex-direction: column;
    }
    
    .admin-nav li {
        width: 100%;
    }
    
    /* On mobile, use a left border for active state instead of underline */
    .admin-nav li.active {
        border-bottom: none;
        border-left: 3px solid var(--primary);
    }
    
    .admin-nav li.active::after {
        display: none; /* Hide animated underline on mobile */
    }
    
    .admin-nav li.logout {
        margin-left: 0;
        margin-top: 0.5rem;
        border-top: 1px solid var(--border);
    }
    
    .admin-nav li.logout a {
        border-left: none;
    }
}

/* --- Optional Enhancements (from original file) --- */

/* Badge for indicating number of items */
.admin-nav .badge {
    display: inline-block;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    background-color: var(--primary);
    color: var(--primary-foreground);
    margin-left: 0.5rem;
    line-height: 1;
}

/* Status dot indicators */
.status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 0.5rem;
    vertical-align: middle;
}

.status-dot.online { background-color: #22c55e; } /* green-500 */
.status-dot.busy { background-color: #ef4444; } /* red-500 */
.status-dot.away { background-color: #f97316; } /* orange-500 */
</style>