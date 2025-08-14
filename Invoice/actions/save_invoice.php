<?php
// Start session if you are using $_SESSION for user_id
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../classes/Database.php'; // Adjust path: up one level from 'actions'

// --- Helper function to get current user ID (replace with your actual session logic) ---
function getCurrentUserId() {
    // Replace this with your actual session management to get the logged-in user's ID
    return $_SESSION['user_id'] ?? 1; // Default to 1 if no user is logged in (for development)
}

// --- Helper function to generate a unique invoice number ---
function generateInvoiceNumber($conn) {
    // Example: INV-YYYYMM-XXXX (sequential)
    $prefix = "INV-" . date("Ym") . "-";
    $stmt = $conn->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE :prefix ORDER BY id DESC LIMIT 1");
    $stmt->execute(['prefix' => $prefix . '%']);
    $lastInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNumber = 1;
    if ($lastInvoice) {
        $lastNumPart = substr($lastInvoice['invoice_number'], strlen($prefix));
        $nextNumber = intval($lastNumPart) + 1;
    }
    return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->connect();

    // --- Sanitize and retrieve main invoice data ---
    $shop_id = filter_input(INPUT_POST, 'shop_id', FILTER_VALIDATE_INT);
    $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
    $quotation_id = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE); // Optional
    $invoice_date = filter_input(INPUT_POST, 'invoice_date', FILTER_SANITIZE_STRING);
    $due_date = filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_STRING);
    $payment_terms = filter_input(INPUT_POST, 'payment_terms', FILTER_SANITIZE_STRING);
    $notes_general = filter_input(INPUT_POST, 'notes_general', FILTER_SANITIZE_STRING);
    
    $apply_ppda_levy = isset($_POST['apply_ppda_levy']) ? 1 : 0;
    $ppda_levy_percentage = filter_input(INPUT_POST, 'ppda_levy_percentage', FILTER_VALIDATE_FLOAT);
    $vat_percentage = filter_input(INPUT_POST, 'vat_percentage', FILTER_VALIDATE_FLOAT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    $items = $_POST['items'] ?? [];
    $current_user_id = getCurrentUserId();

    // --- Basic Validation ---
    if (!$shop_id || !$customer_id || !$invoice_date || empty($items)) {
        header('Location: ../create_invoice.php?error=' . urlencode('Missing required fields: Shop, Customer, Invoice Date, or Items.'));
        exit;
    }
    if ($ppda_levy_percentage === false || $vat_percentage === false) {
        header('Location: ../create_invoice.php?error=' . urlencode('Invalid PPDA or VAT percentage.'));
        exit;
    }
    if ($due_date === "") $due_date = null; // Allow empty due date

    // --- Calculate Totals (server-side for security/accuracy) ---
    $gross_total_amount = 0;
    foreach ($items as $item) {
        $quantity = filter_var($item['quantity'], FILTER_VALIDATE_FLOAT);
        $rate_per_unit = filter_var($item['rate_per_unit'], FILTER_VALIDATE_FLOAT);
        if ($quantity !== false && $rate_per_unit !== false) {
            $gross_total_amount += $quantity * $rate_per_unit;
        } else {
             header('Location: ../create_invoice.php?error=' . urlencode('Invalid quantity or rate for an item.'));
             exit;
        }
    }

    $ppda_levy_amount_calc = 0;
    if ($apply_ppda_levy) {
        $ppda_levy_amount_calc = ($gross_total_amount * $ppda_levy_percentage) / 100;
    }
    
    $amount_before_vat_calc = $gross_total_amount + $ppda_levy_amount_calc;
    $vat_amount_calc = ($amount_before_vat_calc * $vat_percentage) / 100;
    $total_net_amount_calc = $amount_before_vat_calc + $vat_amount_calc;

    $invoice_number = generateInvoiceNumber($conn);

    $conn->beginTransaction();
    try {
        // --- Insert into `invoices` table ---
        $stmt_invoice = $conn->prepare("INSERT INTO invoices 
            (invoice_number, shop_id, customer_id, quotation_id, invoice_date, due_date, payment_terms, notes_general, 
             apply_ppda_levy, ppda_levy_percentage, vat_percentage, 
             gross_total_amount, ppda_levy_amount, amount_before_vat, vat_amount, total_net_amount, 
             status, created_by_user_id, updated_by_user_id) 
            VALUES 
            (:invoice_number, :shop_id, :customer_id, :quotation_id, :invoice_date, :due_date, :payment_terms, :notes_general,
             :apply_ppda_levy, :ppda_levy_percentage, :vat_percentage,
             :gross_total_amount, :ppda_levy_amount, :amount_before_vat, :vat_amount, :total_net_amount,
             :status, :created_by_user_id, :updated_by_user_id)");

        $stmt_invoice->execute([
            ':invoice_number' => $invoice_number,
            ':shop_id' => $shop_id,
            ':customer_id' => $customer_id,
            ':quotation_id' => $quotation_id ?: null,
            ':invoice_date' => $invoice_date,
            ':due_date' => $due_date,
            ':payment_terms' => $payment_terms,
            ':notes_general' => $notes_general,
            ':apply_ppda_levy' => $apply_ppda_levy,
            ':ppda_levy_percentage' => $ppda_levy_percentage,
            ':vat_percentage' => $vat_percentage,
            ':gross_total_amount' => round($gross_total_amount, 2),
            ':ppda_levy_amount' => round($ppda_levy_amount_calc, 2),
            ':amount_before_vat' => round($amount_before_vat_calc, 2),
            ':vat_amount' => round($vat_amount_calc, 2),
            ':total_net_amount' => round($total_net_amount_calc, 2),
            ':status' => $status,
            ':created_by_user_id' => $current_user_id,
            ':updated_by_user_id' => $current_user_id 
        ]);
        $invoice_id = $conn->lastInsertId();

        // --- Insert into `invoice_items` table ---
        $stmt_item = $conn->prepare("INSERT INTO invoice_items 
            (invoice_id, product_id, description, quantity, unit_of_measurement, rate_per_unit, total_amount, created_by_user_id, updated_by_user_id) 
            VALUES 
            (:invoice_id, :product_id, :description, :quantity, :unit_of_measurement, :rate_per_unit, :total_amount, :created_by_user_id, :updated_by_user_id)");

        foreach ($items as $item_row) {
            $product_id = filter_var($item_row['product_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            $description = filter_var($item_row['description'], FILTER_SANITIZE_STRING);
            $quantity = filter_var($item_row['quantity'], FILTER_VALIDATE_FLOAT);
            $unit_of_measurement = filter_var($item_row['unit_of_measurement'], FILTER_SANITIZE_STRING);
            $rate_per_unit = filter_var($item_row['rate_per_unit'], FILTER_VALIDATE_FLOAT);
            
            if ($quantity === false || $rate_per_unit === false || empty($description)) {
                throw new Exception("Invalid data for an item: " . htmlspecialchars($description));
            }
            $item_total_amount = $quantity * $rate_per_unit;

            $stmt_item->execute([
                ':invoice_id' => $invoice_id,
                ':product_id' => $product_id ?: null,
                ':description' => $description,
                ':quantity' => $quantity,
                ':unit_of_measurement' => $unit_of_measurement,
                ':rate_per_unit' => $rate_per_unit,
                ':total_amount' => round($item_total_amount, 2),
                ':created_by_user_id' => $current_user_id,
                ':updated_by_user_id' => $current_user_id
            ]);
        }

        // --- (Optional) Update Stock Levels and Log Transaction ---
        // If invoice status means goods are dispatched (e.g., 'Sent', 'Paid'), update stock.
        if ($status == 'Sent' || $status == 'Paid') { // Adjust conditions as needed
            foreach ($items as $item_row) {
                if (!empty($item_row['product_id']) && !empty($item_row['quantity'])) {
                    $product_id_stock = filter_var($item_row['product_id'], FILTER_VALIDATE_INT);
                    $quantity_stock_out = filter_var($item_row['quantity'], FILTER_VALIDATE_FLOAT);

                    if ($product_id_stock && $quantity_stock_out > 0) {
                        // Decrement inventory_stock
                        $stmt_stock_update = $conn->prepare("UPDATE inventory_stock SET 
                            quantity_in_stock = quantity_in_stock - :quantity,
                            total_sold = total_sold + :quantity,
                            last_updated = CURRENT_TIMESTAMP
                            WHERE product_id = :product_id");
                        $stmt_stock_update->execute([
                            ':quantity' => $quantity_stock_out,
                            ':product_id' => $product_id_stock
                        ]);
                        
                        // Get current running balance for stock_transactions
                        $stmt_balance = $conn->prepare("SELECT quantity_in_stock FROM inventory_stock WHERE product_id = :product_id");
                        $stmt_balance->execute([':product_id' => $product_id_stock]);
                        $current_balance = $stmt_balance->fetchColumn() ?: 0;

                        // Log in stock_transactions
                        $stmt_stock_txn = $conn->prepare("INSERT INTO stock_transactions 
                            (product_id, transaction_type, quantity, running_balance, reference_type, reference_id, reference_number, scanned_by_user_id, notes)
                            VALUES
                            (:product_id, 'stock_out', :quantity, :running_balance, 'invoice', :reference_id, :reference_number, :scanned_by_user_id, :notes)");
                        $stmt_stock_txn->execute([
                            ':product_id' => $product_id_stock,
                            ':quantity' => $quantity_stock_out,
                            ':running_balance' => $current_balance, // The balance *after* this transaction
                            ':reference_id' => $invoice_id,
                            ':reference_number' => $invoice_number,
                            ':scanned_by_user_id' => $current_user_id, // Or a specific user if different
                            ':notes' => 'Invoice ' . $invoice_number
                        ]);
                    }
                }
            }
        }

        $conn->commit();
        // Redirect to a success page or the invoice list/view
        header('Location: ../view_invoice.php?id=' . $invoice_id . '&success=' . urlencode('Invoice created successfully!')); // Assuming view_invoice.php exists
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        // Log the error $e->getMessage()
        header('Location: ../create_invoice.php?error=' . urlencode('Error creating invoice: ' . $e->getMessage()));
        exit;
    }

} else {
    // Not a POST request
    header('Location: ../create_invoice.php?error=' . urlencode('Invalid request method.'));
    exit;
}
?>