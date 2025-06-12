<?php
session_start();
require_once '../classes/InventoryManager.php'; // Adjust path if necessary
require_once '../classes/Database.php';       // Adjust path if necessary

// --- User Authentication (Optional, but good practice for an API) ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'User not authenticated.']);
    exit;
}
// --- End User Authentication ---

header('Content-Type: application/json');
$response = [
    'total_products' => 0,
    'in_stock_count' => 0, // Number of product types in stock
    'low_stock_count' => 0,
    'out_of_stock_count' => 0,
    // Optional: Total quantity of items in stock across all products
    // 'total_quantity_in_stock' => 0,
];

try {
    $db = new Database();
    $conn = $db->connect();

    if ($conn) {
        // 1. Total Products
        $stmt_total = $conn->prepare("SELECT COUNT(id) as total FROM products");
        $stmt_total->execute();
        $result_total = $stmt_total->fetch(PDO::FETCH_ASSOC);
        $response['total_products'] = $result_total ? (int)$result_total['total'] : 0;

        // 2. Stock Status Counts (based on inventory_stock table)
        // This assumes InventoryManager::getStockReport() logic or similar
        // For simplicity, we'll query directly here, but ideally, this logic could be in InventoryManager
        $stmt_statuses = $conn->prepare("
            SELECT
                SUM(CASE WHEN COALESCE(i.quantity_in_stock, 0) > COALESCE(i.minimum_stock_level, 0) THEN 1 ELSE 0 END) as in_stock_products,
                SUM(CASE WHEN COALESCE(i.quantity_in_stock, 0) <= COALESCE(i.minimum_stock_level, 0) AND COALESCE(i.quantity_in_stock, 0) > 0 THEN 1 ELSE 0 END) as low_stock_products,
                SUM(CASE WHEN COALESCE(i.quantity_in_stock, 0) <= 0 THEN 1 ELSE 0 END) as out_of_stock_products
                -- SUM(COALESCE(i.quantity_in_stock, 0)) as total_quantity -- If you want total item count
            FROM products p
            LEFT JOIN inventory_stock i ON p.id = i.product_id
        ");
        // Note: The above query counts product *types* in each status.
        // If a product has no entry in inventory_stock, it's considered out_of_stock (quantity_in_stock defaults to 0 via COALESCE).

        $stmt_statuses->execute();
        $statuses_result = $stmt_statuses->fetch(PDO::FETCH_ASSOC);

        if ($statuses_result) {
            $response['in_stock_count'] = (int)$statuses_result['in_stock_products'];
            $response['low_stock_count'] = (int)$statuses_result['low_stock_products'];
            $response['out_of_stock_count'] = (int)$statuses_result['out_of_stock_products'];
            // $response['total_quantity_in_stock'] = (int)$statuses_result['total_quantity'];
        }

    } else {
        throw new Exception("Database connection failed.");
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    // Log $e->getMessage() for server-side debugging
    error_log("API dashboard_stats.php error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to retrieve dashboard statistics.', 'details' => $e->getMessage()]);
}
?>