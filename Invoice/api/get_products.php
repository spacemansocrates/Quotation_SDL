

<?php
// File: api/get_products.php
header('Content-Type: application/json');
require_once '../classes/Database.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
    $search = $_GET['search'] ?? '';
    
    if ($search) {
        $stmt = $conn->prepare("SELECT id, name, barcode, selling_price, unit_of_measurement FROM products WHERE name LIKE ? OR barcode LIKE ? ORDER BY name LIMIT 20");
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $conn->prepare("SELECT id, name, barcode, selling_price, unit_of_measurement FROM products ORDER BY name LIMIT 20");
        $stmt->execute();
    }
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $products]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

<?php