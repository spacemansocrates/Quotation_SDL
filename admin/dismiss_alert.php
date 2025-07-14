<?php
// Force PHP to show errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Assume a user is logged in. In a real app, this comes from a session.
// session_start();
// if (!isset($_SESSION['user_id'])) { die("Access denied."); }
$current_user_id = 1; // Placeholder for logged-in user ID

// Check if the alert_key was sent via POST
if (isset($_POST['alert_key'])) {
    
    // --- DATABASE CONNECTION ---
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "supplies";
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        // Return a server error status
        http_response_code(500);
        die("Connection failed: " . $conn->connect_error);
    }
    
    $alert_key = $_POST['alert_key'];

    // Use INSERT IGNORE to prevent errors if the user double-clicks.
    // It will simply do nothing if the user_id/alert_key pair already exists.
    $stmt = $conn->prepare("INSERT IGNORE INTO alert_dismissals (user_id, alert_key) VALUES (?, ?)");
    $stmt->bind_param("is", $current_user_id, $alert_key);
    
    if ($stmt->execute()) {
        // Success! Return a JSON response.
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Alert dismissed.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to dismiss alert.']);
    }

    $stmt->close();
    $conn->close();

} else {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}
?>