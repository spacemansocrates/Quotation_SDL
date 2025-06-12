<?php
// delete_invoice.php

// Start session
session_start();

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    $_SESSION['error_message'] = "You must be logged in to perform this action.";
    header('Location: login.php'); // Adjust to your login page
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php'; // Adjust path as necessary

// We only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: admin_invoices.php');
    exit();
}

$invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
$current_user_id = $_SESSION['user_id'];
$isAdmin = ($_SESSION['user_role'] === 'admin');

if ($invoice_id <= 0) {
    $_SESSION['error_message'] = "Invalid invoice ID provided.";
    header('Location: admin_invoices.php');
    exit();
}

$pdo = null; // Initialize PDO variable
try {
    $pdo = getDatabaseConnection();

    // Fetch invoice to check status, ownership, and payment status
    $stmt_check = DatabaseConfig::executeQuery(
        $pdo,
        "SELECT created_by_user_id, status, invoice_number, total_paid FROM invoices WHERE id = :id",
        [':id' => $invoice_id]
    );
    $invoice_to_delete = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$invoice_to_delete) {
        $_SESSION['error_message'] = "Invoice not found. It may have already been deleted.";
        header('Location: admin_invoices.php');
        exit();
    }

    // --- Permission and Safety Checks ---
    if ((float)$invoice_to_delete['total_paid'] > 0.00) {
        $_SESSION['error_message'] = "Invoice #" . htmlspecialchars($invoice_to_delete['invoice_number']) . " cannot be deleted because it has payments recorded. Please reverse payments first or cancel the invoice.";
        header('Location: admin_invoices.php');
        exit();
    }

    if ($invoice_to_delete['status'] !== 'Draft' && !$isAdmin) { // Admins might have special permissions
        $_SESSION['error_message'] = "Invoice #" . htmlspecialchars($invoice_to_delete['invoice_number']) . " cannot be deleted. Only 'Draft' invoices can be deleted. Current status: " . $invoice_to_delete['status'] . ".";
        header('Location: admin_invoices.php');
        exit();
    }
    
    if (!$isAdmin && $invoice_to_delete['created_by_user_id'] != $current_user_id) {
         $_SESSION['error_message'] = "You do not have permission to delete this invoice.";
        header('Location: admin_invoices.php');
        exit();
    }
    // Admins can proceed if other checks pass.

    // Start a transaction for atomicity
    $pdo->beginTransaction();

    // --- Perform Deletion in Correct Order ---

    // 1. Delete child records first: invoice_items
    DatabaseConfig::executeQuery(
        $pdo,
        "DELETE FROM invoice_items WHERE invoice_id = :invoice_id",
        [':invoice_id' => $invoice_id]
    );

    // 2. SET THE SESSION VARIABLE FOR THE TRIGGER
    // This variable is only alive for the current connection. It tells the trigger who is deleting.
    $pdo->exec("SET @app_user_id = " . (int)$current_user_id);

    // 3. Delete the main invoice record. This will fire the 'trg_invoices_after_delete' trigger.
    $delete_stmt = DatabaseConfig::executeQuery(
        $pdo,
        "DELETE FROM invoices WHERE id = :id",
        [':id' => $invoice_id]
    );
    
    // Check if the main invoice deletion was successful
    if ($delete_stmt->rowCount() > 0) {
        $pdo->commit();
        $_SESSION['success_message'] = "Invoice #" . htmlspecialchars($invoice_to_delete['invoice_number']) . " has been successfully deleted.";
    } else {
        // This case would be rare but could happen in a race condition
        $pdo->rollBack(); 
        $_SESSION['error_message'] = "Failed to delete invoice. It may have been deleted by another user just now.";
    }

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Log the detailed error for the admin, but show a generic message to the user.
    error_log("Error in delete_invoice.php: " . $e->getMessage()); 
    $_SESSION['error_message'] = "A database error occurred while trying to delete the invoice.";
} finally {
    // The connection should be closed if it was opened
    if ($pdo) {
        DatabaseConfig::closeConnection($pdo);
    }
}

// Redirect back to the invoices list
header('Location: admin_invoices.php');
exit();
?>