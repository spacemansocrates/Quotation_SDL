<?php
session_start();
header('Content-Type: application/json');

// Security check: ensure user is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoiceId = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
    $newStatus = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
    $adminUserId = (int)$_SESSION['user_id'];

    // Validate the input
    $allowedStatuses = ['Sent', 'Cancelled'];
    if ($invoiceId > 0 && in_array($newStatus, $allowedStatuses)) {
        $pdo = null;
        try {
            $pdo = getDatabaseConnection();
            
            // Check the current status of the invoice to ensure it's a 'Draft'
            $stmtCheck = $pdo->prepare("SELECT status FROM invoices WHERE id = :id");
            $stmtCheck->execute([':id' => $invoiceId]);
            $currentStatus = $stmtCheck->fetchColumn();

            if ($currentStatus === 'Draft') {
                $pdo->beginTransaction();

                // Update the invoice status and the user who updated it
                $updateQuery = "
                    UPDATE invoices 
                    SET 
                        status = :newStatus, 
                        updated_by_user_id = :adminUserId,
                        updated_at = NOW()
                    WHERE id = :invoiceId AND status = 'Draft'
                ";
                
                $stmt = $pdo->prepare($updateQuery);
                $stmt->execute([
                    ':newStatus' => $newStatus,
                    ':adminUserId' => $adminUserId,
                    ':invoiceId' => $invoiceId
                ]);
                
                $rowCount = $stmt->rowCount();
                $pdo->commit();

                if ($rowCount > 0) {
                    // The trigger on the 'invoices' table will handle the activity log
                    $response = ['success' => true];
                } else {
                    $response['message'] = 'Invoice could not be updated. It might have been updated by someone else.';
                }

            } else {
                 $response['message'] = "This action is only allowed for invoices in 'Draft' status. Current status: " . htmlspecialchars($currentStatus);
            }

        } catch (PDOException $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Invoice status update failed: " . $e->getMessage());
            $response['message'] = 'Database error during update.';
        } finally {
            DatabaseConfig::closeConnection($pdo);
        }

    } else {
        $response['message'] = 'Invalid invoice ID or status provided.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);