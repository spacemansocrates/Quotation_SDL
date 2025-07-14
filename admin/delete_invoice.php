<?php
/**
 * delete_invoice.php
 * A robust and secure script to delete an invoice and its items.
 * Returns a JSON response for AJAX calls.
 */

// --- BOILERPLATE AND INITIALIZATION ---
header('Content-Type: application/json');
session_start();

$response = [
    'success' => false,
    'message' => 'An unknown error occurred. Script execution did not complete.'
];

// --- SECURITY AND VALIDATION CHECKS ---

// 1. Check for user authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    $response['message'] = 'Authentication Error: You must be logged in to perform this action.';
    echo json_encode($response);
    exit();
}

// 2. Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid Request: This action requires a POST request.';
    echo json_encode($response);
    exit();
}

// 3. Validate the invoice ID
$invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
if ($invoice_id <= 0) {
    $response['message'] = 'Invalid Input: No valid invoice ID was provided.';
    echo json_encode($response);
    exit();
}

// --- DATABASE OPERATIONS ---

require_once __DIR__ . '/../includes/db_connect.php'; 
$pdo = null;

try {
    $pdo = getDatabaseConnection();
    
    // 4. Fetch the invoice to verify its existence and check permissions
    $stmt_check = DatabaseConfig::executeQuery($pdo, 
        "SELECT created_by_user_id, status, invoice_number, total_paid FROM invoices WHERE id = :id",
        [':id' => $invoice_id]
    );
    $invoice = $stmt_check->fetch(PDO::FETCH_ASSOC);

    // 5. Enforce Business Rules (Server-side)
    if (!$invoice) {
        throw new Exception("Invoice not found. It may have been deleted by another user.");
    }

    $isAdmin = ($_SESSION['user_role'] === 'admin');
    $current_user_id = (int)$_SESSION['user_id'];

    // Rule: Cannot delete if payments exist
    if ((float)$invoice['total_paid'] > 0) {
        throw new Exception("Cannot delete invoice #{$invoice['invoice_number']} because payments have been recorded. Please reverse the payments first.");
    }

    // Rule: Non-admins can only delete their own 'Draft' invoices
    if (!$isAdmin) {
        if ($invoice['status'] !== 'Draft') {
            throw new Exception("You can only delete invoices with a 'Draft' status. This invoice status is '{$invoice['status']}'.");
        }
        if ($invoice['created_by_user_id'] !== $current_user_id) {
            throw new Exception("Permission Denied: You can only delete your own invoices.");
        }
    }
    // Note: Admins can bypass the status/ownership checks but not the payment check.

    // 6. Begin Transaction for atomic operations
    $pdo->beginTransaction();

    // 7. Set the session variable for the database trigger
    // This is critical for your activity log.
    $pdo->exec("SET @app_user_id = " . $current_user_id);

    // 8. Delete child records first to avoid foreign key errors
    DatabaseConfig::executeQuery($pdo, "DELETE FROM invoice_items WHERE invoice_id = :invoice_id", [':invoice_id' => $invoice_id]);

    // 9. Delete the main invoice record
    $delete_stmt = DatabaseConfig::executeQuery($pdo, "DELETE FROM invoices WHERE id = :id", [':id' => $invoice_id]);

    // 10. Verify the deletion and commit
    if ($delete_stmt->rowCount() > 0) {
        $pdo->commit();
        $response['success'] = true;
        $response['message'] = "Invoice #{$invoice['invoice_number']} was successfully deleted.";
    } else {
        // This can happen if the trigger fails silently (with a CONTINUE HANDLER)
        throw new Exception("The invoice was not deleted. This can happen if the database trigger fails without reporting an error. Please check the trigger logic.");
    }

} catch (PDOException $e) {
    // --- THIS IS THE MOST IMPORTANT PART FOR DEBUGGING ---
    // This block catches errors from the DATABASE, including trigger failures.
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = "DATABASE ERROR: " . $e->getMessage();
    // Log the full error for your own records
    error_log("DELETE INVOICE PDO ERROR: " . $e->getMessage());

} catch (Exception $e) {
    // This block catches the business rule errors thrown above.
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = $e->getMessage();

} finally {
    // 11. Always close the connection and send the response
    if ($pdo) {
        DatabaseConfig::closeConnection($pdo);
    }
    echo json_encode($response);
    exit();
}