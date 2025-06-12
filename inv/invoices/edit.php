<?php
// invoices/edit.php (Accessed via controllers/InvoiceController.php)

// This page is typically included by the controller, so $invoice, $customers, $shops, $invoice_items should be available.
// If testing directly, uncomment and set up mock data:
/*
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/InvoiceItem.php';
$conn_test = new mysqli('localhost', 'root', '', 'supplies');
$invoice_obj_test = new Invoice($conn_test);
$invoice_item_obj_test = new InvoiceItem($conn_test);

$invoice_obj_test->id = $_GET['id'] ?? 1; // Example ID
$invoice_obj_test->readOne();
$invoice = $invoice_obj_test; // The $invoice object is now populated

$customers = $invoice_obj_test->getCustomers();
$shops = $invoice_obj_test->getShops();

$invoice_item_obj_test->invoice_id = $invoice->id;
$invoice_items = $invoice_item_obj_test->readByInvoice();
*/

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($invoice) || !$invoice) {
    // Should be handled by controller, but as a fallback
    $_SESSION['error_message'] = "Invoice not found or not passed to edit page.";
    header('Location: ?action=list');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice #<?php echo htmlspecialchars($invoice->invoice_number); ?></title>
    <link rel="stylesheet" href="../assets/css/invoice.css">
    <style>
        /* Basic styling for demonstration, replace with your actual CSS */
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 900px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        h1 { text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group input[type="checkbox"] {
            margin-right: 5px;
        }
        .row { display: flex; flex-wrap: wrap; gap: 20px; }
        .col-half { flex: 1 1 calc(50% - 10px); min-width: 300px; }
        .col-full { flex: 1 1 100%; }
        .item-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .item-table th, .item-table td { border: 1px solid #eee; padding: 8px; text-align: left; }
        .item-table th { background-color: #f9f9f9; }
        .item-row input[type="text"], .item-row input[type="number"], .item-row select { width: 95%; }
        .item-actions button {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2em;
            padding: 0 5px;
        }
        .add-item-btn {
            background-color: #007bff;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
        }
        .total-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            width: 300px;
            padding: 5px 0;
            border-bottom: 1px dashed #eee;
        }
        .total-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1em;
        }
        .total-row span:first-child { font-weight: bold; }
        .btn-submit {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1em;
            margin-top: 20px;
        }
        .back-link { display: block; text-align: center; margin-top: 20px; text-decoration: none; color: #007bff; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Invoice #<?php echo htmlspecialchars($invoice->invoice_number); ?></h1>

        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <form action="?action=update" method="POST" id="invoiceForm">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($invoice->id); ?>">
            <?php include __DIR__ . '/includes/invoice_form.php'; ?>
            <div class="col-full">
                <button type="submit" class="btn-submit">Update Invoice</button>
            </div>
        </form>
        <a href="?action=view&id=<?php echo htmlspecialchars($invoice->id); ?>" class="back-link">Back to Invoice Details</a>
    </div>

    <script src="../assets/js/invoice.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Convert mysqli_result to array for JSON encoding
            function get_all_shops_for_js($shops_result) {
                $shops_array = [];
                if ($shops_result) {
                    $shops_result->data_seek(0); // Reset pointer if already used
                    while ($row = $shops_result->fetch_assoc()) {
                        $shops_array[] = $row;
                    }
                }
                return $shops_array;
            }

            function get_all_customers_for_js($customers_result) {
                $customers_array = [];
                if ($customers_result) {
                    $customers_result->data_seek(0); // Reset pointer if already used
                    while ($row = $customers_result->fetch_assoc()) {
                        $customers_array[] = $row;
                    }
                }
                return $customers_array;
            }

            function get_invoice_items_for_js($invoice_items_result) {
                $items_array = [];
                if ($invoice_items_result) {
                    $invoice_items_result->data_seek(0);
                    while ($row = $invoice_items_result->fetch_assoc()) {
                        $items_array[] = $row;
                    }
                }
                return $items_array;
            }

            initInvoiceForm(
                {
                    invoiceData: <?php echo json_encode($invoice); ?>,
                    invoiceItems: <?php echo json_encode(get_invoice_items_for_js($invoice_items)); ?>,
                    shops: <?php echo json_encode(get_all_shops_for_js($shops)); ?>,
                    customers: <?php echo json_encode(get_all_customers_for_js($customers)); ?>
                },
                'edit' // Indicate that this is the edit form
            );
        });
    </script>
</body>
</html>