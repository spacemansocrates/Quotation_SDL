<?php

session_start();

// --- Authentication and Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    $_SESSION['error_message'] = "You must be logged in to perform this action.";
    header('Location: login.php'); // Adjust to your login page
    exit();
}
// Optional: Check if user role has permission to record payments
// if (!in_array($_SESSION['user_role'], ['admin', 'manager', 'staff_accounts'])) {
//     $_SESSION['error_message'] = "You do not have permission to record payments.";
//     header('Location: dashboard.php'); // Or appropriate page
//     exit();
// }

require_once __DIR__ . '/../includes/db_connect.php'; // Adjust path as needed

$pdo = getDatabaseConnection();
$customers = [];
$selected_customer_id = null;
$invoices_for_payment = [];
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']);

$current_user_id = $_SESSION['user_id'];

try {
    // Fetch all customers for the dropdown
    $customers = DatabaseConfig::executeQuery($pdo, "SELECT id, customer_code, name FROM customers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching customers: " . $e->getMessage();
    error_log("Record Payment Customer Fetch Error: " . $e->getMessage());
}

// --- Handle POST request for recording payment ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id_post = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
    $payment_date = filter_input(INPUT_POST, 'payment_date', FILTER_SANITIZE_SPECIAL_CHARS);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS);
    $reference_number = filter_input(INPUT_POST, 'reference_number', FILTER_SANITIZE_SPECIAL_CHARS);
    $notes = filter_input(INPUT_POST, 'payment_notes', FILTER_SANITIZE_SPECIAL_CHARS);
    $items_to_pay = $_POST['items'] ?? []; // Array of [invoice_id => amount_to_pay]

    $selected_customer_id = $customer_id_post; // Keep customer selected on error

    // Basic Validations
    if (empty($customer_id_post)) {
        $error_message = "Please select a customer.";
    } elseif (empty($payment_date)) {
        $error_message = "Payment date is required.";
    } elseif (empty($payment_method)) {
        $error_message = "Payment method is required.";
    } elseif (empty($items_to_pay)) {
        $error_message = "No invoice amounts specified for payment.";
    } else {
        $total_payment_processed = 0;
        $processed_invoice_numbers = [];

        try {
            $pdo->beginTransaction();

            foreach ($items_to_pay as $invoice_id_pay => $amount_str) {
                $invoice_id_pay = (int)$invoice_id_pay;
                $amount_to_pay_on_invoice = filter_var($amount_str, FILTER_VALIDATE_FLOAT);

                if ($amount_to_pay_on_invoice === false || $amount_to_pay_on_invoice <= 0) {
                    continue; // Skip invalid or zero amounts
                }

                // Fetch the invoice to verify and get current details
                $stmt_inv = DatabaseConfig::executeQuery($pdo, 
                    "SELECT id, invoice_number, customer_id, total_net_amount, total_paid, status 
                     FROM invoices 
                     WHERE id = :invoice_id AND customer_id = :customer_id_post FOR UPDATE", // Lock row
                    [':invoice_id' => $invoice_id_pay, ':customer_id_post' => $customer_id_post]
                );
                $invoice = $stmt_inv->fetch(PDO::FETCH_ASSOC);

                if (!$invoice) {
                    throw new Exception("Invoice ID #{$invoice_id_pay} not found or does not belong to the selected customer.");
                }

                $balance_due_on_invoice = round($invoice['total_net_amount'] - $invoice['total_paid'], 2);

                if ($amount_to_pay_on_invoice > $balance_due_on_invoice + 0.005) { // Add small tolerance for float issues
                    throw new Exception("Payment amount ( {$amount_to_pay_on_invoice} ) for invoice #{$invoice['invoice_number']} exceeds its balance due ( {$balance_due_on_invoice} ).");
                }
                
                // 1. Insert into payments table
                $sql_payment = "INSERT INTO payments (invoice_id, customer_id, payment_date, amount_paid, payment_method, reference_number, notes, recorded_by_user_id) 
                                VALUES (:invoice_id, :customer_id, :payment_date, :amount_paid, :payment_method, :reference_number, :notes, :recorded_by)";
                DatabaseConfig::executeQuery($pdo, $sql_payment, [
                    ':invoice_id' => $invoice_id_pay,
                    ':customer_id' => $customer_id_post,
                    ':payment_date' => $payment_date,
                    ':amount_paid' => $amount_to_pay_on_invoice,
                    ':payment_method' => $payment_method,
                    ':reference_number' => $reference_number,
                    ':notes' => $notes,
                    ':recorded_by' => $current_user_id
                ]);

                // 2. Update invoice table
                $new_total_paid_on_invoice = round($invoice['total_paid'] + $amount_to_pay_on_invoice, 2);
                $new_balance_due = round($invoice['total_net_amount'] - $new_total_paid_on_invoice, 2);
                $new_status = $invoice['status'];

                if ($new_balance_due <= 0.005) { // Using small tolerance
                    $new_status = 'Paid';
                } elseif ($new_total_paid_on_invoice > 0) {
                    $new_status = 'Partially Paid';
                }
                // Note: 'Overdue' status is typically handled by a separate check/batch job based on due_date and current_date

                $sql_update_invoice = "UPDATE invoices 
                                       SET total_paid = :total_paid, status = :status, updated_at = CURRENT_TIMESTAMP, updated_by_user_id = :updater_id
                                       WHERE id = :invoice_id";
                DatabaseConfig::executeQuery($pdo, $sql_update_invoice, [
                    ':total_paid' => $new_total_paid_on_invoice,
                    ':status' => $new_status,
                    ':updater_id' => $current_user_id,
                    ':invoice_id' => $invoice_id_pay
                ]);
                
                $total_payment_processed += $amount_to_pay_on_invoice;
                $processed_invoice_numbers[] = $invoice['invoice_number'];
            }

            if ($total_payment_processed > 0) {
                $pdo->commit();
                $_SESSION['success_message'] = "Payment of " . number_format($total_payment_processed, 2) . " successfully recorded for invoice(s): " . implode(", ", $processed_invoice_numbers) . ".";
                // Optional: logActivity($current_user_id, 'record_payment_batch', 'payments', null, "Recorded payment: {$total_payment_processed}");
                
                // Use output buffering to prevent header issues
                ob_start();
                header('Location: record_payment.php?customer_id=' . $customer_id_post); // Refresh page for same customer
                ob_end_flush();
                exit();
            } else {
                $pdo->rollBack(); // If no valid amounts were processed
                $error_message = "No valid payment amounts were entered. Payment not recorded.";
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "Error recording payment: " . $e->getMessage();
            error_log("Record Payment Processing Error: " . $e->getMessage());
        }
    }
} elseif (isset($_GET['customer_id'])) { // Handle GET request to pre-load invoices
    $selected_customer_id = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);
}

