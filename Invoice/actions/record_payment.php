

<?php
// File: actions/record_payment.php
session_start();
header('Content-Type: application/json');
require_once '../classes/PaymentManager.php';

try {
    $user_id = $_SESSION['user_id'] ?? 1;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }
    
    $payment_data = [
        'invoice_id' => $_POST['invoice_id'],
        'customer_id' => $_POST['customer_id'],
        'payment_date' => $_POST['payment_date'],
        'amount_paid' => floatval($_POST['amount_paid']),
        'payment_method' => $_POST['payment_method'] ?? null,
        'reference_number' => $_POST['reference_number'] ?? null,
        'notes' => $_POST['notes'] ?? null
    ];
    
    $paymentManager = new PaymentManager();
    $result = $paymentManager->recordPayment($payment_data, $user_id);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
