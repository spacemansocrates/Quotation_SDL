<?php
session_start();
require_once 'classes/InventoryManager.php';
require_once 'classes/Database.php'; // For fetching product name and connecting

// --- User Authentication Placeholder ---
if (!isset($_SESSION['user_id'])) {
    // header('Location: login.php');
    // exit;
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
}
// --- End User Authentication Placeholder ---

$inventory = new InventoryManager();
$product_id = null;
$product_details = null; // To store name and SKU
$transactions = [];
$error_message = '';
$page_title = "Transaction History"; // Default title

// Get product_id from GET request
if (isset($_GET['product_id'])) {
    $product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
    if ($product_id === false || $product_id <= 0) {
        $error_message = "Invalid Product ID provided.";
        $product_id = null; // Ensure it's null if invalid
    }
} else {
    $error_message = "No Product ID specified. Please select a product from the stock report.";
}

if ($product_id) {
    try {
        // Fetch product details (name, SKU) for the header
        $db_conn = (new Database())->connect();
        if ($db_conn) {
            $stmt_product = $db_conn->prepare("SELECT id, name, sku, barcode FROM products WHERE id = ?");
            $stmt_product->execute([$product_id]);
            $product_details = $stmt_product->fetch(PDO::FETCH_ASSOC);

            if ($product_details) {
                $page_title = "Transaction History for " . htmlspecialchars($product_details['name']);
            } else {
                $error_message = "Product with ID " . htmlspecialchars($product_id) . " not found.";
                $product_id = null; // Product not found, so no transactions to fetch
            }
        } else {
            $error_message = "Database connection failed. Cannot retrieve product details.";
            // Log this critical error
            error_log("Transaction History: DB connection failed.");
        }

        // If product found, fetch its transactions
        if ($product_id && $product_details) { // Re-check product_id in case it was nulled
            $transactions = $inventory->getTransactionHistory($product_id, 200); // Get up to 200 transactions
            if (empty($transactions) && !$error_message) { // Check error_message again, it might have been set by product fetch
                // No error, but no transactions. Not necessarily an error itself.
            }
        }

    } catch (PDOException $e) {
        $error_message = "Database error: Could not retrieve transaction history. " . $e->getMessage();
        error_log("Transaction History DB Error for Product ID $product_id: " . $e->getMessage());
    } catch (Exception $e) {
        $error_message = "An unexpected error occurred: " . $e->getMessage();
        error_log("Transaction History General Error for Product ID $product_id: " . $e->getMessage());
    }
}

