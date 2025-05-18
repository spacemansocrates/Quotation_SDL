<?php
/**
 * Print Quotation Page
 * 
 * Generates a printer-friendly version of a quotation
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../includes/db_connect.php';

// Get user info from session
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$isAdmin = ($userRole === 'admin');

// Get quotation ID from URL parameter
$quotationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($quotationId <= 0) {
    header('Location: view_quotations.php');
    exit();
}

// Get company information (you may want to store this in a settings table)
$companyInfo = [
    'name' => 'Your Company Name',
    'address' => '123 Business Street, City, Country',
    'phone' => '+1 (555) 123-4567',
    'email' => 'info@yourcompany.com',
    'website' => 'www.yourcompany.com',
    'logo' => '../assets/images/logo.png' // Path to company logo
];

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
        header('Location: view_quotations.php');
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
    error_log("Error in print_quotation.php: " . $e->getMessage());
    $error = "An error occurred while retrieving quotation details for printing.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation #<?php echo htmlspecialchars($quotation['quotation_number'] ?? ''); ?> - Print</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.4;
            color: #333;
        }
        
        .print-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            .print-container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
        
        .quotation-header {
            margin-bottom: 30px;
        }
        
        .company-info {
            text-align: left;
        }
        
        .company-logo {
            max-height: 80px;
            max-width: 300px;
        }
        
        .quotation-title {
            background-color: #f8f9fa;
            padding: 10px 15px;
            margin: 20px 0;
            border-bottom: 2px solid #dee2e6;
        }
        
        .customer-details, .quotation-details {
            margin-bottom: 20px;
        }
        
        .section-title {
            font-weight: bold;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 8px;
            border: 1px solid #dee2e6;
        }
        
        table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: left;
        }
        
        .text-end {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-table {
            width: 350px;
            margin-left: auto;
            margin-top: 20px;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .status-approved {
            color: #198754;
            font-weight: bold;
        }
        
        .status-rejected {
            color: #dc3545;
            font-weight: bold;
        }
        
        .status-submitted {
            color: #0d6efd;
            font-weight: bold;
        }
        
        .status-draft {
            color: #6c757d;
            font-weight: bold;
        }
        
        .signature-area {
            margin-top: 80px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            width: 45%;
            border-top: 1px solid #000;
            padding-top: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="print-container">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="no-print mb-4">
                <button onclick="window.print();" class="btn btn-primary">
                    Print Quotation
                </button>
                <a href="view_quotation_details.php?id=<?php echo $quotationId; ?>" class="btn btn-outline-secondary">
                    Back to Details
                </a>
            </div>
            
            <div class="quotation-header row">
                <div class="col-6 company-info">
                    <?php if (file_exists($companyInfo['logo'])): ?>
                        <img src="<?php echo $companyInfo['logo']; ?>" alt="Company Logo" class="company-logo">
                    <?php else: ?>
                        <h2><?php echo htmlspecialchars($companyInfo['name']); ?></h2>
                    <?php endif; ?>
                    <p><?php echo nl2br(htmlspecialchars($companyInfo['address'])); ?><br>
                    Phone: <?php echo htmlspecialchars($companyInfo['phone']); ?><br>
                    Email: <?php echo htmlspecialchars($companyInfo['email']); ?><br>
                    Web: <?php echo htmlspecialchars($companyInfo['website']); ?></p>
                </div>
                <div class="col-6 text-end">
                    <h1>QUOTATION</h1>
                    <p>
                        <strong>Quotation #:</strong> <?php echo htmlspecialchars($quotation['quotation_number']); ?><br>
                        <strong>Date:</strong> <?php echo date('d M Y', strtotime($quotation['quotation_date'])); ?><br>
                        <strong>Valid Until:</strong> <?php echo !empty($quotation['valid_until']) ? date('d M Y', strtotime($quotation['valid_until'])) : 'N/A'; ?>
                    </p>
                    <p>
                        <strong>Status:</strong> 
                        <span class="status-<?php echo strtolower($quotation['status']); ?>">
                            <?php echo htmlspecialchars($quotation['status']); ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-6 customer-details">
                    <div class="section-title">BILL TO</div>
                    <p>
                        <strong><?php echo htmlspecialchars($quotation['customer_name'] ?? $quotation['customer_name_override'] ?? 'N/A'); ?></strong><br>
                        <?php if (!empty($quotation['customer_address'])): ?>
                            <?php echo nl2br(htmlspecialchars($quotation['customer_address'])); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($quotation['customer_phone'])): ?>
                            Phone: <?php echo htmlspecialchars($quotation['customer_phone']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($quotation['customer_email'])): ?>
                            Email: <?php echo htmlspecialchars($quotation['customer_email']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-6 quotation-details">
                    <div class="section-title">PREPARED BY</div>
                    <p>
                        <strong><?php echo htmlspecialchars($quotation['created_by_full_name'] ?? $quotation['created_by_username']); ?></strong><br>
                        <?php echo htmlspecialchars($companyInfo['name']); ?><br>
                        Phone: <?php echo htmlspecialchars($companyInfo['phone']); ?><br>
                        Email: <?php echo htmlspecialchars($companyInfo['email']); ?>
                    </p>
                </div>
            </div>
            
            <div class="quotation-title">
                <h4>Quotation Details</h4>
            </div>
            
            <table class="table-items">
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
            </table>
            
            <div class="totals-table">
                <table>
                    <tr>
                        <th>Subtotal</th>
                        <td class="text-end"><?php echo number_format($quotation['subtotal_amount'], 2); ?></td>
                    </tr>
                    <?php if ($quotation['discount_amount'] > 0): ?>
                        <tr>
                            <th>Discount</th>
                            <td class="text-end"><?php echo number_format($quotation['discount_amount'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($quotation['tax_amount'] > 0): ?>
                        <tr>
                            <th>Tax</th>
                            <td class="text-end"><?php echo number_format($quotation['tax_amount'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Total</th>
                        <td class="text-end"><strong><?php echo number_format($quotation['total_net_amount'], 2); ?></strong></td>
                    </tr>
                </table>
            </div>
            
            <?php if (!empty($quotation['notes'])): ?>
                <div class="mt-4">
                    <div class="section-title">NOTES</div>
                    <p><?php echo nl2br(htmlspecialchars($quotation['notes'])); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($quotation['terms_and_conditions'])): ?>
                <div class="mt-4">
                    <div class="section-title">TERMS AND CONDITIONS</div>
                    <p><?php echo nl2br(htmlspecialchars($quotation['terms_and_conditions'])); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="signature-area">
                <div class="signature-box">
                    <p>For <?php echo htmlspecialchars($companyInfo['name']); ?></p>
                    <p>_____________________________</p>
                    <p>Authorized Signature</p>
                </div>
                
                <div class="signature-box">
                    <p>For <?php echo htmlspecialchars($quotation['customer_name'] ?? $quotation['customer_name_override'] ?? 'Customer'); ?></p>
                    <p>_____________________________</p>
                    <p>Authorized Signature</p>
                </div>
            </div>
            
            <div class="footer mt-5">
                <p class="text-center">
                    Thank you for your business!<br>
                    If you have any questions about this quotation, please contact us.
                </p>
                <p class="text-center">
                    <small>This quotation was generated on <?php echo date('d M Y H:i:s'); ?></small>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto print when page loads (optional)
            // window.print();
        });
    </script>
</body>
</html>