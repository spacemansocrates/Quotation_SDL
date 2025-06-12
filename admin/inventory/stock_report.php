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

function formatStockStatus($status) {
    return ucwords(strtolower(str_replace('_', ' ', $status)));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Report</title>
    <style>
        /* Mobile-first professional styling inspired by shadcn/ui */
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
            -webkit-overflow-scrolling: touch;
        }

        .navbar-content {
            display: flex;
            gap: 8px;
            min-width: max-content;
        }

        .navbar a {
            color: #f1f5f9;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .navbar a:hover {
            background-color: #334155;
        }

        .container {
            padding: 16px;
            max-width: 100%;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 20px 0;
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

        .user-info strong {
            color: #0f172a;
            font-weight: 600;
        }

        /* Mobile card layout */
        .stock-cards {
            display: block;
        }

        .stock-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 16px;
            padding: 16px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .stock-card:hover {
            box-shadow: 0 4px 12px -4px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        .stock-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            gap: 12px;
        }

        .stock-card-title {
            font-weight: 600;
            font-size: 18px;
            color: #0f172a;
            margin: 0;
            flex: 1;
            min-width: 0;
            line-height: 1.3;
        }

        .stock-status-badge {
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .status-in-stock .stock-status-badge {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-low-stock .stock-status-badge {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-out-of-stock .stock-status-badge {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .stock-card-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
            padding: 12px;
            background-color: #f8fafc;
            border-radius: 8px;
        }

        .stock-card-meta-item {
            display: flex;
            flex-direction: column;
        }

        .stock-card-meta-label {
            color: #64748b;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 4px;
        }

        .stock-card-meta-value {
            color: #0f172a;
            font-weight: 600;
            font-family: ui-monospace, 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 14px;
        }

        .stock-numbers {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 16px;
            padding: 16px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 8px;
        }

        .stock-number {
            text-align: center;
        }

        .stock-number-label {
            color: #64748b;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 6px;
            line-height: 1.2;
        }

        .stock-number-value {
            color: #0f172a;
            font-weight: 700;
            font-size: 18px;
            font-family: ui-monospace, 'SF Mono', Monaco, 'Cascadia Code', monospace;
        }

        .stock-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }

        .last-updated {
            color: #64748b;
            font-size: 12px;
        }

        .stock-card-actions {
            display: flex;
            gap: 8px;
        }

        .stock-card-actions a {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #ffffff;
            color: #475569;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .stock-card-actions a:hover {
            background-color: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        /* Hide table on mobile */
        .table-container {
            display: none;
        }

        /* Error and no data messages */
        .error-message {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 16px;
            margin-bottom: 16px;
            border-radius: 8px;
            font-weight: 500;
        }

        .no-data-message {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            color: #64748b;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
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

        /* Tablet and desktop styles */
        @media (min-width: 768px) {
            .container {
                max-width: 1200px;
                margin: 20px auto;
                padding: 24px;
                background-color: #ffffff;
                border-radius: 12px;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            }
            
            h1 {
                font-size: 32px;
                text-align: center;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 20px;
                margin-bottom: 32px;
            }
            
            .user-info {
                text-align: right;
                background-color: transparent;
                border: none;
                box-shadow: none;
                padding: 0;
                margin-bottom: 20px;
            }

            /* Hide cards on desktop */
            .stock-cards {
                display: none;
            }

            /* Show table on desktop */
            .table-container {
                display: block;
                background-color: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                overflow-x: auto;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            }

            .stock-report-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 14px;
            }

            .stock-report-table th,
            .stock-report-table td {
                border: 1px solid #e2e8f0;
                padding: 12px 16px;
                text-align: left;
                vertical-align: middle;
            }

            .stock-report-table th {
                background-color: #f8fafc;
                color: #374151;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 12px;
                letter-spacing: 0.025em;
                position: sticky;
                top: 0;
                z-index: 10;
            }

            .stock-report-table tr:nth-child(even) {
                background-color: #f9fafb;
            }

            .stock-report-table tr:hover {
                background-color: #f1f5f9;
            }

            .status-in-stock td.status-cell {
                background-color: #dcfce7;
                color: #166534;
                font-weight: 600;
            }

            .status-low-stock td.status-cell {
                background-color: #fef3c7;
                color: #92400e;
                font-weight: 600;
            }

            .status-out-of-stock td.status-cell {
                background-color: #fee2e2;
                color: #991b1b;
                font-weight: 600;
            }

            .stock-report-table td.number {
                text-align: right;
                font-family: ui-monospace, 'SF Mono', Monaco, 'Cascadia Code', monospace;
                font-weight: 500;
            }

            .stock-report-table td.barcode-cell {
                font-family: ui-monospace, 'SF Mono', Monaco, 'Cascadia Code', monospace;
                font-size: 13px;
                color: #475569;
            }

            .stock-report-table .actions-cell {
                text-align: center;
                white-space: nowrap;
            }

            .stock-report-table .actions-cell a {
                color: #3b82f6;
                text-decoration: none;
                margin: 0 4px;
                padding: 6px 12px;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 500;
                transition: all 0.2s ease;
                background-color: #ffffff;
            }

            .stock-report-table .actions-cell a:hover {
                background-color: #f1f5f9;
                border-color: #cbd5e1;
            }

            .actions-cell a.edit-link {
                border-color: #fbbf24;
                color: #f59e0b;
            }

            .actions-cell a.edit-link:hover {
                background-color: #fef3c7;
            }
        }

        /* Print styles */
        @media print {
            body {
                font-size: 10pt;
                background-color: #ffffff;
                margin: 0.5in;
                color: #000000;
            }
            
            .navbar, .user-info {
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

            .stock-cards {
                display: none;
            }

            .table-container {
                display: block;
                border: none;
                box-shadow: none;
                overflow: visible;
            }
            
            h1 {
                font-size: 16pt;
                margin-bottom: 15px;
                text-align: center;
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
        <div class="navbar-content">
            <a href="index.php">Dashboard</a>
            <a href="stock_in.php">Stock In</a>
            <a href="stock_out.php">Stock Out</a>
            <a href="stock_report.php">Stock Report</a>
        </div>
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
                <p><a href="stock_in.php">Add new stock</a> or <a href="manage_products.php">manage products</a>.</p>
            </div>
        <?php elseif (!$error_message): ?>
            
            <!-- Mobile Card Layout -->
            <div class="stock-cards">
                <?php foreach ($stock_report_data as $item): ?>
                    <div class="stock-card <?php echo getStockStatusClass($item['stock_status']); ?>">
                        <div class="stock-card-header">
                            <h3 class="stock-card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <div class="stock-status-badge">
                                <?php echo formatStockStatus($item['stock_status']); ?>
                            </div>
                        </div>

                        <div class="stock-card-meta">
                            <div class="stock-card-meta-item">
                                <div class="stock-card-meta-label">SKU</div>
                                <div class="stock-card-meta-value"><?php echo htmlspecialchars($item['sku']); ?></div>
                            </div>
                            <div class="stock-card-meta-item">
                                <div class="stock-card-meta-label">Barcode</div>
                                <div class="stock-card-meta-value"><?php echo htmlspecialchars($item['barcode'] ?: 'N/A'); ?></div>
                            </div>
                        </div>

                        <div class="stock-numbers">
                            <div class="stock-number">
                                <div class="stock-number-label">Current Stock</div>
                                <div class="stock-number-value"><?php echo htmlspecialchars($item['current_stock']); ?></div>
                            </div>
                            <div class="stock-number">
                                <div class="stock-number-label">Total Received</div>
                                <div class="stock-number-value"><?php echo htmlspecialchars($item['total_received']); ?></div>
                            </div>
                            <div class="stock-number">
                                <div class="stock-number-label">Total Sold</div>
                                <div class="stock-number-value"><?php echo htmlspecialchars($item['total_sold']); ?></div>
                            </div>
                        </div>

                        <div class="stock-card-footer">
                            <div class="last-updated">
                                Last updated: <?php echo $item['last_updated'] ? htmlspecialchars(date('M j, Y', strtotime($item['last_updated']))) : 'N/A'; ?>
                            </div>
                            <div class="stock-card-actions">
                                <a href="transaction_history.php?product_id=<?php echo $item['id']; ?>">History</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Desktop Table Layout -->
            <div class="table-container">
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
                                <td class="status-cell"><?php echo formatStockStatus($item['stock_status']); ?></td>
                                <td><?php echo $item['last_updated'] ? htmlspecialchars(date('Y-m-d H:i', strtotime($item['last_updated']))) : 'N/A'; ?></td>
                                <td class="actions-cell">
                                    <a href="transaction_history.php?product_id=<?php echo $item['id']; ?>" title="View transaction history for this product">History</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

</body>
</html>