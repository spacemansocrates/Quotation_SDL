<?php
// File: api/get_quotation_for_invoice.php
header('Content-Type: application/json');
require_once '../classes/InvoiceManager.php';

try {
    $quotation_id = $_GET['quotation_id'] ?? null;
    
    if (!$quotation_id) {
        throw new Exception('Quotation ID required');
    }
    
    $invoiceManager = new InvoiceManager();
    $quotationData = $invoiceManager->prepareInvoiceFromQuotation($quotation_id);
    
    if (!$quotationData) {
        throw new Exception('Quotation not found');
    }
    
    echo json_encode(['success' => true, 'data' => $quotationData]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>