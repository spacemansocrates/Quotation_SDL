<?php
session_start();
require_once 'classes/InventoryManager.php';
require_once 'classes/Database.php'; // Required for fetching products directly

// --- User Authentication Placeholder ---
// In a real application, you would have a robust login system.
// For testing purposes, we'll mock a user session if one doesn't exist.
if (!isset($_SESSION['user_id'])) {
    // header('Location: login.php'); // Uncomment and create login.php for a real system
    // exit;
    $_SESSION['user_id'] = 1;       // Mock User ID
    $_SESSION['username'] = 'test_user'; // Mock Username
    // You might also store user roles/permissions in the session
}
// --- End User Authentication Placeholder ---

$inventory = new InventoryManager();
$message = '';
$message_type = ''; // 'success' or 'error'

// --- Handle POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Action: Add Stock
    if ($_POST['action'] == 'add_stock') {
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $reference_number = filter_input(INPUT_POST, 'reference_number', FILTER_SANITIZE_SPECIAL_CHARS);
        $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS);

        if ($product_id && $quantity && $quantity > 0) {
            $result = $inventory->addStock(
                $product_id,
                $quantity,
                $_SESSION['user_id'],
                'receipt', // Default reference_type for stock in
                null,      // reference_id (e.g., Purchase Order ID, can be added as a field)
                $reference_number,
                $notes
            );

            if ($result['success']) {
                $message = "Stock added successfully for product ID " . htmlspecialchars($product_id) . ". New stock level: " . $result['new_stock'];
                $message_type = 'success';
            } else {
                $message = "Error adding stock: " . htmlspecialchars($result['error']);
                $message_type = 'error';
            }
        } else {
            $message = "Error: Invalid product selected or quantity provided. Quantity must be greater than 0.";
            $message_type = 'error';
        }
    }

    // Action: Generate Barcodes
    if ($_POST['action'] == 'generate_barcode') {
        $product_id_for_barcode = filter_input(INPUT_POST, 'product_id_barcode', FILTER_VALIDATE_INT);
        $quantity_to_print = filter_input(INPUT_POST, 'quantity_print', FILTER_VALIDATE_INT);

        if ($product_id_for_barcode && $quantity_to_print && $quantity_to_print > 0) {
            $result = $inventory->generatePrintableBarcodes($product_id_for_barcode, $quantity_to_print, $_SESSION['user_id']);

            if (isset($result['success']) && $result['success'] === true && isset($result['barcode_content'])) {
                $_SESSION['print_data'] = $result;
                header('Location: print_barcodes.php');
                exit;
            } else {
                $error_message = isset($result['error']) ? $result['error'] : 'Unknown error during barcode generation.';
                $message = "Error generating barcodes: " . htmlspecialchars($error_message);
                $message_type = 'error';
            }
        } else {
             $message = "Error: Invalid product selected or quantity for printing. Quantity must be greater than 0.";
             $message_type = 'error';
        }
    }
}
// --- End Handle POST Requests ---

