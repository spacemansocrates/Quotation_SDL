<?php
// --- START: FORM PROCESSING BLOCK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We need a DB connection here for processing
    $servername = "127.0.0.1";
    $username = "root";
    $password = "";
    $dbname = "supplies";
    $port = 3306;
    $conn_process = new mysqli($servername, $username, $password, $dbname, $port);
    if ($conn_process->connect_error) {
        die("Connection failed during post: " . $conn_process->connect_error);
    }
    
    $action = $_POST['action'] ?? '';

    // --- Process ADD action ---
    if ($action === 'add_warehouse') {
        $name = trim($_POST['name']);
        $code = trim($_POST['warehouse_code']);
        $city = trim($_POST['city']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        // A user ID is required. We'll hardcode '1' for this example.
        // In a real app, this would come from the logged-in user's session.
        $user_id = 1;

        $stmt = $conn_process->prepare("INSERT INTO warehouses (name, warehouse_code, city, is_active, created_by_user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $name, $code, $city, $is_active, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // --- Process EDIT action ---
    if ($action === 'edit_warehouse') {
        $id = (int)$_POST['warehouse_id'];
        $name = trim($_POST['name']);
        $code = trim($_POST['warehouse_code']);
        $city = trim($_POST['city']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $user_id = 1; // Assuming user 1 is making the update

        $stmt = $conn_process->prepare("UPDATE warehouses SET name = ?, warehouse_code = ?, city = ?, is_active = ?, updated_by_user_id = ? WHERE id = ?");
        $stmt->bind_param("sssiii", $name, $code, $city, $is_active, $user_id, $id);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn_process->close();
    
    // Redirect to prevent form resubmission on refresh
    // For an edit, we redirect back to the same warehouse page.
    $redirect_url = "warehouses.php";
    if ($action === 'edit_warehouse' && isset($_POST['warehouse_id'])) {
        $redirect_url .= "?warehouse_id=" . (int)$_POST['warehouse_id'];
    }
    header("Location: " . $redirect_url);
    exit();
}
// --- Database Connection ---
$servername = "127.0.0.1"; // Or "localhost"
$username = "root";
$password = "";
$dbname = "supplies";
$port = 3306; // Add this if your port is not the default 3306

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Data Fetching Logic ---

// --- 1. Fetch Warehouse List for the Sidebar ---
$warehouses_data = [];
$sql_warehouses = "
    SELECT
        w.id,
        w.name,
        w.is_active,
        COALESCE(SUM(ws.quantity_in_stock), 0) AS total_items
    FROM
        warehouses w
    LEFT JOIN
        warehouse_stock ws ON w.id = ws.warehouse_id
    GROUP BY
        w.id, w.name, w.is_active
    ORDER BY
        w.name ASC
";
$result_warehouses = $conn->query($sql_warehouses);
if ($result_warehouses->num_rows > 0) {
    while($row = $result_warehouses->fetch_assoc()) {
        $warehouses_data[] = [
            "id" => $row['id'],
            "name" => $row['name'],
            "items" => $row['total_items'],
            "status" => $row['is_active'] ? 'Active' : 'Inactive'
        ];
    }
}

// --- 2. Determine Selected Warehouse and Fetch its Details ---
$selected_warehouse_id = 0;

// If a specific warehouse is requested via the URL, use it.
if (isset($_GET['warehouse_id']) && is_numeric($_GET['warehouse_id'])) {
    $selected_warehouse_id = (int)$_GET['warehouse_id'];
}
// Otherwise, if no warehouse is selected and the list is not empty, default to the first one.
elseif (!empty($warehouses_data)) {
    $selected_warehouse_id = $warehouses_data[0]['id'];
}

// Find the name of the currently selected warehouse for the header
$selected_warehouse_name = 'No Warehouse Found';
foreach ($warehouses_data as $wh) {
    if ($wh['id'] == $selected_warehouse_id) {
        $selected_warehouse_name = $wh['name'];
        break;
    }
}

// --- 3. Fetch Stat Cards for the Selected Warehouse ---
$stats = [
    'total_items' => 0,
    'unique_products' => 0,
    'capacity_usage' => '76%', // NOTE: Capacity is not in the DB schema, so this remains a static value.
    'low_stock_alerts' => 0
];
if ($selected_warehouse_id > 0) {
    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(quantity_in_stock), 0) AS total_items,
            COALESCE(COUNT(DISTINCT product_id), 0) AS unique_products,
            COALESCE(SUM(CASE WHEN quantity_in_stock < minimum_stock_level THEN 1 ELSE 0 END), 0) AS low_stock_alerts
        FROM
            warehouse_stock
        WHERE
            warehouse_id = ?
    ");
    $stmt->bind_param("i", $selected_warehouse_id);
    $stmt->execute();
    $result_stats = $stmt->get_result()->fetch_assoc();
    if($result_stats) {
        $stats['total_items'] = $result_stats['total_items'];
        $stats['unique_products'] = $result_stats['unique_products'];
        $stats['low_stock_alerts'] = $result_stats['low_stock_alerts'];
    }
    $stmt->close();
}

// ... after the block that determines $selected_warehouse_id

// --- 2b. Fetch ALL details for the selected warehouse for the Edit form ---
$selected_warehouse_details = null;
if ($selected_warehouse_id > 0) {
    $stmt = $conn->prepare("SELECT id, name, warehouse_code, city, is_active FROM warehouses WHERE id = ?");
    $stmt->bind_param("i", $selected_warehouse_id);
    $stmt->execute();
    $result_details = $stmt->get_result();
    if ($result_details->num_rows > 0) {
        $selected_warehouse_details = $result_details->fetch_assoc();
    }
    $stmt->close();
}
// ... after the block that fetches $selected_warehouse_details

// --- 3. Handle Search Query ---
$search_term = trim($_GET['search'] ?? '');
$search_results = [];

// Only perform a search if a term is provided and a warehouse is selected
if (!empty($search_term) && $selected_warehouse_id > 0) {
    $like_term = "%" . $search_term . "%";
    
    $stmt = $conn->prepare("
        SELECT
            p.name AS product_name,
            p.sku,
            c.name AS category_name,
            ws.quantity_in_stock
        FROM
            warehouse_stock ws
        JOIN
            products p ON ws.product_id = p.id
        LEFT JOIN
            categories c ON p.category_id = c.id
        WHERE
            ws.warehouse_id = ? AND (p.name LIKE ? OR p.sku LIKE ?)
        ORDER BY
            p.name ASC
    ");
    $stmt->bind_param("iss", $selected_warehouse_id, $like_term, $like_term);
    $stmt->execute();
    $result_search = $stmt->get_result();
    
    if ($result_search->num_rows > 0) {
        while($row = $result_search->fetch_assoc()) {
            $search_results[] = $row;
        }
    }
    $stmt->close();
}
// --- 4. Fetch Recent Transactions ---
// NOTE: The stock_transactions table does not have a warehouse_id.
// Therefore, we are showing the 5 most recent *system-wide* transactions.
$transactions_data = [];
$sql_transactions = "
    SELECT
        st.transaction_date,
        st.quantity,
        st.transaction_type,
        u.full_name
    FROM
        stock_transactions st
    JOIN
        users u ON st.scanned_by_user_id = u.id
    ORDER BY
        st.transaction_date DESC
    LIMIT 5
";
$result_transactions = $conn->query($sql_transactions);
if ($result_transactions->num_rows > 0) {
    while($row = $result_transactions->fetch_assoc()) {
        $type_map = [
            'stock_in' => 'Receipt',
            'stock_out' => 'Transfer', // Assuming stock_out is for transfers/sales
            'adjustment' => 'Adjustment',
            'return' => 'Return'
        ];
        $quantity_str = ($row['transaction_type'] == 'stock_in' || $row['transaction_type'] == 'return')
                      ? '+' . $row['quantity']
                      : '-' . $row['quantity'];

        $transactions_data[] = [
            "date" => date("d M Y", strtotime($row['transaction_date'])),
            "type" => $type_map[$row['transaction_type']] ?? ucfirst($row['transaction_type']),
            "items" => $quantity_str . ' items',
            "user" => $row['full_name']
        ];
    }
}

// --- 5. Fetch Inventory Distribution Data for the Chart (by Category) ---
$chart_labels = [];
$chart_data = [];
if ($selected_warehouse_id > 0) {
    $stmt = $conn->prepare("
        SELECT
            c.name AS category_name,
            SUM(ws.quantity_in_stock) AS total_quantity
        FROM
            warehouse_stock ws
        JOIN
            products p ON ws.product_id = p.id
        JOIN
            categories c ON p.category_id = c.id
        WHERE
            ws.warehouse_id = ? AND ws.quantity_in_stock > 0
        GROUP BY
            c.id, c.name
        ORDER BY
            total_quantity DESC
    ");
    $stmt->bind_param("i", $selected_warehouse_id);
    $stmt->execute();
    $result_chart = $stmt->get_result();
    if ($result_chart->num_rows > 0) {
        while($row = $result_chart->fetch_assoc()) {
            $chart_labels[] = $row['category_name'];
            $chart_data[] = $row['total_quantity'];
        }
    }
    $stmt->close();
}

// --- 6. Fetch Fast Moving Products ---
// NOTE: This is calculated system-wide based on stock_out transactions in the last 30 days.
$fast_moving_products_data = [];
$sql_fast_moving = "
    SELECT
        p.name,
        SUM(st.quantity) AS total_moved
    FROM
        stock_transactions st
    JOIN
        products p ON st.product_id = p.id
    WHERE
        st.transaction_type = 'stock_out'
        AND st.transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY
        p.id, p.name
    ORDER BY
        total_moved DESC
    LIMIT 3
";
$result_fast_moving = $conn->query($sql_fast_moving);
if ($result_fast_moving->num_rows > 0) {
    while($row = $result_fast_moving->fetch_assoc()) {
        $fast_moving_products_data[] = [
            "name" => $row['name'],
            "rate" => $row['total_moved'] . " units/month"
        ];
    }
}

$conn->close();

// --- Helper Function ---
// This function is used in the HTML below and remains unchanged.
function getStatusClass($status) {
    switch (strtolower($status)) {
        case 'active':
            return 'status-active';
        case 'maintenance':
            return 'status-maintenance';
        case 'inactive':
            return 'status-inactive';
        case 'receipt':
        case 'return':
            return 'type-receipt';
        case 'transfer':
            return 'type-transfer';
        case 'adjustment':
            return 'type-adjustment';
        default:
            return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Dashboard</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .warehouses-nav a {
            text-decoration: none;
            color: inherit;
            display: block;
            padding: 1rem; /* Adjust if your padding is different */
        }
        .warehouses-nav li {
            padding: 0; /* Move padding from LI to the A tag */
        }
    </style>
    <link rel="stylesheet" href="warehouse.css">

</head>
<body>
    <div class="container">
        <!-- Not part of the requested image, but provided in the prompt. You can uncomment this if needed. -->
        <!--
        <aside class="sidebar">
            ... (Sidebar HTML from prompt) ...
        </aside>
        -->

        <div class="main-content">
            <!-- ================== Warehouses Sidebar ================== -->
            <aside class="warehouses-sidebar">
                <h1>Warehouses</h1>
                <!-- NEW "Back to Dashboard" BUTTON ADDED HERE -->
              <a href="inventory_dashboard.php" class="btn-back-dashboard" style="display: flex; align-items: center; justify-content: center; text-decoration: none; color: inherit;">
    <i class="fa-solid fa-arrow-left"></i>
    Back to Dashboard
</a>
                <button class="btn-add-warehouse">
                    <i class="fa-solid fa-plus"></i>
                    Add Warehouse
                </button>
                <nav class="warehouses-nav">
                    <ul>
                        <?php foreach ($warehouses_data as $warehouse): ?>
                        <li class="warehouse-item <?php echo ($warehouse['id'] == $selected_warehouse_id) ? 'active' : ''; ?>">
    <a href="warehouses.php?warehouse_id=<?php echo $warehouse['id']; ?>">
        <div class="item-details">
            <h3><?php echo htmlspecialchars($warehouse['name']); ?></h3>
            <span class="status-badge <?php echo getStatusClass($warehouse['status']); ?>">
                <?php echo htmlspecialchars($warehouse['status']); ?>
            </span>
        </div>
        <p class="item-count"><?php echo number_format($warehouse['items']); ?> items</p>
    </a>
</li>
                        <?php endforeach; ?>
                        <?php if (empty($warehouses_data)): ?>
                            <li class="warehouse-item"><p>No warehouses found.</p></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </aside>

            <!-- ================== Dashboard Content ================== -->
            <main class="dashboard-content">
               <header class="dashboard-header">
                    <h1><?php echo htmlspecialchars($selected_warehouse_name); ?></h1>
                    <!-- SEARCH BAR MOVED HERE -->
                 <form method="GET" action="warehouses.php" class="search-warehouses">
    <!-- This hidden input ensures we keep the same warehouse selected when searching -->
    <input type="hidden" name="warehouse_id" value="<?php echo $selected_warehouse_id; ?>">
    
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="text" name="search" placeholder="Search product name or SKU..." value="<?php echo htmlspecialchars($search_term); ?>">
</form>
                    <div class="header-actions">
                        <button><i class="fa-solid fa-upload"></i> Export</button>
                        <button><i class="fa-solid fa-pen"></i> Edit</button>
                    </div>
                </header>

              <?php if (empty($search_term)): ?>
    <!-- ================== DEFAULT DASHBOARD VIEW ================== -->
    <div class="stats-grid">
        <div class="stat-card">
            <p>Total Items</p>
            <span class="value"><?php echo number_format($stats['total_items']); ?></span>
        </div>
        <div class="stat-card">
            <p>Unique Products</p>
            <span class="value"><?php echo number_format($stats['unique_products']); ?></span>
        </div>
        <div class="stat-card">
            <p>Capacity Usage</p>
            <span class="value"><?php echo htmlspecialchars($stats['capacity_usage']); ?></span>
        </div>
        <div class="stat-card">
            <p>Low Stock Alerts</p>
            <span class="value"><?php echo number_format($stats['low_stock_alerts']); ?></span>
        </div>
    </div>

                <div class="main-dashboard-grid">
        <!-- Left Column -->
        <div class="left-column">
            <!-- (Your existing Recent Transactions card) -->
            <div class="card">
                <div class="card-header">
                    <h2>Recent Transactions</h2>
                    <div class="filter-dropdown">
                        <select>
                            <option>All Types</option>
                            <option>Receipt</option>
                            <option>Transfer</option>
                            <option>Adjustment</option>
                        </select>
                    </div>
                </div>
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Items</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions_data as $tx): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tx['date']); ?></td>
                            <td><span class="transaction-type <?php echo getStatusClass($tx['type']); ?>"><?php echo htmlspecialchars($tx['type']); ?></span></td>
                            <td class="<?php echo strpos($tx['items'], '+') !== false ? 'items-positive' : 'items-negative'; ?>">
                                <?php echo htmlspecialchars($tx['items']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($tx['user']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transactions_data)): ?>
                            <tr><td colspan="4" style="text-align:center; padding: 20px;">No recent transactions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="view-all-transactions">
                   <a href="#" class="view-all-link">View all transactions</a>
                </div>
            </div>
        </div>

                     <!-- Right Column -->
        <div class="right-column">
             <!-- (Your existing Inventory Distribution card) -->
            <div class="card inventory-distribution">
                <div class="card-header">
                    <h2>Inventory Distribution</h2>
                </div>
                <div class="chart-container">
                    <canvas id="inventoryChart"></canvas>
                </div>
            </div>
            <!-- (Your existing Fast Moving Products card) -->
            <div class="card fast-moving-products">
                 <div class="card-header">
                    <h2>Fast Moving Products</h2>
                    <a href="#" class="view-all-link">View all</a>
                </div>
                <div class="product-list">
                    <ul>
                        <?php foreach ($fast_moving_products_data as $product): ?>
                        <li>
                            <span class="product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                            <span class="product-rate"><?php echo htmlspecialchars($product['rate']); ?></span>
                        </li>
                        <?php endforeach; ?>
                         <?php if (empty($fast_moving_products_data)): ?>
                            <li style="text-align:center; padding: 10px;">No fast moving products in the last 30 days.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header search-results-header">
            <h2>Search Results for "<?php echo htmlspecialchars($search_term); ?>"</h2>
            <a href="warehouses.php?warehouse_id=<?php echo $selected_warehouse_id; ?>" class="btn-clear-search">Clear Search</a>
        </div>
        
        <?php if (empty($search_results)): ?>
            <p class="no-results-message">No products found matching your search.</p>
        <?php else: ?>
            <table class="transactions-table"> <!-- Reusing this class for consistent styling -->
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Qty in Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($search_results as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                        <td><?php echo number_format($product['quantity_in_stock']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <!-- ================== Warehouse Modal ================== -->
<div id="warehouseModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">×</span>
        <h2 id="modalTitle">Add New Warehouse</h2>
        <form id="warehouseForm" method="POST" action="warehouses.php">
            <input type="hidden" name="action" id="formAction" value="add_warehouse">
            <input type="hidden" name="warehouse_id" id="formWarehouseId" value="">

            <div class="form-group">
                <label for="warehouseName">Warehouse Name</label>
                <input type="text" id="warehouseName" name="name" required>
            </div>
            <div class="form-group">
                <label for="warehouseCode">Warehouse Code</label>
                <input type="text" id="warehouseCode" name="warehouse_code" required>
            </div>
            <div class="form-group">
                <label for="warehouseCity">City</label>
                <input type="text" id="warehouseCity" name="city">
            </div>
            <div class="form-group-checkbox">
                <input type="checkbox" id="warehouseActive" name="is_active" value="1">
                <label for="warehouseActive">Is Active</label>
            </div>
            <div class="form-group">
                <button type="submit" id="submitBtn" class="btn-primary">Add Warehouse</button>
            </div>
        </form>
    </div>
</div>

    <script>
        // JavaScript for Chart.js
        document.addEventListener('DOMContentLoaded', function () {
            // Check if there is data to display in the chart
            const chartData = <?php echo json_encode($chart_data); ?>;
            const chartLabels = <?php echo json_encode($chart_labels); ?>;
            
            const chartContainer = document.querySelector('.inventory-distribution .chart-container');
            if (chartData.length === 0) {
                 chartContainer.innerHTML = '<p style="text-align: center; padding: 40px; color: #718096;">No inventory data to display for this warehouse.</p>';
                 return; // Stop the chart from initializing
            }

            const ctx = document.getElementById('inventoryChart').getContext('2d');
            const inventoryChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Inventory Distribution',
                        data: chartData,
                        backgroundColor: [
                            '#805AD5', // Accent Purple
                            '#4FD1C5', // Teal
                            '#F6AD55', // Orange
                            '#63B3ED', // Blue
                            '#A0AEC0', // Gray
                            '#ED64A6', // Pink
                            '#48BB78'  // Green
                        ],
                        borderColor: '#FFFFFF',
                        borderWidth: 4,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1A202C',
                            titleFont: { size: 14, weight: 'bold' },
                            bodyFont: { size: 12 },
                            padding: 12,
                            cornerRadius: 8,
                            boxPadding: 4,
                        }
                    }
                }
            });
        });
 // Pass PHP data to JavaScript
    const selectedWarehouseDetails = <?php echo json_encode($selected_warehouse_details); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        // --- Modal Control ---
        const modal = document.getElementById('warehouseModal');
        const addBtn = document.querySelector('.btn-add-warehouse');
        const editBtn = document.querySelector('.header-actions button:nth-child(2)');
        const closeBtn = document.querySelector('.close-btn');

        const modalTitle = document.getElementById('modalTitle');
        const form = document.getElementById('warehouseForm');
        const formAction = document.getElementById('formAction');
        const formWarehouseId = document.getElementById('formWarehouseId');
        const submitBtn = document.getElementById('submitBtn');

        // Form fields
        const warehouseName = document.getElementById('warehouseName');
        const warehouseCode = document.getElementById('warehouseCode');
        const warehouseCity = document.getElementById('warehouseCity');
        const warehouseActive = document.getElementById('warehouseActive');

        // Function to open the modal
        function openModal() {
            modal.style.display = 'block';
        }

        // Function to close the modal
        function closeModal() {
            modal.style.display = 'none';
        }

        // Event listener for "Add Warehouse" button
        addBtn.onclick = function() {
            form.reset();
            modalTitle.textContent = 'Add New Warehouse';
            submitBtn.textContent = 'Add Warehouse';
            formAction.value = 'add_warehouse';
            formWarehouseId.value = '';
            warehouseActive.checked = true; // Default to active
            openModal();
        }

        // Event listener for "Edit" button
        editBtn.onclick = function() {
            if (selectedWarehouseDetails) {
                form.reset();
                modalTitle.textContent = 'Edit Warehouse';
                submitBtn.textContent = 'Save Changes';
                formAction.value = 'edit_warehouse';
                
                // Populate form with data
                formWarehouseId.value = selectedWarehouseDetails.id;
                warehouseName.value = selectedWarehouseDetails.name;
                warehouseCode.value = selectedWarehouseDetails.warehouse_code;
                warehouseCity.value = selectedWarehouseDetails.city;
                warehouseActive.checked = selectedWarehouseDetails.is_active == 1;
                
                openModal();
            } else {
                alert('No warehouse selected or details could not be found.');
            }
        }

        // Event listener for close button and clicking outside the modal
        closeBtn.onclick = closeModal;
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        // --- Chart.js ---
        const chartData = <?php echo json_encode($chart_data); ?>;
        const chartLabels = <?php echo json_encode($chart_labels); ?>;
        
        const chartContainer = document.querySelector('.inventory-distribution .chart-container');
        if (chartData.length === 0) {
             chartContainer.innerHTML = '<p style="text-align: center; padding: 40px; color: #718096;">No inventory data to display for this warehouse.</p>';
             return;
        }

        const ctx = document.getElementById('inventoryChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Inventory Distribution',
                    data: chartData,
                    backgroundColor: ['#805AD5', '#4FD1C5', '#F6AD55', '#63B3ED', '#A0AEC0', '#ED64A6', '#48BB78'],
                    borderColor: '#FFFFFF',
                    borderWidth: 4,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true, pointStyle: 'circle' }},
                    tooltip: { backgroundColor: '#1A202C', titleFont: { size: 14, weight: 'bold' }, bodyFont: { size: 12 }, padding: 12, cornerRadius: 8, boxPadding: 4 }
                }
            }
        });
    });
</script>
</body>
</html>