// Helper function to determine row class based on transaction type
function getTransactionTypeClass($type) {
    switch (strtolower($type)) {
        case 'stock_in':
            return 'type-stock-in';
        case 'stock_out':
            return 'type-stock-out';
        case 'adjustment':
            return 'type-adjustment';
        case 'return':
            return 'type-return';
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
    <title><?php echo $page_title; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #f4f7f6;
            color: #333;
        }
        .navbar {
            background-color: #333;
            padding: 10px 20px;
            color: white;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            margin-right: 15px;
        }
        .navbar a:hover {
            text-decoration: underline;
        }
        .container {
            max-width: 1100px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .page-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }
        .page-header h1 {
            text-align: center;
            color: #333;
            margin-bottom: 5px;
        }
        .product-sub-header {
            text-align: center;
            font-size: 1.1em;
            color: #555;
            margin-top: 0;
        }
        .user-info { text-align: right; margin-bottom: 10px; font-size: 0.9em; color: #555;}
        .user-info strong { color: #000; }

        table.transaction-history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .transaction-history-table th,
        .transaction-history-table td {
            border: 1px solid #ddd;
            padding: 10px 12px;
            text-align: left;
            vertical-align: middle;
        }
        .transaction-history-table th {
            background-color: #e9ecef;
            color: #495057;
            font-weight: bold;
            text-transform: capitalize; /* Changed from uppercase for better readability of mixed case */
            position: sticky;
            top: 0; /* For sticky header if navbar isn't fixed */
            z-index: 10;
        }
        .transaction-history-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .transaction-history-table tr:hover {
            background-color: #e2e6ea;
        }

        /* Transaction Type Highlighting */
        .type-stock-in td.type-cell { color: #155724; font-weight: bold; /* background-color: #d4edda; */ }
        .type-stock-out td.type-cell { color: #721c24; font-weight: bold; /* background-color: #f8d7da; */ }
        .type-adjustment td.type-cell { color: #856404; font-weight: bold; /* background-color: #fff3cd; */ }
        .type-return td.type-cell { color: #004085; font-weight: bold; /* background-color: #cce5ff; */ }

        .transaction-history-table td.number { text-align: right; }
        .transaction-history-table .notes-cell {
            font-style: italic;
            color: #555;
            font-size: 0.95em;
            max-width: 250px; /* Limit width of notes */
            word-wrap: break-word;
        }

        .error-message, .info-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-message { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

        .back-link-container {
            text-align: center;
            margin-top: 30px;
        }
        .back-link-container a {
            padding: 10px 20px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .back-link-container a:hover {
            background-color: #545b62;
        }

        /* For print */
        @media print {
            body { font-size: 9pt; background-color: #fff; margin: 0.5in; }
            .navbar, .user-info, .back-link-container { display: none; }
            .container { box-shadow: none; border: 1px solid #ccc; margin: 0; padding: 0; max-width: 100%;}
            .page-header h1 { font-size: 14pt; }
            .product-sub-header { font-size: 10pt; }
            .transaction-history-table th { background-color: #f2f2f2 !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .transaction-history-table td, .transaction-history-table th { padding: 5px 7px; font-size: 8pt; }
            .notes-cell { max-width: 150px; }
            .type-stock-in td.type-cell { color: #155724 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;}
            .type-stock-out td.type-cell { color: #721c24 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;}
            /* ... other type colors if needed for print */
        }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="index.php">Dashboard</a>
        <a href="stock_in.php">Stock In</a>
        <a href="stock_out.php">Stock Out</a>
        <a href="stock_report.php">Stock Report</a>
    </div>

    <div class="container">
        <div class="user-info">
            Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        </div>
        <div class="page-header">
            <h1>Transaction History</h1>
            <?php if ($product_details): ?>
                <p class="product-sub-header">
                    Product: <strong><?php echo htmlspecialchars($product_details['name']); ?></strong>
                    (SKU: <?php echo htmlspecialchars($product_details['sku']); ?>
                    <?php if (!empty($product_details['barcode'])): ?>
                        | Barcode: <?php echo htmlspecialchars($product_details['barcode']); ?>
                    <?php endif; ?>
                    )
                </p>
            <?php endif; ?>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!$error_message && $product_id && empty($transactions)): ?>
            <div class="info-message">No transaction history found for this product.</div>
        <?php elseif (!$error_message && !empty($transactions)): ?>
            <table class="transaction-history-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Type</th>
                        <th class="number">Quantity</th>
                        <th class="number">Running Balance</th>
                        <th>User</th>
                        <th>Ref. Type</th>
                        <th>Ref. ID</th>
                        <th>Ref. Number</th>
                        <th class="notes-cell">Notes</th>
                        <!-- <th>Transaction ID</th> -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr class="<?php echo getTransactionTypeClass($transaction['transaction_type']); ?>">
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($transaction['transaction_date']))); ?></td>
                            <td class="type-cell">
                                <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($transaction['transaction_type']))); ?>
                            </td>
                            <td class="number"><?php echo htmlspecialchars($transaction['quantity']); ?></td>
                            <td class="number"><?php echo htmlspecialchars($transaction['running_balance']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['scanned_by_username'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($transaction['reference_type'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($transaction['reference_id'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($transaction['reference_number'] ?: 'N/A'); ?></td>
                            <td class="notes-cell"><?php echo nl2br(htmlspecialchars($transaction['notes'] ?: 'N/A')); ?></td>
                            <!-- <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td> -->
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="back-link-container">
            <a href="stock_report.php">Back to Stock Report</a>
        </div>
    </div>

</body>
</html>