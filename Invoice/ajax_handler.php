<?php
// Start session if you are using $_SESSION for user_id
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'classes/Database.php'; // Adjust path if necessary

$db = new Database();
$conn = $db->connect();

$action = $_REQUEST['action'] ?? ''; // Use $_REQUEST to handle GET or POST

// --- Helper function to get current user ID (replace with your actual session logic) ---
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 1; // Default to 1 if no user is logged in (for testing)
}

// --- Action: Search Customers ---
if ($action == 'search_customers') {
    $searchTerm = $_GET['term'] ?? '';
    $stmt = $conn->prepare("SELECT id, name, customer_code FROM customers WHERE name LIKE :term OR customer_code LIKE :term LIMIT 10");
    $stmt->execute(['term' => "%$searchTerm%"]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($customers);
    exit;
}

// --- Action: Get Quotations for Customer ---
if ($action == 'get_customer_quotations') {
    $customerId = $_GET['customer_id'] ?? 0;
    if ($customerId) {
        $stmt = $conn->prepare("SELECT id, quotation_number, quotation_date FROM quotations WHERE customer_id = :customer_id AND status = 'Approved' ORDER BY quotation_date DESC"); // Assuming you only want to load approved quotations
        $stmt->execute(['customer_id' => $customerId]);
        $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($quotations);
    } else {
        echo json_encode([]);
    }
    exit;
}

// --- Action: Get Quotation Details ---
if ($action == 'get_quotation_details') {
    $quotationId = $_GET['quotation_id'] ?? 0;
    $response = ['details' => null, 'items' => []];

    if ($quotationId) {
        // Fetch quotation details
        $stmt = $conn->prepare("SELECT * FROM quotations WHERE id = :id");
        $stmt->execute(['id' => $quotationId]);
        $response['details'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch quotation items
        if ($response['details']) {
            $stmt_items = $conn->prepare("SELECT qi.*, p.name as product_name, p.sku as product_sku FROM quotation_items qi LEFT JOIN products p ON qi.product_id = p.id WHERE qi.quotation_id = :quotation_id ORDER BY qi.item_number");
            $stmt_items->execute(['quotation_id' => $quotationId]);
            $response['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- Action: Search Products ---
if ($action == 'search_products') {
    $searchTerm = $_GET['term'] ?? '';
    // You might want to join with inventory_stock to check availability or get more details
    $stmt = $conn->prepare("SELECT id, name, sku, default_unit_price, default_unit_of_measurement FROM products WHERE name LIKE :term OR sku LIKE :term LIMIT 10");
    $stmt->execute(['term' => "%$searchTerm%"]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($products);
    exit;
}

// If no valid action is provided
header('Content-Type: application/json');
echo json_encode(['error' => 'Invalid action']);
exit;

?>