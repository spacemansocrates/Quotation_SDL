<?php
// Force PHP to show all errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// =================================================================
// 1. PHP SETUP & DATA FETCHING
// =================================================================

// --- DATABASE CONNECTION ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "supplies";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- DATA FETCHING FUNCTIONS ---

/**
 * Fetches the four main Key Performance Indicators (KPIs) for the dashboard.
 */
/**
 * Fetches the four main Key Performance Indicators (KPIs) for the dashboard.
 * MODIFIED: Provides a more accurate Total Inventory Value calculation.
 */
function getDashboardKpis($conn) {
    $sql = "SELECT
        -- THIS IS THE NEW, ACCURATE VALUE CALCULATION
        (SELECT SUM(all_stock.quantity_in_stock * p.default_unit_price)
         FROM (
             -- First, get all stock from warehouses
             SELECT product_id, quantity_in_stock FROM warehouse_stock
             UNION ALL
             -- Then, add all stock from shops
             SELECT product_id, quantity_in_stock FROM shop_stock
         ) as all_stock
         -- Join the combined stock list with the products table to get the price
         JOIN products p ON all_stock.product_id = p.id
        ) as total_value,

        -- The other KPIs remain the same
        (SELECT COUNT(*) FROM stock_transfers WHERE status = 'Pending') as pending_transfers,
        (SELECT COUNT(id) FROM products) as total_products,
        ((SELECT COUNT(*) FROM warehouse_stock WHERE quantity_in_stock <= minimum_stock_level AND minimum_stock_level > 0) +
         (SELECT COUNT(*) FROM shop_stock WHERE quantity_in_stock <= minimum_stock_level AND minimum_stock_level > 0)) as low_stock_items
    ";

    $result = $conn->query($sql);
    if (!$result) {
        die("Error in getDashboardKpis: " . $conn->error);
    }
    
    // The return statement fetches the data and provides default values if the query is empty
    return $result->fetch_assoc() ?? [
        'total_value' => 0,
        'pending_transfers' => 0,
        'total_products' => 0,
        'low_stock_items' => 0
    ];
}

/**
 * Fetches data for the dynamic Alert Panel.
 */
/**
 * Fetches data for the dynamic Alert Panel by querying for specific conditions.
 */
/**
 * Fetches data for the dynamic Alert Panel, respecting user dismissals and a limit.
 */
function getAlertPanelData($conn, $limit = 5) {
    // Assume a user is logged in.
    $current_user_id = 1; // Placeholder

    // 1. Get all dismissed alert keys for the current user
    $dismissed_keys = [];
    $sql_dismissed = "SELECT alert_key FROM alert_dismissals WHERE user_id = ?";
    $stmt_dismissed = $conn->prepare($sql_dismissed);
    $stmt_dismissed->bind_param("i", $current_user_id);
    $stmt_dismissed->execute();
    $result_dismissed = $stmt_dismissed->get_result();
    while ($row = $result_dismissed->fetch_assoc()) {
        $dismissed_keys[$row['alert_key']] = true;
    }
    $stmt_dismissed->close();

    // 2. Find all potential alerts and filter them
    $all_potential_alerts = [];

    // --- Alert Type 1: Pending Transfers ---
    $sql_pending = "SELECT id, transfer_reference FROM stock_transfers WHERE status = 'Pending'";
    $result_pending = $conn->query($sql_pending);
    while ($row = $result_pending->fetch_assoc()) {
        $key = "pending_transfer_" . $row['id'];
        if (!isset($dismissed_keys[$key])) { // Check if this specific alert has been dismissed
            $all_potential_alerts[] = [
                'alert_key' => $key, 'type' => 'pending', 'icon' => 'fa-solid fa-clock-rotate-left',
                'title' => 'Pending Approval', 'details' => "Transfer #{$row['transfer_reference']} needs action.",
                'action_text' => 'Review', 'action_link' => '#', 'time' => 'Action Required'
            ];
        }
    }

    // --- Alert Type 2: Critical Low Stock (quantity <= minimum) ---
    // We check both warehouses and shops
    $sql_low_stock = "
        SELECT 'product' as item_type, p.id, p.name as item_name, l.name as location_name
        FROM warehouse_stock ws JOIN products p ON ws.product_id = p.id JOIN warehouses l ON ws.warehouse_id = l.id
        WHERE ws.quantity_in_stock <= ws.minimum_stock_level AND ws.minimum_stock_level > 0
        UNION ALL
        SELECT 'product' as item_type, p.id, p.name as item_name, l.name as location_name
        FROM shop_stock ss JOIN products p ON ss.product_id = p.id JOIN shops l ON ss.shop_id = l.id
        WHERE ss.quantity_in_stock <= ss.minimum_stock_level AND ss.minimum_stock_level > 0
    ";
    $result_low_stock = $conn->query($sql_low_stock);
    while ($row = $result_low_stock->fetch_assoc()) {
        $key = "low_stock_product_" . $row['id'] . "_location_" . $row['location_name']; // Make key unique to product & location
        $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key); // Sanitize key
        if (!isset($dismissed_keys[$key])) {
            $all_potential_alerts[] = [
                'alert_key' => $key, 'type' => 'critical', 'icon' => 'fa-solid fa-circle-exclamation',
                'title' => 'Low Stock Warning', 'details' => "{$row['item_name']} is low at {$row['location_name']}.",
                'action_text' => 'View Item', 'action_link' => '#', 'time' => 'Inventory Alert'
            ];
        }
    }
    
    // 3. Return only the top N alerts
    return array_slice($all_potential_alerts, 0, $limit);
}
/**
 * Fetches the top selling products based on quantity sold in the last 30 days.
 */
