<?php
// File: api/get_customers.php
header('Content-Type: application/json');
require_once '../classes/Database.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
    $search = $_GET['search'] ?? '';
    
    if ($search) {
        $stmt = $conn->prepare("SELECT id, name, customer_code, address FROM customers WHERE name LIKE ? OR customer_code LIKE ? ORDER BY name LIMIT 20");
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $conn->prepare("SELECT id, name, customer_code, address FROM customers ORDER BY name LIMIT 20");
        $stmt->execute();
    }
    
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $customers]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>