// --- Fetch Products for Dropdowns ---
$products = [];
try {
    $db_for_products = new Database();
    $conn_for_products = $db_for_products->connect();
    if ($conn_for_products) {
        // Fetch products that can receive stock
        // You might want to add a filter here, e.g., p.is_stockable = 1
        $stmt_products = $conn_for_products->prepare("SELECT id, name, sku, barcode FROM products ORDER BY name ASC");
        $stmt_products->execute();
        $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $message = "Critical Error: Could not connect to the database to fetch products.";
        $message_type = 'error';
    }
} catch (PDOException $e) {
    $message = "Database Error: " . $e->getMessage();
    $message_type = 'error';
    // Log this error $e->getMessage()
}
// --- End Fetch Products ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock In - Add Inventory</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
        .navbar { background-color: #333; padding: 10px 20px; color: white; }
        .navbar a { color: white; text-decoration: none; margin-right: 15px; }
        .navbar a:hover { text-decoration: underline; }
        .container { max-width: 700px; margin: 30px auto; padding: 25px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom:20px; }
        h1 { text-align: center; }
        label { display: block; margin-top: 15px; font-weight: bold; margin-bottom: 5px; }
        input[type="number"], input[type="text"], select, textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        input[type="number"]:focus, input[type="text"]:focus, select:focus, textarea:focus {
            border-color: #007bff;
            outline: none;
        }
        textarea { min-height: 80px; resize: vertical; }
        button {
            padding: 12px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 20px;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        button:hover { background-color: #0056b3; }
        .button-secondary { background-color: #6c757d; }
        .button-secondary:hover { background-color: #545b62; }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border: 1px solid transparent;
            font-size: 1em;
        }
        .success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        hr { margin: 40px 0; border: 0; border-top: 1px solid #eee; }
        .form-section { margin-bottom: 30px; }
        .user-info { text-align: right; margin-bottom: 10px; font-size: 0.9em; color: #555;}
        .user-info strong { color: #000; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="index.php">Dashboard</a>
        <a href="stock_in.php">Stock In</a>
        <a href="stock_out.php">Stock Out</a>
        <a href="stock_report.php">Stock Report</a>
        <!-- Add other navigation links here -->
    </div>

    <div class="container">
        <div class="user-info">
            Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        </div>
        <h1>Stock In Operations</h1>

        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Add New Stock</h2>
            <form method="POST" action="stock_in.php">
                <input type="hidden" name="action" value="add_stock">

                <label for="product_id_stock">Product:</label>
                <select name="product_id" id="product_id_stock" required>
                    <option value="">-- Select Product --</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>">
                            <?php echo htmlspecialchars($product['name'] . ' (SKU: ' . $product['sku'] . ') - Barcode: ' . ($product['barcode'] ?: 'N/A')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="quantity_stock">Quantity Received:</label>
                <input type="number" id="quantity_stock" name="quantity" min="1" required>

                <label for="reference_number_stock">Reference Number (e.g., Invoice/GRN):</label>
                <input type="text" id="reference_number_stock" name="reference_number" placeholder="e.g., INV-2023-001">

                <label for="notes_stock">Notes (Optional):</label>
                <textarea name="notes" id="notes_stock" placeholder="e.g., Received from Supplier XYZ"></textarea>

                <button type="submit">Add Stock to Inventory</button>
            </form>
        </div>

        <hr>

        <div class="form-section">
            <h2>Generate Barcodes for Printing</h2>
            <p>Use this to print barcode labels for products, typically after adding new stock or for existing items that need labels.</p>
            <form method="POST" action="stock_in.php">
                <input type="hidden" name="action" value="generate_barcode">

                <label for="product_id_barcode">Product:</label>
                <select name="product_id_barcode" id="product_id_barcode" required>
                    <option value="">-- Select Product --</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>">
                            <?php echo htmlspecialchars($product['name'] . ' (SKU: ' . $product['sku'] . ') - Barcode: ' . ($product['barcode'] ?: 'Not Yet Generated')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="quantity_print">Number of Barcode Labels to Print:</label>
                <input type="number" id="quantity_print" name="quantity_print" min="1" value="1" required>

                <button type="submit" class="button-secondary">Generate & Prepare for Printing</button>
            </form>
        </div>

    </div>
</body>
</html>



stock_report.php 


<?php
session_start();
require_once 'classes/InventoryManager.php';
require_once 'classes/Database.php'; // In case needed for other things, good to have

// --- User Authentication Placeholder ---
if (!isset($_SESSION['user_id'])) {
    // header('Location: login.php');
    // exit;
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
}
// --- End User Authentication Placeholder ---

$inventory = new InventoryManager();
$stock_report_data = [];
$error_message = '';

try {
    $stock_report_data = $inventory->getStockReport();
} catch (PDOException $e) {
    // Log the detailed error $e->getMessage()
    $error_message = "Database error: Could not retrieve stock report. Please try again later or contact support.";
    // You might want to log $e->getMessage() to a file for debugging
    error_log("Stock Report DB Error: " . $e->getMessage());
} catch (Exception $e) {
    $error_message = "An unexpected error occurred: " . $e->getMessage();
    error_log("Stock Report General Error: " . $e->getMessage());
}

// Helper function to determine row class based on stock status
function getStockStatusClass($status) {
    switch (strtoupper($status)) {
        case 'OUT_OF_STOCK':
            return 'status-out-of-stock';
        case 'LOW_STOCK':
            return 'status-low-stock';
        case 'IN_STOCK':
            return 'status-in-stock';
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
    <title>Stock Report</title>
    <style>
/* Mobile-first professional table styling inspired by shadcn/ui */
* {
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f8fafc;
    color: #0f172a;
    line-height: 1.5;
    font-size: 14px;
}

.navbar {
    background-color: #020617;
    padding: 12px 16px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 50;
    overflow-x: auto;
}

.navbar a {
    color: #f1f5f9;
    text-decoration: none;
    margin-right: 20px;
    font-weight: 500;
    font-size: 14px;
    padding: 8px 12px;
    border-radius: 6px;
    transition: all 0.2s ease;
    display: inline-block;
    white-space: nowrap;
}

.navbar a:hover {
    background-color: #334155;
    text-decoration: none;
}

.container {
    max-width: 100%;
    margin: 0;
    padding: 16px;
    background-color: transparent;
}

@media (min-width: 768px) {
    .container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 24px;
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }
}

h1 {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 24px 0;
    padding: 0;
    border: none;
    text-align: left;
}

@media (min-width: 768px) {
    h1 {
        font-size: 32px;
        text-align: center;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 16px;
        margin-bottom: 32px;
    }
}

.user-info {
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
    font-size: 13px;
    color: #64748b;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

@media (min-width: 768px) {
    .user-info {
        text-align: right;
        background-color: transparent;
        border: none;
        box-shadow: none;
        padding: 0;
        margin-bottom: 16px;
    }
}

.user-info strong {
    color: #0f172a;
    font-weight: 600;
}

/* Table Container for horizontal scroll on mobile */
.table-container {
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow-x: auto;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    -webkit-overflow-scrolling: touch;
}

.stock-report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 800px; /* Ensures table doesn't get too cramped */
}

@media (min-width: 768px) {
    .stock-report-table {
        font-size: 14px;
        min-width: auto;
    }
}

.stock-report-table th,
.stock-report-table td {
    border: 1px solid #e2e8f0;
    padding: 8px 6px;
    text-align: left;
    vertical-align: middle;
}

@media (min-width: 768px) {
    .stock-report-table th,
    .stock-report-table td {
        padding: 12px 16px;
    }
}

.stock-report-table th {
    background-color: #f8fafc;
    color: #374151;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 0.025em;
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: nowrap;
}

@media (min-width: 768px) {
    .stock-report-table th {
        font-size: 12px;
    }
}

.stock-report-table tr:nth-child(even) {
    background-color: #f9fafb;
}

.stock-report-table tr:hover {
    background-color: #f1f5f9;
}

/* Status styling */
.status-in-stock td.status-cell {
    background-color: #dcfce7 !important;
    color: #166534 !important;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 0.025em;
}

.status-low-stock td.status-cell {
    background-color: #fef3c7 !important;
    color: #92400e !important;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 0.025em;
}

.status-out-of-stock td.status-cell {
    background-color: #fee2e2 !important;
    color: #991b1b !important;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 0.025em;
}

.stock-report-table td.number {
    text-align: right;
    font-family: ui-monospace, 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
    font-weight: 600;
    color: #374151;
}

.stock-report-table td.barcode-cell {
    font-family: ui-monospace, 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
    font-size: 11px;
    color: #475569;
    background-color: #f8fafc;
}

.stock-report-table .actions-cell {
    text-align: center;
    white-space: nowrap;
    min-width: 80px;
}

.stock-report-table .actions-cell a {
    color: #3b82f6;
    text-decoration: none;
    margin: 0 2px;
    padding: 4px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
    transition: all 0.2s ease;
    background-color: #ffffff;
    display: inline-block;
}

@media (min-width: 768px) {
    .stock-report-table .actions-cell a {
        margin: 0 4px;
        padding: 6px 12px;
        font-size: 12px;
    }
}

.stock-report-table .actions-cell a:hover {
    background-color: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}

.actions-cell a.edit-link {
    border-color: #fbbf24;
    color: #f59e0b;
}

.actions-cell a.edit-link:hover {
    background-color: #fef3c7;
}

/* Error and no data messages */
.error-message {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 16px;
    margin-bottom: 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
}

.no-data-message {
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 32px 16px;
    text-align: center;
    color: #64748b;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.no-data-message p {
    margin: 0 0 12px 0;
}

.no-data-message a {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
}

.no-data-message a:hover {
    text-decoration: underline;
}

/* Print styles */
@media print {
    body {
        font-size: 10pt;
        background-color: #ffffff;
        margin: 0.5in;
        color: #000000;
    }
    
    .navbar,
    .user-info {
        display: none;
    }
    
    .container {
        box-shadow: none;
        border: 1px solid #cccccc;
        margin: 0;
        padding: 0;
        max-width: 100%;
        background-color: #ffffff;
    }
    
    .table-container {
        border: none;
        box-shadow: none;
        overflow: visible;
    }
    
    h1 {
        font-size: 16pt;
        margin-bottom: 15px;
        text-align: center;
    }
    
    .stock-report-table {
        min-width: auto;
    }
    
    .stock-report-table th {
        background-color: #f2f2f2 !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .stock-report-table td,
    .stock-report-table th {
        padding: 6px 8px;
        border: 1px solid #cccccc;
    }
    
    .actions-cell {
        display: none;
    }
    
    .stock-report-table tr:nth-child(even) {
        background-color: #f9f9f9 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .status-in-stock td.status-cell {
        background-color: #d4edda !important;
        color: #155724 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .status-low-stock td.status-cell {
        background-color: #fff3cd !important;
        color: #856404 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .status-out-of-stock td.status-cell {
        background-color: #f8d7da !important;
        color: #721c24 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
    </style>
</head>
<body>

    <div class="navbar">
        <a href="index.php">Dashboard</a>
        <a href="stock_in.php">Stock In</a>
        <a href="stock_out.php">Stock Out</a>
        <a href="stock_report.php">Stock Report</a>
        <!-- Add other navigation links here -->
    </div>

    <div class="container">
        <div class="user-info">
            Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        </div>
        <h1>Inventory Stock Report</h1>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!$error_message && empty($stock_report_data)): ?>
            <div class="no-data-message">
                <p>No products found in the inventory system yet, or no stock data available.</p>
                <p><a href="stock_in.php">Add new stock</a> or <a href="manage_products.php">manage products</a>.</p> <!-- Assuming manage_products.php -->
            </div>
        <?php elseif (!$error_message): ?>
            <table class="stock-report-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th class="number">Current Stock</th>
                        <th class="number">Total Received</th>
                        <th class="number">Total Sold</th>
                        <th class="number">Min. Level</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stock_report_data as $item): ?>
                        <tr class="<?php echo getStockStatusClass($item['stock_status']); ?>">
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['sku']); ?></td>
                            <td class="barcode-cell"><?php echo htmlspecialchars($item['barcode'] ?: 'N/A'); ?></td>
                            <td class="number"><?php echo htmlspecialchars($item['current_stock']); ?></td>
                            <td class="number"><?php echo htmlspecialchars($item['total_received']); ?></td>
                            <td class="number"><?php echo htmlspecialchars($item['total_sold']); ?></td>
                            <td class="number"><?php echo htmlspecialchars($item['minimum_stock_level']); ?></td>
                            <td class="status-cell"><?php echo ucwords(strtolower(str_replace('_', ' ', htmlspecialchars($item['stock_status'])))); ?></td>
                            <td><?php echo $item['last_updated'] ? htmlspecialchars(date('Y-m-d H:i', strtotime($item['last_updated']))) : 'N/A'; ?></td>
                            <td class="actions-cell">
                                <a href="transaction_history.php?product_id=<?php echo $item['id']; ?>" title="View transaction history for this product">History</a>
                                <!-- Add link to edit product/stock levels if you have such a page -->
                                <!-- <a href="edit_product_stock.php?product_id=<?php echo $item['id']; ?>" class="edit-link" title="Edit product or stock levels">Edit</a> -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        // Future JavaScript for filtering, sorting, or live updates could go here.
        // For now, it's a static report.
    </script>

</body>
</html>