function getTopSellingProductsData($conn, $limit = 5) {
    // This query joins invoice items with products, sums the quantities sold for each product,
    // filters for invoices within the last 30 days, groups by product,
    // orders by the total quantity sold, and takes the top N results.
    $sql = "SELECT
                p.name as product_name,
                SUM(ii.quantity) as total_quantity_sold
            FROM invoice_items ii
            JOIN products p ON ii.product_id = p.id
            JOIN invoices i ON ii.invoice_id = i.id
            WHERE i.invoice_date >= CURDATE() - INTERVAL 30 DAY
            GROUP BY p.id, p.name
            ORDER BY total_quantity_sold DESC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    // We need to reverse the array because Chart.js displays the first item at the bottom.
    // For a top-to-bottom ranking, the best seller needs to be last in the data array.
    return array_reverse($products);
}
/**
 * Fetches all warehouses with enhanced, aggregated stats for display cards.
 */
function getWarehouseData($conn) {
    $max_items_capacity = 25000;

    $sql = "SELECT
                w.id,
                w.warehouse_code,
                w.name,
                w.is_active,
                COALESCE(SUM(ws.quantity_in_stock), 0) as total_items,
                COALESCE(COUNT(DISTINCT ws.product_id), 0) as unique_skus,
                (COALESCE(SUM(ws.quantity_in_stock), 0) / {$max_items_capacity}) * 100 as capacity_percentage,
                MAX(ws.last_updated) as last_activity_date
            FROM warehouses w
            LEFT JOIN warehouse_stock ws ON w.id = ws.warehouse_id
            GROUP BY w.id, w.name, w.is_active, w.warehouse_code
            ORDER BY w.name ASC";

    $result = $conn->query($sql);
    if (!$result) {
        die("Error in getWarehouseData: " . $conn->error);
    }
    
    $warehouses = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $warehouses[] = $row;
        }
    }
    return $warehouses;
}

/**
 * Helper function to format dates nicely.
 */
function format_activity_date($date_string) {
    if (is_null($date_string)) {
        return 'No activity yet';
    }
    $date = new DateTime($date_string);
    return 'Last activity: ' . $date->format('d M Y, H:i');
}

// --- Call the functions to get all the data for the page ---
$kpiData = getDashboardKpis($conn);
$alertData = getAlertPanelData($conn);
$warehouseData = getWarehouseData($conn);
$topProductsData = getTopSellingProductsData($conn);

$treemapData = [];
foreach ($warehouseData as $wh) {
    // We only want to show warehouses that actually have stock
    if ($wh['total_items'] > 0) {
        $treemapData[] = [
            'name' => $wh['name'],
            'value' => (int)$wh['total_items'] // Ensure value is an integer
        ];
    }
}
// We need to convert this PHP array into a JavaScript object using json_encode
$treemapDataJson = json_encode($treemapData);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory</title>
  
    <!-- Google Fonts & Font Awesome and charts -->
      <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-chart-treemap@2.3.0/dist/chartjs-chart-treemap.min.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="ware.css">

