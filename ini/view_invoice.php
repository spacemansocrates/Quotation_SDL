<?php
// view_invoice.php

// Start session, include database connection, and any necessary functions/classes
// session_start(); // If not already started by a global include
// require_once 'config/db_connect.php'; // Your database connection
// require_once 'includes/functions.php'; // Optional helper functions

// Check if user is logged in and has permission (optional, but good practice)
// if (!isUserLoggedIn() || !canUserViewInvoices()) { // <-- Changed to canUserViewInvoices
//    header("Location: login.php");
//    exit;
// }

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Invoice ID is missing."); // <-- Changed
}

$invoice_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$invoice_id) {
    die("Error: Invalid Invoice ID."); // <-- Changed
}

$invoice = null;
$invoice_items = [];
// Customer and Shop details will be part of the $invoice array from the main query
// $created_by_user will also be part of the $invoice array

try {
    $conn = new PDO("mysql:host=localhost;dbname=supplies", "root", ""); // Replace with your actual connection
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch Invoice Details
    $sql_invoice = "SELECT i.*,
                           c.name AS customer_name, c.customer_code,
                           c.address_line1 AS customer_address_line1, c.email AS customer_email, c.phone AS customer_phone,
                           s.name AS shop_name, s.shop_code,
                           u_created.username AS created_by_username,
                           oq.quotation_number AS original_quotation_number
                    FROM invoices i
                    LEFT JOIN customers c ON i.customer_id = c.id
                    LEFT JOIN shops s ON i.shop_id = s.id
                    LEFT JOIN users u_created ON i.created_by_user_id = u_created.id
                    LEFT JOIN quotations oq ON i.quotation_id = oq.id -- To get original quotation number if exists
                    WHERE i.id = :invoice_id";

    $stmt_invoice = $conn->prepare($sql_invoice);
    $stmt_invoice->bindParam(':invoice_id', $invoice_id, PDO::PARAM_INT);
    $stmt_invoice->execute();
    $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        die("Error: Invoice not found."); // <-- Changed
    }

    // Fetch Invoice Items
    $sql_items = "SELECT ii.*, 
                         p.name as product_name, 
                         p.sku as product_sku, 
                         uom.name as uom_name
                  FROM invoice_items ii
                  LEFT JOIN products p ON ii.product_id = p.id
                  LEFT JOIN units_of_measurement uom ON ii.unit_of_measurement = uom.name -- Assuming unit_of_measurement stores the name
                  WHERE ii.invoice_id = :invoice_id
                  ORDER BY ii.item_number ASC";

    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bindParam(':invoice_id', $invoice_id, PDO::PARAM_INT);
    $stmt_items->execute();
    $invoice_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log error properly for production
    die("Database error: " . $e->getMessage());
}
$conn = null;

