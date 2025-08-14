<?php
// includes/nav-main.php

// Prevent direct script access for security.
if (count(get_included_files()) === 1) {
    http_response_code(403);
    die('Direct access not allowed.');
}

// --- Configuration ---
// Define which roles can access which pages and actions (lowercase).
$nav_permissions = [
    // Standard Management Pages
    'admin_shops.php'      => ['admin'],
    'admin_users.php'      => ['admin'],
    'admin_customers.php'  => ['admin', 'manager', 'supervisor', 'viewer'],
    'admin_categories.php' => ['admin', 'manager', 'supervisor', 'viewer'],
    'admin_products.php'   => ['admin', 'manager', 'supervisor', 'viewer'],
    'admin_units.php'      => ['admin', 'manager', 'supervisor', 'viewer'],

    // Pages linked from the Action Buttons
    'admin_invoices.php'   => ['admin', 'manager', 'supervisor'],
    'admin_quotations.php' => ['admin', 'manager', 'supervisor', 'viewer'],
    'inventory/'           => ['admin', 'manager', 'supervisor'], // Fixed: removed duplicate
    'select_customer_for_statement.php' => ['admin', 'manager', 'supervisor'], // Added missing permission
];

// --- Environment Setup ---
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = strtolower($_SESSION['user_role'] ?? '');

// --- Helper Function ---
if (!function_exists('esc_nav')) {
    function esc_nav(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
?>

<nav class="main-nav">
    <!-- Standard navigation links on the left -->
    <ul class="nav-links">
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
    </ul>

    <!-- Action buttons on the right -->
    <div class="nav-actions">
        <?php if (isset($nav_permissions['inventory/']) && in_array($user_role, $nav_permissions['inventory/'])): ?>
            <a href="inventory/" class="nav-button btn-inventory">Inventory</a>
        <?php endif; ?>

        <?php if (isset($nav_permissions['select_customer_for_statement.php']) && in_array($user_role, $nav_permissions['select_customer_for_statement.php'])): ?>
            <a href="select_customer_for_statement.php" class="nav-button btn-inventory">Statements</a>
        <?php endif; ?>

        <?php if (isset($nav_permissions['admin_quotations.php']) && in_array($user_role, $nav_permissions['admin_quotations.php'])): ?>
            <a href="admin_quotations.php" class="nav-button btn-quotation">Quotations</a>
        <?php endif; ?>

        <?php if (isset($nav_permissions['admin_invoices.php']) && in_array($user_role, $nav_permissions['admin_invoices.php'])): ?>
            <a href="admin_invoices.php" class="nav-button btn-invoice">Invoices</a>
        <?php endif; ?>
        
        <?php // Logout always available if logged in ?>
        <?php if (isset($_SESSION['user_id'])): ?>
             <a href="logout.php" class="nav-button btn-logout">Logout</a>
        <?php endif; ?>
    </div>
</nav>

<style>
/* 
  Main Navigation Styling
  - This can be moved to your main stylesheet.
  - Colors are defined as CSS variables for easy theming.
*/

.main-nav {
    /* Define colors for the action buttons */
    --color-invoice: #3b82f6; /* blue-500 */
    --color-invoice-hover: #2563eb; /* blue-600 */
    --color-inventory: #10b981; /* emerald-500 */
    --color-inventory-hover: #059669; /* emerald-600 */
    --color-quotation: #8b5cf6; /* violet-500 */
    --color-quotation-hover: #7c3aed; /* violet-600 */
    --color-logout: #64748b; /* slate-500 */
    --color-logout-hover: #475569; /* slate-600 */

    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 1rem;
    background-color: var(--background);
    border-bottom: 1px solid var(--border);
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

/* --- Left Side: Standard Links --- */
.nav-links {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 0.25rem; /* Space between links */
}

.nav-links a {
    display: block;
    padding: 0.5rem 1rem;
    color: var(--muted-foreground);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.nav-links a:hover {
    color: var(--primary);
    background-color: var(--secondary);
}

.nav-links li.active a {
    color: var(--primary);
    background-color: var(--secondary);
    font-weight: 600;
}

/* --- Right Side: Action Buttons --- */
.nav-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem; /* Space between buttons */
}

.nav-button {
    display: inline-block;
    padding: 0.5rem 1.25rem;
    color: white;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    text-align: center;
    transition: background-color 0.2s ease, transform 0.1s ease;
}

.nav-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

/* Button Color Styles */
.btn-invoice { background-color: var(--color-invoice); }
.btn-invoice:hover { background-color: var(--color-invoice-hover); }

.btn-inventory { background-color: var(--color-inventory); }
.btn-inventory:hover { background-color: var(--color-inventory-hover); }

.btn-quotation { background-color: var(--color-quotation); }
.btn-quotation:hover { background-color: var(--color-quotation-hover); }

.btn-logout { background-color: var(--color-logout); }
.btn-logout:hover { background-color: var(--color-logout-hover); }

/* --- Responsive Styles for Mobile --- */
@media (max-width: 900px) { /* Adjust breakpoint as needed */
    .main-nav {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
        padding: 1rem;
    }

    .nav-links {
        flex-wrap: wrap; /* Allow links to wrap if needed */
        justify-content: center;
        gap: 0.5rem;
    }
    
    .nav-actions {
        justify-content: center;
        flex-wrap: wrap; /* Allow buttons to wrap */
        border-top: 1px solid var(--border);
        padding-top: 1rem;
    }
}
</style>