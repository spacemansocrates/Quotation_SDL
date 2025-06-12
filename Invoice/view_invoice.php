
<!-- File: view_invoice.php -->
<?php
require_once 'classes/InvoiceManager.php';
require_once 'classes/PaymentManager.php';

$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) {
    header('Location: list_invoices.php');
    exit;
}

$invoiceManager = new InvoiceManager();
$paymentManager = new PaymentManager();

$invoice = $invoiceManager->getInvoiceById($invoice_id);
if (!$invoice) {
    header('Location: list_invoices.php?error=Invoice not found');
    exit;
}

$payments = $paymentManager->getPaymentsByInvoice($invoice_id);
?>

<div class="container mt-4">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Invoice <?= $_GET['success'] ?> successfully!</div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></h2>
        <div>
            <button class="btn btn-outline-primary" onclick="window.print()">Print</button>
            <a href="generate_pdf.php?type=invoice&id=<?= $invoice['id'] ?>" class="btn btn-outline-secondary">Download PDF</a>
            <a href="edit_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-primary">Edit</a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <h5>From:</h5>
            <p>
                <strong><?= htmlspecialchars($invoice['shop_details']['name']) ?></strong><br>
                <?= nl2br(htmlspecialchars($invoice['shop_details']['address'] ?? '')) ?>
            </p>
        </div>
        <div class="col-md-6">
            <h5>To:</h5>
            <p>
                <strong><?= htmlspecialchars($invoice['customer_name_override'] ?: $invoice['customer_details']['name']) ?></strong><br>
                <?= nl2br(htmlspecialchars($invoice['customer_address_override'] ?: $invoice['customer_details']['address'])) ?>
            </p>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <p><strong>Invoice Date:</strong> <?= date('M d, Y', strtotime($invoice['invoice_date'])) ?></p>
            <?php if ($invoice['due_date']): ?>
            <p><strong>Due Date:</strong> <?= date('M d, Y', strtotime($invoice['due_date'])) ?></p>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <p><strong>Status:</strong> 
                <span class="badge bg-<?= match($invoice['status']) {
                    'Draft' => 'secondary',
                    'Sent' => 'primary',
                    'Paid' => 'success',
                    'Partially Paid' => 'warning',
                    'Overdue' => 'danger',
                    default => 'light text-dark'
                } ?>"><?= $invoice['status'] ?></span>
            </p>
        </div>
    </div>
    
    <div class="table-responsive mb-4">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Rate</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoice['items'] as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['description']) ?></td>
                    <td><?= number_format($item['quantity'], 2) ?></td>
                    <td><?= htmlspecialchars($item['unit_of_measurement'] ?? '') ?></td>
                    <td>$<?= number_format($item['rate_per_unit'], 2) ?></td>
                    <td>$<?= number_format($item['total_amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end"><strong>Gross Total:</strong></td>
                    <td><strong>$<?= number_format($invoice['gross_total_amount'], 2) ?></strong></td>
                </tr>
                <?php if ($invoice['apply_ppda_levy'] && $invoice['ppda_levy_amount'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-end">PPDA Levy (<?= $invoice['ppda_levy_percentage'] ?>%):</td>
                    <td>$<?= number_format($invoice['ppda_levy_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="4" class="text-end">VAT (<?= $invoice['vat_percentage'] ?>%):</td>
                    <td>$<?= number_format($invoice['vat_amount'], 2) ?></td>
                </tr>
                <tr class="table-primary">
                    <td colspan="4" class="text-end"><strong>Total Net Amount:</strong></td>
                    <td><strong>$<?= number_format($invoice['total_net_amount'], 2) ?></strong></td>
                </tr>
                <tr>
                    <td colspan="4" class="text-end">Total Paid:</td>
                    <td>$<?= number_format($invoice['total_paid'], 2) ?></td>
                </tr>
                <tr class="<?= $invoice['balance_due'] > 0 ? 'table-warning' : 'table-success' ?>">
                    <td colspan="4" class="text-end"><strong>Balance Due:</strong></td>
                    <td><strong>$<?= number_format($invoice['balance_due'], 2) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <h5>Payments</h5>
            <?php if ($payments): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody id="paymentsTableBody">
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                <td>$<?= number_format($payment['amount_paid'], 2) ?></td>
                                <td><?= htmlspecialchars($payment['payment_method'] ?? '') ?></td>
                                <td><?= htmlspecialchars($payment['reference_number'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No payments recorded yet.</p>
            <?php endif; ?>
            
            <?php if ($invoice['balance_due'] > 0): ?>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">Record Payment</button>
            <?php endif; ?>
        </div>
        
        <div class="col-md-6">
            <?php if ($invoice['notes_general']): ?>
                <h5>Notes</h5>
                <p><?= nl2br(htmlspecialchars($invoice['notes_general'])) ?></p>
            <?php endif; ?>
            
            <?php if ($invoice['payment_terms']): ?>
                <h5>Payment Terms</h5>
                <p><?= htmlspecialchars($invoice['payment_terms']) ?></p>
            <?php endif; ?>
            
            <?php if ($invoice['delivery_period']): ?>
                <h5>Delivery Period</h5>
                <p><?= htmlspecialchars($invoice['delivery_period']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="list_invoices.php" class="btn btn-secondary">Back to Invoice List</a>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                    <input type="hidden" name="customer_id" value="<?= $invoice['customer_id'] ?>">
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount_paid" class="form-label">Amount Paid</label>
                        <input type="number" class="form-control" id="amount_paid" name="amount_paid" step="0.01" max="<?= $invoice['balance_due'] ?>" required>
                        <div class="form-text">Maximum: $<?= number_format($invoice['balance_due'], 2) ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value="">Select Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reference_number" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="reference_number" name="reference_number" placeholder="Transaction ID, Check Number, etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="payment_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('actions/record_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while recording the payment.');
    });
});
</script>
</body>
</html>