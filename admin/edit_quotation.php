<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header('Location: login.php'); // Adjust if your login page is elsewhere
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';

$quotation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error_message = '';
$success_message = '';
$quotation = null;
$quotation_items_data = []; // For pre-filling the form

$isAdmin = ($_SESSION['role'] === 'admin');
$current_user_id = $_SESSION['user_id'];

// Fetch lists for dropdowns
$customers = [];
$products = [];
$shops = [];
$units_of_measurement = [];

try {
    $pdo = getDatabaseConnection();
    $customers = DatabaseConfig::executeQuery($pdo, "SELECT id, customer_code, name FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $products = DatabaseConfig::executeQuery($pdo, "SELECT id, sku, name, default_unit_price, default_unit_of_measurement FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $shops = DatabaseConfig::executeQuery($pdo, "SELECT id, shop_code, name FROM shops ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $units_of_measurement = DatabaseConfig::executeQuery($pdo, "SELECT name FROM units_of_measurement ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    if ($quotation_id > 0) {
        // Fetch existing quotation data
        $stmt = DatabaseConfig::executeQuery($pdo, "SELECT * FROM quotations WHERE id = :id", [':id' => $quotation_id]);
        $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quotation) {
            $_SESSION['error_message'] = "Quotation not found.";
            header('Location: admin_quotations.php');
            exit();
        }

        // Check edit permission
        if ($quotation['status'] !== 'Draft' && !$isAdmin) {
            $_SESSION['error_message'] = "This quotation cannot be edited as it's not in 'Draft' status.";
            header('Location: view_quotation.php?id=' . $quotation_id);
            exit();
        }
        
        $stmt_items = DatabaseConfig::executeQuery($pdo, "SELECT * FROM quotation_items WHERE quotation_id = :quotation_id ORDER BY item_number ASC", [':quotation_id' => $quotation_id]);
        $quotation_items_data = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    } else {
         $_SESSION['error_message'] = "No Quotation ID specified for editing.";
         header('Location: admin_quotations.php');
         exit();
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Edit Quotation DB Error: " . $e->getMessage());
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quotation) {
    // --- BEGIN FORM PROCESSING ---
    try {
        $pdo->beginTransaction();

        // Sanitize and retrieve main quotation data
        $shop_id = filter_input(INPUT_POST, 'shop_id', FILTER_VALIDATE_INT);
        $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT) ?: null; // Allow null
        $customer_name_override = trim(filter_input(INPUT_POST, 'customer_name_override', FILTER_SANITIZE_STRING));
        $customer_address_override = trim(filter_input(INPUT_POST, 'customer_address_override', FILTER_SANITIZE_STRING));
        $quotation_date = filter_input(INPUT_POST, 'quotation_date', FILTER_SANITIZE_STRING);
        // ... (retrieve all other fields from the form for `quotations` table)
        $notes_general = trim(filter_input(INPUT_POST, 'notes_general', FILTER_SANITIZE_STRING));
        $delivery_period = trim(filter_input(INPUT_POST, 'delivery_period', FILTER_SANITIZE_STRING));
        $payment_terms = trim(filter_input(INPUT_POST, 'payment_terms', FILTER_SANITIZE_STRING));
        $quotation_validity_days = filter_input(INPUT_POST, 'quotation_validity_days', FILTER_VALIDATE_INT);
        $company_tpin = trim(filter_input(INPUT_POST, 'company_tpin', FILTER_SANITIZE_STRING));
        $mra_wht_note_content = trim(filter_input(INPUT_POST, 'mra_wht_note_content', FILTER_SANITIZE_STRING));
        $apply_ppda_levy = isset($_POST['apply_ppda_levy']) ? 1 : 0;
        $ppda_levy_percentage = filter_input(INPUT_POST, 'ppda_levy_percentage', FILTER_VALIDATE_FLOAT);
        $vat_percentage = filter_input(INPUT_POST, 'vat_percentage', FILTER_VALIDATE_FLOAT);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

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
        $amount_before_vat_calc = $gross_total_amount_calc;

        if ($apply_ppda_levy && $ppda_levy_percentage > 0) {
            $ppda_levy_amount_calc = round(($gross_total_amount_calc * $ppda_levy_percentage) / 100, 2);
            $amount_before_vat_calc = $gross_total_amount_calc + $ppda_levy_amount_calc;
        }
        
        $vat_amount_calc = round(($amount_before_vat_calc * $vat_percentage) / 100, 2);
        $total_net_amount_calc = $amount_before_vat_calc + $vat_amount_calc;

        // Update Quotation
        $sql_update_quotation = "UPDATE quotations SET 
            shop_id = :shop_id, customer_id = :customer_id, customer_name_override = :cno, customer_address_override = :cao, 
            quotation_date = :q_date, company_tpin = :c_tpin, notes_general = :notes_g, delivery_period = :del_p, 
            payment_terms = :pay_t, quotation_validity_days = :qvd, mra_wht_note_content = :mra, 
            apply_ppda_levy = :apply_ppda, ppda_levy_percentage = :ppda_perc, vat_percentage = :vat_perc, 
            gross_total_amount = :gross_total, ppda_levy_amount = :ppda_amount, amount_before_vat = :pre_vat, 
            vat_amount = :vat_amount, total_net_amount = :net_total, status = :status, 
            updated_by_user_id = :updater_id, updated_at = CURRENT_TIMESTAMP
            WHERE id = :quotation_id";
        
        $params_update_quotation = [
            ':shop_id' => $shop_id, ':customer_id' => $customer_id, ':cno' => $customer_name_override, ':cao' => $customer_address_override,
            ':q_date' => $quotation_date, ':c_tpin' => $company_tpin, ':notes_g' => $notes_general, ':del_p' => $delivery_period,
            ':pay_t' => $payment_terms, ':qvd' => $quotation_validity_days, ':mra' => $mra_wht_note_content,
            ':apply_ppda' => $apply_ppda_levy, ':ppda_perc' => $ppda_levy_percentage, ':vat_perc' => $vat_percentage,
            ':gross_total' => $gross_total_amount_calc, ':ppda_amount' => $ppda_levy_amount_calc, ':pre_vat' => $amount_before_vat_calc,
            ':vat_amount' => $vat_amount_calc, ':net_total' => $total_net_amount_calc, ':status' => $status,
            ':updater_id' => $current_user_id, ':quotation_id' => $quotation_id
        ];
        DatabaseConfig::executeQuery($pdo, $sql_update_quotation, $params_update_quotation);

        // Delete existing items
        DatabaseConfig::executeQuery($pdo, "DELETE FROM quotation_items WHERE quotation_id = :quotation_id", [':quotation_id' => $quotation_id]);

        // Insert new/updated items
        $sql_insert_item = "INSERT INTO quotation_items 
            (quotation_id, product_id, item_number, description, image_path_override, quantity, unit_of_measurement, rate_per_unit, total_amount, created_by_user_id, updated_by_user_id) 
            VALUES (:qid, :pid, :item_num, :desc, :img_override, :qty, :uom, :rate, :total, :creator_id, :updater_id)";

        foreach ($posted_items as $idx => $item_data) {
            $item_product_id = filter_var($item_data['product_id'], FILTER_VALIDATE_INT) ?: null;
            $item_description = trim(filter_var($item_data['description'], FILTER_SANITIZE_STRING));
            $item_quantity = filter_var($item_data['quantity'], FILTER_VALIDATE_FLOAT);
            $item_uom = trim(filter_var($item_data['unit_of_measurement'], FILTER_SANITIZE_STRING));
            $item_rate = filter_var($item_data['rate_per_unit'], FILTER_VALIDATE_FLOAT);
            $item_total = $item_quantity * $item_rate;

            if ($item_quantity > 0 && $item_rate >= 0) { // Basic validation for item
                 DatabaseConfig::executeQuery($pdo, $sql_insert_item, [
                    ':qid' => $quotation_id,
                    ':pid' => $item_product_id,
                    ':item_num' => $idx + 1, // Re-number items
                    ':desc' => $item_description,
                    ':img_override' => null, // Add field if you use this
                    ':qty' => $item_quantity,
                    ':uom' => $item_uom,
                    ':rate' => $item_rate,
                    ':total' => $item_total,
                    ':creator_id' => $quotation['created_by_user_id'], // Keep original creator
                    ':updater_id' => $current_user_id
                ]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = "Quotation #" . htmlspecialchars($quotation['quotation_number']) . " updated successfully.";
        
        // Refresh data after update
        $stmt = DatabaseConfig::executeQuery($pdo, "SELECT * FROM quotations WHERE id = :id", [':id' => $quotation_id]);
        $quotation = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt_items = DatabaseConfig::executeQuery($pdo, "SELECT * FROM quotation_items WHERE quotation_id = :quotation_id ORDER BY item_number ASC", [':quotation_id' => $quotation_id]);
        $quotation_items_data = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        $success_message = $_SESSION['success_message']; // To display on page
        unset($_SESSION['success_message']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error updating quotation: " . $e->getMessage();
        error_log("Update Quotation Error: " . $e->getMessage());
    }
    // --- END FORM PROCESSING ---
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}


DatabaseConfig::closeConnection($pdo); // Close connection if opened
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quotation - <?php echo htmlspecialchars($quotation['quotation_number'] ?? 'N/A'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .item-row .btn-danger { margin-top: 32px; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>

    <div class="container py-4">
        <h1>Edit Quotation: <?php echo htmlspecialchars($quotation['quotation_number'] ?? 'N/A'); ?></h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($quotation): ?>
        <form method="POST" action="edit_quotation.php?id=<?php echo $quotation_id; ?>" id="quotationForm">
            <div class="card mb-3">
                <div class="card-header">Quotation Details</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="quotation_number" class="form-label">Quotation Number</label>
                            <input type="text" class="form-control" id="quotation_number" name="quotation_number" value="<?php echo htmlspecialchars($quotation['quotation_number']); ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="quotation_date" class="form-label">Quotation Date *</label>
                            <input type="text" class="form-control datepicker" id="quotation_date" name="quotation_date" value="<?php echo htmlspecialchars($quotation['quotation_date']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="shop_id" class="form-label">Shop/Branch *</label>
                            <select class="form-select" id="shop_id" name="shop_id" required>
                                <option value="">Select Shop</option>
                                <?php foreach ($shops as $shop): ?>
                                <option value="<?php echo $shop['id']; ?>" <?php echo ($quotation['shop_id'] == $shop['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($shop['name'] . ($shop['shop_code'] ? ' ('.$shop['shop_code'].')' : '')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                     <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="company_tpin" class="form-label">Company TPIN (Displayed on Quote)</label>
                            <input type="text" class="form-control" id="company_tpin" name="company_tpin" value="<?php echo htmlspecialchars($quotation['company_tpin']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Draft" <?php echo ($quotation['status'] == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="Submitted" <?php echo ($quotation['status'] == 'Submitted') ? 'selected' : ''; ?>>Submitted</option>
                                <?php if ($isAdmin): // Only admin can change to Approved/Rejected directly ?>
                                <option value="Approved" <?php echo ($quotation['status'] == 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo ($quotation['status'] == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Customer Details</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Select Customer (Optional)</label>
                            <select class="form-select" id="customer_id" name="customer_id">
                                <option value="">-- Select Existing Customer --</option>
                                <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" <?php echo ($quotation['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['name'] . ($customer['customer_code'] ? ' ('.$customer['customer_code'].')' : '')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <p class="text-muted">Or, override customer details below:</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_name_override" class="form-label">Customer Name Override</label>
                            <input type="text" class="form-control" id="customer_name_override" name="customer_name_override" value="<?php echo htmlspecialchars($quotation['customer_name_override']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="customer_address_override" class="form-label">Customer Address Override</label>
                            <textarea class="form-control" id="customer_address_override" name="customer_address_override" rows="3"><?php echo htmlspecialchars($quotation['customer_address_override']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Quotation Items</div>
                <div class="card-body">
                    <div id="quotationItemsContainer">
                        <?php foreach ($quotation_items_data as $idx => $item): ?>
                        <div class="row item-row mb-2 align-items-center">
                            <div class="col-md-3">
                                <label class="form-label">Product</label>
                                <select class="form-select product-select" name="items[<?php echo $idx; ?>][product_id]">
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" 
                                            data-price="<?php echo $product['default_unit_price']; ?>" 
                                            data-uom="<?php echo htmlspecialchars($product['default_unit_of_measurement']); ?>"
                                            <?php echo ($item['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['name'] . ($product['sku'] ? ' ('.$product['sku'].')' : '')); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Description Override</label>
                                <textarea class="form-control item-description" name="items[<?php echo $idx; ?>][description]" rows="1"><?php echo htmlspecialchars($item['description']); ?></textarea>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Qty *</label>
                                <input type="number" class="form-control item-quantity" name="items[<?php echo $idx; ?>][quantity]" value="<?php echo htmlspecialchars($item['quantity']); ?>" step="0.01" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">UoM *</label>
                                <input type="text" class="form-control item-uom" name="items[<?php echo $idx; ?>][unit_of_measurement]" value="<?php echo htmlspecialchars($item['unit_of_measurement']); ?>" required>
                                <!-- Or make this a dropdown -->
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Rate *</label>
                                <input type="number" class="form-control item-rate" name="items[<?php echo $idx; ?>][rate_per_unit]" value="<?php echo htmlspecialchars($item['rate_per_unit']); ?>" step="0.01" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Total</label>
                                <input type="text" class="form-control item-total" readonly value="<?php echo htmlspecialchars(number_format($item['total_amount'], 2)); ?>">
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-sm btn-danger removeItemBtn"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
                            <input type="text" class="form-control" id="gross_total_amount" name="gross_total_amount" value="<?php echo htmlspecialchars(number_format($quotation['gross_total_amount'], 2)); ?>" readonly>
                        </div>
                        <div class="col-md-3 mb-3 form-check form-switch pt-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="apply_ppda_levy" name="apply_ppda_levy" <?php echo $quotation['apply_ppda_levy'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="apply_ppda_levy">Apply PPDA Levy</label>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="ppda_levy_percentage" class="form-label">PPDA Levy %</label>
                            <input type="number" class="form-control" id="ppda_levy_percentage" name="ppda_levy_percentage" value="<?php echo htmlspecialchars($quotation['ppda_levy_percentage']); ?>" step="0.01">
                        </div>
                         <div class="col-md-3 mb-3">
                            <label for="ppda_levy_amount" class="form-label">PPDA Levy Amount</label>
                            <input type="text" class="form-control" id="ppda_levy_amount" name="ppda_levy_amount" value="<?php echo htmlspecialchars(number_format($quotation['ppda_levy_amount'], 2)); ?>" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="amount_before_vat" class="form-label">Amount Before VAT</label>
                            <input type="text" class="form-control" id="amount_before_vat" name="amount_before_vat" value="<?php echo htmlspecialchars(number_format($quotation['amount_before_vat'], 2)); ?>" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="vat_percentage" class="form-label">VAT % *</label>
                            <input type="number" class="form-control" id="vat_percentage" name="vat_percentage" value="<?php echo htmlspecialchars($quotation['vat_percentage']); ?>" step="0.01" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="vat_amount" class="form-label">VAT Amount</label>
                            <input type="text" class="form-control" id="vat_amount" name="vat_amount" value="<?php echo htmlspecialchars(number_format($quotation['vat_amount'], 2)); ?>" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="total_net_amount" class="form-label">Total Net Amount</label>
                            <input type="text" class="form-control" id="total_net_amount" name="total_net_amount" value="<?php echo htmlspecialchars(number_format($quotation['total_net_amount'], 2)); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Terms & Notes</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="payment_terms" class="form-label">Payment Terms</label>
                            <input type="text" class="form-control" id="payment_terms" name="payment_terms" value="<?php echo htmlspecialchars($quotation['payment_terms']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="delivery_period" class="form-label">Delivery Period</label>
                            <input type="text" class="form-control" id="delivery_period" name="delivery_period" value="<?php echo htmlspecialchars($quotation['delivery_period']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="quotation_validity_days" class="form-label">Validity (Days)</label>
                            <input type="number" class="form-control" id="quotation_validity_days" name="quotation_validity_days" value="<?php echo htmlspecialchars($quotation['quotation_validity_days']); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes_general" class="form-label">General Notes</label>
                        <textarea class="form-control" id="notes_general" name="notes_general" rows="3"><?php echo htmlspecialchars($quotation['notes_general']); ?></textarea>
                    </div>
                     <div class="mb-3">
                        <label for="mra_wht_note_content" class="form-label">MRA WHT Note Content</label>
                        <textarea class="form-control" id="mra_wht_note_content" name="mra_wht_note_content" rows="2"><?php echo htmlspecialchars($quotation['mra_wht_note_content']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="view_quotation.php?id=<?php echo $quotation_id; ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
            </div>
        </form>
        <?php else: ?>
            <p>Quotation could not be loaded for editing.</p>
            <a href="admin_quotations.php" class="btn btn-primary">Back to List</a>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Store product data for easy access
        const productsData = <?php echo json_encode($products); ?>;

        function calculateItemTotal(itemRow) {
            const quantity = parseFloat(itemRow.querySelector('.item-quantity').value) || 0;
            const rate = parseFloat(itemRow.querySelector('.item-rate').value) || 0;
            const totalField = itemRow.querySelector('.item-total');
            const total = quantity * rate;
            totalField.value = total.toFixed(2);
            calculateGrandTotals();
        }

        function calculateGrandTotals() {
            let grossTotal = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                grossTotal += parseFloat(row.querySelector('.item-total').value) || 0;
            });
            document.getElementById('gross_total_amount').value = grossTotal.toFixed(2);

            const applyPPDA = document.getElementById('apply_ppda_levy').checked;
            const ppdaPercentage = parseFloat(document.getElementById('ppda_levy_percentage').value) || 0;
            let ppdaAmount = 0;
            let amountBeforeVat = grossTotal;

            if (applyPPDA && ppdaPercentage > 0) {
                ppdaAmount = (grossTotal * ppdaPercentage) / 100;
                amountBeforeVat = grossTotal + ppdaAmount;
            }
            document.getElementById('ppda_levy_amount').value = ppdaAmount.toFixed(2);
            document.getElementById('amount_before_vat').value = amountBeforeVat.toFixed(2);

            const vatPercentage = parseFloat(document.getElementById('vat_percentage').value) || 0;
            const vatAmount = (amountBeforeVat * vatPercentage) / 100;
            document.getElementById('vat_amount').value = vatAmount.toFixed(2);

            const totalNetAmount = amountBeforeVat + vatAmount;
            document.getElementById('total_net_amount').value = totalNetAmount.toFixed(2);
        }
        
        let itemIndex = <?php echo count($quotation_items_data); ?>; // Start index for new items

        function addNewItemRow() {
            const container = document.getElementById('quotationItemsContainer');
            const newRow = document.createElement('div');
            newRow.classList.add('row', 'item-row', 'mb-2', 'align-items-center');
            newRow.innerHTML = `
                <div class="col-md-3">
                    <label class="form-label visually-hidden">Product</label>
                    <select class="form-select product-select" name="items[${itemIndex}][product_id]">
                        <option value="">-- Select Product --</option>
                        ${productsData.map(p => `<option value="${p.id}" data-price="${p.default_unit_price}" data-uom="${p.default_unit_of_measurement || ''}">${p.name} ${p.sku ? '('+p.sku+')' : ''}</option>`).join('')}
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label visually-hidden">Description Override</label>
                    <textarea class="form-control item-description" name="items[${itemIndex}][description]" rows="1"></textarea>
                </div>
                <div class="col-md-1">
                    <label class="form-label visually-hidden">Qty</label>
                    <input type="number" class="form-control item-quantity" name="items[${itemIndex}][quantity]" value="1" step="0.01" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label visually-hidden">UoM</label>
                    <input type="text" class="form-control item-uom" name="items[${itemIndex}][unit_of_measurement]" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label visually-hidden">Rate</label>
                    <input type="number" class="form-control item-rate" name="items[${itemIndex}][rate_per_unit]" value="0.00" step="0.01" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label visually-hidden">Total</label>
                    <input type="text" class="form-control item-total" readonly>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-danger removeItemBtn"><i class="bi bi-trash"></i></button>
                </div>
            `;
            container.appendChild(newRow);
            attachItemEventListeners(newRow);
            itemIndex++;
            calculateItemTotal(newRow);
        }

        function attachItemEventListeners(row) {
            row.querySelector('.item-quantity').addEventListener('input', () => calculateItemTotal(row));
            row.querySelector('.item-rate').addEventListener('input', () => calculateItemTotal(row));
            row.querySelector('.removeItemBtn').addEventListener('click', () => {
                row.remove();
                calculateGrandTotals();
            });
            const productSelect = row.querySelector('.product-select');
            if (productSelect) {
                productSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const price = selectedOption.dataset.price || '0.00';
                    const uom = selectedOption.dataset.uom || '';
                    row.querySelector('.item-rate').value = parseFloat(price).toFixed(2);
                    row.querySelector('.item-uom').value = uom;
                    calculateItemTotal(row);
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".datepicker", { dateFormat: "Y-m-d", allowInput: true });

            document.querySelectorAll('.item-row').forEach(attachItemEventListeners);
            document.getElementById('addItemBtn').addEventListener('click', addNewItemRow);

            ['apply_ppda_levy', 'ppda_levy_percentage', 'vat_percentage'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener(el.type === 'checkbox' ? 'change' : 'input', calculateGrandTotals);
                }
            });
             // Initial calculation for pre-filled items
            document.querySelectorAll('.item-row').forEach(row => {
                if(!row.querySelector('.item-total').value){ // if total is not prefilled (e.g. an error occurred before)
                    calculateItemTotal(row);
                }
            });
            if(document.querySelectorAll('.item-row').length > 0) {
                calculateGrandTotals(); // Calculate grand totals on page load if items exist
            }
        });
    </script>
</body>
</html>