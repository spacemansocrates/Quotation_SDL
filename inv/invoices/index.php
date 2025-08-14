<?php
// invoices/index.php (Accessed via controllers/InvoiceController.php)

// Start session if not already started (assuming a global include)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// // This page is typically included by the controller, so $invoices, $total_pages, $page etc. should be available
// // If testing directly, uncomment and set up mock data:
// /*
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../models/Invoice.php';
$conn_test = new mysqli('localhost', 'root', '', 'supplies');
$invoice_test = new Invoice($conn_test);
$invoices = $invoice_test->read('', '', 10, 0); // Example mock
$total_pages = 1; $page = 1; $search = ''; $status_filter = '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice List</title>
    <link rel="stylesheet" href="../assets/css/invoice.css">
    <style>
        /* Basic styling for demonstration, replace with your actual CSS */
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-success { background-color: #28a745; }
        .btn-warning { background-color: #ffc107; color: #333; }
        .btn-danger { background-color: #dc3545; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .actions a { margin-right: 5px; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #ddd;
            margin: 0 4px;
            text-decoration: none;
            color: #007bff;
            border-radius: 4px;
        }
        .pagination span.current-page {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .filter-form { margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end;}
        .filter-form label { font-weight: bold; }
        .filter-form input, .filter-form select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice List</h1>
            <a href="?action=create" class="btn btn-success">Create New Invoice</a>
        </div>

        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <form method="GET" action="" class="filter-form">
            <input type="hidden" name="action" value="list">
            <div>
                <label for="search">Search:</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Invoice #, Customer, Shop">
            </div>
            <div>
                <label for="status">Status:</label>
                <select id="status" name="status">
                    <option value="">All</option>
                    <option value="Draft" <?php echo ($status_filter == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="Finalized" <?php echo ($status_filter == 'Finalized') ? 'selected' : ''; ?>>Finalized</option>
                    <option value="Paid" <?php echo ($status_filter == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                    <option value="Partially Paid" <?php echo ($status_filter == 'Partially Paid') ? 'selected' : ''; ?>>Partially Paid</option>
                    <option value="Overdue" <?php echo ($status_filter == 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                    <option value="Cancelled" <?php echo ($status_filter == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn">Apply Filter</button>
            </div>
        </form>

        <table class="table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Shop</th>
                    <th>Total Net</th>
                    <th>Paid</th>
                    <th>Balance Due</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($invoices && $invoices->num_rows > 0): ?>
                    <?php while ($row = $invoices->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['invoice_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['shop_name']); ?></td>
                            <td><?php echo number_format($row['total_net_amount'], 2); ?></td>
                            <td><?php echo number_format($row['total_paid'], 2); ?></td>
                            <td><?php echo number_format($row['balance_due'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td><?php echo htmlspecialchars($row['created_by_user']); ?></td>
                            <td class="actions">
                                <a href="?action=view&id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">View</a>
                                <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                <a href="?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10">No invoices found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php
                    $queryString = http_build_query(array_merge($_GET, ['page' => $i]));
                ?>
                <?php if ($i == $page): ?>
                    <span class="current-page"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo $queryString; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>
</body>
</html>