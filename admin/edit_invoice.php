<?php
// edit_invoice.php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['user_role'])) {
    $_SESSION['error_message'] = "You must be logged in to access this page.";
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php'; // Adjust path as needed

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error_message = '';
$success_message = '';
$invoice = null;
$invoice_items_data = [];

$isAdmin = ($_SESSION['user_role'] === 'admin');
$current_user_id = $_SESSION['user_id'];

// Fetch lists for dropdowns
$customers = [];
$products = [];
$shops = [];
$units_of_measurement = []; // Can be fetched from the table or a predefined array

try {
    $pdo = getDatabaseConnection();
    $customers = DatabaseConfig::executeQuery($pdo, "SELECT id, customer_code, name FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $products = DatabaseConfig::executeQuery($pdo, "SELECT id, sku, name, description, default_unit_price, default_unit_of_measurement FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $shops = DatabaseConfig::executeQuery($pdo, "SELECT id, shop_code, name FROM shops ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $units_of_measurement = DatabaseConfig::executeQuery($pdo, "SELECT name FROM units_of_measurement ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    if ($invoice_id > 0) {
        $stmt = DatabaseConfig::executeQuery($pdo, 
            "SELECT i.*, oq.quotation_number as original_quotation_number 
             FROM invoices i 
             LEFT JOIN quotations oq ON i.quotation_id = oq.id
             WHERE i.id = :id", [':id' => $invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            $_SESSION['error_message'] = "Invoice not found.";
            header('Location: admin_invoices.php'); // Redirect to invoice list
            exit();
        }

        // --- CRITICAL EDIT PERMISSION CHECKS FOR INVOICES ---
        if ((float)$invoice['total_paid'] > 0 && !$isAdmin) { // Non-admins cannot edit if any payment exists
             $_SESSION['error_message'] = "Invoice #" . htmlspecialchars($invoice['invoice_number']) . " cannot be edited because payments have been recorded. Please reverse payments or contact an administrator.";
             header('Location: view_invoice.php?id=' . $invoice_id);
             exit();
        }
        if ($invoice['status'] !== 'Draft' && !$isAdmin) { // Non-admins can only edit 'Draft' invoices
            $_SESSION['error_message'] = "Invoice #" . htmlspecialchars($invoice['invoice_number']) . " cannot be edited. Its status is '" . $invoice['status'] . "', not 'Draft'.";
            header('Location: view_invoice.php?id=' . $invoice_id);
            exit();
        }
        // Admins might have more leeway, but caution is advised for non-draft/paid invoices.
        // For this script, we'll assume admins can edit 'Draft' invoices even if created by others,
        // but if total_paid > 0, even admins should be very careful or blocked by UI/different process.
        // For simplicity, let's say if an admin proceeds with editing a paid invoice, they know what they are doing (though risky).

        $stmt_items = DatabaseConfig::executeQuery($pdo, "SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY item_number ASC", [':invoice_id' => $invoice_id]);
        $invoice_items_data = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    } else {
         $_SESSION['error_message'] = "No Invoice ID specified for editing.";
         header('Location: admin_invoices.php');
         exit();
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Edit Invoice DB Error: " . $e->getMessage());
}

function sanitizeString($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invoice) {
    // --- BEGIN FORM PROCESSING ---
    // Re-check editability in case of direct POST or race conditions
    if ((float)$invoice['total_paid'] > 0 && !$isAdmin) {
        $_SESSION['error_message'] = "Submission blocked: Invoice #" . htmlspecialchars($invoice['invoice_number']) . " has payments recorded.";
        header('Location: view_invoice.php?id=' . $invoice_id);
        exit();
    }
     if ($invoice['status'] !== 'Draft' && !$isAdmin) {
        $_SESSION['error_message'] = "Submission blocked: Invoice #" . htmlspecialchars($invoice['invoice_number']) . " is not 'Draft'.";
        header('Location: view_invoice.php?id=' . $invoice_id);
        exit();
    }


    try {
        if (!$pdo) $pdo = getDatabaseConnection(); // Re-establish connection if closed
        $pdo->beginTransaction();

        $shop_id = filter_input(INPUT_POST, 'shop_id', FILTER_VALIDATE_INT);
        $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT) ?: null;
        $customer_name_override = sanitizeString($_POST['customer_name_override'] ?? null);
        $customer_address_override = sanitizeString($_POST['customer_address_override'] ?? null);
        $invoice_date = sanitizeString($_POST['invoice_date'] ?? '');
        $due_date = sanitizeString($_POST['due_date'] ?? null); // Due date can be optional
        $company_tpin = sanitizeString($_POST['company_tpin'] ?? $invoice['company_tpin']); // Use existing if not provided
        $notes_general = sanitizeString($_POST['notes_general'] ?? '');
        $delivery_period = sanitizeString($_POST['delivery_period'] ?? '');
        $payment_terms = sanitizeString($_POST['payment_terms'] ?? '');
        $apply_ppda_levy = isset($_POST['apply_ppda_levy']) ? 1 : 0;
        $ppda_levy_percentage = filter_input(INPUT_POST, 'ppda_levy_percentage', FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]]);
        $vat_percentage = filter_input(INPUT_POST, 'vat_percentage', FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]]);
        
        // Status: Be careful with status changes during edit. Usually limited.
        // For 'Draft' invoices, user might change it to 'Sent' or admin might change it.
        // If already 'Sent', changes might be restricted.
        $status = sanitizeString($_POST['status'] ?? $invoice['status']); // Default to current status

               // ... (previous POST handling code) ...

        // Server-side recalculation of totals
        $gross_total_amount_calc = 0;
        $posted_items = $_POST['items'] ?? [];
        foreach ($posted_items as $item) {
            $item_qty = filter_var($item['quantity'], FILTER_VALIDATE_FLOAT);
            $item_rate = filter_var($item['rate_per_unit'], FILTER_VALIDATE_FLOAT);
            if ($item_qty !== false && $item_rate !== false) {
                $gross_total_amount_calc += $item_qty * $item_rate;
            }
        }
        
        $ppda_levy_amount_calc = 0;
        if ($apply_ppda_levy && $ppda_levy_percentage > 0) {
            // PPDA calculated on Gross Total
            $ppda_levy_amount_calc = round(($gross_total_amount_calc * $ppda_levy_percentage) / 100, 2);
        }
        
        // VAT calculated on Gross Total
        $vat_amount_calc = round(($gross_total_amount_calc * $vat_percentage) / 100, 2);

        // Amount Before VAT (as per DB schema: Gross + PPDA Levy)
        // This is for storage/display, VAT itself is calculated on Gross Total.
        $amount_before_vat_calc = round($gross_total_amount_calc + $ppda_levy_amount_calc, 2);
        
        // Total Net Amount = Gross + PPDA Levy + VAT
        $total_net_amount_calc = round($gross_total_amount_calc + $ppda_levy_amount_calc + $vat_amount_calc, 2);
        // total_paid and balance_due are not changed here. balance_due is a generated column.

        $sql_update_invoice = "UPDATE invoices SET 
            shop_id = :shop_id, customer_id = :customer_id, 
            customer_name_override = :cust_name_ovr, customer_address_override = :cust_addr_ovr,
            invoice_date = :inv_date, due_date = :due_date, company_tpin = :c_tpin, 
            notes_general = :notes_g, delivery_period = :del_p, payment_terms = :pay_t, 
            apply_ppda_levy = :apply_ppda, ppda_levy_percentage = :ppda_perc, vat_percentage = :vat_perc, 
            gross_total_amount = :gross_total, 
            ppda_levy_amount = :ppda_amount,       -- Corrected from previous
            amount_before_vat = :pre_vat,          -- Corrected from previous
            vat_amount = :vat_amount,              -- Corrected from previous
            total_net_amount = :net_total,         -- Corrected from previous
            status = :status, 
            updated_by_user_id = :updater_id, updated_at = CURRENT_TIMESTAMP
            WHERE id = :invoice_id";
        
        $params_update_invoice = [
            // ... other params ...
            ':apply_ppda' => $apply_ppda_levy, 
            ':ppda_perc' => $ppda_levy_percentage, 
            ':vat_perc' => $vat_percentage,
            ':gross_total' => $gross_total_amount_calc,         // Sum of items
            ':ppda_amount' => $ppda_levy_amount_calc,       // Gross * PPDA %
            ':pre_vat' => $amount_before_vat_calc,          // Gross + PPDA Amount
            ':vat_amount' => $vat_amount_calc,              // Gross * VAT %
            ':net_total' => $total_net_amount_calc,         // Gross + PPDA Amount + VAT Amount
            // ... other params ...
            ':status' => $status,
            ':updater_id' => $current_user_id, 
            ':invoice_id' => $invoice_id
        ];
        DatabaseConfig::executeQuery($pdo, $sql_update_invoice, $params_update_invoice);

        // ... (rest of the POST handling code for items, commit, success message etc.) ...
        // Delete existing items
        DatabaseConfig::executeQuery($pdo, "DELETE FROM invoice_items WHERE invoice_id = :invoice_id", [':invoice_id' => $invoice_id]);

        // Insert new/updated items
        $sql_insert_item = "INSERT INTO invoice_items 
            (invoice_id, product_id, item_number, description, image_path_override, quantity, unit_of_measurement, rate_per_unit, created_by_user_id, updated_by_user_id) 
            VALUES (:inv_id, :pid, :item_num, :desc, :img_override, :qty, :uom, :rate, :creator_id, :updater_id)";
            // total_amount is a generated column for invoice_items

        foreach ($posted_items as $idx => $item_data) {
            $item_product_id = filter_var($item_data['product_id'], FILTER_VALIDATE_INT) ?: null;
            $item_description = sanitizeString($item_data['description'] ?? '');
            // If product selected and description is empty, try to use product's default description
            if (empty($item_description) && $item_product_id) {
                foreach($products as $p_lookup) {
                    if ($p_lookup['id'] == $item_product_id) {
                        $item_description = $p_lookup['description'];
                        break;
                    }
                }
            }
            $item_quantity = filter_var($item_data['quantity'], FILTER_VALIDATE_FLOAT);
            $item_uom = sanitizeString($item_data['unit_of_measurement'] ?? '');
            $item_rate = filter_var($item_data['rate_per_unit'], FILTER_VALIDATE_FLOAT);
            // total_amount is auto-calculated by DB

            if ($item_quantity !== false && $item_quantity > 0 && $item_rate !== false && $item_rate >= 0) {
                 DatabaseConfig::executeQuery($pdo, $sql_insert_item, [
                    ':inv_id' => $invoice_id,
                    ':pid' => $item_product_id,
                    ':item_num' => $idx + 1,
                    ':desc' => $item_description,
                    ':img_override' => $item_data['image_path_override'] ?? null, // Add field to form if you use this
                    ':qty' => $item_quantity,
                    ':uom' => $item_uom,
                    ':rate' => $item_rate,
                    ':creator_id' => $invoice['created_by_user_id'], 
                    ':updater_id' => $current_user_id
                ]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = "Invoice #" . htmlspecialchars($invoice['invoice_number']) . " updated successfully.";
        
        // Refresh data
        $stmt = DatabaseConfig::executeQuery($pdo, "SELECT i.*, oq.quotation_number as original_quotation_number 
            FROM invoices i 
            LEFT JOIN quotations oq ON i.quotation_id = oq.id
            WHERE i.id = :id", [':id' => $invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt_items = DatabaseConfig::executeQuery($pdo, "SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY item_number ASC", [':invoice_id' => $invoice_id]);
        $invoice_items_data = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        $success_message = $_SESSION['success_message']; 
        unset($_SESSION['success_message']);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_message = "Error updating invoice: " . $e->getMessage();
        error_log("Update Invoice Error: " . $e->getMessage());
    }
    // --- END FORM PROCESSING ---
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if(isset($_SESSION['success_message']) && !$success_message) { // If not set by POST processing
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($pdo) DatabaseConfig::closeConnection($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice - <?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .item-row .btn-danger { margin-top: 32px; } /* For alignment if labels are present */
        .select2-container--bootstrap-5 .select2-selection {min-height: calc(1.5em + .75rem + 2px);}
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {line-height: 1.5;}
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {height: calc(1.5em + .75rem);}
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; // Adjust path for nav include ?>

    <div class="container py-4">
        <h1>Edit Invoice: <?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($invoice): ?>
        <form method="POST" action="edit_invoice.php?id=<?php echo $invoice_id; ?>" id="invoiceForm">
            <div class="card mb-3">
                <div class="card-header">Invoice Details</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="invoice_number" class="form-label">Invoice Number</label>
                            <input type="text" class="form-control" id="invoice_number" name="invoice_number" value="<?php echo htmlspecialchars($invoice['invoice_number']); ?>" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="invoice_date" class="form-label">Invoice Date *</label>
                            <input type="text" class="form-control datepicker" id="invoice_date" name="invoice_date" value="<?php echo htmlspecialchars($invoice['invoice_date']); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="text" class="form-control datepicker" id="due_date" name="due_date" value="<?php echo htmlspecialchars($invoice['due_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="shop_id" class="form-label">Shop/Branch *</label>
                            <select class="form-select select2-basic" id="shop_id" name="shop_id" required>
                                <option value="">Select Shop</option>
                                <?php foreach ($shops as $shop_item): ?>
                                <option value="<?php echo $shop_item['id']; ?>" <?php echo ($invoice['shop_id'] == $shop_item['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($shop_item['name'] . ($shop_item['shop_code'] ? ' ('.$shop_item['shop_code'].')' : '')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                     <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="company_tpin" class="form-label">Company TPIN</label>
                            <input type="text" class="form-control" id="company_tpin" name="company_tpin" value="<?php echo htmlspecialchars($invoice['company_tpin']); ?>">
                        </div>
                         <div class="col-md-4 mb-3">
                            <label for="original_quotation" class="form-label">Original Quotation #</label>
                            <input type="text" class="form-control" id="original_quotation" value="<?php echo htmlspecialchars($invoice['original_quotation_number'] ?? 'N/A'); ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required <?php // if ($invoice['status'] !== 'Draft' && !$isAdmin) echo 'disabled'; ?>>
                                <option value="Draft" <?php echo ($invoice['status'] == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="Sent" <?php echo ($invoice['status'] == 'Sent') ? 'selected' : ''; ?>>Sent</option>
                                <?php if ($isAdmin): // Admins might have more control over status changes ?>
                                <option value="Paid" <?php echo ($invoice['status'] == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                <option value="Partially Paid" <?php echo ($invoice['status'] == 'Partially Paid') ? 'selected' : ''; ?>>Partially Paid</option>
                                <option value="Overdue" <?php echo ($invoice['status'] == 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                                <option value="Cancelled" <?php echo ($invoice['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                <?php else: // Non-admins might only be able to change from Draft to Sent ?>
                                    <?php if ($invoice['status'] !== 'Draft'): ?>
                                        <option value="<?php echo htmlspecialchars($invoice['status']);?>" selected><?php echo htmlspecialchars($invoice['status']);?></option>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </select>
                            <?php if ($invoice['status'] !== 'Draft' && !$isAdmin): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($invoice['status']); ?>">
                            <small class="form-text text-muted">Status cannot be changed for non-draft invoices by non-admins.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Customer Details</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Select Customer</label>
                            <select class="form-select select2-basic" id="customer_id" name="customer_id">
                                <option value="">-- Select Existing Customer --</option>
                                <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" 
                                        data-addr1="<?php echo htmlspecialchars($customer['address_line1'] ?? ''); ?>"
                                        <?php echo ($invoice['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['name'] . ($customer['customer_code'] ? ' ('.$customer['customer_code'].')' : '')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <p class="text-muted small">Or, override customer details below (if no customer selected, these will be used):</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_name_override" class="form-label">Customer Name Override</label>
                            <input type="text" class="form-control" id="customer_name_override" name="customer_name_override" value="<?php echo htmlspecialchars($invoice['customer_name_override'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="customer_address_override" class="form-label">Customer Address Override</label>
                           <textarea class="form-control" id="customer_address_override" name="customer_address_override" rows="3"><?php echo htmlspecialchars($invoice['customer_address_override'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Invoice Items</div>
                <div class="card-body">
                    <div id="invoiceItemsContainer">
                        <?php foreach ($invoice_items_data as $idx => $item): ?>
                        <div class="row item-row mb-2 align-items-start">
                            <div class="col-md-3 mb-2">
                                <label class="form-label small">Product</label>
                                <select class="form-select product-select select2-item" name="items[<?php echo $idx; ?>][product_id]">
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" 
                                            data-price="<?php echo htmlspecialchars($product['default_unit_price']); ?>" 
                                            data-uom="<?php echo htmlspecialchars($product['default_unit_of_measurement']); ?>"
                                            data-desc="<?php echo htmlspecialchars($product['description']); ?>"
                                            <?php echo ($item['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['name'] . ($product['sku'] ? ' ('.$product['sku'].')' : '')); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label small">Description Override *</label>
                                <textarea class="form-control item-description" name="items[<?php echo $idx; ?>][description]" rows="1" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label small">Qty *</label>
                                <input type="number" class="form-control item-quantity" name="items[<?php echo $idx; ?>][quantity]" value="<?php echo htmlspecialchars($item['quantity']); ?>" step="any" required>
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label small">UoM *</label>
                                <input type="text" class="form-control item-uom" name="items[<?php echo $idx; ?>][unit_of_measurement]" value="<?php echo htmlspecialchars($item['unit_of_measurement']); ?>" required>
                                <!-- Or use a select with $units_of_measurement -->
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label small">Rate *</label>
                                <input type="number" class="form-control item-rate" name="items[<?php echo $idx; ?>][rate_per_unit]" value="<?php echo htmlspecialchars($item['rate_per_unit']); ?>" step="any" required>
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label small">Total</label>
                                <input type="text" class="form-control item-total bg-light" readonly value="<?php echo htmlspecialchars(number_format($item['quantity'] * $item['rate_per_unit'], 2)); // total_amount is generated, this is for display ?>">
                            </div>
                            <div class="col-md-1 mb-2 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-danger removeItemBtn"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                         <?php if (empty($invoice_items_data)): // Add one empty row if no items ?>
                            <script> let noInitialItems = true; </script>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="addItemBtn" class="btn btn-outline-primary mt-2"><i class="bi bi-plus-circle"></i> Add Item</button>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header">Totals & Financials</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="gross_total_amount" class="form-label">Gross Total</label>
                            <input type="text" class="form-control bg-light" id="gross_total_amount" name="gross_total_amount_display" value="<?php echo htmlspecialchars(number_format($invoice['gross_total_amount'] ?? 0, 2)); ?>" readonly>
                        </div>
                        <div class="col-md-3 mb-3 form-check form-switch pt-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="apply_ppda_levy" name="apply_ppda_levy" <?php echo !empty($invoice['apply_ppda_levy']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="apply_ppda_levy">Apply PPDA Levy</label>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="ppda_levy_percentage" class="form-label">PPDA Levy %</label>
                            <input type="number" class="form-control" id="ppda_levy_percentage" name="ppda_levy_percentage" value="<?php echo htmlspecialchars($invoice['ppda_levy_percentage'] ?? '1.00'); ?>" step="0.01">
                        </div>
                         <div class="col-md-3 mb-3">
                            <label for="ppda_levy_amount" class="form-label">PPDA Levy Amount</label>
                            <input type="text" class="form-control bg-light" id="ppda_levy_amount" name="ppda_levy_amount_display" value="<?php echo htmlspecialchars(number_format($invoice['ppda_levy_amount'] ?? 0, 2)); ?>" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="amount_before_vat" class="form-label">Amount Before VAT</label>
                            <input type="text" class="form-control bg-light" id="amount_before_vat" name="amount_before_vat_display" value="<?php echo htmlspecialchars(number_format($invoice['amount_before_vat'] ?? 0, 2)); ?>" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="vat_percentage" class="form-label">VAT % *</label>
                            <input type="number" class="form-control" id="vat_percentage" name="vat_percentage" value="<?php echo htmlspecialchars($invoice['vat_percentage'] ?? '16.50'); ?>" step="0.01" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="vat_amount" class="form-label">VAT Amount</label>
                            <input type="text" class="form-control bg-light" id="vat_amount" name="vat_amount_display" value="<?php echo htmlspecialchars(number_format($invoice['vat_amount'] ?? 0, 2)); ?>" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="total_net_amount" class="form-label">Total Net Amount</label>
                            <input type="text" class="form-control bg-light fw-bold" id="total_net_amount" name="total_net_amount_display" value="<?php echo htmlspecialchars(number_format($invoice['total_net_amount'] ?? 0, 2)); ?>" readonly>
                        </div>
                    </div>
                    <div class="row mt-2">
                         <div class="col-md-3 mb-3">
                            <label for="total_paid" class="form-label">Total Paid</label>
                            <input type="text" class="form-control bg-light" id="total_paid" value="<?php echo htmlspecialchars(number_format($invoice['total_paid'] ?? 0, 2)); ?>" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="balance_due" class="form-label">Balance Due</label>
                            <input type="text" class="form-control bg-light fw-bold" id="balance_due" value="<?php echo htmlspecialchars(number_format($invoice['balance_due'] ?? 0, 2)); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Terms & Notes</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="payment_terms" class="form-label">Payment Terms</label>
                            <input type="text" class="form-control" id="payment_terms" name="payment_terms" value="<?php echo htmlspecialchars($invoice['payment_terms'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="delivery_period" class="form-label">Delivery Period</label>
                            <input type="text" class="form-control" id="delivery_period" name="delivery_period" value="<?php echo htmlspecialchars($invoice['delivery_period'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes_general" class="form-label">General Notes</label>
                        <textarea class="form-control" id="notes_general" name="notes_general" rows="3"><?php echo htmlspecialchars($invoice['notes_general'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="view_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" 
                    <?php if (((float)($invoice['total_paid'] ?? 0) > 0 || $invoice['status'] !== 'Draft') && !$isAdmin) echo 'disabled title="Cannot edit: Invoice is not Draft or has payments."'; ?>>
                    <i class="bi bi-save"></i> Save Changes
                </button>
            </div>
        </form>
        <?php else: ?>
            <p>Invoice could not be loaded for editing.</p>
            <a href="admin_invoices.php" class="btn btn-primary">Back to List</a>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const productsData = <?php echo json_encode($products); ?>;
        let itemIndex = <?php echo count($invoice_items_data); ?>; 

        function formatNumber(num) {
            return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function calculateItemTotal(itemRow) {
            const quantity = parseFloat(itemRow.querySelector('.item-quantity').value) || 0;
            const rate = parseFloat(itemRow.querySelector('.item-rate').value) || 0;
            const totalField = itemRow.querySelector('.item-total');
            const total = quantity * rate;
            totalField.value = formatNumber(total);
            calculateGrandTotals();
        }

             function calculateGrandTotals() {
            let grossTotal = 0;
            document.querySelectorAll('#invoiceItemsContainer .item-row').forEach(row => {
                const totalValue = row.querySelector('.item-total').value.replace(/,/g, '');
                grossTotal += parseFloat(totalValue) || 0;
            });
            document.getElementById('gross_total_amount').value = formatNumber(grossTotal);

            const applyPPDA = document.getElementById('apply_ppda_levy').checked;
            const ppdaPercentage = parseFloat(document.getElementById('ppda_levy_percentage').value) || 0;
            let ppdaAmount = 0;

            if (applyPPDA && ppdaPercentage > 0) {
                // PPDA calculated on Gross Total
                ppdaAmount = (grossTotal * ppdaPercentage) / 100;
            }
            document.getElementById('ppda_levy_amount').value = formatNumber(ppdaAmount);

            // Amount Before VAT (for display, as per DB schema: Gross + PPDA Levy)
            const amountBeforeVatDisplay = grossTotal + ppdaAmount;
            document.getElementById('amount_before_vat').value = formatNumber(amountBeforeVatDisplay);

            // VAT calculated on Gross Total
            const vatPercentage = parseFloat(document.getElementById('vat_percentage').value) || 0;
            const vatAmount = (grossTotal * vatPercentage) / 100; 
            document.getElementById('vat_amount').value = formatNumber(vatAmount);

            // Total Net Amount = Gross + PPDA Levy + VAT
            const totalNetAmount = grossTotal + ppdaAmount + vatAmount;
            document.getElementById('total_net_amount').value = formatNumber(totalNetAmount);

            // Optional: Update balance due preview if needed, though it's ultimately from DB
            // const totalPaid = parseFloat(document.getElementById('total_paid').value.replace(/,/g, '')) || 0;
            // document.getElementById('balance_due').value = formatNumber(totalNetAmount - totalPaid);
        }   
        function initializeSelect2(element) {
            $(element).select2({
                theme: "bootstrap-5",
                width: $(element).data('width') ? $(element).data('width') : $(element).hasClass('w-100') ? '100%' : 'style',
                placeholder: $(element).data('placeholder'),
            });
        }

        function addNewItemRow(prefillProduct = null) {
            const container = document.getElementById('invoiceItemsContainer');
            const newRow = document.createElement('div');
            newRow.classList.add('row', 'item-row', 'mb-2', 'align-items-start'); // align-items-start for better label alignment
            
            let productOptions = productsData.map(p => 
                `<option value="${p.id}" data-price="${p.default_unit_price || '0.00'}" data-uom="${p.default_unit_of_measurement || ''}" data-desc="${p.description || ''}">${p.name} ${p.sku ? '('+p.sku+')' : ''}</option>`
            ).join('');

            newRow.innerHTML = `
                <div class="col-md-3 mb-2">
                    <label class="form-label small visually-hidden">Product</label>
                    <select class="form-select product-select select2-item" name="items[${itemIndex}][product_id]">
                        <option value="">-- Select Product --</option>
                        ${productOptions}
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label small visually-hidden">Description Override</label>
                    <textarea class="form-control item-description" name="items[${itemIndex}][description]" rows="1" required></textarea>
                </div>
                <div class="col-md-1 mb-2">
                    <label class="form-label small visually-hidden">Qty</label>
                    <input type="number" class="form-control item-quantity" name="items[${itemIndex}][quantity]" value="1" step="any" required>
                </div>
                <div class="col-md-1 mb-2">
                    <label class="form-label small visually-hidden">UoM</label>
                    <input type="text" class="form-control item-uom" name="items[${itemIndex}][unit_of_measurement]" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label small visually-hidden">Rate</label>
                    <input type="number" class="form-control item-rate" name="items[${itemIndex}][rate_per_unit]" value="0.00" step="any" required>
                </div>
                <div class="col-md-1 mb-2">
                    <label class="form-label small visually-hidden">Total</label>
                    <input type="text" class="form-control item-total bg-light" readonly>
                </div>
                <div class="col-md-1 mb-2 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-danger removeItemBtn"><i class="bi bi-trash"></i></button>
                </div>
            `;
            container.appendChild(newRow);
            initializeSelect2(newRow.querySelector('.product-select'));
            attachItemEventListeners(newRow);
            
            if (prefillProduct) {
                const select = newRow.querySelector('.product-select');
                select.value = prefillProduct.id;
                $(select).trigger('change'); // Trigger change for Select2 and our listener
            } else {
                 calculateItemTotal(newRow); // Calculate for the new empty row
            }
            itemIndex++;
        }

        function attachItemEventListeners(row) {
            row.querySelector('.item-quantity').addEventListener('input', () => calculateItemTotal(row));
            row.querySelector('.item-rate').addEventListener('input', () => calculateItemTotal(row));
            row.querySelector('.removeItemBtn').addEventListener('click', () => {
                $(row.querySelector('.product-select')).select2('destroy'); // Destroy select2 before removing
                row.remove();
                calculateGrandTotals();
            });
            const productSelect = row.querySelector('.product-select');
            if (productSelect) {
                $(productSelect).on('change', function() { // Use jQuery change for select2
                    const selectedOption = this.options[this.selectedIndex];
                    const price = selectedOption.dataset.price || '0.00';
                    const uom = selectedOption.dataset.uom || '';
                    const desc = selectedOption.dataset.desc || '';
                    
                    row.querySelector('.item-rate').value = parseFloat(price).toFixed(2);
                    row.querySelector('.item-uom').value = uom;
                    const descField = row.querySelector('.item-description');
                    if (!descField.value.trim()) { // Only prefill description if it's empty
                        descField.value = desc;
                    }
                    calculateItemTotal(row);
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".datepicker", { dateFormat: "Y-m-d", allowInput: true });
            
            document.querySelectorAll('.select2-basic').forEach(el => initializeSelect2(el));
            document.querySelectorAll('.select2-item').forEach(el => initializeSelect2(el));


            document.querySelectorAll('#invoiceItemsContainer .item-row').forEach(attachItemEventListeners);
            document.getElementById('addItemBtn').addEventListener('click', () => addNewItemRow());

            ['apply_ppda_levy', 'ppda_levy_percentage', 'vat_percentage'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener(el.type === 'checkbox' ? 'change' : 'input', calculateGrandTotals);
                }
            });

            if (typeof noInitialItems !== 'undefined' && noInitialItems && document.querySelectorAll('#invoiceItemsContainer .item-row').length === 0) {
                addNewItemRow(); // Add one row if there were no items to start with
            } else if(document.querySelectorAll('#invoiceItemsContainer .item-row').length > 0) {
                calculateGrandTotals(); // Calculate grand totals on page load if items exist
            }

            $('#customer_id').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const name = selectedOption.text().split(' (')[0]; // Basic parse
                const address = selectedOption.data('addr1');

                if (this.value) { // If a customer is selected
                    $('#customer_name_override').val(name);
                    $('#customer_address_override').val(address);
                } else {
                    // Optionally clear overrides if no customer selected
                    // $('#customer_name_override').val('');
                    // $('#customer_address_override').val('');
                }
            });
        });
    </script>
</body>
</html>