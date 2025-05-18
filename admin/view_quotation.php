<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header('Location: login.php'); // Adjust if your login page is elsewhere
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';

$quotation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error_message = '';
$quotation = null;
$quotation_items = [];
$customer = null;
$shop = null;
$created_by_user = null;

if ($quotation_id <= 0) {
    $error_message = "Invalid quotation ID.";
} else {
    try {
        $pdo = getDatabaseConnection();

        // Fetch quotation details
        $stmt = DatabaseConfig::executeQuery(
            $pdo,
            "SELECT q.*, c.name AS customer_name_db, c.address_line1 AS customer_address_line1, c.city_location AS customer_city, c.phone AS customer_phone, c.email AS customer_email, c.tpin_no AS customer_tpin,
                    s.name AS shop_name, s.address_line1 AS shop_address_line1, s.address_line2 AS shop_address_line2, s.city AS shop_city, s.country AS shop_country, s.phone AS shop_phone, s.email AS shop_email, s.logo_path AS shop_logo_path, s.tpin_no AS shop_tpin,
                    u.username AS created_by_username, u.full_name AS created_by_fullname
             FROM quotations q
             LEFT JOIN customers c ON q.customer_id = c.id
             LEFT JOIN shops s ON q.shop_id = s.id
             LEFT JOIN users u ON q.created_by_user_id = u.id
             WHERE q.id = :quotation_id",
            [':quotation_id' => $quotation_id]
        );
        $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quotation) {
            $error_message = "Quotation not found.";
        } else {
            // Fetch quotation items
            $stmt_items = DatabaseConfig::executeQuery(
                $pdo,
                "SELECT qi.*, p.name AS product_name, p.sku AS product_sku
                 FROM quotation_items qi
                 LEFT JOIN products p ON qi.product_id = p.id
                 WHERE qi.quotation_id = :quotation_id
                 ORDER BY qi.item_number ASC",
                [':quotation_id' => $quotation_id]
            );
            $quotation_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }

        DatabaseConfig::closeConnection($pdo);
    } catch (PDOException $e) {
        error_log("Error in view_quotation.php: " . $e->getMessage());
        $error_message = "An error occurred while retrieving quotation details.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quotation - <?php echo htmlspecialchars($quotation['quotation_number'] ?? 'N/A'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .quotation-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .quotation-header img { max-height: 80px; margin-bottom: 20px; }
        .section-title { border-bottom: 2px solid #0d6efd; padding-bottom: 5px; margin-bottom: 15px; font-size: 1.2rem; color: #0d6efd;}
        .totals-table td:first-child { font-weight: bold; text-align: right; }
        .badge-status { font-size: 1rem; }
        @media print {
            body { background-color: #fff; }
            .no-print { display: none !important; }
            .quotation-container { box-shadow: none; border: 1px solid #dee2e6; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>

    <div class="container py-4">
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <a href="admin_quotations.php" class="btn btn-secondary no-print">Back to List</a>
        <?php elseif ($quotation): ?>
            <div class="quotation-container" id="quotation-to-print">
                <div class="row mb-3 quotation-header">
                    <div class="col-md-6">
                        <?php if (!empty($quotation['shop_logo_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($quotation['shop_logo_path']); ?>" alt="<?php echo htmlspecialchars($quotation['shop_name']); ?> Logo" class="img-fluid">
                        <?php endif; ?>
                        <h5><?php echo htmlspecialchars($quotation['shop_name']); ?></h5>
                        <p class="mb-0"><?php echo htmlspecialchars($quotation['shop_address_line1']); ?></p>
                        <?php if (!empty($quotation['shop_address_line2'])): ?>
                            <p class="mb-0"><?php echo htmlspecialchars($quotation['shop_address_line2']); ?></p>
                        <?php endif; ?>
                        <p class="mb-0"><?php echo htmlspecialchars($quotation['shop_city'] . ', ' . $quotation['shop_country']); ?></p>
                        <p class="mb-0">Phone: <?php echo htmlspecialchars($quotation['shop_phone']); ?></p>
                        <p class="mb-0">Email: <?php echo htmlspecialchars($quotation['shop_email']); ?></p>
                        <?php if (!empty($quotation['shop_tpin'])): ?>
                             <p class="mb-0">TPIN: <?php echo htmlspecialchars($quotation['shop_tpin']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h2>QUOTATION</h2>
                        <p class="mb-1"><strong>Quotation #:</strong> <?php echo htmlspecialchars($quotation['quotation_number']); ?></p>
                        <p class="mb-1"><strong>Date:</strong> <?php echo date('d M Y', strtotime($quotation['quotation_date'])); ?></p>
                        <p class="mb-1"><strong>Status:</strong> 
                            <span class="badge 
                                <?php 
                                    switch ($quotation['status']) {
                                        case 'Draft': echo 'bg-secondary'; break;
                                        case 'Submitted': echo 'bg-primary'; break;
                                        case 'Approved': echo 'bg-success'; break;
                                        case 'Rejected': echo 'bg-danger'; break;
                                        default: echo 'bg-info';
                                    }
                                ?> badge-status">
                                <?php echo htmlspecialchars($quotation['status']); ?>
                            </span>
                        </p>
                         <?php if (!empty($quotation['company_tpin'])): ?>
                             <p class="mb-0">Our TPIN: <?php echo htmlspecialchars($quotation['company_tpin']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <hr>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="section-title">Bill To:</h6>
                        <h5><?php echo htmlspecialchars($quotation['customer_name_override'] ?: $quotation['customer_name_db']); ?></h5>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($quotation['customer_address_override'] ?: ($quotation['customer_address_line1'] . "\n" . $quotation['customer_city']))); ?></p>
                        <?php if (empty($quotation['customer_name_override'])): // Show DB details if no override ?>
                            <?php if($quotation['customer_phone']): ?><p class="mb-0">Phone: <?php echo htmlspecialchars($quotation['customer_phone']); ?></p><?php endif; ?>
                            <?php if($quotation['customer_email']): ?><p class="mb-0">Email: <?php echo htmlspecialchars($quotation['customer_email']); ?></p><?php endif; ?>
                            <?php if($quotation['customer_tpin']): ?><p class="mb-0">TPIN: <?php echo htmlspecialchars($quotation['customer_tpin']); ?></p><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <h6 class="section-title">Items:</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Item / Description</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Rate</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quotation_items as $index => $item): ?>
                            <tr>
                                <td><?php echo $item['item_number'] ?: ($index + 1); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($item['product_name'] ? $item['product_name'] . ($item['product_sku'] ? ' (SKU: '.$item['product_sku'].')' : '') : ''); ?>
                                    <?php if($item['description']) echo '<br><small>' . nl2br(htmlspecialchars($item['description'])) . '</small>'; ?>
                                </td>
                                <td><?php echo htmlspecialchars(number_format($item['quantity'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($item['unit_of_measurement']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($item['rate_per_unit'], 2)); ?></td>
                                <td class="text-end"><?php echo htmlspecialchars(number_format($item['total_amount'], 2)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <?php if (!empty($quotation['notes_general'])): ?>
                            <h6 class="section-title">General Notes:</h6>
                            <p><?php echo nl2br(htmlspecialchars($quotation['notes_general'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($quotation['mra_wht_note_content'])): ?>
                            <h6 class="section-title">MRA WHT Note:</h6>
                            <p><?php echo nl2br(htmlspecialchars($quotation['mra_wht_note_content'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <table class="table totals-table">
                            <tbody>
                                <tr>
                                    <td>Gross Total:</td>
                                    <td class="text-end"><?php echo number_format($quotation['gross_total_amount'], 2); ?></td>
                                </tr>
                                <?php if ($quotation['apply_ppda_levy'] && $quotation['ppda_levy_amount'] > 0): ?>
                                <tr>
                                    <td>PPDA Levy (<?php echo number_format($quotation['ppda_levy_percentage'], 2); ?>%):</td>
                                    <td class="text-end"><?php echo number_format($quotation['ppda_levy_amount'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>Amount Before VAT:</td>
                                    <td class="text-end"><?php echo number_format($quotation['amount_before_vat'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td>VAT (<?php echo number_format($quotation['vat_percentage'], 2); ?>%):</td>
                                    <td class="text-end"><?php echo number_format($quotation['vat_amount'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Net Amount:</strong></td>
                                    <td class="text-end"><strong><?php echo number_format($quotation['total_net_amount'], 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Payment Terms:</strong> <?php echo htmlspecialchars($quotation['payment_terms']); ?></p>
                        <p><strong>Delivery Period:</strong> <?php echo htmlspecialchars($quotation['delivery_period']); ?></p>
                        <p><strong>Quotation Validity:</strong> <?php echo htmlspecialchars($quotation['quotation_validity_days']); ?> days from quotation date</p>
                    </div>
                     <div class="col-md-6 text-md-end">
                        <p class="mb-0"><em>Quotation prepared by: <?php echo htmlspecialchars($quotation['created_by_fullname'] ?: $quotation['created_by_username']); ?></em></p>
                        <p class="mb-0"><small>Generated on: <?php echo date('d M Y H:i:s'); ?></small></p>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-center no-print">
                <a href="admin_quotations.php" class="btn btn-secondary"><i class="bi bi-arrow-left-circle"></i> Back to List</a>
                <a href="edit_quotation.php?id=<?php echo $quotation_id; ?>" class="btn btn-info <?php echo ($quotation['status'] !== 'Draft' && $_SESSION['role'] !== 'admin') ? 'disabled' : ''; ?>">
                    <i class="bi bi-pencil-square"></i> Edit
                </a>
                <a href="print_quotation.php?id=<?php echo $quotation_id; ?>" target="_blank" class="btn btn-success"><i class="bi bi-printer"></i> Print</a>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>