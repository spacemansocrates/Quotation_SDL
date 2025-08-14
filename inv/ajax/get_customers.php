<?php
// ajax/get_customers.php
session_start();
require_once __DIR__ . '/../db_connect.php';

// Assuming you have a Customer model or just direct query for simplicity here
// In a real app, you might have a dedicated model/controller for customer data.

header('Content-Type: application/json');

$search = $_GET['search'] ?? '';

$query = "SELECT id, name FROM customers";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " WHERE name LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}
$query .= " ORDER BY name ASC LIMIT 20"; // Limit results for performance

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

echo json_encode($customers);

$conn->close();
?>