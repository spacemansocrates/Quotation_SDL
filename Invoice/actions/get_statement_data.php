
<?php
// File: actions/get_statement_data.php
header('Content-Type: application/json');
require_once '../classes/StatementGenerator.php';

try {
    $customer_id = $_GET['customer_id'] ?? null;
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    
    if (!$customer_id || !$start_date || !$end_date) {
        throw new Exception('Customer ID, start date, and end date are required');
    }
    
    $statementGenerator = new StatementGenerator();
    $statementData = $statementGenerator->generateCustomerStatementData($customer_id, $start_date, $end_date);
    
    echo json_encode(['success' => true, 'data' => $statementData]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>