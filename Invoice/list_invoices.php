<!-- File: list_invoices.php -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Invoices</h2>
        <a href="create_invoice.php" class="btn btn-primary">Create Invoice</a>
    </div>
    
    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-md-3">
            <input type="text" class="form-control" id="customerFilter" placeholder="Filter by customer...">
        </div>
        <div class="col-md-2">
            <select class="form-select" id="statusFilter">
                <option value="">All Statuses</option>
                <option value="Draft">Draft</option>
                <option value="Sent">Sent</option>
                <option value="Paid">Paid</option>
                <option value="Partially Paid">Partially Paid</option>
                <option value="Overdue">Overdue</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" class="form-control" id="fromDate">
        </div>
        <div class="col-md-2">
            <input type="date" class="form-control" id="toDate">
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-primary" id="filterBtn">Filter</button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th>Shop</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="invoiceTableBody">
                <?php
                require_once 'classes/InvoiceManager.php';
                $invoiceManager = new InvoiceManager();
                $invoices = $invoiceManager->listInvoices();
                
                foreach ($invoices as $invoice):
                    $status_class = match($invoice['status']) {
                        'Draft' => 'badge bg-secondary',
                        'Sent' => 'badge bg-primary',
                        'Paid' => 'badge bg-success',
                        'Partially Paid' => 'badge bg-warning',
                        'Overdue' => 'badge bg-danger',
                        default => 'badge bg-light text-dark'
                    };
                ?>
                    <tr>
                        <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                        <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                        <td><?= htmlspecialchars($invoice['shop_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($invoice['invoice_date'])) ?></td>
                        <td>$<?= number_format($invoice['total_net_amount'], 2) ?></td>
                        <td>$<?= number_format($invoice['total_paid'], 2) ?></td>
                        <td>$<?= number_format($invoice['balance_due'], 2) ?></td>
                        <td><span class="<?= $status_class ?>"><?= $invoice['status'] ?></span></td>
                        <td>
                            <a href="view_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="edit_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/invoice_list.js"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>