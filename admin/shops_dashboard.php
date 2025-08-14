<?php
// --- NEW: FORM PROCESSING BLOCK ---
// This must be at the very top of the file, before any HTML is sent.

// --- Configuration ---
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'supplies';
$db_port = 3306;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    if ($conn->connect_error) {
        die("Connection failed during POST: " . $conn->connect_error);
    }

    $action = $_POST['action'] ?? '';

    // --- Handle Update Shop ---
    if ($action === 'update_shop' && isset($_POST['shop_id'])) {
        $shop_id = (int)$_POST['shop_id'];
        $stmt = $conn->prepare("UPDATE shops SET name=?, shop_code=?, address_line1=?, address_line2=?, city=?, country=?, phone=?, email=?, tpin_no=? WHERE id=?");
        $stmt->bind_param("sssssssssi",
            $_POST['name'], $_POST['shop_code'], $_POST['address_line1'], $_POST['address_line2'],
            $_POST['city'], $_POST['country'], $_POST['phone'], $_POST['email'], $_POST['tpin_no'],
            $shop_id
        );
        $stmt->execute();
        $stmt->close();
        $conn->close();
        // Redirect to prevent form re-submission
        header("Location: shops_dashboard.php?shop_id=" . $shop_id);
        exit();
    }

    // --- Handle Add New Shop ---
    if ($action === 'add_shop') {
        $stmt = $conn->prepare("INSERT INTO shops (name, shop_code, address_line1, address_line2, city, country, phone, email, tpin_no, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"); // Assuming user 1 for now
        $stmt->bind_param("sssssssss",
            $_POST['name'], $_POST['shop_code'], $_POST['address_line1'], $_POST['address_line2'],
            $_POST['city'], $_POST['country'], $_POST['phone'], $_POST['email'], $_POST['tpin_no']
        );
        $stmt->execute();
        $new_shop_id = $conn->insert_id;
        $stmt->close();
        $conn->close();
        // Redirect to the newly created shop's dashboard
        header("Location: shops_dashboard.php?shop_id=" . $new_shop_id);
        exit();
    }
}

// --- DATABASE CONNECTION AND DATA FETCHING (Existing Code) ---

// Error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Create Connection ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// --- Check Connection ---
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Determine Selected Shop ---
$selected_shop_id = isset($_GET['shop_id']) ? (int)$_GET['shop_id'] : 1;

// --- 1. FETCH ALL SHOPS FOR SIDEBAR ---
$shops_list = [];
// NOTE: I added `is_active` back in, assuming you added the column as recommended.
// If not, please remove it from the SELECT statement again.
$result = $conn->query("SELECT id, name, shop_code, is_active FROM shops ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $shops_list[] = $row;
    }
    $result->free();
}

// --- 2. FETCH FULL DETAILS FOR THE SELECTED SHOP (MODIFIED) ---
$current_shop_details = null;
$stmt = $conn->prepare("SELECT * FROM shops WHERE id = ?"); // Get all columns
$stmt->bind_param("i", $selected_shop_id);
$stmt->execute();
$result = $stmt->get_result();
if ($shop_data = $result->fetch_assoc()) {
    $current_shop_details = $shop_data;
}
$stmt->close();

if (!$current_shop_details) {
    die("Error: The selected shop with ID {$selected_shop_id} was not found.");
}

$current_shop_name = $current_shop_details['name'];
$current_shop_updated_at = date("F j, Y \a\\t g:i A", strtotime($current_shop_details['updated_at']));

// --- All other data fetching logic for KPIs and charts remains the same ---
// ... (omitting the large block of existing fetching code for brevity, it stays unchanged)
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$last_30_days = date('Y-m-d H:i:s', strtotime('-30 days'));

