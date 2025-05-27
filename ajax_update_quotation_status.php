<?php
ob_start();
session_start();
header('Content-Type: application/json');

// Ensure this path is correct for your db_connect.php
require_once __DIR__ . '/../includes/db_connect.php'; 

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Security: Only Admins can perform this action
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $response['message'] = 'Unauthorized access. Admin privileges required.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['quotation_id']) || !isset($_POST['new_status'])) {
        $response['message'] = 'Missing required parameters (quotation_id or new_status).';
        echo json_encode($response);
        exit();
    }

    $quotationId = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);
    $newStatus = trim($_POST['new_status']); // 'Approved' or 'Rejected'
    $adminNotes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : null;
    $adminUserId = (int)$_SESSION['user_id'];

    if (!$quotationId) {
        $response['message'] = 'Invalid Quotation ID provided.';
        echo json_encode($response);
        exit();
    }

    $allowedNewStatuses = ['Approved', 'Rejected'];
    if (!in_array($newStatus, $allowedNewStatuses)) {
        $response['message'] = 'Invalid new status. Must be "Approved" or "Rejected".';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getDatabaseConnection();

        // Optional: Check current status of the quotation before updating
        $stmtCheck = DatabaseConfig::executeQuery($pdo, "SELECT status FROM quotations WHERE id = :id", [':id' => $quotationId]);
        $currentQuotation = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$currentQuotation) {
            $response['message'] = 'Quotation not found.';
            echo json_encode($response);
            DatabaseConfig::closeConnection($pdo);
            exit();
        }

        // Business logic: For example, only 'Submitted' or 'Draft' quotations can be approved/rejected by an admin.
        // You can customize this logic as needed.
        if (!in_array($currentQuotation['status'], ['Submitted', 'Draft'])) {
            $response['message'] = "Quotation is currently '{$currentQuotation['status']}' and cannot be directly changed to '{$newStatus}'.";
           // echo json_encode($response); // Uncomment if you want to enforce this strictly
           // DatabaseConfig::closeConnection($pdo);
           // exit();
        }

        $sql = "UPDATE quotations 
                SET 
                    status = :newStatus, 
                    approved_by_user_id = :adminUserId, 
                    approval_date = NOW(),
                    admin_notes = :adminNotes 
                WHERE id = :quotationId";
        
        $params = [
            ':newStatus' => $newStatus,
            ':adminUserId' => $adminUserId,
            ':adminNotes' => $adminNotes, // PDO handles null if $adminNotes is null
            ':quotationId' => $quotationId
        ];

        $updateStmt = DatabaseConfig::executeQuery($pdo, $sql, $params);

        if ($updateStmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = "Quotation #{$quotationId} status successfully updated to {$newStatus}.";
            // Optional: Log this action
            error_log("Admin (ID: {$adminUserId}) set quotation (ID: {$quotationId}) to {$newStatus}. Notes: {$adminNotes}");
        } else {
            // This could mean the quotation was already in the newStatus or ID not found, though we checked earlier.
            $response['message'] = 'Failed to update quotation status. No changes were made or quotation not found.';
        }

        DatabaseConfig::closeConnection($pdo);

    } catch (PDOException $e) {
        error_log("Database error in ajax_update_quotation_status.php: " . $e->getMessage());
        // Avoid exposing detailed SQL errors to the client in production
        $response['message'] = 'A database error occurred while updating the quotation.'; 
    }
} else {
    $response['message'] = 'Invalid request method. Only POST is accepted.';
}

echo json_encode($response);
ob_end_flush();
?>