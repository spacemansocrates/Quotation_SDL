<?php
session_start();
require_once '../classes/InventoryManager.php'; // Adjust path
require_once '../classes/Database.php';       // Adjust path

// --- User Authentication ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'User not authenticated.']);
    exit;
}
// --- End User Authentication ---

header('Content-Type: application/json');
$recent_activities = [];
$limit = 5; // Number of recent activities to fetch

try {
    $inventory = new InventoryManager(); // InventoryManager has getTransactionHistory
    // We need all recent transactions, not for a specific product.
    // So, we'll write a new query or adapt getTransactionHistory.
    // For now, let's write a direct query.

    $db = new Database();
    $conn = $db->connect();

    if ($conn) {
        $stmt = $conn->prepare("
            SELECT
                st.id as transaction_id,
                st.transaction_type,
                st.quantity,
                st.reference_number,
                st.notes,
                st.transaction_date,
                u.username as scanned_by_username,
                p.name as product_name
            FROM stock_transactions st
            JOIN products p ON st.product_id = p.id
            LEFT JOIN users u ON st.scanned_by_user_id = u.id -- Ensure users table exists with id and username
            ORDER BY st.transaction_date DESC, st.id DESC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        throw new Exception("Database connection failed.");
    }

    echo json_encode($recent_activities);

} catch (Exception $e) {
    http_response_code(500);
    error_log("API recent_activity.php error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to retrieve recent activity.', 'details' => $e->getMessage()]);
}
?>