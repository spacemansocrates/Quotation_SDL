
<?php
// --- DATABASE CONNECTION ---
$servername = 'srv582.hstgr.io';
$username = "u789944046_socrates";
$password = "Naho1386";
$dbname = "u789944046_suppliesdirect";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

ob_start();
session_start();

// FIXED: Enhanced authentication check with admin role verification
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

// FIXED: Check if user is admin - redirect if not
if ($_SESSION['user_role'] !== 'admin') {
    // Redirect non-admin users to appropriate dashboard or access denied page
    header("Location: access_denied.php"); // or user_dashboard.php
    exit();
}

$userId = (int)$_SESSION['user_id'];
$isAdmin = true; // We know user is admin at this point

// --- HELPER FUNCTIONS ---

// A simple function to make timestamps human-readable (e.g., "2 hours ago")
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return "invalid date";
    }

    $seconds_ago = time() - $timestamp;

    if ($seconds_ago < 0) {
        return 'in the future';
    }
    if ($seconds_ago < 60) {
        return "just now";
    }

    $intervals = [
        'year'   => 31536000,
        'month'  => 2592000,
        'week'   => 604800,
        'day'    => 86400,
        'hour'   => 3600,
        'minute' => 60
    ];

    foreach ($intervals as $unit => $seconds) {
        $count = floor($seconds_ago / $seconds);
        if ($count >= 1) {
            return $count . ' ' . $unit . ($count > 1 ? 's' : '') . ' ago';
        }
    }

    return "just now";
}

// --- PHP DATA FETCHING FUNCTIONS ---

/**
 * Gets the four main summary statistics for the dashboard cards.
 * Uses subqueries for efficiency in a single DB call.
 */
function getSummaryData($conn) {
    $sql = "SELECT
        (SELECT SUM(total_paid) FROM invoices) as total_revenue,
        (SELECT COUNT(id) FROM invoices WHERE status IN ('Sent', 'Overdue', 'Partially Paid')) as pending_invoices,
        (SELECT COUNT(id) FROM quotations WHERE status IN ('Sent', 'Approved')) as active_quotations,
        (SELECT COUNT(id) FROM products) as inventory_items,

        -- CORRECTED: Sum the counts from both new stock tables
        (
            (SELECT COUNT(*) FROM warehouse_stock WHERE quantity_in_stock <= minimum_stock_level AND minimum_stock_level > 0)
            +
            (SELECT COUNT(*) FROM shop_stock WHERE quantity_in_stock <= minimum_stock_level AND minimum_stock_level > 0)
        ) as low_stock_count,
        
        (SELECT SUM(amount_paid) FROM payments WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())) as current_month_revenue,
        (SELECT SUM(amount_paid) FROM payments WHERE YEAR(payment_date) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(payment_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)) as last_month_revenue
    ";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error in getSummaryData: " . $conn->error);
    }
    $data = $result->fetch_assoc();

    return [
        'total_revenue' => $data['total_revenue'] ?? 0,
        'pending_invoices' => $data['pending_invoices'] ?? 0,
        'quotations' => $data['active_quotations'] ?? 0,
        'inventory_items' => $data['inventory_items'] ?? 0,
        'low_stock_count' => $data['low_stock_count'] ?? 0,
        'current_month_revenue' => $data['current_month_revenue'] ?? 0,
        'last_month_revenue' => $data['last_month_revenue'] ?? 0,
    ];
}

/**
 * Fetches the 5 most recent invoices with customer names.
 */
function getRecentInvoices($conn) {
    $sql = "SELECT
                i.id as invoice_id,
                i.invoice_number,
                COALESCE(c.name, i.customer_name_override) as client_name,
                i.invoice_date,
                i.total_net_amount,
                i.status
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            ORDER BY i.invoice_date DESC, i.id DESC
            LIMIT 5";
    
    $result = $conn->query($sql);
    $invoices = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $invoices[] = $row;
        }
    }
    return $invoices;
}

/**
 * Fetches up to 5 products that are low on stock from EITHER warehouses OR shops.
 * It now includes the location name in the results to be displayed.
 */
