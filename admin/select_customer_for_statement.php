<?php
 session_start(); 
require_once __DIR__ . '/../includes/db_connect.php'; // Adjust path if needed
require_once __DIR__ . '/../includes/quonav.php';


$customers = [];
try {
    $pdo = getDatabaseConnection();
    // Fetch all customers to populate the dropdown
    $customers = DatabaseConfig::executeQuery($pdo, "SELECT id, name, customer_code FROM customers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error while fetching customers: " . $e->getMessage());
}
DatabaseConfig::closeConnection($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Customer Statement</title>
    <!-- Using a Bootstrap CDN for quick styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 600px; }
        .card { margin-top: 50px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Generate Customer Statement</h3>
        </div>
        <div class="card-body">
            <p class="card-text">Select a customer and a date to generate their statement of outstanding invoices.</p>
            
            <!-- 
                This form will send the selected customer_id and as_of_date
                to your existing customer_statement.php page.
                
                - method="GET": Passes the data in the URL.
                - action="customer_statement.php": The file that will process the data.
                - target="_blank": Opens the statement in a new tab for better user experience.
            -->
            <form action="customer_statement.php" method="GET" target="_blank">
                <div class="mb-3">
                    <label for="customer_id" class="form-label">Customer</label>
                    <select class="form-select" id="customer_id" name="customer_id" required>
                        <option value="" disabled selected>-- Please select a customer --</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo htmlspecialchars($customer['id']); ?>">
                                <?php echo htmlspecialchars($customer['name']); ?> (<?php echo htmlspecialchars($customer['customer_code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="as_of_date" class="form-label">Statement As Of Date</label>
                    <input type="date" class="form-control" id="as_of_date" name="as_of_date" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Generate Statement</button>
                </div>
            </form>
        </div>
        <div class="card-footer text-muted">
            The statement will open in a new tab.
        </div>
    </div>
</div>

</body>
</html>