</head>
<body>
    <style>
 
    </style>
    <div class="container">
        <!-- ================== Sidebar ================== -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fa-solid fa-cube"></i>
                <h2>Supplies Direct</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="active"><i class="fa-solid fa-chart-line"></i> <span>Dashboard</span></a></li>
                    <li><a href="warehouses.php"><i class="fa-solid fa-warehouse"></i> <span>Warehouses</span></a></li>
                    <li><a href="shops_dashboard.php"><i class="fa-solid fa-store"></i> <span>Shops</span></a></li>
                    <li><a href="#"><i class="fa-solid fa-box-archive"></i> <span>Products</span></a></li>
                    <li><a href="#"><i class="fa-solid fa-right-left"></i> <span>Transfers</span></a></li>
                    <li><a href="#"><i class="fa-solid fa-file-lines"></i> <span>Reports</span></a></li>
                    <li><a href="#"><i class="fa-solid fa-users"></i> <span>Users</span></a></li>
                    <li><a href="#"><i class="fa-solid fa-gear"></i> <span>Settings</span></a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-footer-item">
                    <i class="fa-solid fa-chevron-left"></i>
                </div>
                <div class="sidebar-footer-item">
                     <p><span class="status-dot"></span> System Status: <strong>Online</strong></p>
                     <p class="sync-time">Last sync: Today, 14:32</p>
                </div>
            </div>
        </aside>
<!-- ========================================================= -->
<!-- STEP 1: ADD WAREHOUSE MODAL HTML                        -->
<!-- ========================================================= -->
<div id="addWarehouseModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Add New Warehouse</h4>
            <!-- The 'close-modal' class is important for our JS -->
            <button class="close-modal">×</button> 
        </div>
        <div class="modal-body">
            <!-- This form will post data to our new PHP script -->
            <form id="addWarehouseForm" action="add_warehouse.php" method="POST">
                <div class="form-group">
                    <label for="warehouse_name">Warehouse Name</label>
                    <input type="text" id="warehouse_name" name="warehouse_name" required placeholder="e.g., Main Distribution Center">
                </div>
                <div class="form-group">
                    <label for="warehouse_code">Warehouse Code</label>
                    <input type="text" id="warehouse_code" name="warehouse_code" required placeholder="e.g., WH-005">
                </div>
                <div class="form-group">
                    <label for="city_location">City / Location</label>
                    <input type="text" id="city_location" name="city_location" placeholder="e.g., Lilongwe">
                </div>
                <div class="form-group">
                    <label for="address_line1">Address</label>
                    <input type="text" id="address_line1" name="address_line1" placeholder="Street Address">
                </div>
                
                <div class="modal-footer">
                    <!-- The 'close-modal' class lets this button also close the modal -->
                    <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Save Warehouse</button>
                </div>
            </form>
        </div>
    </div>
</div>
        <!-- ================== Main Content ================== -->
        <main class="main-content">
            <!-- ================== Main Header ================== -->
            <header class="main-header">
                <h1>Supplies Direct </h1>
                <div class="header-actions">
                    <i class="fa-regular fa-bell"></i>
                    <i class="fa-solid fa-gear"></i>
                    <div class="user-profile">
                        <img src="https://i.pravatar.cc/40?u=admin" alt="Admin">
                        <span>Admin <i class="fa-solid fa-chevron-down"></i></span>
                    </div>
                </div>
            </header>

            <!-- ================== Dashboard ================== -->
            <section class="dashboard">
                <div class="dashboard-header">
                    <h2>Inventory Dashboard</h2>
                    <div class="dashboard-actions">
                        <div class="search-bar">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" placeholder="Search...">
                        </div>
                        <button class="btn btn-primary"><i class="fa-solid fa-download"></i> Export</button>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="kpi-cards">
                    <!-- Total Inventory Value Card -->
<div class="card kpi-card">
    <div class="card-header">
        <p>Total Inventory Value</p>
        <span class="icon-bg"><i class="fa-solid fa-dollar-sign"></i></span>
    </div>
    <!-- This line displays the dynamic value from our PHP function -->
    <h3>MWK<?php echo number_format($kpiData['total_value'] ?? 0, 2); ?></h3>
    <p class="kpi-comparison"><span class="text-gray">Estimated total retail value</span></p>
</div>
                    <!-- Low Stock Items Card -->
<div class="card kpi-card">
    <div class="card-header">
        <p>Low Stock Items</p>
        <span class="icon-bg"><i class="fa-solid fa-triangle-exclamation"></i></span>
    </div>
    <!-- This line displays the dynamic value from our PHP function -->
    <h3><?php echo $kpiData['low_stock_items'] ?? 0; ?></h3>
    <p class="kpi-comparison"><span class="text-gray">Items at or below minimum level</span></p>
