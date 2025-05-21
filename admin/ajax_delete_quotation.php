<?php
// ajax_delete_quotation.php
header('Content-Type: application/json');

// Start session, include DB connection, check permissions
// session_start();
// require_once 'config/db_connect.php';
// require_once 'includes/functions.php'; // For $userId, $isAdmin if needed for permission checks

// if (!isUserLoggedIn() || !isset($_POST['quotation_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized or missing ID.']);
//     exit;
// }

$quotation_id = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);

if (!$quotation_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Quotation ID.']);
    exit;
}

try {
    $conn = new PDO("mysql:host=your_servername;dbname=your_dbname", "your_username", "your_password"); // Replace
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->beginTransaction();

    // Optional: Check if the user has permission to delete this specific quotation
    // E.g., if status is 'Draft' and user is creator or admin
    // $stmt_check = $conn->prepare("SELECT status, created_by_user_id FROM quotations WHERE id = :id");
    // $stmt_check->execute([':id' => $quotation_id]);
    // $q_data = $stmt_check->fetch(PDO::FETCH_ASSOC);
    // if (!$q_data || ($q_data['status'] !== 'Draft' && !$isAdmin) || ($q_data['status'] === 'Draft' && !$isAdmin && $q_data['created_by_user_id'] != $userId) ) {
    //     $conn->rollBack();
    //     echo json_encode(['success' => false, 'message' => 'Permission denied or quotation cannot be deleted.']);
    //     exit;
    // }


    // 1. Delete related items from quotation_items
    $sql_delete_items = "DELETE FROM quotation_items WHERE quotation_id = :quotation_id";
    $stmt_delete_items = $conn->prepare($sql_delete_items);
    $stmt_delete_items->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
    $stmt_delete_items->execute();

    // 2. Delete the quotation from quotations table
    $sql_delete_quotation = "DELETE FROM quotations WHERE id = :quotation_id";
    $stmt_delete_quotation = $conn->prepare($sql_delete_quotation);
    $stmt_delete_quotation->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
    $stmt_delete_quotation->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Quotation deleted successfully.']);

} catch (PDOException $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    // Log error for production
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
$conn = null;
?>