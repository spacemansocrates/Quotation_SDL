<?php
// search_products.php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "supplies";

$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if (empty($query)) {
    echo json_encode([]);
    exit;
}

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT id, sku, name, description, default_unit_price, default_unit_of_measurement, default_image_path
            FROM products
            WHERE sku LIKE :query OR name LIKE :query
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['query' => "%" . $query . "%"]);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($products);

} catch(PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
$conn = null;
?>