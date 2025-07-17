<?php
// edit_invoice.php
session_start();

// Using a universal auth_check.php is a better practice, but for now this works.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['user_role'])) {
    $_SESSION['error_message'] = "You must be logged in to access this page.";
    header('Location: ../index.php'); // Go to the universal login page
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
$units_of_measurement = [];

// Use a single try-catch block for initial data fetching
try {
    $pdo = DatabaseConfig::getConnection();
    $customers = DatabaseConfig::executeQuery($pdo, "SELECT id, customer_code, name, address_line1 FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
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
            header('Location: admin_invoices.php');
            exit();
        }

        // Permission checks
        if ((float)($invoice['total_paid'] ?? 0) > 0 && !$isAdmin) {
             $_SESSION['error_message'] = "Invoice #" . htmlspecialchars($invoice['invoice_number']) . " cannot be edited because payments have been recorded.";
             header('Location: view_invoice.php?id=' . $invoice_id);
             exit();
        }
        if (($invoice['status'] ?? 'Draft') !== 'Draft' && !$isAdmin) {
            $_SESSION['error_message'] = "Invoice #" . htmlspecialchars($invoice['invoice_number']) . " cannot be edited. Its status is '" . $invoice['status'] . "', not 'Draft'.";
            header('Location: view_invoice.php?id=' . $invoice_id);
            exit();
        }

        $stmt_items = DatabaseConfig::executeQuery($pdo, "SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY item_number ASC", [':invoice_id' => $invoice_id]);
        $invoice_items_data = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    } else {
         $_SESSION['error_message'] = "No Invoice ID specified for editing.";
         header('Location: admin_invoices.php');
         exit();
    }

} catch (PDOException $e) {
    $error_message = "Database error during page load: " . $e->getMessage();
    error_log("Edit Invoice Page Load DB Error: " . $e->getMessage());
}

// Improved sanitizeString function
function sanitizeString($input) {
    return htmlspecialchars(strip_tags(trim($input ?? '')), ENT_QUOTES, 'UTF-8');
}

