<?php
// process_quotation.php
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "supplies";
$conn = null; // Initialize

// --- Helper function to generate quotation number ---
function generateQuotationNumber($conn, $shop_ids_array, $customer_id) {
    // Fetch shop codes for all selected shops
    $shop_codes_array = [];
    if (!empty($shop_ids_array)) {
        $shop_placeholders = implode(',', array_fill(0, count($shop_ids_array), '?'));
        $stmt_shop = $conn->prepare("SELECT shop_code FROM shops WHERE id IN ($shop_placeholders)");
        $stmt_shop->execute($shop_ids_array);
        while ($row = $stmt_shop->fetch(PDO::FETCH_ASSOC)) {
            $shop_codes_array[] = $row['shop_code'];
        }
    }
    $shop_code_prefix = !empty($shop_codes_array) ? implode('-', $shop_codes_array) : 'SHOP'; // Use a default or handle error if no shop

    // Fetch customer code
    $stmt_customer = $conn->prepare("SELECT customer_code FROM customers WHERE id = ?");
    $stmt_customer->execute([$customer_id]);
    $customer = $stmt_customer->fetch(PDO::FETCH_ASSOC);
    $customer_code_prefix = $customer ? $customer['customer_code'] : 'CUST'; // Use a default or handle error

    // Generate date part and a sequence number
    $date_part = date('Ymd'); // e.g., 20250517

    // Get the next sequence number for this date and shop/customer combo (more robust)
    // This is simplified; a robust approach might involve a separate sequence table or MAX(id) for today
    $sequence_stmt = $conn->prepare("SELECT COUNT(id) as count_today FROM quotations WHERE quotation_number LIKE ?");
    $sequence_stmt->execute(["SDL/{$shop_code_prefix}/{$customer_code_prefix}-{$date_part}%"]);
    $count_today = $sequence_stmt->fetch(PDO::FETCH_ASSOC)['count_today'];
    $next_sequence = str_pad($count_today + 1, 4, '0', STR_PAD_LEFT); // e.g., 0001

    return "SDL/{$shop_code_prefix}/{$customer_code_prefix}-{$date_part}{$next_sequence}";
}
// --- End Helper ---


