<?php
/**
 * View Quotation Details Page
 * 
 * Displays detailed information about a specific quotation
 */

// Start session
session_start();

// Check if user is logged in
// Use the same session variable name as the login system
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['user_role'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../includes/db_connect.php';

// Get user info from session
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$isAdmin = ($userRole === 'admin');

// Get quotation ID from URL parameter
$quotationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($quotationId <= 0) {
    header('Location: view_quotation.php');
    exit();
}

try {
    // Get database connection
    $pdo = getDatabaseConnection();
    
    // Get quotation details
    $query = "
        SELECT q.*, 
               c.name AS customer_name, 
               c.address AS customer_address,
               c.phone AS customer_phone,
               c.email AS customer_email,
               u.username AS created_by_username,
               u.full_name AS created_by_full_name
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN users u ON q.created_by_user_id = u.id
        WHERE q.id = :quotationId
    ";
    
    $params = [':quotationId' => $quotationId];
    
    // If not admin, restrict to own quotations
    if (!$isAdmin) {
        $query .= " AND q.created_by_user_id = :userId";
        $params[':userId'] = $userId;
    }
    
    $stmt = DatabaseConfig::executeQuery($pdo, $query, $params);
    $quotation = $stmt->fetch();
    
    // Check if quotation exists and user has access
    if (!$quotation) {
        header('Location: view_quotation.php');
        exit();
    }
    
    // Get quotation items
    $itemsQuery = "
        SELECT qi.*, p.name AS product_name, p.description AS product_description
        FROM quotation_items qi
        LEFT JOIN products p ON qi.product_id = p.id
        WHERE qi.quotation_id = :quotationId
        ORDER BY qi.item_order ASC
    ";
    
    $itemsStmt = DatabaseConfig::executeQuery($pdo, $itemsQuery, [':quotationId' => $quotationId]);
    $quotationItems = $itemsStmt->fetchAll();
    
    // Close connection
    DatabaseConfig::closeConnection($pdo);
    
} catch (PDOException $e) {
    // Log error
    error_log("Error in view_quotation_details.php: " . $e->getMessage());
    $error = "An error occurred while retrieving quotation details.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Details</title>
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
        .quotation-header {
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
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="view_quotation.php">Quotations</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Details</li>
                        </ol>
                    </nav>
                    <div>
                        <?php if ($quotation['status'] === 'Draft' || $isAdmin): ?>
                            <a href="edit_quotation.php?id=<?php echo $quotationId; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        <?php endif; ?>
                        <a href="print_quotation.php?id=<?php echo $quotationId; ?>" class="btn btn-outline-primary ms-2" target="_blank">
                            <i class="bi bi-printer"></i> Print
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0">Quotation #<?php echo htmlspecialchars($quotation['quotation_number']); ?></h4>
                                <?php
                                    $statusClass = '';
                                    switch ($quotation['status']) {
                                        case 'Draft':
                                            $statusClass = 'bg-secondary';
                                            break;
                                        case 'Submitted':
                                            $statusClass = 'bg-primary';
                                            break;
                                        case 'Approved':
                                            $statusClass = 'bg-success';
                                            break;
                                        case 'Rejected':
                                            $statusClass = 'bg-danger';
                                            break;
                                        default:
                                            $statusClass = 'bg-secondary';
                                    }
                                ?>
                                <span class="badge status-badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($quotation['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row quotation-header">
                                <div class="col-md-6">
                                    <h5>Customer Information</h5>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($quotation['customer_name'] ?? $quotation['customer_name_override'] ?? 'N/A'); ?></p>
                                    <?php if (!empty($quotation['customer_address'])): ?>
                                        <p class="mb-1"><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($quotation['customer_address'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($quotation['customer_phone'])): ?>
                                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($quotation['customer_phone']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($quotation['customer_email'])): ?>
                                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($quotation['customer_email']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h5>Quotation Details</h5>
                                    <p class="mb-1"><strong>Quotation Date:</strong> <?php echo date('d M Y', strtotime($quotation['quotation_date'])); ?></p>
                                    <p class="mb-1"><strong>Valid Until:</strong> <?php echo !empty($quotation['valid_until']) ? date('d M Y', strtotime($quotation['valid_until'])) : 'N/A'; ?></p>
                                    <p class="mb-1"><strong>Created By:</strong> <?php echo htmlspecialchars($quotation['created_by_full_name'] ?? $quotation['created_by_username']); ?></p>
                                    <p class="mb-1"><strong>Created On:</strong> <?php echo date('d M Y H:i', strtotime($quotation['created_at'])); ?></p>
                                    <?php if (!empty($quotation['updated_at']) && $quotation['updated_at'] !== $quotation['created_at']): ?>
                                        <p class="mb-1"><strong>Last Updated:</strong> <?php echo date('d M Y H:i', strtotime($quotation['updated_at'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($quotation['notes'])): ?>
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5>Notes</h5>
                                        <p><?php echo nl2br(htmlspecialchars($quotation['notes'])); ?></p>
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
                                                    <th width="30%">Item</th>
                                                    <th width="25%">Description</th>
                                                    <th width="10%">Quantity</th>
                                                    <th width="15%">Unit Price</th>
                                                    <th width="15%">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($quotationItems)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center">No items found for this quotation.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php $itemNumber = 1; ?>
                                                    <?php foreach ($quotationItems as $item): ?>
                                                        <tr>
                                                            <td><?php echo $itemNumber++; ?></td>
                                                            <td><?php echo htmlspecialchars($item['product_name'] ?? $item['item_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($item['product_description'] ?? $item['item_description'] ?? ''); ?></td>
                                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                                            <td class="text-end"><?php echo number_format($item['unit_price'], 2); ?></td>
                                                            <td class="text-end"><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                                                    <td class="text-end"><?php echo number_format($quotation['subtotal_amount'], 2); ?></td>
                                                </tr>
                                                <?php if ($quotation['discount_amount'] > 0): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-end"><strong>Discount:</strong></td>
                                                        <td class="text-end"><?php echo number_format($quotation['discount_amount'], 2); ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if ($quotation['tax_amount'] > 0): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-end"><strong>Tax:</strong></td>
                                                        <td class="text-end"><?php echo number_format($quotation['tax_amount'], 2); ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                                    <td class="text-end"><strong><?php echo number_format($quotation['total_net_amount'], 2); ?></strong></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($quotation['terms_and_conditions'])): ?>
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h5>Terms and Conditions</h5>
                                        <p><?php echo nl2br(htmlspecialchars($quotation['terms_and_conditions'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="actions-bar">
                                <div class="d-flex justify-content-between">
                                    <a href="view_quotation.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Back to Quotations
                                    </a>
                                    <div>
                                        <?php if ($quotation['status'] === 'Draft' && ($quotation['created_by_user_id'] == $userId || $isAdmin)): ?>
                                            <a href="submit_quotation.php?id=<?php echo $quotationId; ?>" class="btn btn-success" onclick="return confirm('Are you sure you want to submit this quotation? It will no longer be editable.');">
                                                <i class="bi bi-check-circle"></i> Submit Quotation
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($isAdmin && $quotation['status'] === 'Submitted'): ?>
                                            <a href="approve_quotation.php?id=<?php echo $quotationId; ?>" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this quotation?');">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </a>
                                            <a href="reject_quotation.php?id=<?php echo $quotationId; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this quotation?');">
                                                <i class="bi bi-x-lg"></i> Reject
                                            </a>
                                        <?php endif; ?>
                                    </div>
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