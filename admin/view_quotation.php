<?php
// view_quotation.php

// Start session, include database connection, and any necessary functions/classes
// session_start(); // If not already started by a global include
// require_once 'config/db_connect.php'; // Your database connection
// require_once 'includes/functions.php'; // Optional helper functions

// Check if user is logged in and has permission (optional, but good practice)
// if (!isUserLoggedIn() || !canUserViewQuotations()) {
//    header("Location: login.php");
//    exit;
// }

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Quotation ID is missing."); // Or redirect to admin_quotations.php with an error message
}

$quotation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$quotation_id) {
    die("Error: Invalid Quotation ID."); // Or redirect
}

$quotation = null;
$quotation_items = [];
$customer = null;
$shop = null;
$created_by_user = null;

try {
    $conn = new PDO("mysql:host=localhost;dbname=supplies", "root", ""); // Replace with your actual connection
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch Quotation Details
    $sql_quotation = "SELECT q.*,
       c.name AS customer_name, c.customer_code,
       c.address_line1 AS customer_address_line1, c.email AS customer_email, c.phone AS customer_phone,
       s.name AS shop_name, s.shop_code,
       u.username AS created_by_username
FROM quotations q
LEFT JOIN customers c ON q.customer_id = c.id
LEFT JOIN shops s ON q.shop_id = s.id
LEFT JOIN users u ON q.created_by_user_id = u.id
WHERE q.id = :quotation_id";

    $stmt_quotation = $conn->prepare($sql_quotation);
    $stmt_quotation->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
    $stmt_quotation->execute();
    $quotation = $stmt_quotation->fetch(PDO::FETCH_ASSOC);

    if (!$quotation) {
        die("Error: Quotation not found."); // Or redirect
    }

    // Fetch Quotation Items
    $sql_items = "SELECT qi.*, 
       p.name as product_name, 
       p.sku as product_sku, 
       uom.name as uom_name
FROM quotation_items qi
LEFT JOIN products p ON qi.product_id = p.id
LEFT JOIN units_of_measurement uom ON qi.unit_of_measurement = uom.name
WHERE qi.quotation_id = :quotation_id
ORDER BY qi.item_number ASC";

    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
    $stmt_items->execute();
    $quotation_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log error properly for production
    die("Database error: " . $e->getMessage());
}
$conn = null;

// Determine customer name and address to display (considering overrides)
$display_customer_name = $quotation['customer_name_override'] ?? $quotation['customer_name'];
$display_customer_address = $quotation['customer_address_override'] ?? $quotation['customer_address_line1'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quotation - <?php echo htmlspecialchars($quotation['quotation_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f8f9fa; }
        .quotation-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .quotation-header h1 { margin-bottom: 0; }
        .section-title { margin-top: 2rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #eee; }
        .table th { background-color: #f1f1f1; }
        .totals-table td:first-child { font-weight: bold; text-align: right; }
        .print-button-container { margin-top: 20px; text-align: right; }
    </style>
</head>
<body>
    <div class="container quotation-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="quotation-header">
                <h1>Quotation Details</h1>
                <p class="lead mb-0">Quotation #: <?php echo htmlspecialchars($quotation['quotation_number']); ?></p>
            </div>
            <div>
                <a href="admin_quotations.php" class="btn btn-outline-secondary">Back to List</a>
                <a href="print_quotation.php?id=<?php echo $quotation_id; ?>" class="btn btn-primary" target="_blank">Print Quotation</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h5 class="section-title">Customer Information</h5>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($display_customer_name); ?></p>
                <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($display_customer_address)); ?></p>
                <p><strong>Customer Code:</strong> <?php echo htmlspecialchars($quotation['customer_code'] ?? 'N/A'); ?></p>
                <?php if (isset($quotation['customer_email'])): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($quotation['customer_email']); ?></p>
                <?php endif; ?>
                <?php if (isset($quotation['customer_phone'])): ?>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($quotation['customer_phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h5 class="section-title">Quotation & Company Info</h5>
                <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($quotation['quotation_date'])); ?></p>
                <p><strong>Status:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($quotation['status']); ?></span></p>
                <p><strong>Shop:</strong> <?php echo htmlspecialchars($quotation['shop_name'] ?? $quotation['shop_code'] ?? 'N/A'); ?></p>
                <p><strong>Company TPIN:</strong> <?php echo htmlspecialchars($quotation['company_tpin'] ?? 'N/A'); ?></p>
                <p><strong>Validity:</strong> <?php echo htmlspecialchars($quotation['quotation_validity_days']); ?> days</p>
                <p><strong>Created By:</strong> <?php echo htmlspecialchars($quotation['created_by_username'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <h5 class="section-title">Items</h5>
        <?php if (empty($quotation_items)): ?>
            <p>No items found for this quotation.</p>
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
                    <?php foreach ($quotation_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_number']); ?></td>
                            <td><?php echo htmlspecialchars($item['product_sku'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['product_name'] ?? 'Custom Item'); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($item['description'])); ?></td>
                            <td><?php echo htmlspecialchars(number_format($item['quantity'], 2)); ?></td>
                            <td><?php echo htmlspecialchars($item['uom_name'] ?? $item['uom_code'] ?? $item['unit_of_measurement'] ?? 'N/A'); ?></td>
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
                            <td><?php echo number_format($quotation['gross_total_amount'], 2); ?></td>
                        </tr>
                        <?php if ($quotation['apply_ppda_levy'] == 1 && isset($quotation['ppda_levy_amount'])): ?>
                        <tr>
                            <td>PPDA Levy (<?php echo htmlspecialchars($quotation['ppda_levy_percentage']); ?>%):</td>
                            <td><?php echo number_format($quotation['ppda_levy_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>Amount Before VAT:</td>
                            <td><?php echo number_format($quotation['amount_before_vat'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>VAT (<?php echo htmlspecialchars($quotation['vat_percentage']); ?>%):</td>
                            <td><?php echo number_format($quotation['vat_amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Net Amount:</strong></td>
                            <td><strong><?php echo number_format($quotation['total_net_amount'], 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($quotation['notes_general']) || !empty($quotation['delivery_period']) || !empty($quotation['payment_terms']) || !empty($quotation['mra_wht_note_content'])): ?>
        <div class="mt-4">
            <h5 class="section-title">Additional Information</h5>
            <?php if (!empty($quotation['notes_general'])): ?>
                <p><strong>General Notes:</strong><br><?php echo nl2br(htmlspecialchars($quotation['notes_general'])); ?></p>
            <?php endif; ?>
            <?php if (!empty($quotation['delivery_period'])): ?>
                <p><strong>Delivery Period:</strong> <?php echo htmlspecialchars($quotation['delivery_period']); ?></p>
            <?php endif; ?>
            <?php if (!empty($quotation['payment_terms'])): ?>
                <p><strong>Payment Terms:</strong> <?php echo htmlspecialchars($quotation['payment_terms']); ?></p>
            <?php endif; ?>
            <?php if (!empty($quotation['mra_wht_note_content'])): ?>
                <p><strong>MRA WHT Note:</strong><br><?php echo nl2br(htmlspecialchars($quotation['mra_wht_note_content'])); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>