</div>
             <!-- Pending Transfers Card -->
<div class="card kpi-card">
    <div class="card-header">
        <p>Pending Transfers</p>
        <span class="icon-bg"><i class="fa-solid fa-right-left"></i></span>
    </div>
    <!-- This line displays the dynamic value from our PHP function -->
    <h3><?php echo $kpiData['pending_transfers'] ?? 0; ?></h3>
    <p class="kpi-comparison"><span class="text-gray">Awaiting warehouse action</span></p>
</div>
                    <!-- Total Products Card -->
<div class="card kpi-card">
    <div class="card-header">
        <p>Total Products</p>
        <span class="icon-bg"><i class="fa-solid fa-boxes-stacked"></i></span>
    </div>
    <!-- This line displays the dynamic value from our PHP function -->
    <h3><?php echo number_format($kpiData['total_products'] ?? 0); ?></h3>
    <p class="kpi-comparison"><span class="text-gray">Unique SKUs in catalog</span></p>
</div>
                </div>

                <!-- Mid Section Panels -->
                <div class="mid-section">
                  <div class="card alert-panel">
    <div class="card-header">
        <h4>Alert Panel</h4>
        <i class="fa-solid fa-ellipsis-vertical"></i>
    </div>

    <div id="alert-list-container"> <!-- Added a container for easier JS targeting -->
        <?php if (empty($alertData)): ?>
            <div class="alert-item no-alerts">
                <i class="fa-solid fa-circle-check alert-icon normal"></i>
                <div class="alert-details">
                    <p><strong>All Systems Normal</strong></p>
                    <p class="sub-text">No active alerts to show.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($alertData as $alert): ?>
            <div class="alert-item" id="alert-<?php echo htmlspecialchars($alert['alert_key']); ?>">
                <i class="<?php echo htmlspecialchars($alert['icon']); ?> alert-icon <?php echo htmlspecialchars($alert['type']); ?>"></i>
                <div class="alert-details">
                    <p><strong><?php echo htmlspecialchars($alert['title']); ?></strong></p>
                    <p class="sub-text"><?php echo htmlspecialchars($alert['details']); ?></p>
                    <a href="<?php echo htmlspecialchars($alert['action_link']); ?>" class="alert-action"><?php echo htmlspecialchars($alert['action_text']); ?></a>
                </div>
                <!-- THE NEW DISMISS BUTTON -->
                <button class="dismiss-alert-btn" data-alert-key="<?php echo htmlspecialchars($alert['alert_key']); ?>" title="Dismiss Alert">×</button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
                    <div class="card chart-card">
     <div class="card-header">
        <h4>Stock Distribution by Location</h4>
        <!-- This dropdown is static for now but could be used later -->
        <select name="categories" class="dropdown">
            <option value="all">Total Items</option>
        </select>
    </div>
    <!-- ========================================================= -->
    <!-- STEP 3: ADD THE CANVAS FOR THE CHART                    -->
    <!-- ========================================================= -->
    <div class="chart-container">
        <canvas id="stockDistributionChart"></canvas>
    </div>
</div>
                  <!-- Top Selling Products Chart Card -->
<div class="card chart-card">
     <div class="card-header">
        <h4>Top Selling Products</h4>
        <select name="timeframe" class="dropdown">
            <option value="30">Last 30 Days</option>
            <!-- You could add more options like "Last 90 Days" here later -->
        </select>
    </div>
    <div class="chart-container">
        <!-- New Canvas for the Bar Chart -->
        <canvas id="topSellingProductsChart"></canvas>
    </div>
</div>
                </div>

                <!-- Warehouses Management -->
               <!-- Warehouses Management -->
