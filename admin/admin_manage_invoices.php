<?php
ob_start();
session_start();

// STRICT ADMIN ACCESS CHECK
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminUserId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/../includes/time_formating_helper.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Get filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$createdById = isset($_GET['created_by']) ? (int)$_GET['created_by'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';

try {
    $pdo = getDatabaseConnection();

    // Query invoices instead of quotations
    $query = "
        SELECT i.*, c.name AS customer_name, u.username AS created_by_username
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN users u ON i.created_by_user_id = u.id
        WHERE 1=1
    ";

    $params = [];

    if (!empty($startDate)) {
        $query .= " AND i.invoice_date >= :startDate";
        $params[':startDate'] = $startDate;
    }
    if (!empty($endDate)) {
        $query .= " AND i.invoice_date <= :endDate";
        $params[':endDate'] = $endDate;
    }
    if ($customerId > 0) {
        $query .= " AND i.customer_id = :customerId";
        $params[':customerId'] = $customerId;
    }
    if ($createdById > 0) {
        $query .= " AND i.created_by_user_id = :createdById";
        $params[':createdById'] = $createdById;
    }
    if (!empty($status)) {
        $query .= " AND i.status = :status";
        $params[':status'] = $status;
    }

    $query .= " ORDER BY i.created_at DESC";

    $stmt = DatabaseConfig::executeQuery($pdo, $query, $params);
    $invoices = $stmt->fetchAll();

    $customersStmt = DatabaseConfig::executeQuery($pdo, "SELECT id, name FROM customers ORDER BY name ASC");
    $customers = $customersStmt->fetchAll();

    $usersStmt = DatabaseConfig::executeQuery($pdo, "SELECT id, username, full_name FROM users ORDER BY username ASC");
    $users = $usersStmt->fetchAll();

    DatabaseConfig::closeConnection($pdo);

} catch (PDOException $e) {
    error_log("Error in admin_manage_invoices.php: " . $e->getMessage());
    $error = "An error occurred while retrieving invoices for management.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Invoices</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .actions-column { width: 250px; }
        .filter-section { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .status-badge { font-size: 0.8rem; padding: 0.25rem 0.5rem; }
        .action-button-group .btn { margin-right: 5px; margin-bottom: 5px; }
        .action-button-group .btn:last-child { margin-right: 0; }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 1090; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/invnav.php'; ?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1>Manage Invoices</h1>
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
                            <a href="admin_manage_invoices.php" class="btn btn-outline-secondary ms-2">Reset</a>
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
                                <th>Customer</th>
                                <th>Total Amount</th>
                                <th>Balance Due</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th class="actions-column">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-3">No invoices found matching your criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr id="invoice-row-<?php echo $invoice['id']; ?>">
                                        <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['customer_name'] ?? $invoice['customer_name_override'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format($invoice['total_net_amount'], 2); ?></td>
                                        <td><?php echo number_format($invoice['balance_due'], 2); ?></td>
                                        <td>
                                            <?php
                                                $statusClass = '';
                                                switch ($invoice['status']) {
                                                    case 'Draft': $statusClass = 'bg-secondary'; break;
                                                    case 'Sent': $statusClass = 'bg-info'; break;
                                                    case 'Partially Paid': $statusClass = 'bg-warning text-dark'; break;
                                                    case 'Paid': $statusClass = 'bg-success'; break;
                                                    case 'Overdue': $statusClass = 'bg-danger'; break;
                                                    case 'Cancelled': $statusClass = 'bg-dark'; break;
                                                    default: $statusClass = 'bg-light text-dark';
                                                }
                                            ?>
                                            <span class="badge status-badge <?php echo $statusClass; ?>" id="status-badge-<?php echo $invoice['id']; ?>">
                                                <?php echo htmlspecialchars($invoice['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($invoice['created_by_username']); ?></td>
                                        <td class="actions-column">
                                          <div class="btn-group action-button-group" id="action-buttons-<?php echo $invoice['id']; ?>">
    <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-info" title="View Details">
        <i class="bi bi-eye"></i> View
    </a>

    <?php // Allow delivery notes for Sent, Paid, or Partially Paid invoices ?>
    <?php if (in_array($invoice['status'], ['Sent', 'Partially Paid', 'Paid'])): ?>
        <a href="create_delivery_note.php?invoice_id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-primary" title="Generate Delivery Note">
            <i class="bi bi-truck"></i> Delivery
        </a>
    <?php endif; ?>
    
    <?php if (in_array($invoice['status'], ['Draft'])): ?>
        <button type="button" class="btn btn-sm btn-outline-success send-invoice"
                data-invoice-id="<?php echo $invoice['id']; ?>"
                data-invoice-number="<?php echo htmlspecialchars($invoice['invoice_number']); ?>"
                title="Mark as Sent">
            <i class="bi bi-send"></i> Mark as Sent
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger cancel-invoice"
                data-invoice-id="<?php echo $invoice['id']; ?>"
                data-invoice-number="<?php echo htmlspecialchars($invoice['invoice_number']); ?>"
                title="Cancel Invoice">
            <i class="bi bi-x-circle"></i> Cancel
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

    <div class="modal fade" id="sendInvoiceModal" tabindex="-1" aria-labelledby="sendInvoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sendInvoiceModalLabel">Confirm: Mark as Sent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to mark invoice #<strong id="invoiceNumberToSend"></strong> as 'Sent'? This action is final.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="confirmSendButton">Mark as Sent</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cancelInvoiceModal" tabindex="-1" aria-labelledby="cancelInvoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelInvoiceModalLabel">Confirm: Cancel Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to cancel invoice #<strong id="invoiceNumberToCancel"></strong>? This cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="confirmCancelButton">Cancel Invoice</button>
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

        const sendModal = new bootstrap.Modal(document.getElementById('sendInvoiceModal'));
        const cancelModal = new bootstrap.Modal(document.getElementById('cancelInvoiceModal'));
        let currentInvoiceIdToProcess, currentInvoiceNumberToProcess;

        document.querySelectorAll('.send-invoice').forEach(button => {
            button.addEventListener('click', function () {
                currentInvoiceIdToProcess = this.getAttribute('data-invoice-id');
                currentInvoiceNumberToProcess = this.getAttribute('data-invoice-number');
                document.getElementById('invoiceNumberToSend').textContent = currentInvoiceNumberToProcess;
                sendModal.show();
            });
        });

        document.querySelectorAll('.cancel-invoice').forEach(button => {
            button.addEventListener('click', function () {
                currentInvoiceIdToProcess = this.getAttribute('data-invoice-id');
                currentInvoiceNumberToProcess = this.getAttribute('data-invoice-number');
                document.getElementById('invoiceNumberToCancel').textContent = currentInvoiceNumberToProcess;
                cancelModal.show();
            });
        });

        document.getElementById('confirmSendButton').addEventListener('click', function() {
            processInvoiceStatusUpdate(currentInvoiceIdToProcess, 'Sent', this, sendModal);
        });

        document.getElementById('confirmCancelButton').addEventListener('click', function() {
            processInvoiceStatusUpdate(currentInvoiceIdToProcess, 'Cancelled', this, cancelModal);
        });

        function processInvoiceStatusUpdate(invoiceId, newStatus, buttonElement, modalInstance) {
            const originalButtonText = buttonElement.innerHTML;
            buttonElement.disabled = true;
            buttonElement.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...`;

            const formData = new FormData();
            formData.append('invoice_id', invoiceId);
            formData.append('new_status', newStatus);

            fetch('ajax_update_invoice_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalInstance.hide();
                    const statusBadge = document.getElementById(`status-badge-${invoiceId}`);
                    if (statusBadge) {
                        statusBadge.textContent = newStatus;
                        statusBadge.className = 'badge status-badge '; // Reset classes
                        if (newStatus === 'Sent') statusBadge.classList.add('bg-info');
                        else if (newStatus === 'Cancelled') statusBadge.classList.add('bg-dark');
                    }

                    const actionButtonsContainer = document.getElementById(`action-buttons-${invoiceId}`);
                    if(actionButtonsContainer){
                        actionButtonsContainer.querySelector('.send-invoice')?.remove();
                        actionButtonsContainer.querySelector('.cancel-invoice')?.remove();
                    }
                    showToast(`Invoice #${currentInvoiceNumberToProcess} has been updated to '${newStatus}'.`, 'success');
                } else {
                    showToast('Error updating status: ' + (data.message || 'Unknown error'), 'danger');
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