function getLowStockItems($conn) {
    $sql = "
        (SELECT
            p.sku,
            p.name,
            ws.quantity_in_stock,
            ws.minimum_stock_level,
            w.name as location_name
        FROM warehouse_stock ws
        JOIN products p ON ws.product_id = p.id
        JOIN warehouses w ON ws.warehouse_id = w.id
        WHERE ws.quantity_in_stock <= ws.minimum_stock_level AND ws.minimum_stock_level > 0)

        UNION ALL

        (SELECT
            p.sku,
            p.name,
            ss.quantity_in_stock,
            ss.minimum_stock_level,
            s.name as location_name
        FROM shop_stock ss
        JOIN products p ON ss.product_id = p.id
        JOIN shops s ON ss.shop_id = s.id
        WHERE ss.quantity_in_stock <= ss.minimum_stock_level AND ss.minimum_stock_level > 0)

        ORDER BY (minimum_stock_level - quantity_in_stock) DESC
        LIMIT 5";

    $result = $conn->query($sql);
    if (!$result) {
        die("Error in getLowStockItems: " . $conn->error);
    }

    $items = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    return $items;
}

/**
 * Fetches the 5 most recent activities from the log, now including the username.
 */
function getRecentActivity($conn) {
    $sql = "SELECT username_snapshot, action_type, target_entity, description, timestamp
            FROM activity_log
            ORDER BY timestamp DESC
            LIMIT 5";
    
    $result = $conn->query($sql);
    $activities = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
    }
    return $activities;
}

/**
 * Fetches revenue data for the last 6 months for the bar chart.
 * It sums `amount_paid` from the `payments` table, which reflects actual income.
 */
function getChartData($conn) {
    $labels = [];
    $data = [];
    
    $sql = "SELECT DATE_FORMAT(payment_date, '%b') as month_label, SUM(amount_paid) as monthly_total
            FROM payments
            WHERE payment_date >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY DATE_FORMAT(payment_date, '%Y-%m') ASC";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $labels[] = $row['month_label'];
            $data[] = $row['monthly_total'];
        }
    }
    return ['labels' => $labels, 'data' => $data];
}