<!-- Warehouses Management -->
<div class="warehouses-management">
    <div class="dashboard-header">
        <h2>Warehouses Management</h2>
        <div class="dashboard-actions">
            <div class="view-toggle">
                <i class="fa-solid fa-grip active"></i>
                <i class="fa-solid fa-list"></i>
            </div>
            <!-- THIS IS THE BUTTON THAT WAS MISSING -->
            <button id="addWarehouseBtn" class="btn btn-dark"><i class="fa-solid fa-plus"></i> Add New Warehouse</button>
        </div>
    </div>

    <div class="warehouse-cards">
        <?php if (empty($warehouseData)): ?>
            <p class="no-warehouses">No warehouses found. 
                <a href="#" id="addWarehouseLink">Click here to add one.</a>
            </p>
        <?php else: ?>
            <?php foreach ($warehouseData as $wh):
                // Prepare variables for the card
                $status = $wh['is_active'] ? 'Active' : 'Inactive';
                $statusClass = $wh['is_active'] ? 'status-active' : 'status-maintenance';
                $capacity = round($wh['capacity_percentage']);
                
                // Determine capacity bar color
                $capacityBarClass = 'low';
                if ($capacity > 75) {
                    $capacityBarClass = 'high';
                } elseif ($capacity > 40) {
                    $capacityBarClass = 'medium';
                }
            ?>
            <div class="card warehouse-card">
                <div class="card-header">
                    <div>
                        <h4><?php echo htmlspecialchars($wh['name']); ?></h4>
                        <p class="sub-text"><?php echo htmlspecialchars($wh['warehouse_code']); ?></p>
                    </div>
                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                </div>

                <!-- ENHANCED STATS SECTION -->
                <div class="warehouse-stats">
                    <div>
                        <p class="sub-text">Total Items</p>
                        <p><strong><?php echo number_format($wh['total_items']); ?></strong></p>
                    </div>
                    <div>
                        <p class="sub-text">Unique SKUs</p>
                        <p><strong><?php echo number_format($wh['unique_skus']); ?></strong></p>
                    </div>
                </div>
                
                <!-- NEW: CAPACITY PROGRESS BAR -->
                <div class="capacity-section">
                    <div class="capacity-header">
                        <p class="sub-text">Capacity</p>
                        <p><strong><?php echo $capacity; ?>%</strong></p>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill <?php echo $capacityBarClass; ?>" style="width: <?php echo $capacity; ?>%;"></div>
                    </div>
                </div>

                <!-- NEW: FOOTER WITH LAST ACTIVITY AND ACTIONS -->
                <div class="warehouse-card-footer">
                    <p class="sub-text last-activity">
                        <i class="fa-regular fa-clock"></i>
                        <?php echo format_activity_date($wh['last_activity_date']); ?>
                    </p>
                    <div class="action-buttons">
                        <a href="view_warehouse.php?id=<?php echo $wh['id']; ?>" class="btn-action">Details</a>
                        <a href="manage_warehouse_stock.php?id=<?php echo $wh['id']; ?>" class="btn-action primary">Manage</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>            </section>
        </main>
    </div>
    <div class="help-button">?</div>
    <script>
        /* ========================================================= */
