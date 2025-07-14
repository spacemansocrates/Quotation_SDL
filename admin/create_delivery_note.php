<?php
ob_start();
session_start();

// STRICT ADMIN ACCESS CHECK
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminUserId = (int)$_SESSION['user_id'];
$invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

if ($invoiceId === 0) {
    die("Error: Invalid Invoice ID.");
}

require_once __DIR__ . '/../includes/db_connect.php';

$pdo = getDatabaseConnection();

try {
    // 1. Fetch the invoice and related data
    $stmt = DatabaseConfig::executeQuery($pdo,
        "SELECT i.*, c.customer_code, s.shop_code, s.city 
         FROM invoices i 
         JOIN customers c ON i.customer_id = c.id
         JOIN shops s ON i.shop_id = s.id
         WHERE i.id = :invoiceId AND i.status IN ('Sent', 'Partially Paid', 'Paid')",
        [':invoiceId' => $invoiceId]
    );
    $invoice = $stmt->fetch();

    if (!$invoice) {
        die("Error: Invoice not found or its status does not permit creating a delivery note.");
    }

    // 2. Fetch all items from the invoice
    $itemsStmt = DatabaseConfig::executeQuery($pdo, "SELECT * FROM invoice_items WHERE invoice_id = :invoiceId", [':invoiceId' => $invoiceId]);
    $invoiceItems = $itemsStmt->fetchAll();

    if (empty($invoiceItems)) {
        die("Error: Cannot create a delivery note for an invoice with no items.");
    }
    
    // START TRANSACTION
    $pdo->beginTransaction();

    // 3. Generate a unique Delivery Note number
    $shopId = $invoice['shop_id'];
    $customerId = $invoice['customer_id'];
    $shopCode = $invoice['shop_code'];
    $cityCode = strtoupper(substr($invoice['city'], 0, 2)); // e.g., BT for Blantyre
    $customerCode = $invoice['customer_code'];

    // Lock the sequence row to prevent race conditions
    $seqStmt = $pdo->prepare("SELECT last_sequence_number FROM delivery_note_sequences WHERE shop_id = :shopId AND customer_id = :customerId FOR UPDATE");
    $seqStmt->execute([':shopId' => $shopId, ':customerId' => $customerId]);
    $sequence = $seqStmt->fetchColumn();
    
    $newSequenceNumber = 1;
    if ($sequence !== false) {
        $newSequenceNumber = $sequence + 1;
        $updateSeqStmt = $pdo->prepare("UPDATE delivery_note_sequences SET last_sequence_number = :newNumber WHERE shop_id = :shopId AND customer_id = :customerId");
        $updateSeqStmt->execute([':newNumber' => $newSequenceNumber, ':shopId' => $shopId, ':customerId' => $customerId]);
    } else {
        $insertSeqStmt = $pdo->prepare("INSERT INTO delivery_note_sequences (shop_id, customer_id, last_sequence_number) VALUES (:shopId, :customerId, 1)");
        $insertSeqStmt->execute([':shopId' => $shopId, ':customerId' => $customerId]);
    }
    
    $deliveryNoteNumber = sprintf("%s/%s/%s-%03d", $shopCode, $cityCode, $customerCode, $newSequenceNumber);

    // 4. Insert into delivery_notes table
    $dnStmt = $pdo->prepare(
        "INSERT INTO delivery_notes (delivery_note_number, invoice_id, delivery_date, created_by_user_id) 
         VALUES (:dnNumber, :invoiceId, CURDATE(), :userId)"
    );
    $dnStmt->execute([
        ':dnNumber' => $deliveryNoteNumber,
        ':invoiceId' => $invoiceId,
        ':userId' => $adminUserId
    ]);
    $deliveryNoteId = $pdo->lastInsertId();

    // 5. Insert items into delivery_note_items table
    $dniStmt = $pdo->prepare(
        "INSERT INTO delivery_note_items (delivery_note_id, product_id, description, quantity, unit_of_measurement) 
         VALUES (:dnId, :productId, :description, :quantity, :uom)"
    );
    foreach ($invoiceItems as $item) {
        $dniStmt->execute([
            ':dnId' => $deliveryNoteId,
            ':productId' => $item['product_id'],
            ':description' => $item['description'],
            ':quantity' => $item['quantity'],
            ':uom' => $item['unit_of_measurement']
        ]);
    }

    // COMMIT TRANSACTION
    $pdo->commit();

    // 6. Redirect to view the newly created delivery note
    header("Location: view_delivery_note.php?id=" . $deliveryNoteId);
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in create_delivery_note.php: " . $e->getMessage());
    die("A database error occurred. Please contact support. " . $e->getMessage());
} finally {
    DatabaseConfig::closeConnection($pdo);
    ob_end_flush();
}