// Determine customer name and address to display (considering overrides)
$display_customer_name = $invoice['customer_name_override'] ?? $invoice['customer_name'];
$display_customer_address = $invoice['customer_address_override'] ?? $invoice['customer_address_line1'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoice - <?php echo htmlspecialchars($invoice['invoice_number']); // <-- Changed ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f8f9fa; }
        .invoice-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); } /* <-- Renamed class */
        .invoice-header h1 { margin-bottom: 0; } /* <-- Renamed class */
        .section-title { margin-top: 2rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #eee; }
        .table th { background-color: #f1f1f1; }
        .totals-table td:first-child { font-weight: bold; text-align: right; }
        .print-button-container { margin-top: 20px; text-align: right; }
    </style>
</head>
<body>
    <div class="container invoice-container"> 
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="invoice-header"> 
                <h1>Invoice Details</h1> 
                <p class="lead mb-0">Invoice #: <?php echo htmlspecialchars($invoice['invoice_number']); // <-- Changed ?></p>
            </div>
            <div>
                <a href="admin_invoices.php" class="btn btn-outline-secondary">Back to List</a> 
                <a href="print_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-primary" target="_blank">Print Invoice</a> 
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h5 class="section-title">Customer Information</h5>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($display_customer_name); ?></p>
                <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($display_customer_address)); ?></p>
                <p><strong>Customer Code:</strong> <?php echo htmlspecialchars($invoice['customer_code'] ?? 'N/A'); ?></p>
                <?php if (isset($invoice['customer_email'])): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($invoice['customer_email']); ?></p>
                <?php endif; ?>
                <?php if (isset($invoice['customer_phone'])): ?>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($invoice['customer_phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h5 class="section-title">Invoice & Company Info</h5> {/* <-- Changed */}
                <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($invoice['invoice_date'])); // <-- Changed ?></p>
                <?php if (isset($invoice['due_date'])): ?>
                    <p><strong>Due Date:</strong> <?php echo date('F j, Y', strtotime($invoice['due_date'])); // <-- Added ?></p>
                <?php endif; ?>
                <p><strong>Status:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($invoice['status']); ?></span></p>
                <p><strong>Shop:</strong> <?php echo htmlspecialchars($invoice['shop_name'] ?? $invoice['shop_code'] ?? 'N/A'); ?></p>
                <p><strong>Company TPIN:</strong> <?php echo htmlspecialchars($invoice['company_tpin'] ?? 'N/A'); ?></p>
                <p><strong>Created By:</strong> <?php echo htmlspecialchars($invoice['created_by_username'] ?? 'N/A'); ?></p>
                <?php if (!empty($invoice['original_quotation_number'])): // <-- Added ?>
                    <p><strong>Original Quotation #:</strong> <?php echo htmlspecialchars($invoice['original_quotation_number']); ?></p>
                <?php endif; ?>
                <!-- Removed Admin Notes and Approved By section as it's not in invoices table -->
            </div>
        </div>

        <h5 class="section-title">Items</h5>
        <?php if (empty($invoice_items)): ?>
            <p>No items found for this invoice.</p> 
        <?php else: ?>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>SKU</th>
                        <th>Product/Service</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>UoM</th>
                        <th>Rate</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice_items as $item): // <-- Changed variable ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_number']); ?></td>
                            <td><?php echo htmlspecialchars($item['product_sku'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['product_name'] ?? 'Custom Item'); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($item['description'])); ?></td>
                            <td><?php echo htmlspecialchars(number_format($item['quantity'], 2)); ?></td>
                            <td><?php echo htmlspecialchars($item['uom_name'] ?? $item['unit_of_measurement'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(number_format($item['rate_per_unit'], 2)); ?></td>
                            <td><?php echo htmlspecialchars(number_format($item['total_amount'], 2)); ?></td>
                        </tr>
                        <?php if (!empty($item['image_path_override'])): ?>
                        <tr>
                            <td colspan="8" class="text-center">
                                <img src="<?php echo htmlspecialchars($item['image_path_override']); ?>" alt="Item Image" style="max-height: 100px; max-width: 150px; margin-top: 5px;">
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="row justify-content-end mt-4">
            <div class="col-md-5">
                <h5 class="section-title">Summary</h5>
                <table class="table totals-table">
                    <tbody>
                        <tr>
                            <td>Gross Total:</td>
                            <td><?php echo number_format($invoice['gross_total_amount'], 2); ?></td>
                        </tr>
                        <?php if ($invoice['apply_ppda_levy'] == 1 && isset($invoice['ppda_levy_amount'])): ?>
                        <tr>
                            <td>PPDA Levy (<?php echo htmlspecialchars($invoice['ppda_levy_percentage']); ?>%):</td>
                            <td><?php echo number_format($invoice['ppda_levy_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr> {/* Added Amount Before VAT */}
                            <td>Amount Before VAT:</td>
                            <td><?php echo number_format($invoice['amount_before_vat'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>VAT (<?php echo htmlspecialchars($invoice['vat_percentage']); ?>%):</td>
                            <td><?php echo number_format($invoice['vat_amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Net Amount:</strong></td>
                            <td><strong><?php echo number_format($invoice['total_net_amount'], 2); ?></strong></td>
                        </tr>
                        <tr> {/* Added Total Paid */}
                            <td>Total Paid:</td>
                            <td><?php echo number_format($invoice['total_paid'], 2); ?></td>
                        </tr>
                        <tr> {/* Added Balance Due */}
                            <td><strong>Balance Due:</strong></td>
                            <td><strong><?php echo number_format($invoice['balance_due'], 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($invoice['notes_general']) || !empty($invoice['delivery_period']) || !empty($invoice['payment_terms'])): ?>
        <div class="mt-4">
            <h5 class="section-title">Additional Information</h5>
            <?php if (!empty($invoice['notes_general'])): ?>
                <p><strong>General Notes:</strong><br><?php echo nl2br(htmlspecialchars($invoice['notes_general'])); ?></p>
            <?php endif; ?>
            <?php if (!empty($invoice['delivery_period'])): ?>
                <p><strong>Delivery Period:</strong> <?php echo htmlspecialchars($invoice['delivery_period']); ?></p>
            <?php endif; ?>
            <?php if (!empty($invoice['payment_terms'])): ?>
                <p><strong>Payment Terms:</strong> <?php echo htmlspecialchars($invoice['payment_terms']); ?></p>
            <?php endif; ?>
            <!-- Removed MRA WHT Note section as it's not in invoices table -->
        </div>
        <?php endif; ?>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>