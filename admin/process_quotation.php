<?php
// process_quotation.php
session_start(); // <------------------------------------------------- ADD 

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
    $shop_ids = isset($_POST['shops']) ? (array)$_POST['shops'] : [];
    $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
    $quotation_date = filter_input(INPUT_POST, 'quotation_date', FILTER_SANITIZE_STRING);
    $company_tpin = filter_input(INPUT_POST, 'company_tpin', FILTER_SANITIZE_STRING);

    // --- User ID - Retrieve from session ---
    if (!isset($_SESSION['user_id'])) { // <------------------------ CHANGE THIS SECTION
        // If user_id is not set in session, the user is not logged in properly.
        throw new Exception("User not authenticated. Please log in.");
    }
    $created_by_user_id = $_SESSION['user_id']; // Use the actual logged-in user's ID
    $updated_by_user_id = $created_by_user_id;  // On creation, updated_by is same as created_by
    // --- End User ID ---

    if (empty($shop_ids) || !$customer_id || empty($quotation_date)) {
        throw new Exception("Shop, Customer, or Quotation Date is missing.");
    }
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
                quotation_id,
                product_id,
                item_number,
                description,
                image_path_override,
                quantity,
                unit_of_measurement, -- Changed from unit_of_measurement_id
                rate_per_unit,
                total_amount,
                created_at,
                updated_at,
                created_by_user_id,
                updated_by_user_id
              ) VALUES (
                :quotation_id,
                :product_id,
                :item_number,
                :description,
                :image_path_override,
                :quantity,
                :unit_of_measurement, -- Changed from :unit_of_measurement_id
                :rate_per_unit,
                :total_amount,
                NOW(),
                NOW(),
                :created_by_user_id,
                :updated_by_user_id
              )";
$stmt_item = $conn->prepare($sql_item);

$item_product_ids = isset($_POST['product_id']) ? $_POST['product_id'] : [];
$item_names_from_form = isset($_POST['item_name']) ? $_POST['item_name'] : []; // Original product name, can be stored in description or if you add a field for it.
$item_descriptions_from_form = isset($_POST['item_description']) ? $_POST['item_description'] : [];
$item_quantities = isset($_POST['item_quantity']) ? $_POST['item_quantity'] : [];
$item_uom_ids_from_form = isset($_POST['item_uom']) ? $_POST['item_uom'] : []; // IMPORTANT: This should ideally be the ID from units_of_measurement table
$item_unit_prices = isset($_POST['item_unit_price']) ? $_POST['item_unit_price'] : [];

// Handle image uploads for items
$item_image_uploads = isset($_FILES['item_image_upload']) ? $_FILES['item_image_upload'] : null;
$item_number_counter = 0; // Initialize item number for this quotation

for ($i = 0; $i < count($item_product_ids); $i++) {
    if (empty($item_product_ids[$i]) && empty($item_descriptions_from_form[$i])) { // Skip if no product ID and no manual description
        continue; // Skip potentially empty rows if item adding is very dynamic
    }

    $item_number_counter++; // Increment item number

    // Sanitize and validate inputs for each item
    $product_id = !empty($item_product_ids[$i]) ? filter_var($item_product_ids[$i], FILTER_VALIDATE_INT) : null;

    // Use the specific item description entered, fallback to product name if description field was left linked to product's default
    $description = filter_var($item_descriptions_from_form[$i], FILTER_SANITIZE_STRING);
    if (empty($description) && $product_id && !empty($item_names_from_form[$i])) { // If no override description, use product name
         $description = filter_var($item_names_from_form[$i], FILTER_SANITIZE_STRING);
    }

    $quantity = filter_var($item_quantities[$i], FILTER_VALIDATE_FLOAT);
    // IMPORTANT: If item_uom_ids_from_form[$i] is not already an ID, you need to fetch it here.
    // For example, if it's a code like 'PCS', you'd do:
    // $uom_code = filter_var($item_uom_ids_from_form[$i], FILTER_SANITIZE_STRING);
    // $stmt_uom = $conn->prepare("SELECT id FROM units_of_measurement WHERE code = ?");
    // $stmt_uom->execute([$uom_code]);
    // $unit_of_measurement_id = $stmt_uom->fetchColumn();
    // If it's directly the ID from a select dropdown:
$unit_of_measurement_for_db = filter_var($item_uom_ids_from_form[$i], FILTER_SANITIZE_STRING); // This will hold the string from the form


    $rate_per_unit = filter_var($item_unit_prices[$i], FILTER_VALIDATE_FLOAT);
    $total_amount = $quantity * $rate_per_unit;

    $uploaded_image_path_override = null;
    if ($item_image_uploads && isset($item_image_uploads['tmp_name'][$i]) && $item_image_uploads['error'][$i] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/quotation_item_images/'; // Create this directory and make it writable
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true); // Use 0775 for better security if possible
        }
        // Sanitize filename, make it unique
        $original_filename = basename($item_image_uploads['name'][$i]);
        $safe_filename = preg_replace("/[^A-Za-z0-9._-]/", "", $original_filename); // Basic sanitization
        $extension = pathinfo($safe_filename, PATHINFO_EXTENSION);
        $filename_no_ext = pathinfo($safe_filename, PATHINFO_FILENAME);
        $final_filename = $quotation_id . '_' . $item_number_counter . '_' . time() . '_' . $filename_no_ext . '.' . $extension;
        $uploaded_image_path_override = $upload_dir . $final_filename;

        if (!move_uploaded_file($item_image_uploads['tmp_name'][$i], $uploaded_image_path_override)) {
            // Handle upload error, maybe log it but don't stop the whole process unless critical
            error_log("Failed to upload item image: " . $item_image_uploads['name'][$i] . " for quotation " . $quotation_id);
            $uploaded_image_path_override = null; // Reset if upload failed
        }
    }

    $stmt_item->execute([
        ':quotation_id' => $quotation_id,
        ':product_id' => $product_id, // Can be NULL if it's a custom item without a product link
        ':item_number' => $item_number_counter,
        ':description' => $description,
        ':image_path_override' => $uploaded_image_path_override,
        ':quantity' => $quantity,
        ':unit_of_measurement' => $unit_of_measurement_for_db, 
        ':rate_per_unit' => $rate_per_unit,
        ':total_amount' => $total_amount,
        ':created_by_user_id' => $created_by_user_id,
        ':updated_by_user_id' => $created_by_user_id // On creation, created_by and updated_by are the same
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