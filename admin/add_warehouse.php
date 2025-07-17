<?php
/* ========================================================= */
/* STEP 4: BACKEND PROCESSING SCRIPT (add_warehouse.php)     */
/* ========================================================= */

// --- DATABASE CONNECTION ---
$servername = "srv582.hstgr.io";
$username = "u789944046_socrates";
$password = "Naho1386";
$dbname = "u789944046_suppliesdirect";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the form was submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- DATA VALIDATION & SANITIZATION ---
    // Use trim() to remove whitespace from the beginning and end of the string
    $warehouse_name = trim($_POST['warehouse_name']);
    $warehouse_code = trim($_POST['warehouse_code']);
    $city = trim($_POST['city_location']);
    $address = trim($_POST['address_line1']);

    // For demonstration, we assume a user with ID 1 is creating this.
    // In a real application, you would get this from the session.
    $created_by_user_id = 1;

    // --- Basic Validation ---
    if (empty($warehouse_name) || empty($warehouse_code)) {
        // You can handle errors more gracefully, e.g., by redirecting back with an error message
        die("Error: Warehouse Name and Code are required.");
    }

    // --- PREPARED STATEMENT TO PREVENT SQL INJECTION ---
    // 1. Prepare the SQL statement with placeholders (?)
    $stmt = $conn->prepare(
        "INSERT INTO warehouses (name, warehouse_code, city, address_line1, created_by_user_id, is_active) 
         VALUES (?, ?, ?, ?, ?, 1)"
    );

    // Check if the statement was prepared successfully
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }

    // 2. Bind the variables to the placeholders
    // 'ssssi' denotes the type of each parameter: s=string, i=integer
    $stmt->bind_param("ssssi", $warehouse_name, $warehouse_code, $city, $address, $created_by_user_id);

    // 3. Execute the statement
    if ($stmt->execute()) {
        // If successful, redirect back to the dashboard.
        // You could also add a success message to the URL.
        header("Location: inventory_dashboard.php?status=success");
        exit(); // Always call exit() after a header redirect
    } else {
        // If it fails, show an error.
        // In a production environment, you would log this error instead of showing it to the user.
        echo "Error: " . $stmt->error;
    }

    // 4. Close the statement
    $stmt->close();

} else {
    // If someone tries to access this file directly without submitting the form
    echo "Invalid request method.";
}

// Close the database connection
$conn->close();

?>