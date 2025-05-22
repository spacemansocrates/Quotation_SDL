<?php
// Start session
session_start();

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $_SESSION['error_message'] = "You must be logged in to perform this action.";
    header('Location: login.php'); // Adjust to your login page
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quotation_id = isset($_POST['quotation_id']) ? (int)$_POST['quotation_id'] : 0;
    $current_user_id = $_SESSION['user_id'];
    $isAdmin = ($_SESSION['role'] === 'admin');

    if ($quotation_id <= 0) {
        $_SESSION['error_message'] = "Invalid quotation ID for deletion.";
        header('Location: admin_quotations.php');
        exit();
    }

    try {
        $pdo = getDatabaseConnection();

        // Fetch quotation to check status and ownership
        $stmt_check = DatabaseConfig::executeQuery(
            $pdo,
            "SELECT created_by_user_id, status, quotation_number FROM quotations WHERE id = :id",
            [':id' => $quotation_id]
        );
        $quotation_to_delete = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$quotation_to_delete) {
            $_SESSION['error_message'] = "Quotation not found.";
            header('Location: admin_quotations.php');
            exit();
        }

        // Check permissions: Only 'Draft' status can be deleted by owner or admin
        // Admins can delete any status (you might want to restrict this further)
        if ($quotation_to_delete['status'] !== 'Draft' && !$isAdmin) {
            $_SESSION['error_message'] = "Quotation cannot be deleted because its status is not 'Draft'.";
            header('Location: admin_quotations.php');
            exit();
        }
        if (!$isAdmin && $quotation_to_delete['created_by_user_id'] != $current_user_id) {
             $_SESSION['error_message'] = "You do not have permission to delete this quotation.";
            header('Location: admin_quotations.php');
            exit();
        }


        $pdo->beginTransaction();

        // 1. Delete from quotation_items
        DatabaseConfig::executeQuery(
            $pdo,
            "DELETE FROM quotation_items WHERE quotation_id = :quotation_id",
            [':quotation_id' => $quotation_id]
        );

        // 2. Delete from quotations
        $delete_stmt = DatabaseConfig::executeQuery(
            $pdo,
            "DELETE FROM quotations WHERE id = :id",
            [':id' => $quotation_id]
        );

        // 3. (Optional) Log activity
        // Assuming you have a function/method to log to activity_log
        // logActivity($current_user_id, 'delete_quotation', 'quotations', $quotation_id, 'Deleted quotation #'.$quotation_to_delete['quotation_number']);
        // For simplicity, I'll skip the actual logging call here.

        if ($delete_stmt->rowCount() > 0) {
            $pdo->commit();
            $_SESSION['success_message'] = "Quotation #" . htmlspecialchars($quotation_to_delete['quotation_number']) . " and its items have been successfully deleted.";
        } else {
            $pdo->rollBack(); // Should not happen if initial check found the quote
            $_SESSION['error_message'] = "Failed to delete quotation. It might have been already deleted.";
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in delete_quotation.php: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    } finally {
        DatabaseConfig::closeConnection($pdo);
    }

} else {
    $_SESSION['error_message'] = "Invalid request method.";
}

header('Location: admin_quotations.php');
exit();
?>