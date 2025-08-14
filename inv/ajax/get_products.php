<?php
// ajax/get_products.php
session_start();
require_once __DIR__ . '/../db_connect.php';

header('Content-Type: application/json');

$search = $_GET['search'] ?? '';

if (empty($search)) {
    echo json_encode([]);
    exit;
}

// Search by SKU or Name
$query = "SELECT id, sku, name, default_unit_price, default_unit_of_measurement, default_image_path
          FROM products
          WHERE sku LIKE ? OR name LIKE ?
          ORDER BY name ASC
          LIMIT 10"; // Limit results to prevent large responses

$stmt = $conn->prepare($query);
$searchTerm = '%' . $search . '%';
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products);

$conn->close();
?>