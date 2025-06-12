
<?php
// File: actions/get_invoice_data.php
header('Content-Type: application/json');
require_once '../classes/InvoiceManager.php';

try {
    $invoice_id = $_GET['id'] ?? null;
    
    if (!$invoice_id) {
        throw new Exception('Invoice ID required');
    }
    
    $invoiceManager = new InvoiceManager();
    $invoiceData = $invoiceManager->getInvoiceById($invoice_id);
    
    if (!$invoiceData) {
        throw new Exception('Invoice not found');
    }
    
    echo json_encode(['success' => true, 'data' => $invoiceData]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>