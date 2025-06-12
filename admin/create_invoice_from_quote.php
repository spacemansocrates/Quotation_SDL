<?php
session_start();
// Use your database connection file and the new helpers file
require_once __DIR__ . '/../includes/db_connect.php'; 
require_once 'helpers.php'; 

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to perform this action.");
}

$pdo = null; // Initialize pdo variable

// --- Part 1: Handle POST Request (Form Submission) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDatabaseConnection(); // Get PDO connection
        DatabaseConfig::beginTransaction($pdo); // Use your class to start a transaction

        // 1. Get data from POST
        $original_quotation_id = $_POST['original_quotation_id'];
        $customer_id = $_POST['customer_id'];
        $shop_id = $_POST['shop_id'];
        $invoice_date = $_POST['invoice_date'];
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $notes = $_POST['notes_general'];
        $payment_terms = $_POST['payment_terms'];
        $delivery_period = $_POST['delivery_period'];
        $apply_ppda = isset($_POST['apply_ppda_levy']) ? 1 : 0;
        
        $vat_percentage = $_POST['vat_percentage'];
        $ppda_percentage = $_POST['ppda_levy_percentage'];
        $items = $_POST['items'];

        // 2. Server-side Calculation (Never trust client-side math)
        $gross_total = 0;
        foreach ($items as $item) {
            $item_total = (float)$item['quantity'] * (float)$item['rate_per_unit'];
            $gross_total += $item_total;
        }

        $ppda_levy_amount = $apply_ppda ? ($gross_total * ($ppda_percentage / 100)) : 0;
        $amount_before_vat = $gross_total + $ppda_levy_amount;
        $vat_amount = $amount_before_vat * ($vat_percentage / 100);
        $total_net_amount = $amount_before_vat + $vat_amount;
        
        // 3. Generate a new Invoice Number using the helper function
        $invoice_number = generateInvoiceNumber($shop_id, $customer_id);

        // 4. Insert into `invoices` table
        $sql = "INSERT INTO invoices (invoice_number, quotation_id, shop_id, customer_id, invoice_date, due_date, notes_general, payment_terms, delivery_period, apply_ppda_levy, ppda_levy_percentage, vat_percentage, gross_total_amount, ppda_levy_amount, amount_before_vat, vat_amount, total_net_amount, status, created_by_user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?)";
        $params = [
            $invoice_number, $original_quotation_id, $shop_id, $customer_id, $invoice_date, $due_date, $notes, $payment_terms, $delivery_period, $apply_ppda, $ppda_percentage, $vat_percentage, $gross_total, $ppda_levy_amount, $amount_before_vat, $vat_amount, $total_net_amount, $_SESSION['user_id']
        ];
        DatabaseConfig::executeQuery($pdo, $sql, $params);

        $new_invoice_id = $pdo->lastInsertId();

        // 5. Insert into `invoice_items` table
        $itemSql = "INSERT INTO invoice_items (invoice_id, product_id, description, quantity, unit_of_measurement, rate_per_unit, total_amount, created_by_user_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        foreach ($items as $item) {
            $product_id = !empty($item['product_id']) ? $item['product_id'] : null;
            $item_total = (float)$item['quantity'] * (float)$item['rate_per_unit'];
            $item_params = [
                $new_invoice_id, $product_id, $item['description'], $item['quantity'], $item['unit_of_measurement'], $item['rate_per_unit'], $item_total, $_SESSION['user_id']
            ];
            DatabaseConfig::executeQuery($pdo, $itemSql, $item_params);
        }

        // 6. Update the original quotation to link it to the new invoice
        $updateQuoteSql = "UPDATE quotations SET generated_invoice_id = ?, status = 'Invoiced' WHERE id = ?";
        DatabaseConfig::executeQuery($pdo, $updateQuoteSql, [$new_invoice_id, $original_quotation_id]);

        DatabaseConfig::commitTransaction($pdo); // Commit the changes
        
        header("Location: view_invoice.php?id=" . $new_invoice_id . "&status=created");
        exit();

    } catch (Exception $e) {
        if ($pdo) {
            DatabaseConfig::rollbackTransaction($pdo); // Rollback on error
        }
        // Use your logging mechanism from the DB class or just die
        error_log("Invoice Creation Failed: " . $e->getMessage());
        die("Error creating invoice. The operation has been cancelled. Please check the logs or contact support. Error: " . $e->getMessage());
    }
}


