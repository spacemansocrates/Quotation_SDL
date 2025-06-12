<?php
ob_start();
session_start();

// Check for user authentication - FIXED to match login.php session variables
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];
$isAdmin = $_SESSION['user_role'] === 'admin'; // FIXED to match login.php

// Adjust paths if necessary
require_once __DIR__ . '/../includes/time_formating_helper.php'; // Add this for time formatting functions
require_once __DIR__ . '/../includes/invnav.php';
require_once __DIR__ . '/../includes/db_connect.php'; 

// Get filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$createdById = isset($_GET['created_by']) ? (int)$_GET['created_by'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';

try {
    // Get database connection
    $pdo = getDatabaseConnection();
    
    // Build query based on user role and filters
    $query = "
        SELECT i.*, c.name AS customer_name, u.username AS created_by_username,
               s.name AS shop_name, s.shop_code
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN users u ON i.created_by_user_id = u.id
        LEFT JOIN shops s ON i.shop_id = s.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply role-based filtering
    if (!$isAdmin) {
        $query .= " AND i.created_by_user_id = :userId";
        $params[':userId'] = $userId;
    }
    
    // Apply date filters
    if (!empty($startDate)) {
        $query .= " AND i.invoice_date >= :startDate";
        $params[':startDate'] = $startDate;
    }
    
    if (!empty($endDate)) {
        $query .= " AND i.invoice_date <= :endDate";
        $params[':endDate'] = $endDate;
    }
    
    // Apply customer filter
    if ($customerId > 0) {
        $query .= " AND i.customer_id = :customerId";
        $params[':customerId'] = $customerId;
    }
    
    // Apply created by filter (admin only)
    if ($isAdmin && $createdById > 0) {
        $query .= " AND i.created_by_user_id = :createdById";
        $params[':createdById'] = $createdById;
    }
    
    // Apply status filter
    if (!empty($status)) {
        $query .= " AND i.status = :status";
        $params[':status'] = $status;
    }
    
    // Order by most recent first
    $query .= " ORDER BY i.created_at DESC";
    
    // Execute query
    $stmt = DatabaseConfig::executeQuery($pdo, $query, $params);
    $invoices = $stmt->fetchAll();
    
    // Get all customers for filter dropdown
    $customersStmt = DatabaseConfig::executeQuery(
        $pdo,
        "SELECT id, name FROM customers ORDER BY name ASC"
    );
    $customers = $customersStmt->fetchAll();
    
    // Get all users for filter dropdown (admin only)
    $users = [];
    if ($isAdmin) {
        $usersStmt = DatabaseConfig::executeQuery(
            $pdo,
            "SELECT id, username, full_name FROM users ORDER BY username ASC"
        );
        $users = $usersStmt->fetchAll();
    }
    
    // Close connection
    DatabaseConfig::closeConnection($pdo);
    
} catch (PDOException $e) {
    // Log error
    error_log("Error in view_invoices.php: " . $e->getMessage());
    $error = "An error occurred while retrieving invoices.";
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoices</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .actions-column {
            width: 180px;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .balance-due {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/invnav.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1>Invoices</h1>
                    <a href="create_invoice.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Create New Invoice
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-12">
                <div class="filter-section">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-2">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="text" class="form-control datepicker" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="text" class="form-control datepicker" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="customer_id" class="form-label">Customer</label>
                            <select class="form-select" id="customer_id" name="customer_id">
                                <option value="0">All Customers</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" <?php echo $customerId == $customer['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="Draft" <?php echo $status === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="Sent" <?php echo $status === 'Sent' ? 'selected' : ''; ?>>Sent</option>
                                <option value="Paid" <?php echo $status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="Partially Paid" <?php echo $status === 'Partially Paid' ? 'selected' : ''; ?>>Partially Paid</option>
                                <option value="Overdue" <?php echo $status === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                                <option value="Cancelled" <?php echo $status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <?php if ($isAdmin): ?>
                            <div class="col-md-2">
                                <label for="created_by" class="form-label">Created By</label>
                                <select class="form-select" id="created_by" name="created_by">
                                    <option value="0">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo $createdById == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                            <?php if (!empty($user['full_name'])): ?>
                                                (<?php echo htmlspecialchars($user['full_name']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="view_invoices.php" class="btn btn-outline-secondary ms-2">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th>Customer</th>
                                <th>Shop</th>
                                <th>Total Amount</th>
                                <th>Paid</th>
                                <th>Balance Due</th>
                                <th>Status</th>
                                <?php if ($isAdmin): ?>
                                    <th>Created By</th>
                                <?php endif; ?>
                                <th class="actions-column">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="<?php echo $isAdmin ? '11' : '10'; ?>" class="text-center py-3">No invoices found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?></td>
                                        <td>
                                            <?php if ($invoice['due_date']): ?>
                                                <?php echo date('d M Y', strtotime($invoice['due_date'])); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($invoice['customer_name'] ?? $invoice['customer_name_override'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['shop_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format($invoice['total_net_amount'], 2); ?></td>
                                        <td><?php echo number_format($invoice['total_paid'], 2); ?></td>
                                        <td class="balance-due">
                                            <?php 
                                                $balance = $invoice['balance_due'];
                                                $balanceClass = '';
                                                if ($balance > 0) {
                                                    $balanceClass = 'text-danger';
                                                } elseif ($balance == 0) {
                                                    $balanceClass = 'text-success';
                                                }
                                            ?>
                                            <span class="<?php echo $balanceClass; ?>">
                                                <?php echo number_format($balance, 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                                $statusClass = '';
                                                switch ($invoice['status']) {
                                                    case 'Draft':
                                                        $statusClass = 'bg-secondary';
                                                        break;
                                                    case 'Sent':
                                                        $statusClass = 'bg-primary';
                                                        break;
                                                    case 'Paid':
                                                        $statusClass = 'bg-success';
                                                        break;
                                                    case 'Partially Paid':
                                                        $statusClass = 'bg-warning text-dark';
                                                        break;
                                                    case 'Overdue':
                                                        $statusClass = 'bg-danger';
                                                        break;
                                                    case 'Cancelled':
                                                        $statusClass = 'bg-dark';
                                                        break;
                                                    default:
                                                        $statusClass = 'bg-secondary';
                                                }
                                            ?>
                                            <span class="badge status-badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($invoice['status']); ?>
                                            </span>
                                        </td>
                                        <?php if ($isAdmin): ?>
                                            <td><?php echo htmlspecialchars($invoice['created_by_username']); ?></td>
                                        <?php endif; ?>
                                        <td class="actions-column">
                                            <div class="btn-group">
                                                <a href="view_invoice_details.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($invoice['status'] === 'Draft' || $isAdmin): ?>
                                                    <a href="edit_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="print_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-success" title="Print" target="_blank">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                                <?php if ($invoice['balance_due'] > 0 && $invoice['status'] !== 'Cancelled'): ?>
                                                    <a href="record_payment.php?invoice_id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-info" title="Record Payment">
                                                        <i class="bi bi-cash"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($invoice['status'] === 'Draft' && ($isAdmin || $invoice['created_by_user_id'] == $userId)): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-invoice" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteInvoiceModal" 
                                                        data-invoice-id="<?php echo $invoice['id']; ?>"
                                                        data-invoice-number="<?php echo htmlspecialchars($invoice['invoice_number']); ?>"
                                                        title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
 <!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteInvoiceModal" tabindex="-1" aria-labelledby="deleteInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteInvoiceModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete invoice #<strong id="invoiceNumberToDelete"></strong>? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteInvoiceForm" method="POST" style="display: inline;">
                    <input type="hidden" name="invoice_id" id="invoiceIdInput">
                    <button type="submit" class="btn btn-danger">Delete Invoice</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap and Flatpickr CDN scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Initialize date pickers
    flatpickr(".datepicker", {
        dateFormat: "Y-m-d",
        allowInput: true
    });

    // Handle delete modal
    const deleteInvoiceModal = document.getElementById('deleteInvoiceModal');
    if (deleteInvoiceModal) {
        deleteInvoiceModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;
            
            // Extract info from data attributes
            const invoiceId = button.getAttribute('data-invoice-id');
            const invoiceNumber = button.getAttribute('data-invoice-number');

            // Update the modal's content
            document.getElementById('invoiceNumberToDelete').textContent = invoiceNumber;
            document.getElementById('invoiceIdInput').value = invoiceId;
        });
    }

    // Handle form submission
 const deleteInvoiceForm = document.getElementById('deleteInvoiceForm');
if (deleteInvoiceForm) {
    deleteInvoiceForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        const deleteButton = this.querySelector('button[type="submit"]');
        
        // Disable button and show loading state
        deleteButton.disabled = true;
        deleteButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
        
        fetch('delete_invoice.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Find and remove the table row
                const invoiceId = formData.get('invoice_id');
                const rowToRemove = document.querySelector(`button[data-invoice-id="${invoiceId}"]`).closest('tr');
                if (rowToRemove) {
                    rowToRemove.remove();
                }
                
                // Close the modal
                const modalInstance = bootstrap.Modal.getInstance(deleteInvoiceModal);
                modalInstance.hide();
            } else {
                alert('Error deleting invoice: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred.');
        })
        .finally(() => {
            // Reset button state
            deleteButton.disabled = false;
            deleteButton.textContent = 'Delete Invoice';
        });
    });
}