// --- FETCH DATA FOR THE VIEW ---
// FIXED: Use the actual logged-in user ID from session instead of hardcoded value
$stmt = $conn->prepare("SELECT full_name, role FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc() ?? ['full_name' => 'Guest', 'role' => 'admin'];
$stmt->close();

$summary = getSummaryData($conn);
$recentInvoices = getRecentInvoices($conn);
$lowStockItems = getLowStockItems($conn);
$recentActivity = getRecentActivity($conn);
$chartData = getChartData($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplies Direct Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* gray-50 */
        }
    </style>
</head>
<body class="flex h-screen">

    <!-- Sidebar -->
    <aside id="sidebar" class="w-64 bg-white border-r border-gray-200 fixed inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
        <div class="p-6">
            <h1 class="text-2xl font-bold text-gray-800">Supplies Direct</h1>
        </div>
        <nav class="mt-6">
            <ul>
                <!-- Active link -->
                <li>
                    <a href="#" class="flex items-center px-6 py-3 text-gray-700 bg-gray-100 font-semibold">
                        <i class="fas fa-gauge-high fa-fw h-5 w-5"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>
                </li>

                <li class="mt-4 px-6 text-xs uppercase font-semibold tracking-wider text-gray-500">Finance</li>
                <li>
                    <a href="admin_invoices.php" class="flex items-center px-6 py-2 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-file-invoice-dollar fa-fw h-5 w-5"></i>
                        <span class="ml-3">Invoices</span>
                    </a>
                </li>
                <li>
                    <a href="admin_quotations.php" class="flex items-center px-6 py-2 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-receipt fa-fw h-5 w-5"></i>
                        <span class="ml-3">Quotations</span>
                    </a>
                </li>
                <li>
                    <a href="record_payment.php" class="flex items-center px-6 py-2 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-credit-card fa-fw h-5 w-5"></i>
                        <span class="ml-3">Payments</span>
                    </a>
                </li>

                <li class="mt-4 px-6 text-xs uppercase font-semibold tracking-wider text-gray-500">Inventory</li>
                <li>
                    <a href="admin_products" class="flex items-center px-6 py-2 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-box-archive fa-fw h-5 w-5"></i>
                        <span class="ml-3">Products</span>
                    </a>
                </li>
                <li>
                    <a href="inventory_dashboard.php" class="flex items-center px-6 py-2 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-warehouse fa-fw h-5 w-5"></i>
                        <span class="ml-3">Stock Management</span>
                    </a>
                </li>
                
                <li class="mt-4 px-6 text-xs uppercase font-semibold tracking-wider text-gray-500">Settings</li>
                <li>
                    <a href="cust.php" class="flex items-center px-6 py-2 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-users fa-fw h-5 w-5"></i>
                        <span class="ml-3">Customers</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="flex items-center px-6 py-2 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-gear fa-fw h-5 w-5"></i>
                        <span class="ml-3">System Settings</span>
                    </a>
                </li>
                <!-- ADDED: Logout link -->
                <li class="mt-4">
                    <a href="/Quotation_SDL/logout.php" class="flex items-center px-6 py-2 text-red-600 hover:bg-red-50">
                        
                        <i class="fas fa-sign-out-alt fa-fw h-5 w-5"></i>
                        <span class="ml-3">Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>
    <!-- Main Content -->
  <main class="flex-1 overflow-y-auto lg:ml-64">
        <div class="px-6 lg:px-10 py-8">
            <!-- Header -->
          <!-- Header -->
<header class="flex justify-between items-center mb-8">
    <div class="flex items-center">
        <!-- NEW: Hamburger Menu Button for Mobile -->
        <button id="mobile-menu-button" class="lg:hidden text-gray-500 hover:text-gray-700 p-2 -ml-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
            </svg>
        </button>

        <div class="ml-2 lg:ml-0">
            <h2 class="text-2xl font-bold text-gray-800">Admin Dashboard</h2>
            <p class="text-gray-500">Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>!</p>
        </div>
    </div>
    <div class="flex items-center space-x-5">
        <!-- ... (the rest of your header content remains the same) ... -->
        <button class="text-gray-500 hover:text-gray-700">
             <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </button>
        <button class="text-gray-500 hover:text-gray-700">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
        </button>
        <div class="flex items-center space-x-3">
            <img class="h-10 w-10 rounded-full object-cover bg-gray-200 text-gray-500" src="https://placehold.co/40x40/E2E8F0/4A5568?text=<?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>" alt="Admin Profile Picture">
            <div>
                <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></p>
                <p class="text-xs text-gray-500"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></p>
            </div>
        </div>
    </div>
</header>

            <!-- Summary Cards -->
           <!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <!-- MODIFIED: Total Revenue Card -->
<div class="overflow-x-auto">
    <table class="w-full text-sm text-left text-gray-500">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th scope="col" class="py-3 px-4">SKU</th>
                <th scope="col" class="py-3 px-4">Name</th>
                <!-- NEW: Added Location Column -->
                <th scope="col" class="py-3 px-4">Location</th>
                <th scope="col" class="py-3 px-4">Stock</th>
                <th scope="col" class="py-3 px-4">Min. Level</th>
                <th scope="col" class="py-3 px-4">Action</th>
            </tr>
        </thead>
        <tbody>
           <?php foreach ($lowStockItems as $item): ?>
            <tr class="bg-white border-b">
                <td class="py-4 px-4 font-medium text-gray-900"><?php echo htmlspecialchars($item['sku']); ?></td>
                <td class="py-4 px-4"><?php echo htmlspecialchars($item['name']); ?></td>
                <!-- NEW: Displaying the location name -->
                <td class="py-4 px-4 text-gray-600"><?php echo htmlspecialchars($item['location_name']); ?></td>
                <td class="py-4 px-4 font-medium text-red-600"><?php echo htmlspecialchars($item['quantity_in_stock']); ?></td>
                <td class="py-4 px-4"><?php echo htmlspecialchars($item['minimum_stock_level']); ?></td>
                <td class="py-4 px-4">
                    <a href="#" class="font-medium text-blue-600 hover:underline">Reorder</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lowStockItems)): ?>
                <!-- MODIFIED: colspan is now 6 -->
                <tr><td colspan="6" class="text-center py-4 text-gray-500">No items are low on stock.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

    <!-- Pending Invoices Card (Unchanged) -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm text-gray-500">Pending Invoices</p>
                <p class="text-3xl font-bold text-gray-800"><?php echo $summary['pending_invoices']; ?></p>
            </div>
             <div class="bg-yellow-100 text-yellow-600 p-2 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
        </div>
    </div>
    
    <!-- Active Quotations Card (Unchanged) -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm text-gray-500">Active Quotations</p>
                <p class="text-3xl font-bold text-gray-800"><?php echo $summary['quotations']; ?></p>
            </div>
             <div class="bg-green-100 text-green-600 p-2 rounded-lg">
               <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
            </div>
        </div>
    </div>
    
    <!-- Inventory Items Card (Unchanged) -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm text-gray-500">Inventory Items</p>
                <p class="text-3xl font-bold text-gray-800"><?php echo $summary['inventory_items']; ?></p>
                <?php if ($summary['low_stock_count'] > 0): ?>
                <p class="text-sm text-red-500 flex items-center mt-1">
                    <?php echo $summary['low_stock_count']; ?> low stock item(s)
                </p>
                <?php endif; ?>
            </div>
             <div class="bg-red-100 text-red-600 p-2 rounded-lg">
               <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
            </div>
        </div>
    </div>
