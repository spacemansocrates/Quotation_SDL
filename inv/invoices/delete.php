<?php
// invoices/delete.php (Accessed via controllers/InvoiceController.php)

// This page is typically included by the controller, so $invoice should be available.
// If testing directly, uncomment and set up mock data:
/*
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../models/Invoice.php';
$conn_test = new mysqli('localhost', 'root', '', 'supplies');
$invoice_obj_test = new Invoice($conn_test);
$invoice_obj_test->id = $_GET['id'] ?? 1; // Example ID
$invoice_obj_test->readOne();
$invoice = $invoice_obj_test;
*/

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($invoice) || !$invoice) {
    $_SESSION['error_message'] = "Invoice not found for deletion.";
    header('Location: ?action=list');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Invoice Confirmation</title>
    <link rel="stylesheet" href="../assets/css/invoice.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; text-align: center; }
        .container { max-width: 600px; margin: auto; padding: 30px; border: 1px solid #dc3545; border-radius: 8px; background-color: #fff3f3; }
        h1 { color: #dc3545; margin-bottom: 20px; }
        p { margin-bottom: 25px; font-size: 1.1em; }
        .btn-group { display: flex; justify-content: center; gap: 20px; }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 1em;
        }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
         .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Delete Invoice</h1>
        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>
        <p>Are you sure you want to delete Invoice **#<?php echo htmlspecialchars($invoice->invoice_number); ?>** (ID: <?php echo htmlspecialchars($invoice->id); ?>)?</p>
        <p><strong>This action cannot be undone. All associated invoice items and payments will also be deleted.</strong></p>
        <form action="?action=destroy" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($invoice->id); ?>">
            <div class="btn-group">
                <button type="submit" class="btn btn-danger">Yes, Delete Invoice</button>
                <a href="?action=view&id=<?php echo htmlspecialchars($invoice->id); ?>" class="btn btn-secondary">No, Go Back</a>
            </div>
        </form>
    </div>
</body>
</html>