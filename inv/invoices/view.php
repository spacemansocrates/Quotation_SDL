<?php
// invoices/view.php (Accessed via controllers/InvoiceController.php)

// This page is typically included by the controller, so $invoice, $invoice_items, $payments should be available.
// If testing directly, uncomment and set up mock data:
/*
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/InvoiceItem.php';
$conn_test = new mysqli('localhost', 'root', '', 'supplies');
$invoice_obj_test = new Invoice($conn_test);
$invoice_item_obj_test = new InvoiceItem($conn_test);

$invoice_obj_test->id = $_GET['id'] ?? 1; // Example ID
$invoice_obj_test->readOne();
$invoice = $invoice_obj_test;

$invoice_item_obj_test->invoice_id = $invoice->id;
$invoice_items = $invoice_item_obj_test->readByInvoice();

// Mock payments
$payments = new stdClass(); // Mock an empty result set
$payments->num_rows = 0;
*/

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($invoice) || !$invoice) {
    $_SESSION['error_message'] = "Invoice not found or not passed to view page.";
    header('Location: ?action=list');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Details - #<?php echo htmlspecialchars($invoice->invoice_number); ?></title>
    <link rel="stylesheet" href="../assets/css/invoice.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 900px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        h1 { text-align: center; margin-bottom: 20px; }
        .invoice-header, .invoice-details, .customer-shop-details, .item-details, .payment-details, .totals-summary {
            margin-bottom: 25px;
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .invoice-header h2 { margin-top: 0; }
        .invoice-details p, .customer-shop-details p { margin: 5px 0; }
        .customer-shop-details .col { width: 48%; display: inline-block; vertical-align: top; }
        .customer-shop-details .col:first-child { margin-right: 4%; }
        .item-table, .payment-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .item-table th, .item-table td, .payment-table th, .payment-table td {
            border: 1px solid #ddd; padding: 8px; text-align: left;
        }
        .item-table th, .payment-table th { background-color: #e9e9e9; }
        .totals-summary .row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px dashed #ddd;
        }
        .totals-summary .row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1em;
            margin-top: 10px;
        }
        .btn-group { text-align: center; margin-top: 20px; }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0 5px;
        }
        .btn-edit { background-color: #ffc107; color: #333; }
        .btn-delete { background-color: #dc3545; }
        .btn-back { background-color: #6c757d; }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            margin-left: 10px;
        }
        .status-Draft { background-color: #6c757d; }
        .status-Finalized { background-color: #007bff; }
        .status-Paid { background-color: #28aa45; }
        .status-PartiallyPaid { background-color: #ffc107; }
        .status-Overdue { background-color: #dc3545; }
        .status-Cancelled { background-color: #666; }
         .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Invoice Details</h1>

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

        <div class="invoice-header">
            <h2>Invoice #<?php echo htmlspecialchars($invoice->invoice_number); ?>
                <span class="status-badge status-<?php echo str_replace(' ', '', htmlspecialchars($invoice->status)); ?>">
                    <?php echo htmlspecialchars($invoice->status); ?>
                </span>
            </h2>
        </div>

        <div class="invoice-details">
            <p><strong>Invoice Date:</strong> <?php echo htmlspecialchars($invoice->invoice_date); ?></p>
            <p><strong>Due Date:</strong> <?php echo htmlspecialchars($invoice->due_date); ?></p>
            <?php if ($invoice->quotation_id): ?>
                <p><strong>Linked Quotation ID:</strong> <?php echo htmlspecialchars($invoice->quotation_id); ?></p>
            <?php endif; ?>
            <p><strong>Company TPIN:</strong> <?php echo htmlspecialchars($invoice->company_tpin); ?></p>
            <p><strong>Delivery Period:</strong> <?php echo htmlspecialchars($invoice->delivery_period); ?></p>
            <p><strong>Payment Terms:</strong> <?php echo htmlspecialchars($invoice->payment_terms); ?></p>
            <?php if (!empty($invoice->notes_general)): ?>
                <p><strong>General Notes:</strong> <?php echo nl2br(htmlspecialchars($invoice->notes_general)); ?></p>
            <?php endif; ?>
        </div>

        <div class="customer-shop-details">
            <div class="col">
                <h3>Customer Details</h3>
                <?php if (!empty($invoice->customer_name_override)): ?>
                    <p><strong>Name (Override):</strong> <?php echo htmlspecialchars($invoice->customer_name_override); ?></p>
                <?php else: ?>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($invoice->customer_data['name'] ?? 'N/A'); ?></p>
                <?php endif; ?>

                <?php if (!empty($invoice->customer_address_override)): ?>
                    <p><strong>Address (Override):</strong> <?php echo nl2br(htmlspecialchars($invoice->customer_address_override)); ?></p>
                <?php else: ?>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($invoice->customer_data['address_line1'] ?? '') . ', ' . htmlspecialchars($invoice->customer_data['address_line2'] ?? '') . ', ' . htmlspecialchars($invoice->customer_data['city_location'] ?? 'N/A'); ?></p>
                <?php endif; ?>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($invoice->customer_data['phone'] ?? 'N/A'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($invoice->customer_data['email'] ?? 'N/A'); ?></p>
                <p><strong>TPIN:</strong> <?php echo htmlspecialchars($invoice->customer_data['tpin_no'] ?? 'N/A'); ?></p>
            </div>
            <div class="col">
                <h3>Shop Details</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($invoice->shop_data['name'] ?? 'N/A'); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($invoice->shop_data['address_line1'] ?? '') . ', ' . htmlspecialchars($invoice->shop_data['address_line2'] ?? '') . ', ' . htmlspecialchars($invoice->shop_data['city'] ?? 'N/A'); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($invoice->shop_data['phone'] ?? 'N/A'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($invoice->shop_data['email'] ?? 'N/A'); ?></p>
                <p><strong>TPIN:</strong> <?php echo htmlspecialchars($invoice->shop_data['tpin_no'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <div class="item-details">
            <h3>Invoice Items</h3>
            <table class="item-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th>Product SKU</th>
                        <th>Quantity</th>
                        <th>UoM</th>
                        <th>Rate/Unit</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($invoice_items && $invoice_items->num_rows > 0): ?>
                        <?php while ($item = $invoice_items->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_number']); ?></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td><?php echo htmlspecialchars($item['product_sku'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($item['quantity'], 2); ?></td>
                                <td><?php echo htmlspecialchars($item['unit_of_measurement']); ?></td>
                                <td><?php echo number_format($item['rate_per_unit'], 2); ?></td>
                                <td><?php echo number_format($item['total_amount'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">No items found for this invoice.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="totals-summary">
            <h3>Summary</h3>
            <div class="row">
                <span>Gross Total Amount:</span>
                <span><?php echo number_format($invoice->gross_total_amount, 2); ?></span>
            </div>
            <?php if ($invoice->apply_ppda_levy): ?>
            <div class="row">
                <span>PPDA Levy (<?php echo htmlspecialchars($invoice->ppda_levy_percentage); ?>%):</span>
                <span><?php echo number_format($invoice->ppda_levy_amount, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="row">
                <span>Amount Before VAT:</span>
                <span><?php echo number_format($invoice->amount_before_vat, 2); ?></span>
            </div>
            <div class="row">
                <span>VAT (<?php echo htmlspecialchars($invoice->vat_percentage); ?>%):</span>
                <span><?php echo number_format($invoice->vat_amount, 2); ?></span>
            </div>
            <div class="row">
                <span>Total Net Amount:</span>
                <span><?php echo number_format($invoice->total_net_amount, 2); ?></span>
            </div>
            <div class="row">
                <span>Total Paid:</span>
                <span><?php echo number_format($invoice->total_paid, 2); ?></span>
            </div>
            <div class="row">
                <span>Balance Due:</span>
                <span><?php echo number_format($invoice->balance_due, 2); ?></span>
            </div>
        </div>

        <div class="payment-details">
            <h3>Payments Received</h3>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Recorded By</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments && $payments->num_rows > 0): ?>
                        <?php while ($payment = $payments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                <td><?php echo number_format($payment['amount_paid'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($payment['reference_number']); ?></td>
                                <td><?php echo htmlspecialchars($payment['recorded_by_user'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($payment['notes']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No payments recorded for this invoice.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p style="text-align: right; margin-top: 10px;">
                <a href="payments/create.php?invoice_id=<?php echo htmlspecialchars($invoice->id); ?>&customer_id=<?php echo htmlspecialchars($invoice->customer_id); ?>" class="btn btn-primary btn-sm">Add Payment</a>
            </p>
        </div>


        <div class="btn-group">
            <a href="?action=edit&id=<?php echo htmlspecialchars($invoice->id); ?>" class="btn btn-edit">Edit Invoice</a>
            <a href="?action=delete&id=<?php echo htmlspecialchars($invoice->id); ?>" class="btn btn-delete">Delete Invoice</a>
            <a href="?action=list" class="btn btn-back">Back to List</a>
        </div>
    </div>
</body>
</html>