/* STEP 3: MODAL CONTROL JAVASCRIPT                          */
/* ========================================================= */
document.addEventListener('DOMContentLoaded', function() {
    // Get the elements we need
    const addWarehouseModal = document.getElementById('addWarehouseModal');
    const addWarehouseBtn = document.getElementById('addWarehouseBtn');
    // Get ALL elements that can close the modal (the 'x' button and the 'Cancel' button)
    const closeModalBtns = document.querySelectorAll('.close-modal');

    // Function to open the modal
    function openModal() {
        if (addWarehouseModal) {
            addWarehouseModal.style.display = 'flex';
        }
    }

    // Function to close the modal
    function closeModal() {
        if (addWarehouseModal) {
            addWarehouseModal.style.display = 'none';
        }
    }

    // Event listener for the "Add New Warehouse" button
    if (addWarehouseBtn) {
        addWarehouseBtn.addEventListener('click', openModal);
    }
    
    // Event listeners for all the "close" buttons
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', closeModal);
    });

    // Optional: Close the modal if the user clicks on the overlay
    if (addWarehouseModal) {
        addWarehouseModal.addEventListener('click', function(event) {
            // Check if the click was on the overlay itself, not the content
            if (event.target === addWarehouseModal) {
                closeModal();
            }
        });
    }

    // Your other JS code can go here...
});
        document.addEventListener('DOMContentLoaded', function() {
    // This is a great place to add interactivity.
    // For example, you could make the view toggle buttons functional.

    const viewToggleButtons = document.querySelectorAll('.view-toggle i');
    
    viewToggleButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove 'active' class from all buttons
            viewToggleButtons.forEach(btn => btn.classList.remove('active'));
            // Add 'active' class to the clicked button
            button.classList.add('active');

            // You would add logic here to switch between grid and list view
            // for the warehouse cards. For this demo, it's just a visual toggle.
            console.log(`View switched to ${button.classList.contains('fa-grip') ? 'Grid' : 'List'}`);
        });
    });

    console.log("Inventory Management System dashboard loaded.");
});
document.addEventListener('DOMContentLoaded', function() {
    const alertContainer = document.getElementById('alert-list-container');

    if (alertContainer) {
        alertContainer.addEventListener('click', function(event) {
            // Check if a dismiss button was clicked
            if (event.target.classList.contains('dismiss-alert-btn')) {
                const button = event.target;
                const alertKey = button.dataset.alertKey;
                const alertItem = document.getElementById('alert-' + alertKey);

                // Prepare data to send to the server
                const formData = new FormData();
                formData.append('alert_key', alertKey);

                // Use fetch to call our PHP script
                fetch('dismiss_alert.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // On success, visually remove the alert from the page
                        if (alertItem) {
                            alertItem.classList.add('fading-out');
                            // Wait for the animation to finish before removing the element
                            setTimeout(() => {
                                alertItem.remove();
                                // Check if the container is now empty
                                if(alertContainer.children.length === 0) {
                                    alertContainer.innerHTML = `
                                        <div class="alert-item no-alerts">
                                            <i class="fa-solid fa-circle-check alert-icon normal"></i>
                                            <div class="alert-details">
                                                <p><strong>All Systems Normal</strong></p>
                                                <p class="sub-text">No active alerts to show.</p>
                                            </div>
                                        </div>`;
                                }
                            }, 500);
                        }
                    } else {
                        // Handle error (e.g., show a small notification)
                        console.error('Failed to dismiss alert:', data.message);
                        alert('Could not dismiss alert. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Network error:', error);
                    alert('A network error occurred. Please check your connection.');
                });
            }
        });
    }

// =========================================================
// INITIALIZE THE TREEMAP CHART (FINAL, POLISHED VERSION)
// =========================================================
const treemapCtx = document.getElementById('stockDistributionChart');
if (treemapCtx) {
    const treemapData = <?php echo $treemapDataJson; ?>;

    new Chart(treemapCtx, {
        type: 'treemap',
        data: {
            datasets: [{
                label: 'Stock Distribution',
                tree: treemapData,
                key: 'value',
                groups: ['name'],
                backgroundColor: [
                    '#1f2937', // dark-gray
                    '#4f46e5', // indigo
                    '#6b7280', // gray
                    '#7c3aed', // purple
                    '#c026d3', // fuchsia
                    '#db2777'  // pink
                ],

                // ===============================================
                // FINAL POLISH - ADD BORDER RADIUS & FIX HOVER GLITCH
                // ===============================================
                
                // 1. Add the border radius
                borderRadius: 6,

                // 2. Disable the default hover color to stop the flashing/glitch
                hoverColor: false, 
                
                // Our custom hover background color still works
                hoverBackgroundColor: '#9ca3af', // A neutral medium gray

                // Styling for the text inside the rectangles
                color: 'white',
                font: {
                    size: 14,
                    weight: 'bold'
                }
            }],
        },
        options: {
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return context[0].raw.g;
                          
                        },
                        label: function(context) {
                            const value = context.raw.v;
                            return `Total Items: ${value.toLocaleString()}`;
                        }
                    }
                }
            },
            maintainAspectRatio: false
        }
    });
}

     const topProductsCtx = document.getElementById('topSellingProductsChart');
    if (topProductsCtx) {
        // Get the data we created in PHP
        const topProductsData = <?php echo json_encode($topProductsData); ?>;

        // Separate the labels (product names) and the data (quantities)
        const productLabels = topProductsData.map(item => item.product_name);
        const productValues = topProductsData.map(item => item.total_quantity_sold);

        new Chart(topProductsCtx, {
            type: 'bar', // The chart type
            data: {
                labels: productLabels,
                datasets: [{
                    label: 'Units Sold',
                    data: productValues,
                    backgroundColor: '#1f2937', // Solid dark color
                    borderColor: '#1f2937',
                    borderWidth: 1,
                    // The border radius you requested
                    borderRadius: 4, 
                    borderSkipped: false, // Ensures radius is applied to all corners
                }]
            },
            options: {
                // This is the key to making it a horizontal bar chart
                indexAxis: 'y', 
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false // The legend is redundant here
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ` Units Sold: ${context.raw.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    // Configure the X-axis (the horizontal one)
                    x: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            // A subtle grid line color
                            color: '#f3f4f6' 
                        },
                        ticks: {
                            // Ensure ticks are integers if values are low
                            precision: 0 
                        }
                    },
                    // Configure the Y-axis (the vertical one with product names)
                    y: {
                        grid: {
                            // Hide the vertical grid lines for a cleaner look
                            display: false 
                        }
                    }
                }
            }
        });
    }

});
    </script>
</body>
</html>