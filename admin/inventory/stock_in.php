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
        :root {
            --background: 0 0% 100%;
            --foreground: 222.2 84% 4.9%;
            --card: 0 0% 100%;
            --card-foreground: 222.2 84% 4.9%;
            --popover: 0 0% 100%;
            --popover-foreground: 222.2 84% 4.9%;
            --primary: 221.2 83.2% 53.3%;
            --primary-foreground: 210 40% 98%;
            --secondary: 210 40% 96%;
            --secondary-foreground: 222.2 84% 4.9%;
            --muted: 210 40% 96%;
            --muted-foreground: 215.4 16.3% 46.9%;
            --accent: 210 40% 96%;
            --accent-foreground: 222.2 84% 4.9%;
            --destructive: 0 84.2% 60.2%;
            --destructive-foreground: 210 40% 98%;
            --border: 214.3 31.8% 91.4%;
            --input: 214.3 31.8% 91.4%;
            --ring: 221.2 83.2% 53.3%;
            --radius: 0.5rem;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: hsl(var(--background));
            color: hsl(var(--foreground));
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .navbar {
            background-color: hsl(var(--card));
            border-bottom: 1px solid hsl(var(--border));
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(10px);
        }

        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .nav-link {
            color: hsl(var(--muted-foreground));
            text-decoration: none;
            padding: 0.5rem 0.75rem;
            border-radius: calc(var(--radius) - 2px);
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .nav-link:hover, .nav-link.active {
            background-color: hsl(var(--accent));
            color: hsl(var(--accent-foreground));
        }

        .container {
            max-width: 768px;
            margin: 0 auto;
            padding: 1rem;
        }

        .user-info {
            text-align: right;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: hsl(var(--muted-foreground));
        }

        .user-info strong {
            color: hsl(var(--foreground));
        }

        h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: hsl(var(--foreground));
            margin-bottom: 2rem;
            text-align: center;
        }

        .card {
            background-color: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        }

        .card-header {
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: hsl(var(--foreground));
            margin: 0;
        }

        .card-description {
            font-size: 0.875rem;
            color: hsl(var(--muted-foreground));
            margin-top: 0.5rem;
            line-height: 1.4;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: hsl(var(--foreground));
            margin-bottom: 0.5rem;
        }

        .input, .select, .textarea {
            width: 100%;
            padding: 0.75rem;
            font-size: 0.875rem;
            background-color: hsl(var(--background));
            border: 1px solid hsl(var(--input));
            border-radius: calc(var(--radius) - 2px);
            transition: all 0.2s ease;
            outline: none;
        }

        .input:focus, .select:focus, .textarea:focus {
            border-color: hsl(var(--ring));
            box-shadow: 0 0 0 2px hsl(var(--ring) / 0.2);
        }

        .textarea {
            min-height: 80px;
            resize: vertical;
            font-family: inherit;
        }

        .select {
            cursor: pointer;
            background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1rem;
            padding-right: 2.5rem;
            appearance: none;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: calc(var(--radius) - 2px);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            outline: none;
            width: 100%;
        }

        .button-primary {
            background-color: hsl(var(--primary));
            color: hsl(var(--primary-foreground));
        }

        .button-primary:hover:not(:disabled) {
            background-color: hsl(var(--primary) / 0.9);
        }

        .button-secondary {
            background-color: hsl(var(--secondary));
            color: hsl(var(--secondary-foreground));
            border: 1px solid hsl(var(--border));
        }

        .button-secondary:hover:not(:disabled) {
            background-color: hsl(var(--secondary) / 0.8);
        }

        .button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius);
            border: 1px solid;
            font-size: 0.875rem;
        }

        .alert-success {
            background-color: rgb(240 253 244);
            color: rgb(22 101 52);
            border-color: rgb(187 247 208);
        }

        .alert-error {
            background-color: rgb(254 242 242);
            color: rgb(153 27 27);
            border-color: rgb(252 165 165);
        }

        .divider {
            height: 1px;
            background-color: hsl(var(--border));
            margin: 2rem 0;
            border: none;
        }

        .icon {
            width: 1rem;
            height: 1rem;
            margin-right: 0.5rem;
        }

        /* Mobile optimizations */
        @media (max-width: 640px) {
            .container {
                padding: 0.75rem;
            }

            .card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            h1 {
                font-size: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .navbar {
                padding: 0.75rem 1rem;
            }

            .nav-links {
                width: 100%;
                justify-content: flex-start;
                overflow-x: auto;
                padding-bottom: 0.25rem;
            }

            .nav-link {
                font-size: 0.8rem;
                padding: 0.5rem;
                flex-shrink: 0;
            }

            .user-info {
                text-align: left;
                order: -1;
                width: 100%;
                margin-bottom: 0.75rem;
            }

            .input, .select, .textarea, .button {
                font-size: 1rem; /* Prevents zoom on iOS */
            }
        }

        /* Large screens */
        @media (min-width: 1024px) {
            .container {
                padding: 2rem;
            }

            .button {
                width: auto;
                min-width: 120px;
            }

            .form-actions {
                display: flex;
                justify-content: flex-end;
            }
        }

        /* Focus visible for accessibility */
        .button:focus-visible,
        .input:focus-visible,
        .select:focus-visible,
        .textarea:focus-visible {
            outline: 2px solid hsl(var(--ring));
            outline-offset: 2px;
        }

        /* Loading state */
        .button:disabled {
            position: relative;
        }

        .button:disabled::after {
            content: '';
            position: absolute;
            width: 1rem;
            height: 1rem;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Print styles */
        @media print {
            .navbar,
            .user-info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="user-info">
                Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
            </div>
            <div class="nav-links">
                <a href="index.php" class="nav-link">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v4H8V5z"/>
                    </svg>
                    Dashboard
                </a>
                <a href="stock_in.php" class="nav-link active">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Stock In
                </a>
                <a href="stock_out.php" class="nav-link">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                    </svg>
                    Stock Out
                </a>
                <a href="stock_report.php" class="nav-link">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Reports
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Stock In Operations</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Add New Stock</h2>
                <p class="card-description">
                    Record new inventory received from suppliers or other sources.
                </p>
            </div>
            
            <form method="POST" action="stock_in.php">
                <input type="hidden" name="action" value="add_stock">

                <div class="form-group">
                    <label for="product_id_stock" class="label">Product *</label>
                    <select name="product_id" id="product_id_stock" class="select" required>
                        <option value="">-- Select Product --</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name'] . ' (SKU: ' . $product['sku'] . ') - Barcode: ' . ($product['barcode'] ?: 'N/A')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quantity_stock" class="label">Quantity Received *</label>
                    <input type="number" id="quantity_stock" name="quantity" min="1" class="input" required placeholder="Enter quantity">
                </div>

                <div class="form-group">
                    <label for="reference_number_stock" class="label">Reference Number (e.g., Invoice/GRN)</label>
                    <input type="text" id="reference_number_stock" name="reference_number" class="input" placeholder="e.g., INV-2023-001">
                </div>

                <div class="form-group">
                    <label for="notes_stock" class="label">Notes (Optional)</label>
                    <textarea name="notes" id="notes_stock" class="textarea" placeholder="e.g., Received from Supplier XYZ"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Stock to Inventory
                    </button>
                </div>
            </form>
        </div>

        <hr class="divider">

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Generate Barcodes for Printing</h2>
                <p class="card-description">
                    Use this to print barcode labels for products, typically after adding new stock or for existing items that need labels.
                </p>
            </div>
            
            <form method="POST" action="stock_in.php">
                <input type="hidden" name="action" value="generate_barcode">

                <div class="form-group">
                    <label for="product_id_barcode" class="label">Product *</label>
                    <select name="product_id_barcode" id="product_id_barcode" class="select" required>
                        <option value="">-- Select Product --</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name'] . ' (SKU: ' . $product['sku'] . ') - Barcode: ' . ($product['barcode'] ?: 'Not Yet Generated')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quantity_print" class="label">Number of Barcode Labels to Print *</label>
                    <input type="number" id="quantity_print" name="quantity_print" min="1" value="1" class="input" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-secondary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Generate & Prepare for Printing
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>