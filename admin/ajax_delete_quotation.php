<?php
// ajax_delete_quotation.php
header('Content-Type: application/json');
ob_start(); // Good practice to prevent premature output
session_start();

// Adjust paths to be relative to ajax_delete_quotation.php
// Assuming ajax_delete_quotation.php is in the same directory as view_quotation.php,
// and your 'includes' folder is one level up.
require_once __DIR__ . '/../includes/db_connect.php'; // For getDatabaseConnection() and DatabaseConfig
// require_once __DIR__ . '/../includes/functions.php'; // If you have a functions.php for isUserLoggedIn, $userId, $isAdmin

// Check for user authentication (using session variables from view_quotation.php)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

if (!isset($_POST['quotation_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing Quotation ID.']);
    exit;
}

$quotation_id = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);

if (!$quotation_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Quotation ID.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$isAdmin = $_SESSION['user_role'] === 'admin';
$pdo = null; // Initialize pdo variable

try {
    $pdo = getDatabaseConnection(); // Use your existing function
    $pdo->beginTransaction();

    // --- Permission Check (RECOMMENDED TO UNCOMMENT AND REFINE) ---
    // Fetch quotation details to check status and ownership
    $stmt_check = DatabaseConfig::executeQuery(
        $pdo,
        "SELECT status, created_by_user_id FROM quotations WHERE id = :id",
        [':id' => $quotation_id]
    );
    $quotation_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$quotation_data) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Quotation not found.']);
        exit;
    }

    // Only allow deletion if status is 'Draft' AND (user is admin OR user is the creator)
    // Or if user is admin, they can delete regardless of status (adjust this logic as needed)
    $canDelete = false;
    if ($isAdmin) {
        $canDelete = true; // Admins can delete any (or adjust if there are statuses they can't delete)
    } elseif ($quotation_data['status'] === 'Draft' && $quotation_data['created_by_user_id'] == $userId) {
        $canDelete = true;
    }

    if (!$canDelete) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Permission denied or quotation cannot be deleted in its current status.']);
        exit;
    }
    // --- End Permission Check ---


    // 1. Delete related items from quotation_items
    DatabaseConfig::executeQuery(
        $pdo,
        "DELETE FROM quotation_items WHERE quotation_id = :quotation_id",
        [':quotation_id' => $quotation_id]
    );

    // 2. Delete the quotation from quotations table
    $stmt_delete_quotation = DatabaseConfig::executeQuery(
        $pdo,
        "DELETE FROM quotations WHERE id = :quotation_id",
        [':quotation_id' => $quotation_id]
    );

    if ($stmt_delete_quotation->rowCount() > 0) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Quotation deleted successfully.']);
    } else {
        $pdo->rollBack(); // Rollback if no rows were deleted (e.g., quotation already gone)
        echo json_encode(['success' => false, 'message' => 'Quotation could not be deleted or was not found.']);
    }

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in ajax_delete_quotation.php: " . $e->getMessage()); // Log the actual error
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']); // User-friendly message
} finally {
    if ($pdo) {
        DatabaseConfig::closeConnection($pdo); // Close connection if your class has this method
    }
    ob_end_flush(); // Send output
}
?>