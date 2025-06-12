<?php
// includes/invoice_functions.php

// Function to generate a unique invoice number
function generateInvoiceNumber($shop_id, $customer_id, $conn) {
    // Get shop_code and customer_code for formatting
    $shop_code_query = "SELECT shop_code FROM shops WHERE id = ? LIMIT 1";
    $stmt_shop = $conn->prepare($shop_code_query);
    $stmt_shop->bind_param("i", $shop_id);
    $stmt_shop->execute();
    $result_shop = $stmt_shop->get_result();
    $shop_data = $result_shop->fetch_assoc();
    $shop_code = $shop_data ? $shop_data['shop_code'] : 'UNK';

    $customer_code_query = "SELECT customer_code FROM customers WHERE id = ? LIMIT 1";
    $stmt_customer = $conn->prepare($customer_code_query);
    $stmt_customer->bind_param("i", $customer_id);
    $stmt_customer->execute();
    $result_customer = $stmt_customer->get_result();
    $customer_data = $result_customer->fetch_assoc();
    $customer_code = $customer_data ? $customer_data['customer_code'] : 'UNK';


    // Get last sequence number and increment
    $query = "INSERT INTO invoice_sequences (shop_id, customer_id, last_sequence_number)
              VALUES (?, ?, 1)
              ON DUPLICATE KEY UPDATE last_sequence_number = last_sequence_number + 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $shop_id, $customer_id);
    $stmt->execute();

    // Fetch the updated sequence number
    $query_select = "SELECT last_sequence_number FROM invoice_sequences WHERE shop_id = ? AND customer_id = ?";
    $stmt_select = $conn->prepare($query_select);
    $stmt_select->bind_param("ii", $shop_id, $customer_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    $row = $result->fetch_assoc();
    $sequence_number = $row ? $row['last_sequence_number'] : 1;

    // Format the sequence number with leading zeros (e.g., 001, 010)
    $formatted_sequence = sprintf("%03d", $sequence_number);

    return "INV-" . strtoupper($shop_code) . "/CUST" . strtoupper($customer_code) . "-" . $formatted_sequence;
}

// Function to calculate invoice totals
function calculateInvoiceTotals($invoice_items_array, $apply_ppda_levy, $ppda_levy_percentage, $vat_percentage) {
    $gross_total_amount = 0;
    foreach ($invoice_items_array as $item) {
        // Ensure quantity and rate_per_unit are numeric and not empty
        $quantity = is_numeric($item['quantity']) ? (float)$item['quantity'] : 0;
        $rate_per_unit = is_numeric($item['rate_per_unit']) ? (float)$item['rate_per_unit'] : 0;
        $gross_total_amount += ($quantity * $rate_per_unit);
    }

    $ppda_levy_amount = 0;
    if ($apply_ppda_levy) {
        $ppda_levy_amount = $gross_total_amount * ($ppda_levy_percentage / 100);
    }

    $amount_before_vat = $gross_total_amount + $ppda_levy_amount;
    $vat_amount = $amount_before_vat * ($vat_percentage / 100);
    $total_net_amount = $amount_before_vat + $vat_amount;

    return [
        'gross_total_amount' => round($gross_total_amount, 2),
        'ppda_levy_amount' => round($ppda_levy_amount, 2),
        'amount_before_vat' => round($amount_before_vat, 2),
        'vat_amount' => round($vat_amount, 2),
        'total_net_amount' => round($total_net_amount, 2),
    ];
}

// Function to update invoice status based on due date and payments
function updateInvoiceStatus($invoice_id, $conn, $total_paid, $total_net_amount, $due_date) {
    $current_status_query = "SELECT status FROM invoices WHERE id = ?";
    $stmt = $conn->prepare($current_status_query);
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_invoice_status = $result->fetch_assoc()['status'];

    $new_status = $current_invoice_status; // Default to current status

    if ($total_net_amount <= 0) { // Handle cases where total_net_amount might be 0 or negative
         if ($current_invoice_status != 'Draft' && $current_invoice_status != 'Cancelled') {
             $new_status = 'Paid'; // If no amount is due, it's considered paid
         }
    } elseif ($total_paid >= $total_net_amount) {
        $new_status = 'Paid';
    } elseif ($total_paid > 0 && $total_paid < $total_net_amount) {
        $new_status = 'Partially Paid';
    } else { // total_paid is 0 or less
        // Only set to overdue if it was Finalized or partially paid and due date passed
        if (strtotime($due_date) < time() && ($current_invoice_status == 'Finalized' || $current_invoice_status == 'Partially Paid')) {
            $new_status = 'Overdue';
        } elseif ($current_invoice_status == 'Draft' || $current_invoice_status == 'Cancelled') {
            // Keep draft/cancelled status
            $new_status = $current_invoice_status;
        } else {
            $new_status = 'Finalized'; // Default for unpaid if not Draft/Cancelled/Overdue
        }
    }

    // Update the invoice status in the database
    $update_query = "UPDATE invoices SET status = ?, updated_by_user_id = ? WHERE id = ?";
    $stmt_update = $conn->prepare($update_query);
    $user_id = $_SESSION['user_id']; // Assuming user_id is in session
    $stmt_update->bind_param("sii", $new_status, $user_id, $invoice_id);
    $stmt_update->execute();

    return $new_status;
}

?>