// --- FORM PROCESSING BLOCK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invoice) {
    // Re-check editability as a final security measure
    if (((float)($invoice['total_paid'] ?? 0) > 0 || ($invoice['status'] ?? 'Draft') !== 'Draft') && !$isAdmin) {
        $_SESSION['error_message'] = "Submission blocked: Invoice is not editable.";
        header('Location: view_invoice.php?id=' . $invoice_id);
        exit();
    }

    try {
        if (!$pdo || !$pdo->inTransaction()) {
            $pdo = DatabaseConfig::getConnection();
        }
        $pdo->beginTransaction();

        // 1. Define LPO variables correctly from POST data
        $lpo_number = sanitizeString($_POST['lpo_number']);
        $lpo_document_path = $invoice['lpo_document_path'] ?? null; // Start with the existing path

        // 2. Handle LPO File Deletion
        if (!empty($_POST['delete_lpo_document']) && $_POST['delete_lpo_document'] == '1') {
            if ($lpo_document_path && file_exists(__DIR__ . '/../' . $lpo_document_path)) {
                unlink(__DIR__ . '/../' . $lpo_document_path);
            }
            $lpo_document_path = null; // Clear the path
        }

        // 3. Handle LPO File Upload
        if (isset($_FILES['lpo_document']) && $_FILES['lpo_document']['error'] === UPLOAD_ERR_OK) {
            // First, delete the old file if it exists to replace it
            if ($lpo_document_path && file_exists(__DIR__ . '/../' . $lpo_document_path)) {
                unlink(__DIR__ . '/../' . $lpo_document_path);
            }
            $upload_dir = 'uploads/lpo/';
            if (!is_dir(__DIR__ . '/../' . $upload_dir)) {
                mkdir(__DIR__ . '/../' . $upload_dir, 0755, true);
            }
            $file_ext = pathinfo($_FILES['lpo_document']['name'], PATHINFO_EXTENSION);
            $safe_filename = "lpo_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoice['invoice_number']) . "_" . time() . "." . $file_ext;
            $destination = __DIR__ . '/../' . $upload_dir . $safe_filename;
            if (move_uploaded_file($_FILES['lpo_document']['tmp_name'], $destination)) {
                $lpo_document_path = $upload_dir . $safe_filename;
            } else {
                throw new Exception("Failed to move uploaded LPO file.");
            }
        }
        
        // 4. Define all other variables from POST
        $shop_id = filter_input(INPUT_POST, 'shop_id', FILTER_VALIDATE_INT);
        $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT) ?: null;
        $customer_name_override = sanitizeString($_POST['customer_name_override']);
        $customer_address_override = sanitizeString($_POST['customer_address_override']);
        $invoice_date = sanitizeString($_POST['invoice_date']);
        $due_date = sanitizeString($_POST['due_date']) ?: null;
        $company_tpin = sanitizeString($_POST['company_tpin']);
        $notes_general = sanitizeString($_POST['notes_general']);
        $delivery_period = sanitizeString($_POST['delivery_period']);
        $payment_terms = sanitizeString($_POST['payment_terms']);
        $apply_ppda_levy = isset($_POST['apply_ppda_levy']) ? 1 : 0;
        $ppda_levy_percentage = filter_input(INPUT_POST, 'ppda_levy_percentage', FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]]);
        $vat_percentage = filter_input(INPUT_POST, 'vat_percentage', FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]]);
        $status = sanitizeString($_POST['status']);

        // 5. Server-side recalculation of totals
        $gross_total_amount_calc = 0;
        $posted_items = $_POST['items'] ?? [];
        foreach ($posted_items as $item) {
            $gross_total_amount_calc += (float)($item['quantity'] ?? 0) * (float)($item['rate_per_unit'] ?? 0);
        }
        $ppda_levy_amount_calc = ($apply_ppda_levy && $ppda_levy_percentage > 0) ? round(($gross_total_amount_calc * $ppda_levy_percentage) / 100, 2) : 0;
        $vat_amount_calc = round(($gross_total_amount_calc * $vat_percentage) / 100, 2);
        $amount_before_vat_calc = $gross_total_amount_calc + $ppda_levy_amount_calc;
        $total_net_amount_calc = $gross_total_amount_calc + $ppda_levy_amount_calc + $vat_amount_calc;

        // 6. Update the main invoice record
        $sql_update_invoice = "UPDATE invoices SET
            shop_id = :shop_id, customer_id = :customer_id,
            customer_name_override = :cust_name_ovr, customer_address_override = :cust_addr_ovr,
            lpo_number = :lpo_number, lpo_document_path = :lpo_path,
            invoice_date = :inv_date, due_date = :due_date, company_tpin = :c_tpin,
            notes_general = :notes_g, delivery_period = :del_p, payment_terms = :pay_t,
            apply_ppda_levy = :apply_ppda, ppda_levy_percentage = :ppda_perc, vat_percentage = :vat_perc,
            gross_total_amount = :gross_total, ppda_levy_amount = :ppda_amount,
            amount_before_vat = :pre_vat, vat_amount = :vat_amount, total_net_amount = :net_total,
            status = :status, updated_by_user_id = :updater_id, updated_at = CURRENT_TIMESTAMP
            WHERE id = :invoice_id";
        
        $params_update_invoice = [
            ':shop_id' => $shop_id, ':customer_id' => $customer_id,
            ':cust_name_ovr' => $customer_name_override, ':cust_addr_ovr' => $customer_address_override,
            ':lpo_number' => $lpo_number, ':lpo_path' => $lpo_document_path,
            ':inv_date' => $invoice_date, ':due_date' => $due_date, ':c_tpin' => $company_tpin,
            ':notes_g' => $notes_general, ':del_p' => $delivery_period, ':pay_t' => $payment_terms,
            ':apply_ppda' => $apply_ppda_levy, ':ppda_perc' => $ppda_levy_percentage, ':vat_perc' => $vat_percentage,
            ':gross_total' => $gross_total_amount_calc, ':ppda_amount' => $ppda_levy_amount_calc,
            ':pre_vat' => $amount_before_vat_calc, ':vat_amount' => $vat_amount_calc, ':net_total' => $total_net_amount_calc,
            ':status' => $status, ':updater_id' => $current_user_id, ':invoice_id' => $invoice_id
        ];
        DatabaseConfig::executeQuery($pdo, $sql_update_invoice, $params_update_invoice);

        // 7. Delete existing items and re-insert them
        DatabaseConfig::executeQuery($pdo, "DELETE FROM invoice_items WHERE invoice_id = :invoice_id", [':invoice_id' => $invoice_id]);
        $sql_insert_item = "INSERT INTO invoice_items
            (invoice_id, product_id, item_number, description, image_path_override, quantity, unit_of_measurement, rate_per_unit, total_amount, created_by_user_id, updated_by_user_id)
            VALUES (:inv_id, :pid, :item_num, :desc, :img_override, :qty, :uom, :rate, :total_amount, :creator_id, :updater_id)";
        foreach ($posted_items as $idx => $item_data) {
            $item_quantity = filter_var($item_data['quantity'] ?? 0, FILTER_VALIDATE_FLOAT);
            $item_rate = filter_var($item_data['rate_per_unit'] ?? 0, FILTER_VALIDATE_FLOAT);
            if ($item_quantity > 0 || $item_rate > 0) { // Only insert if there's data
                 DatabaseConfig::executeQuery($pdo, $sql_insert_item, [
                    ':inv_id' => $invoice_id,
                    ':pid' => filter_var($item_data['product_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                    ':item_num' => $idx + 1,
                    ':desc' => sanitizeString($item_data['description']),
                    ':img_override' => null, // Not handled in this form, set to null
                    ':qty' => $item_quantity,
                    ':uom' => sanitizeString($item_data['unit_of_measurement']),
                    ':rate' => $item_rate,
                    ':total_amount' => $item_quantity * $item_rate,
                    ':creator_id' => $invoice['created_by_user_id'],
                    ':updater_id' => $current_user_id
                ]);
            }
        }
        
        $pdo->commit();
        
        // 8. Redirect to prevent form resubmission
        $_SESSION['success_message'] = "Invoice #" . htmlspecialchars($invoice['invoice_number']) . " updated successfully.";
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $invoice_id);
        exit();

    } catch (Exception $e) { // Catch both PDOException and generic Exception
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Error updating invoice: " . $e->getMessage();
        error_log("Update Invoice Error: " . $e->getMessage());
    }
}

