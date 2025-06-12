<?php
// process_invoice.php - Handles the creation of new invoices
session_start();

header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "supplies";
$conn = null;

// Helper function to generate Invoice Number
function generateInvoiceNumber($conn, $shop_id, $customer_id) {
    // Fetch shop code
    $stmt_shop = $conn->prepare("SELECT shop_code FROM shops WHERE id = ?");
    $stmt_shop->execute([$shop_id]);
    $shop = $stmt_shop->fetch(PDO::FETCH_ASSOC);
    $shop_code_prefix = $shop ? $shop['shop_code'] : 'SHOP';

    // Fetch customer code
    $stmt_customer = $conn->prepare("SELECT customer_code FROM customers WHERE id = ?");
    $stmt_customer->execute([$customer_id]);
    $customer = $stmt_customer->fetch(PDO::FETCH_ASSOC);
    $customer_code_prefix = $customer ? $customer['customer_code'] : 'CUST';

    // Get current year and month
    $current_year = date('Y');
    $current_month = date('m');

    // Base prefix for the invoice number
    $invoice_prefix = "INV/{$current_year}/{$current_month}/{$shop_code_prefix}/{$customer_code_prefix}-";

    // Handle sequence management with proper locking
    $stmt_seq = $conn->prepare("SELECT last_sequence_number FROM invoice_sequences WHERE shop_id = ? AND customer_id = ? FOR UPDATE");
    $stmt_seq->execute([$shop_id, $customer_id]);
    $result = $stmt_seq->fetch(PDO::FETCH_ASSOC);

    $next_seq = 1;
    if ($result) {
        $next_seq = $result['last_sequence_number'] + 1;
        $update_seq_stmt = $conn->prepare("UPDATE invoice_sequences SET last_sequence_number = ? WHERE shop_id = ? AND customer_id = ?");
        $update_seq_stmt->execute([$next_seq, $shop_id, $customer_id]);
    } else {
        $insert_seq_stmt = $conn->prepare("INSERT INTO invoice_sequences (shop_id, customer_id, last_sequence_number) VALUES (?, ?, ?)");
        $insert_seq_stmt->execute([$shop_id, $customer_id, $next_seq]);
    }

    $next_sequence_padded = str_pad($next_seq, 3, '0', STR_PAD_LEFT);
    return "{$invoice_prefix}{$next_sequence_padded}";
}

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->beginTransaction();

    // Validate user authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not authenticated. Please log in.");
    }
    $created_by_user_id = $_SESSION['user_id'];

    // Validate and sanitize basic inputs
    $shop_id = filter_input(INPUT_POST, 'shop_id', FILTER_VALIDATE_INT);
    $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
    $invoice_date = filter_input(INPUT_POST, 'invoice_date', FILTER_SANITIZE_SPECIAL_CHARS);
    $due_date = filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_SPECIAL_CHARS);
    $company_tpin = filter_input(INPUT_POST, 'company_tpin', FILTER_SANITIZE_SPECIAL_CHARS);

    if (!$shop_id || !$customer_id || empty($invoice_date)) {
        throw new Exception("Shop ID, Customer ID, or Invoice Date is missing or invalid.");
    }

    // Generate invoice number
    $invoice_number = generateInvoiceNumber($conn, $shop_id, $customer_id);

    // Handle overrides
    $customer_name_override = null;
    $customer_address_override = null;
    
    if (isset($_POST['customer_name_override_checkbox']) && !empty($_POST['customer_name_override'])) {
        $customer_name_override = filter_input(INPUT_POST, 'customer_name_override', FILTER_SANITIZE_SPECIAL_CHARS);
    }
    
    if (isset($_POST['customer_address_override_checkbox']) && !empty($_POST['customer_address_override'])) {
        $customer_address_override = filter_input(INPUT_POST, 'customer_address_override', FILTER_SANITIZE_SPECIAL_CHARS);
    }

    // Optional fields
    $notes_general = filter_input(INPUT_POST, 'notes_general', FILTER_SANITIZE_SPECIAL_CHARS);
    $delivery_period = filter_input(INPUT_POST, 'delivery_period', FILTER_SANITIZE_SPECIAL_CHARS);
    $payment_terms = filter_input(INPUT_POST, 'payment_terms', FILTER_SANITIZE_SPECIAL_CHARS);
    $apply_ppda_levy = isset($_POST['apply_ppda_levy']) ? 1 : 0;
    $ppda_levy_percentage = $apply_ppda_levy ? 1.00 : 0.00;
    
    // Validate VAT percentage
    $vat_percentage = filter_input(INPUT_POST, 'vat_percentage_used', FILTER_VALIDATE_FLOAT);
    if ($vat_percentage === false || $vat_percentage < 0) {
        $vat_percentage = 16.5; // Default value
    }

    // Validate calculated totals
    $gross_total_amount = filter_input(INPUT_POST, 'gross_total_amount', FILTER_VALIDATE_FLOAT);
    $ppda_levy_amount_calc = filter_input(INPUT_POST, 'ppda_levy_amount', FILTER_VALIDATE_FLOAT);
    $amount_before_vat_calc = filter_input(INPUT_POST, 'amount_before_vat', FILTER_VALIDATE_FLOAT);
    $vat_amount_calc = filter_input(INPUT_POST, 'vat_amount', FILTER_VALIDATE_FLOAT);
    $total_net_amount_calc = filter_input(INPUT_POST, 'total_net_amount', FILTER_VALIDATE_FLOAT);

    // Validate that totals are valid numbers
    if ($gross_total_amount === false || $total_net_amount_calc === false) {
        throw new Exception("Invalid total amounts provided.");
    }

    // Insert invoice
    $sql_invoice = "INSERT INTO invoices (
                        invoice_number, shop_id, customer_id, customer_name_override, customer_address_override,
                        invoice_date, due_date, company_tpin, notes_general, delivery_period, payment_terms,
                        apply_ppda_levy, ppda_levy_percentage, vat_percentage,
                        gross_total_amount, ppda_levy_amount, amount_before_vat,
                        vat_amount, total_net_amount, status, created_by_user_id, updated_by_user_id,
                        created_at, updated_at
                    ) VALUES (
                        :invoice_number, :shop_id, :customer_id, :customer_name_override, :customer_address_override,
                        :invoice_date, :due_date, :company_tpin, :notes_general, :delivery_period, :payment_terms,
                        :apply_ppda_levy, :ppda_levy_percentage, :vat_percentage,
                        :gross_total_amount, :ppda_levy_amount, :amount_before_vat,
                        :vat_amount, :total_net_amount, :status, :created_by_user_id, :updated_by_user_id,
                        NOW(), NOW()
                    )";

    $stmt_invoice = $conn->prepare($sql_invoice);
    $stmt_invoice->execute([
        ':invoice_number' => $invoice_number,
        ':shop_id' => $shop_id,
        ':customer_id' => $customer_id,
        ':customer_name_override' => $customer_name_override,
        ':customer_address_override' => $customer_address_override,
        ':invoice_date' => $invoice_date,
        ':due_date' => $due_date ?: null,
        ':company_tpin' => $company_tpin,
        ':notes_general' => $notes_general,
        ':delivery_period' => $delivery_period,
        ':payment_terms' => $payment_terms,
        ':apply_ppda_levy' => $apply_ppda_levy,
        ':ppda_levy_percentage' => $ppda_levy_percentage,
        ':vat_percentage' => $vat_percentage,
        ':gross_total_amount' => $gross_total_amount,
        ':ppda_levy_amount' => $ppda_levy_amount_calc,
        ':amount_before_vat' => $amount_before_vat_calc,
        ':vat_amount' => $vat_amount_calc,
        ':total_net_amount' => $total_net_amount_calc,
        ':status' => 'Draft',
        ':created_by_user_id' => $created_by_user_id,
        ':updated_by_user_id' => $created_by_user_id
    ]);

    $invoice_id = $conn->lastInsertId();

    // Process invoice items
    $sql_item = "INSERT INTO invoice_items (
                    invoice_id, product_id, item_number, description, image_path_override,
                    quantity, unit_of_measurement, rate_per_unit, total_amount,
                    created_at, updated_at, created_by_user_id, updated_by_user_id
                ) VALUES (
                    :invoice_id, :product_id, :item_number, :description, :image_path_override,
                    :quantity, :unit_of_measurement, :rate_per_unit, :total_amount,
                    NOW(), NOW(), :created_by_user_id, :updated_by_user_id
                )";
    
    $stmt_item = $conn->prepare($sql_item);

    // Get item arrays from POST data
    $item_product_ids = $_POST['product_id'] ?? [];
    $item_names = $_POST['item_name'] ?? [];
    $item_descriptions = $_POST['item_description'] ?? [];
    $item_quantities = $_POST['item_quantity'] ?? [];
    $item_uoms = $_POST['item_uom'] ?? [];
    $item_unit_prices = $_POST['item_unit_price'] ?? [];
    $item_images = $_FILES['item_image_upload'] ?? null;

    // Process each item
    $item_number_counter = 0;
    $items_processed = 0;
    $items_skipped = 0;

    $total_items = max(
        count($item_product_ids),
        count($item_names),
        count($item_descriptions),
        count($item_quantities),
        count($item_unit_prices)
    );

    for ($i = 0; $i < $total_items; $i++) {
        // Get values for this item
        $product_id = !empty($item_product_ids[$i]) ? (int)$item_product_ids[$i] : null;
        $item_name = trim($item_names[$i] ?? '');
        $description = trim($item_descriptions[$i] ?? '');
        $quantity = (float)($item_quantities[$i] ?? 0);
        $unit_of_measurement = trim($item_uoms[$i] ?? '');
        $rate_per_unit = (float)($item_unit_prices[$i] ?? 0);

        // Use item_name as description if description is empty
        if (empty($description) && !empty($item_name)) {
            $description = $item_name;
        }

        // Skip if essential data is missing
        if (empty($description) || $quantity <= 0 || $rate_per_unit <= 0) {
            $items_skipped++;
            continue;
        }

        $item_number_counter++;
        $total_amount = $quantity * $rate_per_unit;

        // Handle image upload
        $uploaded_image_path = null;
        if ($item_images && isset($item_images['tmp_name'][$i]) && $item_images['error'][$i] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/invoice_item_images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }
            
            $original_filename = basename($item_images['name'][$i]);
            $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
            $safe_filename = $invoice_id . '_' . $item_number_counter . '_' . time() . '.' . $extension;
            $uploaded_image_path = $upload_dir . $safe_filename;

            if (!move_uploaded_file($item_images['tmp_name'][$i], $uploaded_image_path)) {
                error_log("Failed to upload item image for invoice " . $invoice_id);
                $uploaded_image_path = null;
            }
        }

        // Insert item
        try {
            $stmt_item->execute([
                ':invoice_id' => $invoice_id,
                ':product_id' => $product_id,
                ':item_number' => $item_number_counter,
                ':description' => $description,
                ':image_path_override' => $uploaded_image_path,
                ':quantity' => $quantity,
                ':unit_of_measurement' => $unit_of_measurement,
                ':rate_per_unit' => $rate_per_unit,
                ':total_amount' => $total_amount,
                ':created_by_user_id' => $created_by_user_id,
                ':updated_by_user_id' => $created_by_user_id
            ]);
            
            $items_processed++;
            
        } catch (PDOException $itemE) {
            error_log("Error inserting item: " . $itemE->getMessage());
            // Continue processing other items
        }
    }

    // Check if any items were processed
    if ($items_processed === 0) {
        throw new Exception("No valid items were processed. Please check your item data.");
    }

    $conn->commit();
    
    echo json_encode([
        "success" => true,
        "message" => "Invoice created successfully.",
        "invoice_id" => $invoice_id,
        "invoice_number" => $invoice_number,
        "items_processed" => $items_processed,
        "items_skipped" => $items_skipped
    ]);

} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }
    
    error_log("Invoice creation error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Error creating invoice: " . $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if ($conn) {
        $conn->rollback();
    }
    
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Database error occurred. Please try again."
    ]);
}
?>