try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->beginTransaction();

    // --- 1. Retrieve and Validate Basic Data ---
    $shop_ids = isset($_POST['shops']) ? (array)$_POST['shops'] : []; // Array of shop IDs
    $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
    $quotation_date = filter_input(INPUT_POST, 'quotation_date', FILTER_SANITIZE_STRING);
    $company_tpin = filter_input(INPUT_POST, 'company_tpin', FILTER_SANITIZE_STRING);

    // User ID - Assuming you have a session or other way to get this
    $created_by_user_id = 1; // Replace with actual logged-in user ID
    $updated_by_user_id = $created_by_user_id;

    if (empty($shop_ids) || !$customer_id || empty($quotation_date)) {
        throw new Exception("Shop, Customer, or Quotation Date is missing.");
    }
    // For simplicity, we'll associate the quotation with the *first* shop selected if multiple are chosen for the main `shop_id` field.
    // If you need to link a quotation to *multiple* shops, you'd need a pivot table (e.g., quotation_shops).
    // For this example, we'll use the first selected shop for `shop_id` in the `quotations` table.
    $main_shop_id = $shop_ids[0];


    // --- 2. Generate Quotation Number ---
    // This function needs the $conn to fetch shop_code and customer_code
    $quotation_number = generateQuotationNumber($conn, $shop_ids, $customer_id);

    // --- 3. Handle Overrides ---
    $customer_name_override = isset($_POST['customer_name_override_checkbox']) && !empty($_POST['customer_name_override']) ?
                              filter_input(INPUT_POST, 'customer_name_override', FILTER_SANITIZE_STRING) : null;
    $customer_address_override = isset($_POST['customer_address_override_checkbox']) && !empty($_POST['customer_address_override']) ?
                                 filter_input(INPUT_POST, 'customer_address_override', FILTER_SANITIZE_STRING) : null;

    // --- 4. Retrieve Optional Data ---
    $notes_general = filter_input(INPUT_POST, 'notes_general', FILTER_SANITIZE_STRING);
    $delivery_period = filter_input(INPUT_POST, 'delivery_period', FILTER_SANITIZE_STRING);
    $payment_terms = filter_input(INPUT_POST, 'payment_terms', FILTER_SANITIZE_STRING);
    $quotation_validity_days = filter_input(INPUT_POST, 'quotation_validity_days', FILTER_VALIDATE_INT, ['options' => ['default' => 30]]);
    $mra_wht_note_content = filter_input(INPUT_POST, 'mra_wht_note_content', FILTER_SANITIZE_STRING);
    $apply_ppda_levy = isset($_POST['apply_ppda_levy']) ? 1 : 0;
    $ppda_levy_percentage = $apply_ppda_levy ? 1.00 : 0.00; // Assuming 1% if applied
    $vat_percentage = filter_input(INPUT_POST, 'vat_percentage_used', FILTER_VALIDATE_FLOAT, ['options' => ['default' => 16.5]]); // Get VAT % used

    // --- 5. Retrieve Calculated Totals from JS (passed via FormData) ---
    // It's good to re-verify these on the server if business logic is critical, but for now, we trust client-side.
    $gross_total_amount = filter_input(INPUT_POST, 'gross_total_amount', FILTER_VALIDATE_FLOAT);
    $ppda_levy_amount_calc = filter_input(INPUT_POST, 'ppda_levy_amount', FILTER_VALIDATE_FLOAT);
    $amount_before_vat_calc = filter_input(INPUT_POST, 'amount_before_vat', FILTER_VALIDATE_FLOAT);
    $vat_amount_calc = filter_input(INPUT_POST, 'vat_amount', FILTER_VALIDATE_FLOAT);
    $total_net_amount_calc = filter_input(INPUT_POST, 'total_net_amount', FILTER_VALIDATE_FLOAT);


    // --- 6. Insert into `quotations` table ---
    $sql_quotation = "INSERT INTO quotations (
                        quotation_number, shop_id, customer_id, customer_name_override, customer_address_override,
                        quotation_date, company_tpin, notes_general, delivery_period, payment_terms,
                        quotation_validity_days, mra_wht_note_content, apply_ppda_levy, ppda_levy_percentage,
                        vat_percentage, gross_total_amount, ppda_levy_amount, amount_before_vat,
                        vat_amount, total_net_amount, status, created_by_user_id, updated_by_user_id,
                        created_at, updated_at
                      ) VALUES (
                        :quotation_number, :shop_id, :customer_id, :customer_name_override, :customer_address_override,
                        :quotation_date, :company_tpin, :notes_general, :delivery_period, :payment_terms,
                        :quotation_validity_days, :mra_wht_note_content, :apply_ppda_levy, :ppda_levy_percentage,
                        :vat_percentage, :gross_total_amount, :ppda_levy_amount, :amount_before_vat,
                        :vat_amount, :total_net_amount, :status, :created_by_user_id, :updated_by_user_id,
                        NOW(), NOW()
                      )";

    $stmt_quotation = $conn->prepare($sql_quotation);
    $stmt_quotation->execute([
        ':quotation_number' => $quotation_number,
        ':shop_id' => $main_shop_id, // Using the first selected shop ID
        ':customer_id' => $customer_id,
        ':customer_name_override' => $customer_name_override,
        ':customer_address_override' => $customer_address_override,
        ':quotation_date' => $quotation_date,
        ':company_tpin' => $company_tpin,
        ':notes_general' => $notes_general,
        ':delivery_period' => $delivery_period,
        ':payment_terms' => $payment_terms,
        ':quotation_validity_days' => $quotation_validity_days,
        ':mra_wht_note_content' => $mra_wht_note_content,
        ':apply_ppda_levy' => $apply_ppda_levy,
        ':ppda_levy_percentage' => $ppda_levy_percentage,
        ':vat_percentage' => $vat_percentage,
        ':gross_total_amount' => $gross_total_amount,
        ':ppda_levy_amount' => $ppda_levy_amount_calc,
        ':amount_before_vat' => $amount_before_vat_calc,
        ':vat_amount' => $vat_amount_calc,
        ':total_net_amount' => $total_net_amount_calc,
        ':status' => 'Draft', // Or 'Pending', 'Generated', etc.
        ':created_by_user_id' => $created_by_user_id,
        ':updated_by_user_id' => $updated_by_user_id
    ]);

    $quotation_id = $conn->lastInsertId();

    // --- 7. Insert into `quotation_items` table ---
    // You'll need a `quotation_items` table:
    // quotation_id (FK), product_id (FK), item_name (if overridden or for historical record),
    // quantity, unit_price, unit_of_measurement, total_price, item_image_path (optional)

    $sql_item = "INSERT INTO quotation_items (
                    quotation_id, product_id, item_name, item_description, quantity, unit_of_measurement,
                    unit_price, total_price, item_image_path
                 ) VALUES (
                    :quotation_id, :product_id, :item_name, :item_description, :quantity, :unit_of_measurement,
                    :unit_price, :total_price, :item_image_path
                 )";
    $stmt_item = $conn->prepare($sql_item);

    $item_product_ids = $_POST['product_id']; // Array
    $item_names = $_POST['item_name'];         // Array
    $item_descriptions = $_POST['item_description']; // Array
    $item_quantities = $_POST['item_quantity']; // Array
    $item_uoms = $_POST['item_uom'];           // Array
    $item_unit_prices = $_POST['item_unit_price']; // Array
    // item_total is calculated, not directly taken

    // Handle image uploads for items
    $item_image_uploads = isset($_FILES['item_image_upload']) ? $_FILES['item_image_upload'] : null;


    for ($i = 0; $i < count($item_product_ids); $i++) {
        if (empty($item_product_ids[$i])) continue; // Skip if no product ID (e.g., empty row)

        $product_id = filter_var($item_product_ids[$i], FILTER_VALIDATE_INT);
        $item_name = filter_var($item_names[$i], FILTER_SANITIZE_STRING);
        $item_description = filter_var($item_descriptions[$i], FILTER_SANITIZE_STRING);
        $quantity = filter_var($item_quantities[$i], FILTER_VALIDATE_FLOAT);
        $uom = filter_var($item_uoms[$i], FILTER_SANITIZE_STRING);
        $unit_price = filter_var($item_unit_prices[$i], FILTER_VALIDATE_FLOAT);
        $total_price = $quantity * $unit_price;

        $uploaded_image_path = null;
        if ($item_image_uploads && isset($item_image_uploads['tmp_name'][$i]) && $item_image_uploads['error'][$i] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/quotation_items/'; // Create this directory and make it writable
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $filename = $quotation_id . '_' . $product_id . '_' . time() . '_' . basename($item_image_uploads['name'][$i]);
            $uploaded_image_path = $upload_dir . $filename;
            if (!move_uploaded_file($item_image_uploads['tmp_name'][$i], $uploaded_image_path)) {
                // Handle upload error, maybe log it but don't stop the whole process unless critical
                $uploaded_image_path = null; // Reset if upload failed
            }
        }


        $stmt_item->execute([
            ':quotation_id' => $quotation_id,
            ':product_id' => $product_id,
            ':item_name' => $item_name, // Store the name used at the time of quotation
            ':item_description' => $item_description,
            ':quantity' => $quantity,
            ':unit_of_measurement' => $uom,
            ':unit_price' => $unit_price,
            ':total_price' => $total_price,
            ':item_image_path' => $uploaded_image_path
        ]);
    }

    // If you have a quotation_shops pivot table for many-to-many relationship:
    // $stmt_quot_shop = $conn->prepare("INSERT INTO quotation_shops (quotation_id, shop_id) VALUES (:quotation_id, :shop_id)");
    // foreach ($shop_ids as $s_id) {
    //     $stmt_quot_shop->execute([':quotation_id' => $quotation_id, ':shop_id' => $s_id]);
    // }


    $conn->commit();
    echo json_encode([
        "success" => true,
        "message" => "Quotation created successfully.",
        "quotation_id" => $quotation_id,
        "quotation_number" => $quotation_number
    ]);

} catch (PDOException $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("PDO Error: " . $e->getMessage()); // Log the detailed PDO error
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("General Error: " . $e->getMessage()); // Log the general error
    echo json_encode(["success" => false, "message" => "An error occurred: " . $e->getMessage()]);
} finally {
    $conn = null;
}
?>