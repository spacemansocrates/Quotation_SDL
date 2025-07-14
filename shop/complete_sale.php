<?php
// --- complete_sale.php (REWRITTEN & CORRECTED) ---

header('Content-Type: application/json');
session_start();

// --- 1. DATABASE CONNECTION ---
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = ''; // Your password
$db_name = 'supplies';
$db_port = 3306;

// Enable error reporting to catch issues during development
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// --- 2. GET AND VALIDATE INPUT ---
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['shop_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication error. Please log in.']);
    exit();
}

$cart = $data['cart'] ?? [];
$paymentMethod = $data['paymentMethod'] ?? 'Cash';
$discount = $data['discount'] ?? ['type' => 'none', 'value' => 0];

if (empty($cart)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot complete sale. The cart is empty.']);
    exit();
}

// User, Shop, and default Customer IDs
$userId = (int)$_SESSION['user_id'];
$shopId = (int)$_SESSION['shop_id'];
$walkInCustomerId = 7; // Your "Walk-in Customer" ID

// --- 3. SERVER-SIDE CALCULATIONS ---
// Recalculate everything on the server to ensure data integrity.
$gross_total = 0;
foreach ($cart as $item) {
    $gross_total += (float)$item['price'] * (int)$item['quantity'];
}

$discount_amount = 0;
if ($discount['type'] === 'percentage' && $discount['value'] > 0) {
    $discount_amount = $gross_total * ((float)$discount['value'] / 100);
} elseif ($discount['type'] === 'fixed' && $discount['value'] > 0) {
    $discount_amount = (float)$discount['value'];
}

if ($discount_amount > $gross_total) {
    $discount_amount = $gross_total;
}

$total_after_discount = $gross_total - $discount_amount;
$vat_percentage = 16.5;
$vat_amount = $total_after_discount * ($vat_percentage / 100);
$total_net_amount = $total_after_discount + $vat_amount;


// --- 4. DATABASE TRANSACTION ---
$mysqli->begin_transaction();

try {
    // --- Step A: Create the Invoice ---
    $invoice_number = 'POS-' . $shopId . '-' . time();
    
    $stmt_invoice = $mysqli->prepare(
        "INSERT INTO invoices (invoice_number, shop_id, customer_id, invoice_date, gross_total_amount, discount_type, discount_value, discount_amount, vat_percentage, vat_amount, total_net_amount, total_paid, status, created_by_user_id, payment_terms) 
         VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'Paid', ?, ?)"
    );

    $discountTypeForDb = ($discount['type'] === 'none') ? null : $discount['type'];
    $discountValueForDb = ($discount['type'] === 'none') ? 0 : (float)$discount['value'];

    $stmt_invoice->bind_param(
        "siidsddddddis",      // Correct 13-character string for 13 parameters
        $invoice_number,         // s
        $shopId,                 // i
        $walkInCustomerId,       // i
        $gross_total,            // d
        $discountTypeForDb,      // s
        $discountValueForDb,     // d
        $discount_amount,        // d
        $vat_percentage,         // d
        $vat_amount,             // d
        $total_net_amount,       // d (for total_net_amount)
        $total_net_amount,       // d (for total_paid)
        $userId,                 // i
        $paymentMethod           // s
    );
    $stmt_invoice->execute();
    if ($stmt_invoice->affected_rows === 0) {
        throw new Exception("Failed to create the invoice record.");
    }
    $invoice_id = $mysqli->insert_id;
    $stmt_invoice->close();

    // --- Step B: Insert Invoice Items and Update Stock ---
    // Prepare statements outside the loop for efficiency
    $stmt_items = $mysqli->prepare(
        "INSERT INTO invoice_items (invoice_id, product_id, description, quantity, rate_per_unit, total_amount, created_by_user_id) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt_stock = $mysqli->prepare(
        "UPDATE shop_stock SET quantity_in_stock = quantity_in_stock - ? 
         WHERE product_id = ? AND shop_id = ? AND quantity_in_stock >= ?"
    );

    foreach ($cart as $item) {
        $productId = (int)$item['id'];
        $name = (string)$item['name'];
        $price = (float)$item['price'];
        
        // Use separate variables for clarity, as DB columns have different types
        $quantity_decimal = (float)$item['quantity']; // For invoice_items.quantity (DECIMAL)
        $quantity_integer = (int)$item['quantity'];   // For shop_stock.quantity (INT)
        
        $line_total = $price * $quantity_decimal;

        // **FIXED**: The type string `iisdddi` now correctly matches the data types
        // i: invoice_id, i: product_id, s: description, d: quantity, d: rate_per_unit, d: total_amount, i: created_by_user_id
        $stmt_items->bind_param("iisdddi", $invoice_id, $productId, $name, $quantity_decimal, $price, $line_total, $userId);
        $stmt_items->execute();
        if ($stmt_items->affected_rows === 0) {
            throw new Exception("Failed to add item '{$name}' to the invoice.");
        }

        // Update stock using the integer quantity
        $stmt_stock->bind_param("iiii", $quantity_integer, $productId, $shopId, $quantity_integer);
        $stmt_stock->execute();
        if ($stmt_stock->affected_rows === 0) {
            throw new Exception("Insufficient stock for product '{$name}'. Sale cannot be completed.");
        }
    }
    $stmt_items->close();
    $stmt_stock->close();
    
    // --- Step C: Record the Payment ---
    $stmt_payment = $mysqli->prepare(
        "INSERT INTO payments (invoice_id, customer_id, payment_date, amount_paid, payment_method, recorded_by_user_id)
         VALUES (?, ?, CURDATE(), ?, ?, ?)"
    );
    $stmt_payment->bind_param("iidsi", $invoice_id, $walkInCustomerId, $total_net_amount, $paymentMethod, $userId);
    $stmt_payment->execute();
    if ($stmt_payment->affected_rows === 0) {
        throw new Exception("Failed to record the payment for the invoice.");
    }
    $stmt_payment->close();

    // If we reach here, all database operations were successful. Commit them.
    $mysqli->commit();

    // --- 5. SEND SUCCESS RESPONSE ---
    http_response_code(200);
    echo json_encode([
        'message' => 'Sale completed successfully!',
        'invoice_number' => $invoice_number,
        'invoice_id' => $invoice_id
    ]);

} catch (Exception $e) {
    // An error occurred in the `try` block. Roll back all changes.
    $mysqli->rollback();
    
    http_response_code(500);
    // Send back the specific error message for easier debugging on the frontend.
    echo json_encode(['error' => $e->getMessage()]);

} finally {
    // This block runs whether the transaction succeeded or failed.
    $mysqli->close();
}