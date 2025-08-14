<?php
// ajax/load_quotation.php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../models/Invoice.php'; // Re-use Invoice model for quotation fetching

header('Content-Type: application/json');

$quotation_id = $_GET['quotation_id'] ?? null;

if (empty($quotation_id) || !is_numeric($quotation_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid Quotation ID.']);
    exit;
}

$invoice_model = new Invoice($conn); // Use the Invoice model to fetch quotation data

$quotation_data = $invoice_model->getQuotationDetails($quotation_id);
if (!$quotation_data) {
    echo json_encode(['success' => false, 'message' => 'Quotation not found.']);
    exit;
}

$quotation_items_result = $invoice_model->getQuotationItems($quotation_id);
$items = [];
if ($quotation_items_result) {
    while ($row = $quotation_items_result->fetch_assoc()) {
        // Also fetch product SKU/Name if linked, similar to InvoiceItem readByInvoice
        $product_sku = null;
        $product_name = null;
        if (!empty($row['product_id'])) {
            $product_query = "SELECT sku, name FROM products WHERE id = ? LIMIT 1";
            $stmt_product = $conn->prepare($product_query);
            $stmt_product->bind_param("i", $row['product_id']);
            $stmt_product->execute();
            $product_result = $stmt_product->get_result();
            $product_data = $product_result->fetch_assoc();
            if ($product_data) {
                $product_sku = $product_data['sku'];
                $product_name = $product_data['name'];
            }
        }
        $items[] = [
            'id' => $row['id'], // quotation_item_id, not invoice_item_id
            'product_id' => $row['product_id'],
            'item_number' => $row['item_number'],
            'description' => $row['description'],
            'image_path_override' => $row['image_path_override'],
            'quantity' => $row['quantity'],
            'unit_of_measurement' => $row['unit_of_measurement'],
            'rate_per_unit' => $row['rate_per_unit'],
            'total_amount' => $row['total_amount'],
            'product_sku' => $product_sku, // Add SKU for search box
            'product_name' => $product_name // Add name for description if desired
        ];
    }
}

echo json_encode(['success' => true, 'quotation' => $quotation_data, 'items' => $items]);

$conn->close();
?>