// If a customer is selected (either from GET or after POST error), fetch their outstanding invoices
if ($selected_customer_id) {
    try {
        $stmt_invoices = DatabaseConfig::executeQuery($pdo,
            "SELECT id, invoice_number, invoice_date, total_net_amount, total_paid, 
                    (total_net_amount - total_paid) AS balance_due, status 
             FROM invoices 
             WHERE customer_id = :customer_id 
             AND status IN ('Draft', 'Sent', 'Partially Paid', 'Overdue') 
             AND (total_net_amount - total_paid) > 0.005 -- Only show if balance > 0
             ORDER BY invoice_date ASC, id ASC",
            [':customer_id' => $selected_customer_id]
        );
        $invoices_for_payment = $stmt_invoices->fetchAll(PDO::FETCH_ASSOC);
        if (empty($invoices_for_payment) && $_SERVER['REQUEST_METHOD'] !== 'POST') { // Don't overwrite POST error
            $error_message = $error_message ?: "No outstanding invoices found for this customer.";
        }
    } catch (PDOException $e) {
        $error_message = "Error fetching invoices: " . $e->getMessage();
        error_log("Record Payment Invoice Fetch Error for customer {$selected_customer_id}: " . $e->getMessage());
    }
}

DatabaseConfig::closeConnection($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .table th, .table td { vertical-align: middle; }
        .amount-to-pay-input { max-width: 120px; text-align: right;}
    </style>
</head>
<body>
    <?php // include __DIR__ . '/../includes/nav.php'; // Your navigation bar ?>

    <div class="container mt-4">
        <h1>Record Customer Payment</h1>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="record_payment.php" id="paymentForm">
            <div class="card mb-3">
                <div class="card-header">Step 1: Select Customer & Payment Details</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Customer *</label>
                            <select class="form-select" id="customer_id" name="customer_id" required>
                                <option value="">-- Select Customer --</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" <?php echo ($selected_customer_id == $customer['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['name'] . ($customer['customer_code'] ? ' (' . $customer['customer_code'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="payment_date" class="form-label">Payment Date *</label>
                            <input type="text" class="form-control datepicker" id="payment_date" name="payment_date" value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                         <div class="col-md-3 mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">-- Select Method --</option>
                                <option value="Cash" <?php echo (($_POST['payment_method'] ?? '') == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                                <option value="Bank Transfer" <?php echo (($_POST['payment_method'] ?? '') == 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="Cheque" <?php echo (($_POST['payment_method'] ?? '') == 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                                <option value="Mobile Money" <?php echo (($_POST['payment_method'] ?? '') == 'Mobile Money') ? 'selected' : ''; ?>>Mobile Money</option>
                                <option value="Card" <?php echo (($_POST['payment_method'] ?? '') == 'Card') ? 'selected' : ''; ?>>Card Payment</option>
                                <option value="Other" <?php echo (($_POST['payment_method'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reference_number" class="form-label">Reference / Cheque No.</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" value="<?php echo htmlspecialchars($_POST['reference_number'] ?? ''); ?>">
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="payment_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="payment_notes" name="payment_notes" rows="1"><?php echo htmlspecialchars($_POST['payment_notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($selected_customer_id && !empty($invoices_for_payment)): ?>
            <div class="card mb-3">
                <div class="card-header">Step 2: Allocate Payment to Invoices</div>
                <div class="card-body">
                    <p class="text-muted small">Enter the amount to apply to each invoice. Only checked invoices with an amount greater than zero will be processed.</p>
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th style="width: 5%;"><input type="checkbox" id="checkAllInvoices" title="Check/Uncheck All"></th>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th class="text-end">Total Net</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Balance Due</th>
                                <th class="text-end" style="width: 15%;">Amount to Pay</th>
                            </tr>
                        </thead>
                        <tbody id="invoiceListForPayment">
                            <?php foreach ($invoices_for_payment as $inv): ?>
                                <tr>
                                    <td>
                                        <input class="form-check-input invoice-checkbox" type="checkbox" 
                                               name="selected_invoices[<?php echo $inv['id']; ?>]" 
                                               value="<?php echo $inv['id']; ?>" 
                                               data-balance="<?php echo htmlspecialchars($inv['balance_due']); ?>"
                                               <?php echo isset($_POST['items'][$inv['id']]) && $_POST['items'][$inv['id']] > 0 ? 'checked' : ''; ?>>
                                    </td>
                                    <td><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars(date('d M Y', strtotime($inv['invoice_date']))); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(number_format($inv['total_net_amount'], 2)); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(number_format($inv['total_paid'], 2)); ?></td>
                                    <td class="text-end fw-bold"><?php echo htmlspecialchars(number_format($inv['balance_due'], 2)); ?></td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm amount-to-pay-input" 
                                               name="items[<?php echo $inv['id']; ?>]" 
                                               min="0.01" 
                                               max="<?php echo htmlspecialchars($inv['balance_due']); ?>" 
                                               step="0.01"
                                               value="<?php echo htmlspecialchars($_POST['items'][$inv['id']] ?? ''); ?>"
                                               placeholder="0.00"
                                               <?php echo !(isset($_POST['items'][$inv['id']]) && $_POST['items'][$inv['id']] > 0) ? 'disabled' : ''; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-end fw-bold">Total Amount Being Applied:</td>
                                <td class="text-end fw-bold" id="totalAmountToApplyDisplay">0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-cash-coin"></i> Record Payment</button>
            </div>
            <?php elseif ($selected_customer_id && empty($invoices_for_payment) && $_SERVER['REQUEST_METHOD'] === 'GET'): ?>
                <div class="alert alert-info">No outstanding invoices found for the selected customer.</div>
            <?php endif; ?>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        $(document).ready(function() {
            $('#customer_id').select2({
                theme: "bootstrap-5",
                placeholder: $(this).data('placeholder'),
            }).on('change', function() {
                var customerId = $(this).val();
                if (customerId) {
                    // Reload page with customer_id to fetch their invoices
                    window.location.href = 'record_payment.php?customer_id=' + customerId;
                } else {
                     // Optionally clear invoice list if no customer selected
                    $('#invoiceListForPayment').html('');
                    $('#totalAmountToApplyDisplay').text('0.00');
                }
            });

            $(".datepicker").flatpickr({
                dateFormat: "Y-m-d",
                allowInput: true
            });

            function calculateTotalToApply() {
                let total = 0;
                $('.amount-to-pay-input:enabled').each(function() {
                    let amount = parseFloat($(this).val());
                    if (!isNaN(amount) && amount > 0) {
                        total += amount;
                    }
                });
                $('#totalAmountToApplyDisplay').text(total.toFixed(2));
            }

            // Enable/disable amount input based on checkbox
            $('#invoiceListForPayment').on('change', '.invoice-checkbox', function() {
                const amountInput = $(this).closest('tr').find('.amount-to-pay-input');
                if ($(this).is(':checked')) {
                    amountInput.prop('disabled', false);
                    // Optionally auto-fill with balance due or keep it manual
                    // amountInput.val($(this).data('balance')); 
                } else {
                    amountInput.prop('disabled', true).val('');
                }
                calculateTotalToApply();
            });

            // Recalculate total when amount is changed
            $('#invoiceListForPayment').on('input change', '.amount-to-pay-input', function() {
                const balance = parseFloat($(this).attr('max'));
                let enteredAmount = parseFloat($(this).val());

                if (isNaN(enteredAmount)) enteredAmount = 0;

                if (enteredAmount > balance) {
                    $(this).val(balance.toFixed(2)); // Correct to max balance
                    // Optionally show a small warning
                } else if (enteredAmount < 0) {
                     $(this).val('0.00');
                }
                
                // If amount is entered, ensure checkbox is checked
                if (enteredAmount > 0) {
                    $(this).closest('tr').find('.invoice-checkbox').prop('checked', true).trigger('change'); // Trigger change to ensure input stays enabled
                }


                calculateTotalToApply();
            });
            
            $('#checkAllInvoices').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.invoice-checkbox').prop('checked', isChecked).trigger('change');
            });


            // Initial calculation on page load (if form was submitted with errors and repopulated)
            calculateTotalToApply();
            
            // Ensure inputs are enabled for checked boxes on load
            $('.invoice-checkbox:checked').each(function(){
                $(this).closest('tr').find('.amount-to-pay-input').prop('disabled', false);
            });
        });
    </script>
</body>
</html>