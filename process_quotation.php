<?php
// process_quotation.php
require_once(__DIR__ . '/includes/config.php'); // For session and user_id
require_once(__DIR__ . '/includes/db_connect.php');

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An error occurred.'];
$pdo = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// Ensure user is logged in (basic check, enhance with roles/permissions)
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Authentication required. Please log in.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

try {
    $pdo = getDatabaseConnection();
    DatabaseConfig::beginTransaction($pdo);

    // --- 1. Handle Customer ---
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $customer_code = null;
    $customer_name_for_quotation = $_POST['customer_name_override'] ?? null;
    $customer_address_for_quotation = $_POST['customer_address_override'] ?? null;

    if (!empty($_POST['save_new_customer_checkbox']) && $_POST['save_new_customer_checkbox'] === 'on') {
        // Save new customer
        $new_customer_name = $_POST['customer_name_override'];
        $new_customer_address = $_POST['customer_address_override']; // Could be split into address_line1, address_line2
        $new_customer_tpin = $_POST['new_customer_tpin'] ?? null;
        $new_customer_code_input = $_POST['new_customer_code'] ?? null;

        if (empty($new_customer_name)) {
            throw new Exception("New customer name is required.");
        }

        if (empty($new_customer_code_input)) {
            // Auto-generate customer code if not provided
            $prefix = "C"; // Or a setting
            $stmt_code = DatabaseConfig::executeQuery(
                $pdo,
                "SELECT customer_code FROM customers WHERE customer_code RLIKE ? ORDER BY CAST(SUBSTRING(customer_code, " . (strlen($prefix) + 1) . ") AS UNSIGNED) DESC LIMIT 1",
                ['^' . $prefix . '[0-9]+$']
            );
            $last_cust_code = $stmt_code->fetchColumn();
            $next_cust_number = 1;
            if ($last_cust_code) {
                $last_number_val = (int)substr($last_cust_code, strlen($prefix));
                $next_cust_number = $last_number_val + 1;
            }
            $customer_code = $prefix . sprintf('%05d', $next_cust_number); // e.g., C00001
        } else {
            // Validate provided customer code
            $stmt_check_code = DatabaseConfig::executeQuery($pdo, "SELECT id FROM customers WHERE customer_code = ?", [$new_customer_code_input]);
            if ($stmt_check_code->fetch()) {
                throw new Exception("Customer code '{$new_customer_code_input}' already exists.");
            }
            $customer_code = $new_customer_code_input;
        }
        
        $sql_insert_customer = "INSERT INTO customers (customer_code, name, address_line1, tpin_no, created_by_user_id, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        DatabaseConfig::executeQuery($pdo, $sql_insert_customer, [
            $customer_code,
            $new_customer_name,
            $new_customer_address, // You might want to parse this into address_line1, city etc.
            $new_customer_tpin,
            $current_user_id
        ]);
        $customer_id = $pdo->lastInsertId();
        $customer_name_for_quotation = $new_customer_name; // Use this for quotation override fields
        $customer_address_for_quotation = $new_customer_address;

    } elseif ($customer_id) {
        // Existing customer selected
        $stmt_cust = DatabaseConfig::executeQuery($pdo, "SELECT customer_code, name, address_line1, address_line2, city_location FROM customers WHERE id = ?", [$customer_id]);
        $existing_customer = $stmt_cust->fetch(PDO::FETCH_ASSOC);
        if (!$existing_customer) {
            throw new Exception("Selected customer not found.");
        }
        $customer_code = $existing_customer['customer_code'];
        // If override fields are empty, use existing customer data
        if (empty($customer_name_for_quotation)) $customer_name_for_quotation = $existing_customer['name'];
        if (empty($customer_address_for_quotation)) {
             $customer_address_for_quotation = trim($existing_customer['address_line1'] . "\n" . $existing_customer['address_line2'] . "\n" . $existing_customer['city_location']);
        }
    } else {
        // No customer selected, and not saving a new one. Allow if overrides are present.
        if (empty($customer_name_for_quotation)) {
             throw new Exception("Customer name is required if not selecting an existing customer or saving a new one.");
        }
        // For quotation number, if no customer_code, we might need a generic one or disallow
        $customer_code = "NOCUST"; // Or handle as an error if a customer code is strictly needed for quotation number.
    }


    // --- 2. Prepare Quotation Data ---
    $shop_id = (int)$_POST['shop_id'];
    $stmt_shop = DatabaseConfig::executeQuery($pdo, "SELECT shop_code, tpin_no FROM shops WHERE id = ?", [$shop_id]);
    $shop_data = $stmt_shop->fetch(PDO::FETCH_ASSOC);
    if (!$shop_data) {
        throw new Exception("Selected shop not found.");
    }
    $shop_code = $shop_data['shop_code'];
    $default_company_tpin = $shop_data['tpin_no'];

    // --- 3. Generate Quotation Number ---
    // SDL/shop_code/customer_code-###
    $quotation_number_prefix = "SDL/" . $shop_code . "/" . $customer_code . "-";
    $sql_max_quot_num = "SELECT quotation_number FROM quotations
                         WHERE quotation_number LIKE ?
                         ORDER BY CAST(RIGHT(SUBSTRING_INDEX(quotation_number, '-', -1), 3) AS UNSIGNED) DESC
                         LIMIT 1";
    $stmt_max_quot = DatabaseConfig::executeQuery($pdo, $sql_max_quot_num, [$quotation_number_prefix . '%']);
    $last_quot_num_full = $stmt_max_quot->fetchColumn();

    $next_seq_num = 1;
    if ($last_quot_num_full) {
        $last_seq_part = substr($last_quot_num_full, strlen($quotation_number_prefix));
        $last_seq_digits = substr($last_seq_part, -3);
        if (is_numeric($last_seq_digits)) {
            $next_seq_num = (int)$last_seq_digits + 1;
        }
    }
    $quotation_number_suffix = sprintf("%03d", $next_seq_num);
    $quotation_number = $quotation_number_prefix . $quotation_number_suffix;


    // --- 4. Insert Quotation Header ---
    $quotation_data = [
        'quotation_number' => $quotation_number,
        'shop_id' => $shop_id,
        'customer_id' => $customer_id, // Can be null if not an existing/newly saved DB customer
        'customer_name_override' => $customer_name_for_quotation,
        'customer_address_override' => $customer_address_for_quotation,
        'quotation_date' => $_POST['quotation_date'],
        'company_tpin' => $_POST['company_tpin'] ?: $default_company_tpin,
        'notes_general' => $_POST['general_note'] ?? null,
        'delivery_period' => $_POST['delivery_period'] ?? null,
        'payment_terms' => $_POST['payment_terms'] ?? null,
        'quotation_validity_days' => !empty($_POST['quotation_validity_days']) ? (int)$_POST['quotation_validity_days'] : null,
        'mra_wht_note' => (isset($_POST['include_mra_wht_note']) && $_POST['include_mra_wht_note'] === 'on') ? ($_POST['mra_wht_note_content'] ?? null) : null,
        'apply_ppda_levy' => (isset($_POST['apply_ppda_levy']) && $_POST['apply_ppda_levy'] === 'on') ? 1 : 0,
        'ppda_levy_percentage' => (isset($_POST['apply_ppda_levy']) && $_POST['apply_ppda_levy'] === 'on') ? 1.00 : 0.00, // Assuming fixed 1% if applied
        'vat_percentage' => (float)$_POST['vat_percentage'],
        'gross_total_amount' => (float)($_POST['hidden_gross_total_amount'] ?? 0), // These should come from hidden fields updated by JS
        'ppda_levy_amount' => (float)($_POST['hidden_ppda_levy_amount'] ?? 0),
        'amount_before_vat' => (float)($_POST['hidden_subtotal_before_vat'] ?? 0),
        'vat_amount' => (float)($_POST['hidden_vat_amount'] ?? 0),
        'total_net_amount' => (float)($_POST['hidden_total_net_amount'] ?? 0),
        'status' => $_POST['submit_action'] === 'generate' ? 'Generated' : 'Draft', // 'submit_action' to be sent by JS
        'created_by_user_id' => $current_user_id,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $sql_insert_quotation = "INSERT INTO quotations (
        quotation_number, shop_id, customer_id, customer_name_override, customer_address_override, quotation_date, company_tpin, 
        notes_general, delivery_period, payment_terms, quotation_validity_days, mra_wht_note, 
        apply_ppda_levy, ppda_levy_percentage, vat_percentage, 
        gross_total_amount, ppda_levy_amount, amount_before_vat, vat_amount, total_net_amount, 
        status, created_by_user_id, created_at, updated_at
    ) VALUES (
        :quotation_number, :shop_id, :customer_id, :customer_name_override, :customer_address_override, :quotation_date, :company_tpin,
        :notes_general, :delivery_period, :payment_terms, :quotation_validity_days, :mra_wht_note,
        :apply_ppda_levy, :ppda_levy_percentage, :vat_percentage,
        :gross_total_amount, :ppda_levy_amount, :amount_before_vat, :vat_amount, :total_net_amount,
        :status, :created_by_user_id, :created_at, :updated_at
    )";
    DatabaseConfig::executeQuery($pdo, $sql_insert_quotation, $quotation_data);
    $quotation_id = $pdo->lastInsertId();

    // --- 5. Insert Quotation Items ---
    // Expecting items as an array: $_POST['items'][0]['description'], $_POST['items'][0]['quantity'], etc.
    // Your JS needs to structure this data correctly upon form submission.
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        $sql_insert_item = "INSERT INTO quotation_items (
            quotation_id, product_id, item_number, description, image_path_override, 
            quantity, unit_of_measurement, rate_per_unit, total_amount, 
            created_by_user_id, created_at, updated_at
        ) VALUES (
            :quotation_id, :product_id, :item_number, :description, :image_path_override,
            :quantity, :unit_of_measurement, :rate_per_unit, :total_amount,
            :created_by_user_id, NOW(), NOW()
        )";
        
        foreach ($_POST['items'] as $index => $item) {
            if (empty($item['description']) && empty($item['product_id'])) continue; // Skip empty rows

            $item_data = [
                'quotation_id' => $quotation_id,
                'product_id' => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                'item_number' => $index + 1, // Or use a dedicated item_number field from the form
                'description' => $item['description'],
                'image_path_override' => $item['image_path_override'] ?? null, // Needs handling if file upload
                'quantity' => (float)$item['quantity'],
                'unit_of_measurement' => $item['unit'],
                'rate_per_unit' => (float)$item['rate'],
                'total_amount' => (float)$item['total'],
                'created_by_user_id' => $current_user_id
            ];
            DatabaseConfig::executeQuery($pdo, $sql_insert_item, $item_data);
        }
    }

    DatabaseConfig::commitTransaction($pdo);
    $response = [
        'success' => true, 
        'message' => 'Quotation ' . ($quotation_data['status'] === 'Draft' ? 'saved as draft' : 'generated') . ' successfully!',
        'quotation_id' => $quotation_id,
        'quotation_number' => $quotation_number
    ];

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        DatabaseConfig::rollbackTransaction($pdo);
    }
    // db_connect.php already logs PDOExceptions
    $response['message'] = 'Database error: ' . $e->getMessage(); // Debug
    // $response['message'] = 'A database error occurred while saving the quotation.'; // Prod
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        DatabaseConfig::rollbackTransaction($pdo);
    }
    $response['message'] = $e->getMessage();
} finally {
    DatabaseConfig::closeConnection($pdo);
    echo json_encode($response);
    exit;
}
?>