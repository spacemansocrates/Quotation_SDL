
<?php
// File: api/get_quotations_by_customer.php
header('Content-Type: application/json');
require_once '../classes/Database.php';

try {
    $customer_id = $_GET['customer_id'] ?? null;
    
    if (!$customer_id) {
        throw new Exception('Customer ID required');
    }
    
    $db = new Database();
    $conn = $db->connect();
    
    $stmt = $conn->prepare("SELECT id, quotation_number, quotation_date, total_net_amount, status FROM quotations WHERE customer_id = ? AND status != 'Cancelled' ORDER BY quotation_date DESC");
    $stmt->execute([$customer_id]);
    
    $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $quotations]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>