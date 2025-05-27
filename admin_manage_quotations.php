<?php
ob_start();
session_start();

// STRICT ADMIN ACCESS CHECK
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Redirect to login or an unauthorized page if not an admin
    header("Location: login.php"); 
    exit();
}

$adminUserId = (int)$_SESSION['user_id']; // Admin's user ID

// Adjust paths if necessary
require_once __DIR__ . '/../includes/time_formating_helper.php'; 
require_once __DIR__ . '/../includes/nav.php'; // Ensure nav reflects admin context
require_once __DIR__ . '/../includes/db_connect.php'; 

// Get filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$createdById = isset($_GET['created_by']) ? (int)$_GET['created_by'] : 0;
// Default to 'Submitted' status for admin view, or allow all
// Option 1: Show all statuses by default
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Option 2: Show only Drafts by default
// $status = isset($_GET['status']) ? $_GET['status'] : 'Draft';

try {
    $pdo = getDatabaseConnection();
    
    $query = "
        SELECT q.*, c.name AS customer_name, u.username AS created_by_username
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN users u ON q.created_by_user_id = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($startDate)) {
        $query .= " AND q.quotation_date >= :startDate";
        $params[':startDate'] = $startDate;
    }
    if (!empty($endDate)) {
        $query .= " AND q.quotation_date <= :endDate";
        $params[':endDate'] = $endDate;
    }
    if ($customerId > 0) {
        $query .= " AND q.customer_id = :customerId";
        $params[':customerId'] = $customerId;
    }
    if ($createdById > 0) { // Admin can filter by who created it
        $query .= " AND q.created_by_user_id = :createdById";
        $params[':createdById'] = $createdById;
    }
    if (!empty($status)) {
        $query .= " AND q.status = :status";
        $params[':status'] = $status;
    }
    
    $query .= " ORDER BY q.created_at DESC";
    
    $stmt = DatabaseConfig::executeQuery($pdo, $query, $params);
    $quotations = $stmt->fetchAll();
    
    $customersStmt = DatabaseConfig::executeQuery($pdo, "SELECT id, name FROM customers ORDER BY name ASC");
    $customers = $customersStmt->fetchAll();
    
    $usersStmt = DatabaseConfig::executeQuery($pdo, "SELECT id, username, full_name FROM users ORDER BY username ASC");
    $users = $usersStmt->fetchAll();
    
    DatabaseConfig::closeConnection($pdo);
    
} catch (PDOException $e) {
    error_log("Error in admin_manage_quotations.php: " . $e->getMessage());
    $error = "An error occurred while retrieving quotations for management.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quotations</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .actions-column { width: 220px; /* Adjusted for more buttons */ }
        .filter-section { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .status-badge { font-size: 0.8rem; padding: 0.25rem 0.5rem; }
        .action-button-group .btn { margin-right: 5px; margin-bottom: 5px; } /* Ensure spacing */
        .action-button-group .btn:last-child { margin-right: 0; }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 1090; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1>Manage Quotations</h1>
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
                        <div class="col-md-2">
                            <label for="created_by" class="form-label">Created By</label>
                            <select class="form-select" id="created_by" name="created_by">
                                <option value="0">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $createdById == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if (!empty($user['full_name'])): ?> (<?php echo htmlspecialchars($user['full_name']); ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="admin_manage_quotations.php" class="btn btn-outline-secondary ms-2">Reset</a>
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
                                <th>Created By</th>
                                <th class="actions-column">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($quotations)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-3">No quotations found matching your criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($quotations as $quotation): ?>
                                    <tr id="quotation-row-<?php echo $quotation['id']; ?>">
                                        <td><?php echo htmlspecialchars($quotation['quotation_number']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($quotation['quotation_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($quotation['customer_name'] ?? $quotation['customer_name_override'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format($quotation['total_net_amount'], 2); ?></td>
                                        <td>
                                            <?php
                                                $statusClass = '';
                                                switch ($quotation['status']) {
                                                    case 'Draft': $statusClass = 'bg-secondary'; break;
                                                    case 'Submitted': $statusClass = 'bg-primary'; break;
                                                    case 'Approved': $statusClass = 'bg-success'; break;
                                                    case 'Rejected': $statusClass = 'bg-danger'; break;
                                                    default: $statusClass = 'bg-light text-dark';
                                                }
                                            ?>
                                            <span class="badge status-badge <?php echo $statusClass; ?>" id="status-badge-<?php echo $quotation['id']; ?>">
                                                <?php echo htmlspecialchars($quotation['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($quotation['created_by_username']); ?></td>
                                        <td class="actions-column">
                                            <div class="btn-group action-button-group" id="action-buttons-<?php echo $quotation['id']; ?>">
                                                <a href="view_quotation.php?id=<?php echo $quotation['id']; ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <?php if (in_array($quotation['status'], ['Submitted', 'Draft'])): // Admin can approve/reject Submitted or Drafts ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success approve-quotation"
                                                            data-quotation-id="<?php echo $quotation['id']; ?>"
                                                            data-quotation-number="<?php echo htmlspecialchars($quotation['quotation_number']); ?>"
                                                            title="Approve">
                                                        <i class="bi bi-check-circle"></i> Approve
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger reject-quotation"
                                                            data-quotation-id="<?php echo $quotation['id']; ?>"
                                                            data-quotation-number="<?php echo htmlspecialchars($quotation['quotation_number']); ?>"
                                                            title="Reject">
                                                        <i class="bi bi-x-circle"></i> Reject
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

    <div class="modal fade" id="approveQuotationModal" tabindex="-1" aria-labelledby="approveQuotationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveQuotationModalLabel">Confirm Approval</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to approve quotation #<strong id="quotationNumberToApprove"></strong>?
                    <div class="mt-2">
                        <label for="adminNotesApprove" class="form-label">Optional Notes:</label>
                        <textarea class="form-control" id="adminNotesApprove" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmApproveButton">Approve Quotation</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectQuotationModal" tabindex="-1" aria-labelledby="rejectQuotationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectQuotationModalLabel">Confirm Rejection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to reject quotation #<strong id="quotationNumberToReject"></strong>?
                     <div class="mt-2">
                        <label for="adminNotesReject" class="form-label">Reason for Rejection (Optional):</label>
                        <textarea class="form-control" id="adminNotesReject" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmRejectButton">Reject Quotation</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        flatpickr(".datepicker", { dateFormat: "Y-m-d", allowInput: true });

        const approveModal = new bootstrap.Modal(document.getElementById('approveQuotationModal'));
        const rejectModal = new bootstrap.Modal(document.getElementById('rejectQuotationModal'));
        let currentQuotationIdToProcess, currentQuotationNumberToProcess;

        document.querySelectorAll('.approve-quotation').forEach(button => {
            button.addEventListener('click', function () {
                currentQuotationIdToProcess = this.getAttribute('data-quotation-id');
                currentQuotationNumberToProcess = this.getAttribute('data-quotation-number');
                document.getElementById('quotationNumberToApprove').textContent = currentQuotationNumberToProcess;
                document.getElementById('adminNotesApprove').value = '';
                approveModal.show();
            });
        });

        document.querySelectorAll('.reject-quotation').forEach(button => {
            button.addEventListener('click', function () {
                currentQuotationIdToProcess = this.getAttribute('data-quotation-id');
                currentQuotationNumberToProcess = this.getAttribute('data-quotation-number');
                document.getElementById('quotationNumberToReject').textContent = currentQuotationNumberToProcess;
                document.getElementById('adminNotesReject').value = '';
                rejectModal.show();
            });
        });

        document.getElementById('confirmApproveButton').addEventListener('click', function() {
            const notes = document.getElementById('adminNotesApprove').value;
            processQuotationStatusUpdate(currentQuotationIdToProcess, 'Approved', notes, this, approveModal);
        });

        document.getElementById('confirmRejectButton').addEventListener('click', function() {
            const notes = document.getElementById('adminNotesReject').value;
            processQuotationStatusUpdate(currentQuotationIdToProcess, 'Rejected', notes, this, rejectModal);
        });

        function processQuotationStatusUpdate(quotationId, newStatus, adminNotes, buttonElement, modalInstance) {
            const originalButtonText = buttonElement.innerHTML;
            buttonElement.disabled = true;
            buttonElement.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...`;

            const formData = new FormData();
            formData.append('quotation_id', quotationId);
            formData.append('new_status', newStatus);
            formData.append('admin_notes', adminNotes);
            // You might want to add a CSRF token here for security if your framework supports it

            fetch('ajax_update_quotation_status.php', { // Ensure this path is correct
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalInstance.hide();
                    const statusBadge = document.getElementById(`status-badge-${quotationId}`);
                    if (statusBadge) {
                        statusBadge.textContent = newStatus;
                        statusBadge.className = 'badge status-badge '; // Reset classes
                        if (newStatus === 'Approved') statusBadge.classList.add('bg-success');
                        else if (newStatus === 'Rejected') statusBadge.classList.add('bg-danger');
                    }
                    
                    // Remove approve/reject buttons for this row as action is done
                    const actionButtonsContainer = document.getElementById(`action-buttons-${quotationId}`);
                    if(actionButtonsContainer){
                        const approveBtn = actionButtonsContainer.querySelector(`.approve-quotation[data-quotation-id="${quotationId}"]`);
                        const rejectBtn = actionButtonsContainer.querySelector(`.reject-quotation[data-quotation-id="${quotationId}"]`);
                        if (approveBtn) approveBtn.remove();
                        if (rejectBtn) rejectBtn.remove();
                    }
                    showToast(`Quotation #${currentQuotationNumberToProcess} has been ${newStatus.toLowerCase()}.`, 'success');
                } else {
                    showToast('Error updating status: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An unexpected error occurred. Please try again.', 'danger');
            })
            .finally(() => {
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalButtonText;
            });
        }

        function showToast(message, type = 'info') {
            const toastContainer = document.querySelector('.toast-container');
            const toastId = 'toast-' + Date.now();
            const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
            toast.show();
            toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
        }
    });
    </script>
</body>
</html>