<?php
// helpers.php

// Make sure it can access the database connection class
require_once __DIR__ . '/../includes/db_connect.php'; 


/**
 * Generates a unique, sequential invoice number for a given shop and customer.
 * It safely handles concurrency by using an atomic database operation.
 *
 * @param int $shop_id
 * @param int $customer_id
 * @return string The formatted invoice number.
 * @throws Exception if shop or customer codes cannot be found.
 */
function generateInvoiceNumber(int $shop_id, int $customer_id): string {
    $pdo = getDatabaseConnection(); // Use your helper to get a connection

    // 1. Get shop and customer codes
    $shopStmt = DatabaseConfig::executeQuery($pdo, "SELECT shop_code FROM shops WHERE id = ?", [$shop_id]);
    $shop = $shopStmt->fetch();
    if (!$shop) {
        throw new Exception("Shop with ID {$shop_id} not found.");
    }
    $shop_code = $shop['shop_code'];

    $customerStmt = DatabaseConfig::executeQuery($pdo, "SELECT customer_code FROM customers WHERE id = ?", [$customer_id]);
    $customer = $customerStmt->fetch();
    if (!$customer) {
        throw new Exception("Customer with ID {$customer_id} not found.");
    }
    $customer_code = $customer['customer_code'];
    
    // 2. Atomically update and get the next sequence number
    // Using INSERT ... ON DUPLICATE KEY UPDATE is a safe way to handle this
    $updateSql = "INSERT INTO invoice_sequences (shop_id, customer_id, last_sequence_number) 
                  VALUES (?, ?, 1) 
                  ON DUPLICATE KEY UPDATE last_sequence_number = last_sequence_number + 1";
    DatabaseConfig::executeQuery($pdo, $updateSql, [$shop_id, $customer_id]);

    // 3. Retrieve the newly updated sequence number
    $seqStmt = DatabaseConfig::executeQuery($pdo, "SELECT last_sequence_number FROM invoice_sequences WHERE shop_id = ? AND customer_id = ?", [$shop_id, $customer_id]);
    $sequence = $seqStmt->fetch();
    $next_number = $sequence['last_sequence_number'];

    // 4. Format the invoice number string
    $year = date('Y');
    $month = date('m');
    $padded_seq = str_pad($next_number, 3, '0', STR_PAD_LEFT);

    return "INV/{$year}/{$month}/{$shop_code}/{$customer_code}-{$padded_seq}";
}