<?php
// get_shops.php
// Database connection details (replace with your actual credentials)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "supplies";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("SELECT id, shop_code FROM shops ORDER BY shop_code ASC");
    $stmt->execute();

    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If this script is called via AJAX, output JSON
    // header('Content-Type: application/json');
    // echo json_encode($shops);

    // If you are including this PHP directly in the HTML select options:
    foreach ($shops as $shop) {
        echo '<option value="' . htmlspecialchars($shop['id']) . '">' . htmlspecialchars($shop['shop_code']) . '</option>';
    }

} catch(PDOException $e) {
    // Handle error - log it or output a user-friendly message
    // For AJAX, you might output a JSON error
    // echo json_encode(["error" => "Could not fetch shops: " . $e->getMessage()]);
    echo '<option value="">Error loading shops</option>';
}
$conn = null;
?>