// Session message handling
if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Close DB connection if it was opened
if (isset($pdo)) {
    DatabaseConfig::closeConnection($pdo);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice - <?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></title>
    <!-- Your CSS Includes... -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .item-row .btn-danger { margin-top: 32px; }
        .select2-container--bootstrap-5 .select2-selection {min-height: calc(1.5em + .75rem + 2px);}
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {line-height: 1.5;}
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {height: calc(1.5em + .75rem);}
    </style>
</head>
<body>
    <?php // require_once __DIR__ . '/../includes/nav.php'; ?>

    <div class="container py-4">
        <h1>Edit Invoice: <?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($invoice): ?>
        <!-- ADDED enctype for file uploads -->
        <form method="POST" action="edit_invoice.php?id=<?php echo $invoice_id; ?>" id="invoiceForm" enctype="multipart/form-data">
            <div class="card mb-3">
                <div class="card-header">Invoice Details</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="invoice_number" class="form-label">Invoice Number</label>
                            <input type="text" class="form-control" id="invoice_number" name="invoice_number" value="<?php echo htmlspecialchars($invoice['invoice_number'] ?? ''); ?>" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="invoice_date" class="form-label">Invoice Date *</label>
                            <input type="text" class="form-control datepicker" id="invoice_date" name="invoice_date" value="<?php echo htmlspecialchars($invoice['invoice_date'] ?? ''); ?>" required>
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
                                <option value="<?php echo $shop_item['id']; ?>" <?php echo (($invoice['shop_id'] ?? '') == $shop_item['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($shop_item['name'] . ($shop_item['shop_code'] ? ' ('.$shop_item['shop_code'].')' : '')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                     <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="company_tpin" class="form-label">Company TPIN</label>
                            <input type="text" class="form-control" id="company_tpin" name="company_tpin" value="<?php echo htmlspecialchars($invoice['company_tpin'] ?? ''); ?>">
                        </div>
                         <div class="col-md-4 mb-3">
                            <label for="original_quotation" class="form-label">Original Quotation #</label>
                            <input type="text" class="form-control" id="original_quotation" value="<?php echo htmlspecialchars($invoice['original_quotation_number'] ?? 'N/A'); ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required <?php if (($invoice['status'] ?? 'Draft') !== 'Draft' && !$isAdmin) echo 'disabled'; ?>>
                                <option value="Draft" <?php echo (($invoice['status'] ?? '') == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="Sent" <?php echo (($invoice['status'] ?? '') == 'Sent') ? 'selected' : ''; ?>>Sent</option>
                                <?php if ($isAdmin): ?>
                                <option value="Paid" <?php echo (($invoice['status'] ?? '') == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                <option value="Partially Paid" <?php echo (($invoice['status'] ?? '') == 'Partially Paid') ? 'selected' : ''; ?>>Partially Paid</option>
                                <option value="Overdue" <?php echo (($invoice['status'] ?? '') == 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                                <option value="Cancelled" <?php echo (($invoice['status'] ?? '') == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                <?php else: ?>
                                    <?php if (($invoice['status'] ?? '') !== 'Draft'): ?>
                                        <option value="<?php echo htmlspecialchars($invoice['status']);?>" selected><?php echo htmlspecialchars($invoice['status']);?></option>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </select>
                            <?php if (($invoice['status'] ?? 'Draft') !== 'Draft' && !$isAdmin): ?>
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
                                        <?php echo (($invoice['customer_id'] ?? '') == $customer['id']) ? 'selected' : ''; ?>>
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
                    <hr>
                    <h5>LPO Details (Optional)</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="lpo_number" class="form-label">Customer LPO Number</label>
                            <input type="text" class="form-control" id="lpo_number" name="lpo_number" value="<?php echo htmlspecialchars($invoice['lpo_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lpo_document" class="form-label">LPO Document</label>
                            <?php if (!empty($invoice['lpo_document_path'])): ?>
                                <div class="mb-2" id="current-lpo-section">
                                    Current:
                                    <a href="/Quotation_SDL/<?php echo htmlspecialchars($invoice['lpo_document_path']); ?>" target="_blank">
                                        <?php echo basename($invoice['lpo_document_path']); ?>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="delete-lpo-btn">Delete</button>
                                    <span id="lpo-delete-marker" class="text-danger small" style="display:none;">(Will be deleted on save)</span>
                                </div>
                                <input type="hidden" name="delete_lpo_document" id="delete-lpo-input" value="0">
                            <?php endif; ?>
                            <input class="form-control" type="file" id="lpo_document" name="lpo_document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <small class="form-text text-muted">Uploading a new file will replace the current one.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Items Section -->
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
                                            data-price="<?php echo htmlspecialchars($product['default_unit_price'] ?? '0.00'); ?>"
                                            data-uom="<?php echo htmlspecialchars($product['default_unit_of_measurement'] ?? ''); ?>"
                                            data-desc="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
                                            <?php echo (($item['product_id'] ?? '') == $product['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['name'] . ($product['sku'] ? ' ('.$product['sku'].')' : '')); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label small">Description Override *</label>
                                <textarea class="form-control item-description" name="items[<?php echo $idx; ?>][description]" rows="1" required><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label small">Qty *</label>
                                <input type="number" class="form-control item-quantity" name="items[<?php echo $idx; ?>][quantity]" value="<?php echo htmlspecialchars($item['quantity'] ?? '1'); ?>" step="any" required>
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label small">UoM *</label>
                                <input type="text" class="form-control item-uom" name="items[<?php echo $idx; ?>][unit_of_measurement]" value="<?php echo htmlspecialchars($item['unit_of_measurement'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label small">Rate *</label>
                                <input type="number" class="form-control item-rate" name="items[<?php echo $idx; ?>][rate_per_unit]" value="<?php echo htmlspecialchars($item['rate_per_unit'] ?? '0.00'); ?>" step="any" required>
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label small">Total</label>
                                <input type="text" class="form-control item-total bg-light" readonly value="<?php echo htmlspecialchars(number_format(($item['quantity'] ?? 0) * ($item['rate_per_unit'] ?? 0), 2)); ?>">
                            </div>
                            <div class="col-md-1 mb-2 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-danger removeItemBtn"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                         <?php if (empty($invoice_items_data)): ?>
                            <script> let noInitialItems = true; </script>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="addItemBtn" class="btn btn-outline-primary mt-2"><i class="bi bi-plus-circle"></i> Add Item</button>
                </div>
            </div>

            <!-- Totals & Financials Section -->
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
                    <?php if (((float)($invoice['total_paid'] ?? 0) > 0 || ($invoice['status'] ?? 'Draft') !== 'Draft') && !$isAdmin) echo 'disabled title="Cannot edit: Invoice is not Draft or has payments."'; ?>>
                    <i class="bi bi-save"></i> Save Changes
                </button>
            </div>
        </form>
        <?php else: ?>
            <p>Invoice could not be loaded for editing.</p>
            <a href="admin_invoices.php" class="btn btn-primary">Back to List</a>
        <?php endif; ?>
    </div>

    <!-- JS Includes and script... -->
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
                const totalValue = (row.querySelector('.item-total').value || '0').replace(/,/g, '');
                grossTotal += parseFloat(totalValue) || 0;
            });
            document.getElementById('gross_total_amount').value = formatNumber(grossTotal);

            const applyPPDA = document.getElementById('apply_ppda_levy').checked;
            const ppdaPercentage = parseFloat(document.getElementById('ppda_levy_percentage').value) || 0;
            let ppdaAmount = 0;

            if (applyPPDA && ppdaPercentage > 0) {
                ppdaAmount = (grossTotal * ppdaPercentage) / 100;
            }
            document.getElementById('ppda_levy_amount').value = formatNumber(ppdaAmount);

            const amountBeforeVatDisplay = grossTotal + ppdaAmount;
            document.getElementById('amount_before_vat').value = formatNumber(amountBeforeVatDisplay);

            const vatPercentage = parseFloat(document.getElementById('vat_percentage').value) || 0;
            // Correct VAT calculation should be on the gross total, not amount before VAT
            const vatAmount = (grossTotal * vatPercentage) / 100;
            document.getElementById('vat_amount').value = formatNumber(vatAmount);

            const totalNetAmount = grossTotal + ppdaAmount + vatAmount;
            document.getElementById('total_net_amount').value = formatNumber(totalNetAmount);
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
            newRow.classList.add('row', 'item-row', 'mb-2', 'align-items-start');

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
                $(select).trigger('change');
            } else {
                 calculateItemTotal(newRow);
            }
            itemIndex++;
        }

        function attachItemEventListeners(row) {
            row.querySelector('.item-quantity').addEventListener('input', () => calculateItemTotal(row));
            row.querySelector('.item-rate').addEventListener('input', () => calculateItemTotal(row));
            row.querySelector('.removeItemBtn').addEventListener('click', () => {
                $(row.querySelector('.product-select')).select2('destroy');
                row.remove();
                calculateGrandTotals();
            });
            const productSelect = row.querySelector('.product-select');
            if (productSelect) {
                $(productSelect).on('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const price = selectedOption.dataset.price || '0.00';
                    const uom = selectedOption.dataset.uom || '';
                    const desc = selectedOption.dataset.desc || '';

                    row.querySelector('.item-rate').value = parseFloat(price).toFixed(2);
                    row.querySelector('.item-uom').value = uom;
                    const descField = row.querySelector('.item-description');
                    if (!descField.value.trim()) {
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
            
            const deleteLpoBtn = document.getElementById('delete-lpo-btn');
            if (deleteLpoBtn) {
                deleteLpoBtn.addEventListener('click', function() {
                    document.getElementById('delete-lpo-input').value = '1';
                    document.getElementById('current-lpo-section').style.textDecoration = 'line-through';
                    document.getElementById('lpo-delete-marker').style.display = 'inline';
                    this.style.display = 'none'; // Hide the delete button
                });
            }

            document.getElementById('addItemBtn').addEventListener('click', () => addNewItemRow());

            ['apply_ppda_levy', 'ppda_levy_percentage', 'vat_percentage'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener(el.type === 'checkbox' ? 'change' : 'input', calculateGrandTotals);
                }
            });

            if (typeof noInitialItems !== 'undefined' && noInitialItems && document.querySelectorAll('#invoiceItemsContainer .item-row').length === 0) {
                addNewItemRow();
            } else if(document.querySelectorAll('#invoiceItemsContainer .item-row').length > 0) {
                calculateGrandTotals();
            }

            $('#customer_id').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const name = selectedOption.text().split(' (')[0];
                const address = selectedOption.data('addr1');

                if (this.value) {
                    $('#customer_name_override').val(name);
                    $('#customer_address_override').val(address);
                }
            });
        });
    </script>
</body>
</html>