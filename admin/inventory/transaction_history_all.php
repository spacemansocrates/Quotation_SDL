<?php
session_start();
require_once 'classes/InventoryManager.php'; // For consistency, though not directly used for this query
require_once 'classes/Database.php';

// --- User Authentication Placeholder ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
    // header('Location: login.php'); exit;
}
// --- End User Authentication Placeholder ---

$db = new Database();
$conn = $db->connect();

$transactions = [];
$error_message = '';
$page_title = "All Transaction History";

// --- Pagination ---
$items_per_page = 25; // Number of transactions to display per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

$total_transactions = 0;
$total_pages = 1;

// Second pagination setup (for records)
$records_per_page = 50;
$current_page_records = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset_records = ($current_page_records - 1) * $records_per_page;

// Initialize common variables
$error_message = '';
$transactions = [];
$total_transactions = 0;
$total_pages = 0;
if ($conn) {
    try {
        // Get total number of transactions for pagination
        $stmt_total = $conn->prepare("SELECT COUNT(id) as total FROM stock_transactions");
        $stmt_total->execute();
        $total_result = $stmt_total->fetch(PDO::FETCH_ASSOC);
        $total_transactions = $total_result ? (int)$total_result['total'] : 0;
        $total_pages = ceil($total_transactions / $items_per_page);
        if ($total_pages < 1) $total_pages = 1;
        if ($current_page > $total_pages) $current_page = $total_pages; // Correct current page if out of bounds
        $offset = ($current_page - 1) * $items_per_page; // Recalculate offset if page changed

        // Fetch transactions for the current page
        $stmt = $conn->prepare("
            SELECT
                st.id as transaction_id,
                st.transaction_type,
                st.quantity,
                st.running_balance,
                st.reference_type,
                st.reference_id,
                st.reference_number,
                st.notes,
                st.transaction_date,
                u.username as scanned_by_username,
                p.name as product_name,
                p.sku as product_sku,
                p.id as product_id
            FROM stock_transactions st
            JOIN products p ON st.product_id = p.id
            LEFT JOIN users u ON st.scanned_by_user_id = u.id -- Ensure users table exists
            ORDER BY st.transaction_date DESC, st.id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = "Database error: Could not retrieve transaction history. " . $e->getMessage();
        error_log("All Transaction History DB Error: " . $e->getMessage());
    } catch (Exception $e) {
        $error_message = "An unexpected error occurred: " . $e->getMessage();
        error_log("All Transaction History General Error: " . $e->getMessage());
    }
} else {
    $error_message = "Database connection failed.";
    error_log("All Transaction History: DB connection failed.");
}

// Helper function to get transaction type badge classes
function getTransactionTypeBadge($type) {
    switch (strtolower($type)) {
        case 'stock_in': return 'badge-success';
        case 'stock_out': return 'badge-destructive';
        case 'adjustment': return 'badge-warning';
        case 'return': return 'badge-info';
        default: return 'badge-secondary';
    }
}

// Helper function to get transaction type icon
function getTransactionTypeIcon($type) {
    switch (strtolower($type)) {
        case 'stock_in': return '↗';
        case 'stock_out': return '↘';
        case 'adjustment': return '⚡';
        case 'return': return '↩';
        default: return '•';
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
        /* CSS Variables for consistent theming */
        :root {
            --background: 0 0% 100%;
            --foreground: 240 10% 3.9%;
            --card: 0 0% 100%;
            --card-foreground: 240 10% 3.9%;
            --popover: 0 0% 100%;
            --popover-foreground: 240 10% 3.9%;
            --primary: 240 5.9% 10%;
            --primary-foreground: 0 0% 98%;
            --secondary: 240 4.8% 95.9%;
            --secondary-foreground: 240 5.9% 10%;
            --muted: 240 4.8% 95.9%;
            --muted-foreground: 240 3.8% 46.1%;
            --accent: 240 4.8% 95.9%;
            --accent-foreground: 240 5.9% 10%;
            --destructive: 0 84.2% 60.2%;
            --destructive-foreground: 0 0% 98%;
            --success: 142 76% 36%;
            --success-foreground: 0 0% 98%;
            --warning: 38 92% 50%;
            --warning-foreground: 0 0% 3.9%;
            --info: 221 83% 53%;
            --info-foreground: 0 0% 98%;
            --border: 240 5.9% 90%;
            --input: 240 5.9% 90%;
            --ring: 240 10% 3.9%;
            --radius: 0.5rem;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: hsl(var(--muted));
            color: hsl(var(--foreground));
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Navigation */
        .navbar {
            background: hsl(var(--card));
            border-bottom: 1px solid hsl(var(--border));
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(8px);
        }

        .nav-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .nav-links {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .nav-links::-webkit-scrollbar {
            display: none;
        }

        .nav-link {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
            color: hsl(var(--muted-foreground));
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .nav-link:hover {
            background: hsl(var(--accent));
            color: hsl(var(--accent-foreground));
        }

        .nav-link.active {
            background: hsl(var(--primary));
            color: hsl(var(--primary-foreground));
        }

        /* User info */
        .user-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: hsl(var(--secondary));
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .user-avatar {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background: hsl(var(--primary));
            color: hsl(var(--primary-foreground));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Main container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Header */
        .header {
            margin-bottom: 2rem;
        }

        .header-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: hsl(var(--foreground));
            margin: 0;
            margin-bottom: 0.5rem;
        }

        .header-subtitle {
            color: hsl(var(--muted-foreground));
            font-size: 0.875rem;
            margin: 0;
        }

        /* Cards */
        .card {
            background: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: calc(var(--radius) + 2px);
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        }

        /* Alerts */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: var(--radius);
            border: 1px solid;
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background: hsl(0 84.2% 60.2% / 0.1);
            border-color: hsl(var(--destructive));
            color: hsl(var(--destructive-foreground));
        }

        .alert-info {
            background: hsl(221 83% 53% / 0.1);
            border-color: hsl(var(--info));
            color: hsl(var(--info-foreground));
        }

        /* Stats bar */
        .stats-bar {
            background: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            color: hsl(var(--muted-foreground));
        }

        /* Transaction cards for mobile */
        .transaction-cards {
            display: block;
        }

        .transaction-card {
            background: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            margin-bottom: 0.75rem;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .transaction-card:hover {
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        .transaction-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid hsl(var(--border));
        }

        .transaction-product {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
            min-width: 0;
        }

        .transaction-icon {
            width: 2rem;
            height: 2rem;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .transaction-info {
            min-width: 0;
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            color: hsl(var(--foreground));
            text-decoration: none;
            display: block;
            margin-bottom: 0.125rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-name:hover {
            color: hsl(var(--primary));
        }

        .product-sku {
            font-size: 0.75rem;
            color: hsl(var(--muted-foreground));
            font-family: 'Monaco', 'Consolas', monospace;
        }

        .transaction-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: calc(var(--radius) - 2px);
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-success {
            background: hsl(var(--success) / 0.1);
            color: hsl(var(--success));
            border: 1px solid hsl(var(--success) / 0.2);
        }

        .badge-success .transaction-icon {
            background: hsl(var(--success));
            color: hsl(var(--success-foreground));
        }

        .badge-destructive {
            background: hsl(var(--destructive) / 0.1);
            color: hsl(var(--destructive));
            border: 1px solid hsl(var(--destructive) / 0.2);
        }

        .badge-destructive .transaction-icon {
            background: hsl(var(--destructive));
            color: hsl(var(--destructive-foreground));
        }

        .badge-warning {
            background: hsl(var(--warning) / 0.1);
            color: hsl(var(--warning-foreground));
            border: 1px solid hsl(var(--warning) / 0.2);
        }

        .badge-warning .transaction-icon {
            background: hsl(var(--warning));
            color: hsl(var(--warning-foreground));
        }

        .badge-info {
            background: hsl(var(--info) / 0.1);
            color: hsl(var(--info));
            border: 1px solid hsl(var(--info) / 0.2);
        }

        .badge-info .transaction-icon {
            background: hsl(var(--info));
            color: hsl(var(--info-foreground));
        }

        .badge-secondary {
            background: hsl(var(--secondary));
            color: hsl(var(--secondary-foreground));
        }

        .badge-secondary .transaction-icon {
            background: hsl(var(--muted));
            color: hsl(var(--muted-foreground));
        }

        .transaction-details {
            padding: 1rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .detail-group {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: hsl(var(--muted-foreground));
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            font-weight: 500;
            color: hsl(var(--foreground));
        }

        .detail-value.mono {
            font-family: 'Monaco', 'Consolas', monospace;
        }

        .detail-value.highlight {
            color: hsl(var(--primary));
            font-weight: 600;
        }

        .transaction-notes {
            grid-column: 1 / -1;
            padding-top: 0.5rem;
            border-top: 1px solid hsl(var(--border));
            margin-top: 0.5rem;
        }

        .notes-content {
            font-size: 0.875rem;
            color: hsl(var(--muted-foreground));
            font-style: italic;
            line-height: 1.4;
        }

        /* Pagination */
        .pagination-container {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            padding: 0.25rem;
        }

        .pagination-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.5rem;
            height: 2.5rem;
            padding: 0.5rem;
            border-radius: calc(var(--radius) - 2px);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            background: transparent;
            cursor: pointer;
        }

        .pagination-button:hover:not(.disabled):not(.current) {
            background: hsl(var(--accent));
            color: hsl(var(--accent-foreground));
        }

        .pagination-button.current {
            background: hsl(var(--primary));
            color: hsl(var(--primary-foreground));
        }

        .pagination-button.disabled {
            color: hsl(var(--muted-foreground));
            cursor: not-allowed;
            opacity: 0.5;
        }

        /* Back button */
        .back-container {
            margin-top: 2rem;
            text-align: center;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: hsl(var(--secondary));
            color: hsl(var(--secondary-foreground));
            text-decoration: none;
            border-radius: var(--radius);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .back-button:hover {
            background: hsl(var(--accent));
            color: hsl(var(--accent-foreground));
        }

        /* Desktop table view */
        @media (min-width: 768px) {
            .nav-container {
                padding: 1.5rem;
            }

            .container {
                padding: 2rem;
            }

            .header-title {
                font-size: 2.25rem;
            }

            .transaction-cards {
                display: none;
            }

            .transaction-table-container {
                display: block;
                background: hsl(var(--card));
                border: 1px solid hsl(var(--border));
                border-radius: var(--radius);
                overflow: hidden;
            }

            .transaction-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.875rem;
            }

            .transaction-table th {
                background: hsl(var(--muted));
                color: hsl(var(--muted-foreground));
                font-weight: 600;
                text-align: left;
                padding: 0.75rem;
                border-bottom: 1px solid hsl(var(--border));
                white-space: nowrap;
            }

            .transaction-table td {
                padding: 0.75rem;
                border-bottom: 1px solid hsl(var(--border));
                vertical-align: top;
            }

            .transaction-table tr:hover {
                background: hsl(var(--muted) / 0.5);
            }

            .table-number {
                text-align: right;
                font-family: 'Monaco', 'Consolas', monospace;
            }

            .table-notes {
                max-width: 200px;
                word-wrap: break-word;
                font-size: 0.8125rem;
                color: hsl(var(--muted-foreground));
                font-style: italic;
            }
        }

        /* Mobile-only table (hidden by default) */
        .transaction-table-container {
            display: none;
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                color: black;
            }

            .navbar, .pagination-container, .back-container {
                display: none;
            }

            .container {
                max-width: none;
                padding: 0;
            }

            .transaction-cards {
                display: none;
            }

            .transaction-table-container {
                display: block;
            }

            .card {
                border: 1px solid #ccc;
                background: white;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-links">
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="stock_in.php" class="nav-link">Stock In</a>
                <a href="stock_out.php" class="nav-link">Stock Out</a>
                <a href="stock_report.php" class="nav-link">Stock Report</a>
                <a href="transaction_history_all.php" class="nav-link active">All Transactions</a>
            </div>
            <div class="user-badge">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h1 class="header-title">Transaction History</h1>
            <p class="header-subtitle">Complete record of all stock movements and adjustments</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <span>⚠</span>
                <div><?php echo htmlspecialchars($error_message); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!$error_message && empty($transactions) && $total_transactions > 0): ?>
            <div class="alert alert-info">
                <span>ℹ</span>
                <div>No transactions found for the current page. This might be an issue if you are not on page 1.</div>
            </div>
        <?php elseif (!$error_message && empty($transactions) && $total_transactions == 0): ?>
            <div class="alert alert-info">
                <span>ℹ</span>
                <div>No transaction history found in the system yet.</div>
            </div>
        <?php elseif (!$error_message && !empty($transactions)): ?>
            <div class="stats-bar">
                Showing page <?php echo $current_page; ?> of <?php echo $total_pages; ?> • <?php echo $total_transactions; ?> total transactions
            </div>

            <!-- Mobile Card View -->
            <div class="transaction-cards">
                <?php foreach ($transactions as $transaction): ?>
                    <div class="transaction-card <?php echo getTransactionTypeBadge($transaction['transaction_type']); ?>">
                        <div class="transaction-header">
                            <div class="transaction-product">
                                <div class="transaction-icon">
                                    <?php echo getTransactionTypeIcon($transaction['transaction_type']); ?>
                                </div>
                                <div class="transaction-info">
                                    <a href="transaction_history.php?product_id=<?php echo $transaction['product_id']; ?>" class="product-name">
                                        <?php echo htmlspecialchars($transaction['product_name']); ?>
                                    </a>
                                    <div class="product-sku"><?php echo htmlspecialchars($transaction['product_sku']); ?></div>
                                </div>
                            </div>
                            <div class="transaction-badge <?php echo getTransactionTypeBadge($transaction['transaction_type']); ?>">
                                <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($transaction['transaction_type']))); ?>
                            </div>
                        </div>
                        <div class="transaction-details">
                            <div class="detail-group">
                                <div class="detail-label">Date & Time</div>
                                <div class="detail-value"><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($transaction['transaction_date']))); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">User</div>
                                <div class="detail-value"><?php echo htmlspecialchars($transaction['scanned_by_username'] ?: 'N/A'); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Quantity</div>
                                <div class="detail-value mono highlight"><?php echo htmlspecialchars($transaction['quantity']); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Balance</div>
                                <div class="detail-value mono"><?php echo htmlspecialchars($transaction['running_balance']); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Reference</div>
                                <div class="detail-value"><?php echo htmlspecialchars($transaction['reference_type'] ?: 'N/A'); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Ref. Number</div>
                                <div class="detail-value mono"><?php echo htmlspecialchars($transaction['reference_number'] ?: 'N/A'); ?></div>
                            </div>
                            <?php if ($transaction['notes']): ?>
                                <div class="transaction-notes">
                                    <div class="detail-label">Notes</div>
                                    <div class="notes-content"><?php echo nl2br(htmlspecialchars($transaction['notes'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Desktop Table View -->
            <div class="transaction-table-container">
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Type</th>
                            <th class="table-number">Qty</th>
                            <th class="table-number">Balance</th>
                            <th>User</th>
                            <th>Ref. Type</th>
                            <th>Ref. ID</th>
                            <th>Ref. Num</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($transaction['transaction_date']))); ?></td>
                                <td>
                                    <a href="transaction_history.php?product_id=<?php echo $transaction['product_id']; ?>" class="product-name">
                                        <?php echo htmlspecialchars($transaction['product_name']); ?>
                                    </a>
                                </td>
                                <td class="mono"><?php echo htmlspecialchars($transaction['product_sku']); ?></td>
                                <td>
                                    <span class="transaction-badge <?php echo getTransactionTypeBadge($transaction['transaction_type']); ?>">
                                        <?php echo getTransactionTypeIcon($transaction['transaction_type']); ?>
                                        <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($transaction['transaction_type']))); ?>
                                    </span>
                                </td>
                                <td class="table-number"><?php echo htmlspecialchars($transaction['quantity']); ?></td>
                                <td class="table-number"><?php echo htmlspecialchars($transaction['running_balance']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['scanned_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transaction['reference_type'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transaction['reference_id'] ?: 'N/A'); ?></td>
                                <td class="mono"><?php echo htmlspecialchars($transaction['reference_number'] ?: 'N/A'); ?></td>
                                <td class="table-notes"><?php echo nl2br(htmlspecialchars($transaction['notes'] ?: 'N/A')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

             <!-- Pagination -->
            <div class="pagination-container">
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=1" class="pagination-button" title="First Page">
                            <span>«</span>
                        </a>
                        <a href="?page=<?php echo $current_page - 1; ?>" class="pagination-button" title="Previous Page">
                            <span>‹</span>
                        </a>
                    <?php endif; ?>

                    <?php
                    // Calculate pagination range
                    $range = 2;
                    $start = max(1, $current_page - $range);
                    $end = min($total_pages, $current_page + $range);
                    
                    // Show first page if not in range
                    if ($start > 1) {
                        echo '<a href="?page=1" class="pagination-button">1</a>';
                        if ($start > 2) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                    }
                    
                    // Show page numbers in range
                    for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $current_page): ?>
                            <span class="pagination-button active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>" class="pagination-button"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor;
                    
                    // Show last page if not in range
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                        echo '<a href="?page=' . $total_pages . '" class="pagination-button">' . $total_pages . '</a>';
                    }
                    ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>" class="pagination-button" title="Next Page">
                            <span>›</span>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?>" class="pagination-button" title="Last Page">
                            <span>»</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="pagination-info">
                    Showing <?php echo number_format(($current_page - 1) * $records_per_page + 1); ?>-<?php echo number_format(min($current_page * $records_per_page, $total_transactions)); ?> 
                    of <?php echo number_format($total_transactions); ?> transactions
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Optional: Auto-refresh every 30 seconds to show new transactions
        // setInterval(function() {
        //     location.reload();
        // }, 30000);
        
        // Add keyboard navigation for pagination
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft' && <?php echo $current_page; ?> > 1) {
                window.location.href = '?page=<?php echo $current_page - 1; ?>';
            } else if (e.key === 'ArrowRight' && <?php echo $current_page; ?> < <?php echo $total_pages; ?>) {
                window.location.href = '?page=<?php echo $current_page + 1; ?>';
            }
        });
    </script>
</body>
</html>