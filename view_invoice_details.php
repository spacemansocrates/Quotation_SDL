<?php
/**
 * View Invoice Details Page
 * 
 * Displays detailed information about a specific invoice
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['user_role'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../includes/db_connect.php'; // Adjust path as necessary

// Get user info from session
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$isAdmin = ($userRole === 'admin');

// Get invoice ID from URL parameter
$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoiceId <= 0) {
    // Redirect to a list of invoices, e.g., view_invoices.php or dashboard
    header('Location: admin_invoices.php'); // Changed from view_quotation.php
    exit();
}

try {
    // Get database connection
    $pdo = getDatabaseConnection(); // Ensure this function is correctly defined in db_connect.php
    
    // Get invoice details
    $query = "
        SELECT i.*, 
               c.name AS customer_table_name, 
               c.address_line1 AS customer_address_line1,
               c.address_line2 AS customer_address_line2,
               c.city_location AS customer_city_location,
               c.phone AS customer_phone,
               c.email AS customer_email,
               c.tpin_no AS customer_tpin_no,
               u_creator.username AS created_by_username,
               u_creator.full_name AS created_by_full_name,
               s.name as shop_name,
               s.shop_code as shop_code
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN users u_creator ON i.created_by_user_id = u_creator.id
        LEFT JOIN shops s ON i.shop_id = s.id
        WHERE i.id = :invoiceId
    ";
    
    $params = [':invoiceId' => $invoiceId];
    
    // If not admin, restrict to own invoices (or invoices related to their shop, depending on your logic)
    // Current logic restricts to created_by_user_id. Adjust if necessary for other roles.
    if (!$isAdmin) {
        // Example: Allow managers to see all invoices from their shop, or staff to see only their own.
        // This example maintains the "created by me" restriction for non-admins.
        $query .= " AND i.created_by_user_id = :userId";
        $params[':userId'] = $userId;
    }
    
    // Assuming DatabaseConfig::executeQuery is a helper method
    // If not, replace with $stmt = $pdo->prepare($query); $stmt->execute($params);
    $stmt = DatabaseConfig::executeQuery($pdo, $query, $params);
    $invoice = $stmt->fetch();
    
    // Check if invoice exists and user has access
    if (!$invoice) {
        // Redirect to a list of invoices
        header('Location: admin_invoices.php'); // Changed from view_quotation.php
        exit();
    }
    
    // Get invoice items
    $itemsQuery = "
        SELECT ii.*, p.name AS product_name, p.sku AS product_sku
        FROM invoice_items ii
        LEFT JOIN products p ON ii.product_id = p.id
        WHERE ii.invoice_id = :invoiceId
        ORDER BY ii.item_number ASC, ii.id ASC
    "; // Added ii.id to order for stability if item_number is not unique or null
    
    $itemsStmt = DatabaseConfig::executeQuery($pdo, $itemsQuery, [':invoiceId' => $invoiceId]);
    $invoiceItems = $itemsStmt->fetchAll();
    
    // Close connection if DatabaseConfig doesn't handle it
    // DatabaseConfig::closeConnection($pdo); // Or $pdo = null;
    
} catch (PDOException $e) {
    // Log error
    error_log("Error in view_invoice_details.php: " . $e->getMessage());
    $error = "An error occurred while retrieving invoice details.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .card-header {
            background-color: #f8f9fa;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.35rem 0.65rem;
        }
        .invoice-header {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        .table-items th {
            background-color: #f8f9fa;
        }
        .actions-bar {
            padding: 15px 0;
            border-top: 1px solid #dee2e6;
            margin-top: 20px;
        }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; // Adjust path as necessary ?>
    
    <div class="container-fluid py-4">
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="admin_invoices.php">Invoices</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Details</li>
                        </ol>
                    </nav>
                    <div>
                        <?php if (isset($invoice) && ($invoice['status'] === 'Draft' || $isAdmin)): ?>
                            <a href="edit_invoice.php?id=<?php echo $invoiceId; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        <?php endif; ?>
                        <a href="print_invoice.php?id=<?php echo $invoiceId; ?>" class="btn btn-outline-primary ms-2" target="_blank">
                            <i class="bi bi-printer"></i> Print/PDF
                        </a>
                        <!-- Add other invoice-specific actions here, e.g., Record Payment -->
                        <?php if (isset($invoice) && $invoice['status'] !== 'Paid' && $invoice['status'] !== 'Cancelled'): ?>
                            <!-- <a href="record_payment.php?invoice_id=<?php echo $invoiceId; ?>" class="btn btn-outline-success ms-2">
                                <i class="bi bi-cash-coin"></i> Record Payment
                            </a> -->
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (!isset($invoice)): ?>
            <div class="alert alert-warning">Invoice not found or you do not have permission to view it.</div>
        <?php else: ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0">Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h4>
                                <?php
                                    $statusClass = 'bg-secondary'; // Default
                                    switch (strtolower($invoice['status'])) { // Use strtolower for case-insensitive matching
                                        case 'draft': $statusClass = 'bg-secondary'; break;
                                        case 'sent': $statusClass = 'bg-info text-dark'; break;
                                        case 'paid': $statusClass = 'bg-success'; break;
                                        case 'partially paid': $statusClass = 'bg-warning text-dark'; break;
                                        case 'overdue': $statusClass = 'bg-danger'; break;
                                        case 'cancelled': $statusClass = 'bg-dark'; break;
                                        default: $statusClass = 'bg-light text-dark'; // For unknown statuses
                                    }
                                ?>
                                <span class="badge status-badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars(ucfirst($invoice['status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row invoice-header">
                                <div class="col-md-6">
                                    <h5>Customer Information</h5>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars(!empty($invoice['customer_name_override']) ? $invoice['customer_name_override'] : ($invoice['customer_table_name'] ?? 'N/A')); ?></p>
                                    <?php 
                                        $customerAddress = '';
                                        if (!empty($invoice['customer_address_override'])) {
                                            $customerAddress = nl2br(htmlspecialchars($invoice['customer_address_override']));
                                        } else {
                                            $addressParts = [];
                                            if (!empty($invoice['customer_address_line1'])) $addressParts[] = htmlspecialchars($invoice['customer_address_line1']);
                                            if (!empty($invoice['customer_address_line2'])) $addressParts[] = htmlspecialchars($invoice['customer_address_line2']);
                                            if (!empty($invoice['customer_city_location'])) $addressParts[] = htmlspecialchars($invoice['customer_city_location']);
                                            $customerAddress = implode('<br>', $addressParts);
                                        }
                                    ?>
                                    <?php if (!empty($customerAddress)): ?>
                                        <p class="mb-1"><strong>Address:</strong> <?php echo $customerAddress; ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($invoice['customer_phone'])): ?>
                                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($invoice['customer_phone']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($invoice['customer_email'])): ?>
                                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($invoice['customer_email']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($invoice['customer_tpin_no'])): ?>
                                        <p class="mb-1"><strong>Customer TPIN:</strong> <?php echo htmlspecialchars($invoice['customer_tpin_no']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h5>Invoice Details</h5>
                                    <p class="mb-1"><strong>Invoice Date:</strong> <?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?></p>
                                    <?php if (!empty($invoice['due_date'])): ?>
                                    <p class="mb-1"><strong>Due Date:</strong> <?php echo date('d M Y', strtotime($invoice['due_date'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($invoice['shop_name'])): ?>
                                        <p class="mb-1"><strong>Shop:</strong> <?php echo htmlspecialchars($invoice['shop_name'] . (!empty($invoice['shop_code']) ? ' (' . $invoice['shop_code'] . ')' : '')); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($invoice['company_tpin'])): ?>
                                        <p class="mb-1"><strong>Company TPIN:</strong> <?php echo htmlspecialchars($invoice['company_tpin']); ?></p>
                                    <?php endif; ?>
                                    <p class="mb-1"><strong>Created By:</strong> <?php echo htmlspecialchars($invoice['created_by_full_name'] ?? $invoice['created_by_username']); ?></p>
                                    <p class="mb-1"><strong>Created On:</strong> <?php echo date('d M Y H:i', strtotime($invoice['created_at'])); ?></p>
                                    <?php if (!empty($invoice['updated_at']) && $invoice['updated_at'] !== $invoice['created_at']): ?>
                                        <p class="mb-1"><strong>Last Updated:</strong> <?php echo date('d M Y H:i', strtotime($invoice['updated_at'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($invoice['quotation_id'])): ?>
                                        <p class="mb-1"><strong>Original Quotation:</strong> <a href="view_quotation_details.php?id=<?php echo $invoice['quotation_id']; ?>">View Quotation #<?php echo $invoice['quotation_id']; ?></a></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($invoice['notes_general'])): ?>
                                <div class="row mb-3 mt-3">
                                    <div class="col-12">
                                        <h5>General Notes</h5>
                                        <p><?php echo nl2br(htmlspecialchars($invoice['notes_general'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                             <?php if (!empty($invoice['delivery_period'])): ?>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h5>Delivery Period</h5>
                                        <p><?php echo nl2br(htmlspecialchars($invoice['delivery_period'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                             <?php if (!empty($invoice['payment_terms'])): ?>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h5>Payment Terms</h5>
                                        <p><?php echo nl2br(htmlspecialchars($invoice['payment_terms'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-12">
                                    <h5>Items</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-items">
                                            <thead>
                                                <tr>
                                                    <th width="5%">#</th>
                                                    <th width="40%">Description</th>
                                                    <th width="10%" class="text-center">Quantity</th>
                                                    <th width="10%" class="text-center">UoM</th>
                                                    <th width="15%" class="text-end">Rate/Unit</th>
                                                    <th width="20%" class="text-end">Total Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($invoiceItems)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center">No items found for this invoice.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php $itemIdx = 0; ?>
                                                    <?php foreach ($invoiceItems as $item): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($item['item_number'] ?? ++$itemIdx); ?></td>
                                                            <td>
                                                                <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                                                                <?php if (!empty($item['product_sku'])): ?>
                                                                    <br><small class="text-muted">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center"><?php echo htmlspecialchars(number_format($item['quantity'], 2)); ?></td>
                                                            <td class="text-center"><?php echo htmlspecialchars($item['unit_of_measurement']); ?></td>
                                                            <td class="text-end"><?php echo htmlspecialchars(number_format($item['rate_per_unit'], 2)); ?></td>
                                                            <td class="text-end"><?php echo htmlspecialchars(number_format($item['total_amount'], 2)); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="5" class="text-end"><strong>Gross Total:</strong></td>
                                                    <td class="text-end"><?php echo htmlspecialchars(number_format($invoice['gross_total_amount'] ?? 0.00, 2)); ?></td>
                                                </tr>
                                                <?php if (isset($invoice['apply_ppda_levy']) && $invoice['apply_ppda_levy'] && isset($invoice['ppda_levy_amount'])): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-end"><strong>PPDA Levy (<?php echo htmlspecialchars(number_format($invoice['ppda_levy_percentage'] ?? 1.00, 2)); ?>%):</strong></td>
                                                        <td class="text-end"><?php echo htmlspecialchars(number_format($invoice['ppda_levy_amount'], 2)); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="5" class="text-end"><strong>Amount Before VAT:</strong></td>
                                                        <td class="text-end"><?php echo htmlspecialchars(number_format($invoice['amount_before_vat'] ?? 0.00, 2)); ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                                 <tr>
                                                    <td colspan="5" class="text-end"><strong>VAT (<?php echo htmlspecialchars(number_format($invoice['vat_percentage'] ?? 16.50, 2)); ?>%):</strong></td>
                                                    <td class="text-end"><?php echo htmlspecialchars(number_format($invoice['vat_amount'] ?? 0.00, 2)); ?></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="5" class="text-end"><strong>Total Net Amount:</strong></td>
                                                    <td class="text-end"><strong><?php echo htmlspecialchars(number_format($invoice['total_net_amount'] ?? 0.00, 2)); ?></strong></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="5" class="text-end"><strong>Total Paid:</strong></td>
                                                    <td class="text-end"><?php echo htmlspecialchars(number_format($invoice['total_paid'] ?? 0.00, 2)); ?></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="5" class="text-end"><strong>Balance Due:</strong></td>
                                                    <td class="text-end"><strong><?php echo htmlspecialchars(number_format($invoice['balance_due'] ?? ($invoice['total_net_amount'] - $invoice['total_paid']), 2)); ?></strong></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="actions-bar mt-4">
                                <div class="d-flex justify-content-start">
                                    <a href="admin_invoices.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Back to Invoices
                                    </a>
                                    <!-- Other actions can be placed here or aligned to the right if preferred -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>