// --- Part 2: Handle GET Request (Display Form) ---
try {
    if (!isset($_GET['quote_id'])) {
        die("No quotation specified.");
    }
    $quote_id = (int)$_GET['quote_id'];

    $pdo = getDatabaseConnection();

    // Fetch quotation details
    $sql = "SELECT q.*, c.name as customer_name, s.name as shop_name 
            FROM quotations q 
            JOIN customers c ON q.customer_id = c.id
            JOIN shops s ON q.shop_id = s.id
            WHERE q.id = ?";
    $stmt = DatabaseConfig::executeQuery($pdo, $sql, [$quote_id]);
    $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quotation) {
        die("Quotation not found.");
    }
    if ($quotation['status'] !== 'Approved') {
        die("This quotation is not approved and cannot be converted to an invoice.");
    }
    if (!is_null($quotation['generated_invoice_id'])) {
        die("An invoice has already been generated for this quotation. <a href='view_invoice.php?id={$quotation['generated_invoice_id']}'>View Invoice</a>");
    }

    // Fetch quotation items
    $itemsSql = "SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY item_number ASC";
    $itemsStmt = DatabaseConfig::executeQuery($pdo, $itemsSql, [$quote_id]);
    $quote_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Form Population Failed: " . $e->getMessage());
    die("Error loading quotation data. Please check the logs. Error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Invoice from Quotation</title>
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        /* Apply Inter font to text elements while preserving number formatting */
        body, 
        h1, h2, h3, h4, h5, h6,
        p, span, div,
        label,
        .btn,
        .form-control::placeholder,
        .card-body,
        .container {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Keep default monospace/system fonts for number inputs and numeric content */
        input[type="number"],
        input[type="date"],
        .numeric-field {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, monospace;
            font-variant-numeric: tabular-nums;
        }
        
        /* Ensure text inputs use Inter */
        input[type="text"],
        textarea,
        select {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Custom styling improvements with Inter font */
        .item-row { 
            margin-bottom: 15px; 
            border: 1px solid #e9ecef;
            border-radius: 8px;
            transition: box-shadow 0.2s ease;
        }
        
        .item-row:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        h2 {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1.5rem;
        }
        
        h4 {
            font-weight: 500;
            color: #34495e;
            margin-bottom: 1rem;
        }
        
        .form-group label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .btn {
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
        }
        
        .form-control {
            border-radius: 6px;
            border: 1px solid #ced4da;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .card {
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        /* Preserve numeric styling for amounts and percentages */
        .percentage-display,
        .amount-display {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2>Create Invoice from Quotation #<?php echo htmlspecialchars($quotation['quotation_number']); ?></h2>
    <p>
        <strong>Customer:</strong> <?php echo htmlspecialchars($quotation['customer_name']); ?><br>
        <strong>Shop:</strong> <?php echo htmlspecialchars($quotation['shop_name']); ?>
    </p>

    <!-- The HTML form is identical to the previous version -->
    <form action="create_invoice_from_quote.php" method="POST" id="invoice-form">
        <!-- Hidden fields to pass essential data -->
        <input type="hidden" name="original_quotation_id" value="<?php echo $quotation['id']; ?>">
        <input type="hidden" name="customer_id" value="<?php echo $quotation['customer_id']; ?>">
        <input type="hidden" name="shop_id" value="<?php echo $quotation['shop_id']; ?>">

        <div class="row">
            <div class="form-group col-md-6">
                <label for="invoice_date">Invoice Date</label>
                <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group col-md-6">
                <label for="due_date">Due Date</label>
                <input type="date" class="form-control" id="due_date" name="due_date">
            </div>
        </div>
        
        <hr>
        <h4>Items</h4>
        <div id="invoice-items-container">
            <?php foreach ($quote_items as $index => $item): ?>
            <div class="card item-row">
                <div class="card-body">
                   <input type="hidden" name="items[<?php echo $index; ?>][product_id]" value="<?php echo htmlspecialchars($item['product_id'] ?? ''); ?>">
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="items[<?php echo $index; ?>][description]" class="form-control" rows="2" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-3">
                            <label>Quantity</label>
                            <input type="number" step="0.01" name="items[<?php echo $index; ?>][quantity]" class="form-control numeric-field" value="<?php echo htmlspecialchars($item['quantity']); ?>" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Unit of M.</label>
                            <input type="text" name="items[<?php echo $index; ?>][unit_of_measurement]" class="form-control" value="<?php echo htmlspecialchars($item['unit_of_measurement']); ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Rate per Unit</label>
                            <input type="number" step="0.01" name="items[<?php echo $index; ?>][rate_per_unit]" class="form-control numeric-field" value="<?php echo htmlspecialchars($item['rate_per_unit']); ?>" required>
                        </div>
                        <div class="form-group col-md-3 d-flex align-items-end">
                             <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.item-row').remove()">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-primary mb-3" id="add-item-btn">Add New Item</button>
        <hr>

        <div class="row">
            <div class="form-group col-md-6">
                <label for="notes_general">General Notes</label>
                <textarea class="form-control" name="notes_general" id="notes_general"><?php echo htmlspecialchars($quotation['notes_general']); ?></textarea>
            </div>
            <div class="form-group col-md-6">
                <label for="payment_terms">Payment Terms</label>
                <input type="text" class="form-control" name="payment_terms" id="payment_terms" value="<?php echo htmlspecialchars($quotation['payment_terms']); ?>">
                <label for="delivery_period">Delivery Period</label>
                <input type="text" class="form-control" name="delivery_period" id="delivery_period" value="<?php echo htmlspecialchars($quotation['delivery_period']); ?>">
            </div>
        </div>
        
        <div class="row">
             <div class="form-group col-md-4">
                <label for="vat_percentage">VAT (%)</label>
                <input type="number" step="0.01" class="form-control numeric-field" name="vat_percentage" id="vat_percentage" value="<?php echo htmlspecialchars($quotation['vat_percentage']); ?>" required>
            </div>
            <div class="form-group col-md-4">
                <label for="ppda_levy_percentage">PPDA Levy (%)</label>
                <input type="number" step="0.01" class="form-control numeric-field" name="ppda_levy_percentage" id="ppda_levy_percentage" value="<?php echo htmlspecialchars($quotation['ppda_levy_percentage']); ?>">
            </div>
            <div class="form-group col-md-4 d-flex align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="apply_ppda_levy" id="apply_ppda_levy" <?php if($quotation['apply_ppda_levy']) echo 'checked'; ?>>
                    <label class="form-check-label" for="apply_ppda_levy">
                        Apply PPDA Levy
                    </label>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success btn-lg">Create Invoice</button>
    </form>
</div>

<script>
// JavaScript for adding/removing items is identical to the previous version
document.getElementById('add-item-btn').addEventListener('click', function() {
    const container = document.getElementById('invoice-items-container');
    const index = 'new' + container.querySelectorAll('.item-row').length; // More robust index
    const newItemRow = document.createElement('div');
    newItemRow.classList.add('card', 'item-row');
    newItemRow.innerHTML = `
        <div class="card-body">
            <input type="hidden" name="items[${index}][product_id]" value="">
            <div class="form-group">
                <label>Description</label>
                <textarea name="items[${index}][description]" class="form-control" rows="2" required></textarea>
            </div>
            <div class="row">
                <div class="form-group col-md-3">
                    <label>Quantity</label>
                    <input type="number" step="0.01" name="items[${index}][quantity]" class="form-control numeric-field" value="1" required>
                </div>
                <div class="form-group col-md-3">
                    <label>Unit of M.</label>
                    <input type="text" name="items[${index}][unit_of_measurement]" class="form-control" value="pcs">
                </div>
                <div class="form-group col-md-3">
                    <label>Rate per Unit</label>
                    <input type="number" step="0.01" name="items[${index}][rate_per_unit]" class="form-control numeric-field" value="0.00" required>
                </div>
                <div class="form-group col-md-3 d-flex align-items-end">
                     <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.item-row').remove()">Remove</button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItemRow);
});
</script>
</body>
</html>