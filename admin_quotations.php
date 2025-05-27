<?php
ob_start();
session_start();

// Check for user authentication - FIXED to match login.php session variables
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

// Remove this debug message - it was unreachable code anyway
// echo "it is done";

$userId = (int)$_SESSION['user_id'];
$isAdmin = $_SESSION['user_role'] === 'admin'; // FIXED to match login.php

// Adjust paths if necessary
require_once __DIR__ . '/../includes/time_formating_helper.php'; // Add this for time formatting functions
require_once __DIR__ . '/../includes/nav.php';
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
        SELECT q.*, c.name AS customer_name, u.username AS created_by_username
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN users u ON q.created_by_user_id = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply role-based filtering
    if (!$isAdmin) {
        $query .= " AND q.created_by_user_id = :userId";
        $params[':userId'] = $userId;
    }
    
    // Apply date filters
    if (!empty($startDate)) {
        $query .= " AND q.quotation_date >= :startDate";
        $params[':startDate'] = $startDate;
    }
    
    if (!empty($endDate)) {
        $query .= " AND q.quotation_date <= :endDate";
        $params[':endDate'] = $endDate;
    }
    
    // Apply customer filter
    if ($customerId > 0) {
        $query .= " AND q.customer_id = :customerId";
        $params[':customerId'] = $customerId;
    }
    
    // Apply created by filter (admin only)
    if ($isAdmin && $createdById > 0) {
        $query .= " AND q.created_by_user_id = :createdById";
        $params[':createdById'] = $createdById;
    }
    
    // Apply status filter
    if (!empty($status)) {
        $query .= " AND q.status = :status";
        $params[':status'] = $status;
    }
    
    // Order by most recent first
    $query .= " ORDER BY q.created_at DESC";
    
    // Execute query
    $stmt = DatabaseConfig::executeQuery($pdo, $query, $params);
    $quotations = $stmt->fetchAll();
    
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
    error_log("Error in view_quotation.php: " . $e->getMessage());
    $error = "An error occurred while retrieving quotations.";
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quotations</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .actions-column {
            width: 150px;
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
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1>Quotations</h1>
                    <a href="create_quotation.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Create New Quotation
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
                                <option value="Submitted" <?php echo $status === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                                <option value="Approved" <?php echo $status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                            <a href="view_quotation.php" class="btn btn-outline-secondary ms-2">Reset</a>
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
                                <th>Quotation #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <?php if ($isAdmin): ?>
                                    <th>Created By</th>
                                <?php endif; ?>
                                <th class="actions-column">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($quotations)): ?>
                                <tr>
                                    <td colspan="<?php echo $isAdmin ? '7' : '6'; ?>" class="text-center py-3">No quotations found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($quotations as $quotation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($quotation['quotation_number']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($quotation['quotation_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($quotation['customer_name'] ?? $quotation['customer_name_override'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format($quotation['total_net_amount'], 2); ?></td>
                                        <td>
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
                                        </td>
                                        <?php if ($isAdmin): ?>
                                            <td><?php echo htmlspecialchars($quotation['created_by_username']); ?></td>
                                        <?php endif; ?>
                                        <td class="actions-column">
                                            <div class="btn-group">
                                                <a href="view_quotation.php?id=<?php echo $quotation['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($quotation['status'] === 'Draft' || $isAdmin): ?>
                                                    <a href="edit_quotation.php?id=<?php echo $quotation['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="print_quotation.php?id=<?php echo $quotation['id']; ?>" class="btn btn-sm btn-outline-success" title="Print" target="_blank">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                                <?php if ($quotation['status'] === 'Draft' && ($isAdmin || $quotation['created_by_user_id'] == $userId)): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-quotation" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteQuotationModal" 
                                                        data-quotation-id="<?php echo $quotation['id']; ?>"
                                                        data-quotation-number="<?php echo htmlspecialchars($quotation['quotation_number']); ?>"
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
<div class="modal fade" id="deleteQuotationModal" tabindex="-1" aria-labelledby="deleteQuotationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteQuotationModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete quotation #<strong id="quotationNumberToDelete"></strong>? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteQuotationForm" method="POST" style="display: inline;">
                    <input type="hidden" name="quotation_id" id="quotationIdInput">
                    <button type="submit" class="btn btn-danger">Delete Quotation</button>
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
    const deleteQuotationModal = document.getElementById('deleteQuotationModal');
    if (deleteQuotationModal) {
        deleteQuotationModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;
            
            // Extract info from data attributes
            const quotationId = button.getAttribute('data-quotation-id');
            const quotationNumber = button.getAttribute('data-quotation-number');

            // Update the modal's content
            document.getElementById('quotationNumberToDelete').textContent = quotationNumber;
            document.getElementById('quotationIdInput').value = quotationId;
        });
    }

    // Handle form submission
    const deleteQuotationForm = document.getElementById('deleteQuotationForm');
    if (deleteQuotationForm) {
        deleteQuotationForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            const deleteButton = this.querySelector('button[type="submit"]');
            
            // Disable button and show loading state
            deleteButton.disabled = true;
            deleteButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';

            fetch('ajax_delete_quotation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Find and remove the table row
                    const quotationId = formData.get('quotation_id');
                    const rowToRemove = document.querySelector(`button[data-quotation-id="${quotationId}"]`).closest('tr');
                    if (rowToRemove) {
                        rowToRemove.remove();
                    }
                    
                    // Close the modal
                    const modalInstance = bootstrap.Modal.getInstance(deleteQuotationModal);
                    modalInstance.hide();
                } else {
                    alert('Error deleting quotation: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred.');
            })
            .finally(() => {
                // Reset button state
                deleteButton.disabled = false;
                deleteButton.textContent = 'Delete Quotation';
            });
        });
    }
});
</script>