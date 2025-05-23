<?php
// includes/nav.php

// Prevent direct script access.
if (count(get_included_files()) === 1) {
    http_response_code(403);
    die('Direct access not allowed.');
}

// --- Configuration ---
// Define which roles can access which pages (lowercase)
$nav_permissions = [
    'admin_users.php' => ['admin'],
    'admin_customers.php' => ['admin', 'manager', 'supervisor', 'viewer'], // Viewer can list
    'admin_categories.php' => ['admin', 'manager', 'supervisor', 'viewer'],
    'admin_shops.php' => ['admin', 'manager', 'supervisor', 'viewer'], // Viewer can list
    'admin_products.php' => ['admin', 'manager', 'supervisor', 'viewer'], // Viewer can list
    'admin_units.php' => ['admin', 'manager', 'supervisor', 'viewer'], // Viewer can list
       'admin_quotations.php' => ['admin', 'manager', 'supervisor', 'viewer'], // Viewer can list
         'admin_manage_quotations.php' => ['admin'], // Viewer can list
    // Add other pages like 'dashboard.php' here if needed
    // 'dashboard.php' => ['admin', 'manager', 'supervisor', 'staff', 'viewer'],
];

// --- Get current page and user role ---
$current_page = basename($_SERVER['PHP_SELF']); // Get the filename of the current script
$user_role = strtolower($_SESSION['user_role'] ?? ''); // Get user role, default to empty string if not set

// --- Helper function (optional, use htmlspecialchars directly if preferred) ---
if (!function_exists('esc_nav')) { // Prevent redeclaration if included elsewhere
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

        <?php // Add more links here following the same pattern ?>
        <?php if (isset($nav_permissions['admin_units.php']) && in_array($user_role, $nav_permissions['admin_units.php'])): ?>
        <li class="<?php echo ($current_page === 'admin_units.php') ? 'active' : ''; ?>">
            <a href="admin_units.php">Manage Units</a>
        </li>
    <?php endif; ?>
           <?php if (isset($nav_permissions['admin_quotations.php']) && in_array($user_role, $nav_permissions['admin_quotations.php'])): ?>
        <li class="<?php echo ($current_page === 'admin_quotations.php') ? 'active' : ''; ?>">
            <a href="admin_quotations.php">Manage Quotations</a>
        </li>
    <?php endif; ?>
        <?php if (isset($nav_permissions['admin_manage_quotations.php']) && in_array($user_role, $nav_permissions['admin_manage_quotations.php'])): ?>
        <li class="<?php echo ($current_page === 'admin_manage_quotations.php') ? 'active' : ''; ?>">
            <a href="admin_manage_quotations.php">Quotations Approval</a>
        </li>
    <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <li class="logout">
                <a href="logout.php">Logout (<?php echo esc_nav($_SESSION['username']); ?>)</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<style>/* Nav Styling - Shadcn-inspired */

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
    flex-wrap: wrap;
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
    transition: all 0.2s ease;
}

.admin-nav li a:hover {
    color: var(--primary);
    background-color: var(--secondary);
}

.admin-nav li.active {
    border-bottom: 2px solid var(--primary);
}

.admin-nav li.active a {
    color: var(--primary);
    font-weight: 600;
}

/* Logout button styling */
.admin-nav li.logout {
    margin-left: auto; /* Push to right */
}

.admin-nav li.logout a {
    color: var(--muted-foreground);
    border-left: 1px solid var(--border);
}

.admin-nav li.logout a:hover {
    color: var(--destructive);
    background-color: rgba(239, 68, 68, 0.1);
}

/* Responsive styles */
@media (max-width: 768px) {
    .admin-nav ul {
        flex-direction: column;
    }
    
    .admin-nav li {
        width: 100%;
    }
    
    .admin-nav li.active {
        border-bottom: none;
        border-left: 2px solid var(--primary);
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

/* Role-specific styling for navigation links */
.role-admin .admin-nav li.admin-only a {
    position: relative;
}

.role-admin .admin-nav li.admin-only a::after {
    content: '•';
    color: var(--role-admin);
    position: absolute;
    right: 0.5rem;
    top: 0.75rem;
}

/* Animation for active indicator */
.admin-nav li.active {
    position: relative;
    overflow: hidden;
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
        transform: translateX(-100%);
    }
    to {
        transform: translateX(0);
    }
}

/* Badge for indicating number of items (optional feature) */
.admin-nav .badge {
    display: inline-block;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    background-color: var(--primary);
    color: var(--primary-foreground);
    margin-left: 0.5rem;
}

/* Status dot indicators (can be added to links) */
.status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 0.5rem;
}

.status-dot.online {
    background-color: var(--role-manager);
}

.status-dot.busy {
    background-color: var(--role-admin);
}

.status-dot.away {
    background-color: var(--role-supervisor);
}</style>