</div>
  <!-- Main section with Charts and Recent Activity -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
    <!-- Revenue Overview (Unchanged) -->
    <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Revenue Overview</h3>
            <span class="text-sm text-gray-600">Last 6 Months</span>
        </div>
        <div class="h-80">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Recent Activity (UPDATED) -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Recent Activity</h3>
            <a href="view_activity_log.php" class="text-sm text-blue-600 hover:underline">View all</a>
        </div>
        <div class="space-y-4">
            <?php foreach ($recentActivity as $activity): ?>
            <div class="flex items-start">
                <?php
                    // Icon logic remains the same
                    $icon_bg_color = 'bg-gray-100 text-gray-600';
                    $icon_svg = '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>';
                    switch ($activity['target_entity']) {
                        case 'invoices': case 'payments':
                            $icon_bg_color = 'bg-blue-100 text-blue-600';
                            $icon_svg = '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
                            break;
                        case 'quotations':
                            $icon_bg_color = 'bg-green-100 text-green-600';
                            $icon_svg = '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>';
                            break;
                        case 'products':
                            $icon_bg_color = 'bg-purple-100 text-purple-600';
                            $icon_svg = '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>';
                            break;
                        case 'customers':
                            $icon_bg_color = 'bg-pink-100 text-pink-600';
                            $icon_svg = '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>';
                            break;
                    }
                ?>
                <div class="<?php echo $icon_bg_color; ?> p-2 rounded-full mr-3 flex-shrink-0"><?php echo $icon_svg; ?></div>
                <div>
                    <!-- This is the line that now includes the username -->
                    <p class="text-sm text-gray-800">
                        <span class="font-semibold"><?php echo htmlspecialchars($activity['username_snapshot'] ?? 'System'); ?></span>
                        <?php echo htmlspecialchars(lcfirst(str_replace('Created', 'created', $activity['description']))); ?>
                    </p>
                    <p class="text-xs text-gray-500"><?php echo time_ago($activity['timestamp']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentActivity)): ?>
                <p class="text-sm text-gray-500">No recent activity found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
            <!-- Tables Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
                <!-- Recent Invoices -->
               <!-- Recent Invoices -->