function calculate_percentage_change($current, $previous) {
    if ($previous == 0) return ($current > 0) ? 100.0 : 0.0;
    return (($current - $previous) / $previous) * 100;
}
// -- Today's Revenue --
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_net_amount), 0) AS revenue FROM invoices WHERE shop_id = ? AND invoice_date = ?");
$stmt->bind_param("is", $selected_shop_id, $today);
$stmt->execute();
$todays_revenue = $stmt->get_result()->fetch_assoc()['revenue'];
$stmt->close();
// -- Yesterday's Revenue (for comparison) --
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_net_amount), 0) AS revenue FROM invoices WHERE shop_id = ? AND invoice_date = ?");
$stmt->bind_param("is", $selected_shop_id, $yesterday);
$stmt->execute();
$yesterdays_revenue = $stmt->get_result()->fetch_assoc()['revenue'];
$stmt->close();
$revenue_growth = calculate_percentage_change($todays_revenue, $yesterdays_revenue);
// -- Number of Invoices Today --
$stmt = $conn->prepare("SELECT COUNT(id) AS count FROM invoices WHERE shop_id = ? AND invoice_date = ?");
$stmt->bind_param("is", $selected_shop_id, $today);
$stmt->execute();
$todays_invoices_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();
// -- Number of Invoices Yesterday (for comparison) --
$stmt = $conn->prepare("SELECT COUNT(id) AS count FROM invoices WHERE shop_id = ? AND invoice_date = ?");
$stmt->bind_param("is", $selected_shop_id, $yesterday);
$stmt->execute();
$yesterdays_invoices_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();
$invoices_growth = calculate_percentage_change($todays_invoices_count, $yesterdays_invoices_count);
// -- Total Items in Stock --
$stmt = $conn->prepare("SELECT COALESCE(SUM(quantity_in_stock), 0) AS total_stock FROM shop_stock WHERE shop_id = ?");
$stmt->bind_param("i", $selected_shop_id);
$stmt->execute();
$total_items_in_stock = $stmt->get_result()->fetch_assoc()['total_stock'];
$stmt->close();
// -- Low Stock Alerts --
$stmt = $conn->prepare("SELECT COUNT(id) AS low_stock_count FROM shop_stock WHERE shop_id = ? AND quantity_in_stock <= minimum_stock_level AND minimum_stock_level > 0");
$stmt->bind_param("i", $selected_shop_id);
$stmt->execute();
$low_stock_alerts = $stmt->get_result()->fetch_assoc()['low_stock_count'];
$stmt->close();
// -- Sales Performance (Line Chart - Last 30 days) --
$sales_performance_data = array_fill_keys(array_map(function($i) { return date('M d', strtotime("-$i days")); }, range(29, 0)), 0);
$stmt = $conn->prepare("SELECT DATE(invoice_date) as day, SUM(total_net_amount) as total FROM invoices WHERE shop_id = ? AND invoice_date >= ? GROUP BY day ORDER BY day ASC");
$stmt->bind_param("is", $selected_shop_id, $last_30_days);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $day_key = date('M d', strtotime($row['day']));
    if (array_key_exists($day_key, $sales_performance_data)) {
        $sales_performance_data[$day_key] = (float)$row['total'];
    }
}
$stmt->close();
$sales_chart_labels_json = json_encode(array_keys($sales_performance_data));
$sales_chart_data_json = json_encode(array_values($sales_performance_data));
// -- Revenue by Category (Donut Chart - Last 30 days) --
$category_revenue_data = [];
$stmt = $conn->prepare("
    SELECT c.name as category_name, SUM(ii.total_amount) as category_revenue
    FROM invoice_items ii
    JOIN invoices i ON ii.invoice_id = i.id
    JOIN products p ON ii.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE i.shop_id = ? AND i.created_at >= ?
    GROUP BY c.name
    ORDER BY category_revenue DESC
");
$stmt->bind_param("is", $selected_shop_id, $last_30_days);
$stmt->execute();
$result = $stmt->get_result();
$total_category_revenue = 0;
while($row = $result->fetch_assoc()) {
    $category_revenue_data[] = $row;
    $total_category_revenue += $row['category_revenue'];
}
$stmt->close();
$category_labels = [];
$category_percentages = [];
$category_colors = ['#2F4F4F', '#556B2F', '#708090', '#A9A9A9', '#D3D3D3', '#8B4513', '#6A5ACD'];
$color_index = 0;
foreach ($category_revenue_data as $key => $data) {
    $category_revenue_data[$key]['percentage'] = ($total_category_revenue > 0) ? round(($data['category_revenue'] / $total_category_revenue) * 100) : 0;
    $category_revenue_data[$key]['color'] = $category_colors[$color_index % count($category_colors)];
    $category_labels[] = $data['category_name'];
    $category_percentages[] = $category_revenue_data[$key]['percentage'];
    $color_index++;
}
$category_labels_json = json_encode($category_labels);
$category_data_json = json_encode($category_percentages);
$category_colors_json = json_encode(array_column($category_revenue_data, 'color'));
// -- Recent Invoices --
$recent_invoices = [];
$stmt = $conn->prepare("
    SELECT i.invoice_number, i.invoice_date, i.total_net_amount, COALESCE(c.name, i.customer_name_override, 'N/A') as customer_name
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.shop_id = ?
    ORDER BY i.invoice_date DESC, i.id DESC
    LIMIT 5
");
$stmt->bind_param("i", $selected_shop_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_invoices[] = $row;
}
$stmt->close();
// -- Top Selling Products (Last 30 days) --
$top_selling_products = [];
$stmt = $conn->prepare("
    SELECT p.name as product_name, c.name as category_name, SUM(ii.quantity) as total_sold, SUM(ii.total_amount) as total_revenue
    FROM invoice_items ii
    JOIN invoices i ON ii.invoice_id = i.id
    JOIN products p ON ii.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE i.shop_id = ? AND i.created_at >= ?
    GROUP BY p.id, p.name, c.name
    ORDER BY total_sold DESC
    LIMIT 5
");
$stmt->bind_param("is", $selected_shop_id, $last_30_days);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $top_selling_products[] = $row;
}
$stmt->close();
// --- Close connection ---
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($current_shop_name) ?></title>
    <link rel="stylesheet" href="shops.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js for Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Minimal CSS to support the status indicators if shops.css is not present */
        .sidebar .shop-list .status { font-size: 0.8em; padding: 2px 8px; border-radius: 12px; color: #fff; }
        .sidebar .shop-list .status.active { background-color: #28a745; }
        .sidebar .shop-list .status.closed { background-color: #6c757d; }
        .sidebar .shop-list .status.closing { background-color: #ffc107; color: #000; }
        .sidebar .shop-list a { text-decoration: none; color: inherit; display: block; padding: 12px 15px; border-radius: 8px; margin: 4px 0; }
        .sidebar .shop-list a.active { background-color: #f0f0f0; }
        .sidebar .shop-list a:hover { background-color: #e9e9e9; }
        .growth.positive { color: #28a745; }
        .growth.negative { color: #dc3545; }
        .growth.neutral { color: #6c757d; }
        .kpi-cards .card .growth { font-size: 0.9em; margin-top: 5px; }
        .chart-wrapper { position: relative; height: 300px; width: 100%; }
        .donut-chart-container .chart-wrapper { height: 200px; width: 200px; margin: 0 auto; }
        
        /* --- NEW: CSS FOR MODAL --- */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.6);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .close-btn {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-btn:hover, .close-btn:focus {
            color: black;
        }
        .modal-form .form-group {
            margin-bottom: 15px;
        }
        .modal-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .modal-form input[type="text"], .modal-form input[type="email"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .modal-form .submit-btn {
            background-color: #2F4F4F;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        .modal-form .submit-btn:hover {
            background-color: #36454F;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <!-- NEW: Back Button -->
                <a href="inventory_dashboard.php" class="icon-button" title="Back to Main Dashboard">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h2>Shops</h2>
                <!-- MODIFIED: Added ID to cog button -->
                <button id="edit-shop-btn" class="icon-button" title="Edit Current Shop"><i class="fas fa-cog"></i></button>
            </div>
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search shops...">
            </div>
            <ul class="shop-list">
                 <?php foreach ($shops_list as $shop): ?>
                    <a href="?shop_id=<?= $shop['id'] ?>" class="<?= ($shop['id'] == $selected_shop_id) ? 'active' : '' ?>">
                        <li>
                            <span class="shop-name"><?= htmlspecialchars($shop['name']) ?></span>
                            <span class="shop-code">Code: <?= htmlspecialchars($shop['shop_code']) ?></span>
                            <?php if ($shop['is_active']): ?>
                                <span class="status active">Active</span>
                            <?php else: ?>
                                <span class="status closed">Closed</span>
                            <?php endif; ?>
                        </li>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($shops_list)): ?>
                    <li>No shops found.</li>
                <?php endif; ?>
            </ul>
            <!-- MODIFIED: Added ID to add button -->
            <button id="add-shop-btn" class="add-new-shop-btn">
                <i class="fas fa-plus"></i> Add New Shop
            </button>
        </nav>

        <!-- Main Content Area -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-title">
                    <h1><?= htmlspecialchars($current_shop_name) ?></h1>
                    <p>Last updated: <?= htmlspecialchars($current_shop_updated_at) ?></p>
                </div>
                <div class="header-actions">
                    <button class="action-btn">Last 30 Days <i class="fas fa-chevron-down"></i></button>
                    <button class="icon-button"><i class="fas fa-download"></i></button>
                    <!-- MODIFIED: Added ID to reload button -->
                    <button id="reload-btn" class="icon-button" title="Refresh Data"><i class="fas fa-sync-alt"></i></button>
                </div>
            </header>
            
            <!-- All other content (KPIs, Charts, Tables) remains the same -->
            <!-- ... -->
             <!-- KPI Cards -->
            <section class="kpi-cards">
                <div class="card">
                    <div class="card-header">
                        <span>dRevenue</span>
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h2>MWK<?= number_format($todays_revenue, 2) ?></h2>
                    <p class="growth <?= $revenue_growth >= 0 ? 'positive' : 'negative' ?>">
                        <i class="fas fa-arrow-<?= $revenue_growth >= 0 ? 'up' : 'down' ?>"></i>
                        <?= number_format(abs($revenue_growth), 1) ?>% vs. yesterday
                    </p>
                </div>
                <div class="card">
                    <div class="card-header">
                        <span>Number of Invoices</span>
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h2><?= number_format($todays_invoices_count) ?></h2>
                     <p class="growth <?= $invoices_growth >= 0 ? 'positive' : 'negative' ?>">
                        <i class="fas fa-arrow-<?= $invoices_growth >= 0 ? 'up' : 'down' ?>"></i>
                        <?= number_format(abs($invoices_growth), 1) ?>% vs. yesterday
                    </p>
                </div>
                <div class="card">
                    <div class="card-header">
                        <span>Total Items in Stock</span>
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h2><?= number_format($total_items_in_stock) ?></h2>
                    <p class="growth neutral">Live count from inventory</p>
                </div>
                <div class="card">
                    <div class="card-header">
                        <span>Low Stock Alerts</span>
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h2><?= number_format($low_stock_alerts) ?></h2>
                    <p class="growth <?= $low_stock_alerts > 0 ? 'negative' : 'positive' ?>">Items needing attention</p>
                </div>
            </section>
             <!-- Charts Section -->
            <section class="charts-section">
                <div class="card chart-card">
                    <div class="card-header">
                        <h3>Sales Performance</h3>
                        <div class="chart-legend">
                            <span><i class="fas fa-circle" style="color: #36454F;"></i> Last 30 Days</span>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="salesPerformanceChart"></canvas>
                    </div>
                </div>
                <div class="card chart-card">
                    <div class="card-header">
                        <h3>Revenue by Category</h3>
                        <button class="icon-button"><i class="fas fa-ellipsis-v"></i></button>
                    </div>
                    <div class="donut-chart-container">
                        <div class="chart-wrapper">
                            <canvas id="revenueByCategoryChart"></canvas>
                        </div>
                        <div class="donut-legend">
                            <?php foreach ($category_revenue_data as $cat): ?>
                            <div class="legend-item"><span class="legend-color" style="background-color: <?= $cat['color'] ?>;"></span><?= htmlspecialchars($cat['category_name']) ?> (<?= $cat['percentage'] ?>%)</div>
                            <?php endforeach; ?>
                            <?php if (empty($category_revenue_data)): ?>
                                <div class="legend-item">No sales data for this period.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Tables Section -->
            <section class="tables-section">
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Recent Invoices</h3>
                        <a href="#" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>INVOICE #</th>
                                <th>CUSTOMER</th>
                                <th>DATE</th>
                                <th>AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_invoices as $invoice): ?>
                            <tr>
                                <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                                <td><?= date("M j, Y", strtotime($invoice['invoice_date'])) ?></td>
                                <td>MWK<?= number_format($invoice['total_net_amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_invoices)): ?>
                                <tr><td colspan="4">No recent invoices found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                 <div class="card table-card">
                    <div class="card-header">
                        <h3>Top Selling Products</h3>
                        <a href="#" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>PRODUCT</th>
                                <th>CATEGORY</th>
                                <th>SOLD</th>
                                <th>REVENUE</th>
                            </tr>
                        </thead>
                        <tbody>
                           <?php foreach ($top_selling_products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['product_name']) ?></td>
                                <td><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>
                                <td><?= number_format($product['total_sold']) ?></td>
                                <td>MWK<?= number_format($product['total_revenue'], 2) ?></td>
                            </tr>
                           <?php endforeach; ?>
                           <?php if (empty($top_selling_products)): ?>
                                <tr><td colspan="4">No sales data found for this period.</td></tr>
                           <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- NEW: MODAL HTML STRUCTURE -->
    <div id="shopModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Shop Details</h2>
                <span class="close-btn">×</span>
            </div>
            <form id="shopForm" method="POST" action="shops_dashboard.php" class="modal-form">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="shop_id" id="formShopId">
                
                <div class="form-group">
                    <label for="name">Shop Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="shop_code">Shop Code</label>
                    <input type="text" id="shop_code" name="shop_code" required>
                </div>
                <div class="form-group">
                    <label for="address_line1">Address Line 1</label>
                    <input type="text" id="address_line1" name="address_line1">
                </div>
                <div class="form-group">
                    <label for="address_line2">Address Line 2</label>
                    <input type="text" id="address_line2" name="address_line2">
                </div>
                 <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city">
                </div>
                 <div class="form-group">
                    <label for="country">Country</label>
                    <input type="text" id="country" name="country">
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
                 <div class="form-group">
                    <label for="tpin_no">TPIN Number</label>
                    <input type="text" id="tpin_no" name="tpin_no">
                </div>

                <button type="submit" class="submit-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
    // --- NEW: Pass current shop data from PHP to JavaScript ---
    const currentShopData = <?= json_encode($current_shop_details); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        // --- All the existing chart-drawing JS code remains here ---
        // ... (omitting for brevity)
        const salesCtx = document.getElementById('salesPerformanceChart').getContext('2d');
        const salesPerformanceChart = new Chart(salesCtx, { type: 'line', data: { labels: <?= $sales_chart_labels_json ?>, datasets: [{ label: 'Sales (MWK)', data: <?= $sales_chart_data_json ?>, backgroundColor: 'rgba(54, 69, 79, 0.2)', borderColor: '#36454F', borderWidth: 2, pointBackgroundColor: '#36454F', tension: 0.3 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return '$' + value; } } } }, plugins: { legend: { display: false } } } });
        const revenueCtx = document.getElementById('revenueByCategoryChart').getContext('2d');
        const revenueByCategoryChart = new Chart(revenueCtx, { type: 'doughnut', data: { labels: <?= $category_labels_json ?>, datasets: [{ label: 'Revenue', data: <?= $category_data_json ?>, backgroundColor: <?= $category_colors_json ?>, borderColor: 'transparent', cutout: '75%' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { return `${context.label}: ${context.raw}%`; } } } } } });

        // --- NEW: JAVASCRIPT FOR BUTTONS AND MODALS ---

        // --- Reload Button ---
        const reloadBtn = document.getElementById('reload-btn');
        if(reloadBtn) {
            reloadBtn.addEventListener('click', function() {
                window.location.reload();
            });
        }

        // --- Modal Elements ---
        const modal = document.getElementById('shopModal');
        const editBtn = document.getElementById('edit-shop-btn');
        const addBtn = document.getElementById('add-shop-btn');
        const closeBtn = document.querySelector('.close-btn');
        const shopForm = document.getElementById('shopForm');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const formShopId = document.getElementById('formShopId');

        // --- Open Edit Modal ---
        editBtn.addEventListener('click', function() {
            modalTitle.textContent = 'Edit Shop Details';
            formAction.value = 'update_shop';
            formShopId.value = currentShopData.id;

            // Pre-fill the form with current shop data
            shopForm.elements['name'].value = currentShopData.name || '';
            shopForm.elements['shop_code'].value = currentShopData.shop_code || '';
            shopForm.elements['address_line1'].value = currentShopData.address_line1 || '';
            shopForm.elements['address_line2'].value = currentShopData.address_line2 || '';
            shopForm.elements['city'].value = currentShopData.city || '';
            shopForm.elements['country'].value = currentShopData.country || '';
            shopForm.elements['phone'].value = currentShopData.phone || '';
            shopForm.elements['email'].value = currentShopData.email || '';
            shopForm.elements['tpin_no'].value = currentShopData.tpin_no || '';
            
            modal.style.display = 'block';
        });

        // --- Open Add Modal ---
        addBtn.addEventListener('click', function() {
            modalTitle.textContent = 'Add New Shop';
            formAction.value = 'add_shop';
            shopForm.reset(); // Clear any previous data
            formShopId.value = ''; // No ID for a new shop
            
            modal.style.display = 'block';
        });

        // --- Close Modal Logic ---
        const closeModal = function() {
            modal.style.display = 'none';
        }
        closeBtn.addEventListener('click', closeModal);
        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                closeModal();
            }
        });
    });
    </script>
</body>
</html>