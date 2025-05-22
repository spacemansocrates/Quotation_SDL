<?php
// search_customers.php
header('Content-Type: application/json');

// Database connection
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

    // Adjust the search query based on your customer table fields
    $sql = "SELECT id, customer_code, name, address_line1 /*, other_relevant_fields */
            FROM customers
            WHERE customer_code LIKE :query
               OR name LIKE :query
               OR email LIKE :query -- Add more fields to search
            LIMIT 10"; // Limit results for performance

    $stmt = $conn->prepare($sql);
    $stmt->execute(['query' => "%" . $query . "%"]);

    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($customers);

} catch(PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
$conn = null;
?>