<div class="bg-white p-6 rounded-lg shadow">
     <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Recent Invoices</h3>
        <a href="admin_invoices.php" class="text-sm text-blue-600 hover:underline">View all</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-500">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                <tr>
                    <th scope="col" class="py-3 px-4">Invoice #</th>
                    <th scope="col" class="py-3 px-4">Client</th>
                    <th scope="col" class="py-3 px-4">Date</th>
                    <th scope="col" class="py-3 px-4">Amount</th>
                    <th scope="col" class="py-3 px-4">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentInvoices as $invoice): ?>
                <tr class="bg-white border-b hover:bg-gray-50">
                    <!-- MODIFIED: The invoice number is now a link -->
                    <td class="py-4 px-4 font-medium text-gray-900">
                        <a href="view_invoice_details.php?id=<?php echo $invoice['invoice_id']; ?>" class="text-blue-600 hover:underline">
                            <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                        </a>
                    </td>
                    <td class="py-4 px-4"><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                    <td class="py-4 px-4"><?php echo date("M d, Y", strtotime($invoice['invoice_date'])); ?></td>
                    <td class="py-4 px-4 font-medium">$<?php echo number_format($invoice['total_net_amount'], 2); ?></td>
                    <td class="py-4 px-4">
                        <?php
                            $status = $invoice['status'];
                            $badge_class = 'bg-gray-100 text-gray-800'; // Default
                            if ($status == 'Paid') $badge_class = 'bg-green-100 text-green-800';
                            if (in_array($status, ['Sent', 'Overdue'])) $badge_class = 'bg-yellow-100 text-yellow-800';
                            if ($status == 'Partially Paid') $badge_class = 'bg-blue-100 text-blue-800';
                            if ($status == 'Cancelled') $badge_class = 'bg-red-100 text-red-800';
                        ?>
                        <span class="<?php echo $badge_class; ?> text-xs font-medium mr-2 px-2.5 py-0.5 rounded-full"><?php echo htmlspecialchars($status); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentInvoices)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-gray-500">No recent invoices found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

                <!-- Low Stock Items -->
            <div class="overflow-x-auto">
    <table class="w-full text-sm text-left text-gray-500">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th scope="col" class="py-3 px-4">SKU</th>
                <th scope="col" class="py-3 px-4">Name</th>
                <th scope="col" class="py-3 px-4">Stock</th>
                <th scope="col" class="py-3 px-4">Min. Level</th>
                <th scope="col" class="py-3 px-4">Action</th>
            </tr>
        </thead>
        <tbody>
           <?php foreach ($lowStockItems as $item): ?>
            <tr class="bg-white border-b">
                <td class="py-4 px-4 font-medium text-gray-900"><?php echo htmlspecialchars($item['sku']); ?></td>
                
                <!-- MODIFIED: Display name and location in the same cell -->
                <td class="py-4 px-4">
                    <?php echo htmlspecialchars($item['name']); ?>
                    <span class="block text-xs text-gray-500">(<?php echo htmlspecialchars($item['location_name']); ?>)</span>
                </td>

                <td class="py-4 px-4 font-medium text-red-600"><?php echo htmlspecialchars($item['quantity_in_stock']); ?></td>
                <td class="py-4 px-4"><?php echo htmlspecialchars($item['minimum_stock_level']); ?></td>
                <td class="py-4 px-4">
                    <a href="#" class="font-medium text-blue-600 hover:underline">Reorder</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lowStockItems)): ?>
                <tr><td colspan="5" class="text-center py-4 text-gray-500">No items are low on stock.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
            </div>

        </div>
    </main>
    
    <!-- JAVASCRIPT SECTION -->
    <script>
   // Chart.js implementation for Revenue Overview (this part is existing)
        const revenueData = {
            labels: <?php echo json_encode($chartData['labels']); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode($chartData['data']); ?>,
                backgroundColor: 'rgb(75, 85, 99)',
                borderColor: 'rgb(75, 85, 99)',
                borderWidth: 1,
                borderRadius: 4,
                barPercentage: 0.6,
                categoryPercentage: 0.7
            }]
        };
        const config = { type: 'bar', data: revenueData, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { let label = context.dataset.label || ''; if (label) { label += ': '; } if (context.parsed.y !== null) { label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed.y); } return label; } } } }, scales: { y: { beginAtZero: true, ticks: { callback: function(value, index, ticks) { return '$' + (value / 1000) + 'k'; } } }, x: { grid: { display: false } } } } };
        const revenueChart = new Chart( document.getElementById('revenueChart'), config );

        // NEW: Sidebar Toggle Functionality
        const sidebar = document.getElementById('sidebar');
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const overlay = document.getElementById('sidebar-overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        mobileMenuButton.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

    </script>
</body>
</html>
<?php
// Close the database connection at the end of the script
$conn->close();
?>