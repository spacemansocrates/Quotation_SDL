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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0; // Changed variable name
    $current_user_id = $_SESSION['user_id'];
    $isAdmin = ($_SESSION['user_role'] === 'admin');

    if ($invoice_id <= 0) {
        $_SESSION['error_message'] = "Invalid invoice ID for deletion.";
        header('Location: admin_invoices.php'); // Changed redirect
        exit();
    }

    try {
        $pdo = getDatabaseConnection();

        // Fetch invoice to check status, ownership, and payment status
        $stmt_check = DatabaseConfig::executeQuery(
            $pdo,
            // Added total_paid to the selection
            "SELECT created_by_user_id, status, invoice_number, total_paid FROM invoices WHERE id = :id",
            [':id' => $invoice_id]
        );
        $invoice_to_delete = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$invoice_to_delete) {
            $_SESSION['error_message'] = "Invoice not found.";
            header('Location: admin_invoices.php'); // Changed redirect
            exit();
        }

        // --- Permission and Safety Checks for Invoices ---
        // 1. Check if invoice has any payments recorded
        if ((float)$invoice_to_delete['total_paid'] > 0.00) {
            $_SESSION['error_message'] = "Invoice #" . htmlspecialchars($invoice_to_delete['invoice_number']) . " cannot be deleted because it has payments recorded (Total Paid: " . $invoice_to_delete['total_paid'] . "). Please reverse payments first or cancel the invoice.";
            header('Location: admin_invoices.php');
            exit();
        }

        // 2. Check status: Only 'Draft' invoices (without payments) can be deleted
        if ($invoice_to_delete['status'] !== 'Draft') {
            $_SESSION['error_message'] = "Invoice #" . htmlspecialchars($invoice_to_delete['invoice_number']) . " cannot be deleted. Only 'Draft' invoices (with no payments) can be deleted. Current status: " . $invoice_to_delete['status'] . ". Consider cancelling the invoice instead.";
            header('Location: admin_invoices.php');
            exit();
        }
        
        // 3. Ownership check for non-admins
        if (!$isAdmin && $invoice_to_delete['created_by_user_id'] != $current_user_id) {
             $_SESSION['error_message'] = "You do not have permission to delete this invoice. It was not created by you.";
            header('Location: admin_invoices.php'); // Changed redirect
            exit();
        }
        // Admins can delete 'Draft' invoices (with no payments) regardless of who created them.

        $pdo->beginTransaction();

        // 1. Delete from invoice_items
        DatabaseConfig::executeQuery(
            $pdo,
            "DELETE FROM invoice_items WHERE invoice_id = :invoice_id", // Changed table and FK
            [':invoice_id' => $invoice_id]
        );

        // 2. Delete from invoices
        $delete_stmt = DatabaseConfig::executeQuery(
            $pdo,
            "DELETE FROM invoices WHERE id = :id", // Changed table
            [':id' => $invoice_id]
        );
        
        // 3. (Important Consideration) Stock Adjustments:
        // If deleting this invoice should return items to stock,
        // you would need additional logic here to create reverse stock_transactions.
        // This is not implemented in this basic script.

        // 4. (Optional) Log activity
        // logActivity($current_user_id, 'delete_invoice', 'invoices', $invoice_id, 'Deleted invoice #'.$invoice_to_delete['invoice_number']);

        if ($delete_stmt->rowCount() > 0) {
            $pdo->commit();
            $_SESSION['success_message'] = "Invoice #" . htmlspecialchars($invoice_to_delete['invoice_number']) . " and its items have been successfully deleted.";
        } else {
            $pdo->rollBack(); 
            $_SESSION['error_message'] = "Failed to delete invoice. It might have been already deleted or an issue occurred.";
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in delete_invoice.php: " . $e->getMessage()); // Changed filename in log
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    } finally {
        DatabaseConfig::closeConnection($pdo);
    }

} else {
    $_SESSION['error_message'] = "Invalid request method.";
}

header('Location: admin_invoices.php'); // Changed redirect
exit();
?>