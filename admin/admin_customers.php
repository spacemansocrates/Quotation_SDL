<?php
// admin_customers.php
session_start();

// Adjust the path based on your file structure
require_once __DIR__ . '/../includes/time_formating_helper.php'; // Add this for time formatting functions
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/nav.php';


// --- Authorization Check ---
// Allow admin, manager, supervisor for CRUD. Staff/Viewer might have read-only later.
$allowed_roles = ['admin', 'manager', 'supervisor']; // Roles allowed to perform actions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    // Check if viewer role has access for viewing only
    if (($_GET['action'] ?? 'list') === 'list' && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'viewer') {
         // Viewer can list, proceed without full CRUD rights check for list view
    } else {
        $_SESSION['feedback_message'] = "Access denied. You do not have permission to manage customers.";
        $_SESSION['feedback_type'] = 'error';
        // Redirect to a dashboard or login depending on your flow
        header('Location: login.php'); // Or maybe index.php/dashboard.php
        exit;
    }
}
$can_edit_delete = in_array($_SESSION['user_role'], $allowed_roles); // Flag for enabling edit/delete buttons

// --- Initial Setup ---
$pdo = null;
$action = $_GET['action'] ?? 'list'; // Default action
$customer_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : null;
$current_user_id = $_SESSION['user_id']; // Get logged-in user's ID

$feedback_message = '';
$feedback_type = ''; // 'success' or 'error'

// Display and clear flash messages
if (isset($_SESSION['feedback_message'])) {
    $feedback_message = $_SESSION['feedback_message'];
    $feedback_type = $_SESSION['feedback_type'] ?? 'info';
    unset($_SESSION['feedback_message'], $_SESSION['feedback_type']);
}

// --- Helper Function ---
function esc(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// --- CRUD Operations ---
try {
    $pdo = DatabaseConfig::getConnection();

    // == Handle POST Requests (Create/Update/Delete) ==
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit_delete) { // Only process POST if user has rights
        $post_action = $_POST['action'] ?? '';

        // -- Create Customer --
        if ($post_action === 'create') {
            // Extract data from POST
            $customer_code = trim($_POST['customer_code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $address_line1 = trim($_POST['address_line1'] ?? '');
            $address_line2 = trim($_POST['address_line2'] ?? '');
            $city_location = trim($_POST['city_location'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $tpin_no = trim($_POST['tpin_no'] ?? '');

            // Basic Validation
            $errors = [];
            if (empty($customer_code)) $errors[] = "Customer Code is required.";
            if (empty($name)) $errors[] = "Customer Name is required.";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid Email is required.";
            // Add more specific validation if needed (e.g., TPIN format, phone format)

            if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                    // Check uniqueness (Code and Email are good candidates)
                    $checkSql = "SELECT id FROM customers WHERE customer_code = :customer_code OR email = :email";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkSql, [
                        ':customer_code' => $customer_code,
                        ':email' => $email
                    ]);
                    if ($checkStmt->fetch()) {
                        throw new PDOException("Customer Code or Email already exists.");
                    }

                    $sql = "INSERT INTO customers (customer_code, name, address_line1, address_line2, city_location, phone, email, tpin_no, created_by_user_id, updated_by_user_id)
                            VALUES (:customer_code, :name, :address_line1, :address_line2, :city_location, :phone, :email, :tpin_no, :created_by, :updated_by)";
                    $params = [
                        ':customer_code' => $customer_code,
                        ':name' => $name,
                        ':address_line1' => $address_line1 ?: null,
                        ':address_line2' => $address_line2 ?: null,
                        ':city_location' => $city_location ?: null,
                        ':phone' => $phone ?: null,
                        ':email' => $email,
                        ':tpin_no' => $tpin_no ?: null,
                        ':created_by' => $current_user_id,
                        ':updated_by' => $current_user_id, // Set updated_by on creation too
                    ];
                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "Customer '" . esc($name) . "' created successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_customers.php'); // Redirect
                    exit;

                } catch (PDOException $e) {
                    DatabaseConfig::rollbackTransaction($pdo);
                    $feedback_message = "Error creating customer: " . esc($e->getMessage());
                    $feedback_type = 'error';
                    $form_data = $_POST; // Keep form data
                    $action = 'add'; // Stay on add form
                }
            } else {
                $feedback_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
                $feedback_type = 'error';
                $form_data = $_POST; // Keep form data
                $action = 'add'; // Stay on add form
            }
        }

        // -- Update Customer --
        elseif ($post_action === 'update' && isset($_POST['customer_id'])) {
            $customer_id = (int)$_POST['customer_id'];
            // Extract data
            $customer_code = trim($_POST['customer_code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $address_line1 = trim($_POST['address_line1'] ?? '');
            $address_line2 = trim($_POST['address_line2'] ?? '');
            $city_location = trim($_POST['city_location'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $tpin_no = trim($_POST['tpin_no'] ?? '');

            // Validation
            $errors = [];
            if (empty($customer_code)) $errors[] = "Customer Code is required.";
            if (empty($name)) $errors[] = "Customer Name is required.";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid Email is required.";

            if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                    // Check uniqueness (excluding self)
                     $checkSql = "SELECT id FROM customers WHERE (customer_code = :customer_code OR email = :email) AND id != :id";
                     $checkStmt = DatabaseConfig::executeQuery($pdo, $checkSql, [
                         ':customer_code' => $customer_code,
                         ':email' => $email,
                         ':id' => $customer_id
                     ]);
                     if ($checkStmt->fetch()) {
                        throw new PDOException("Customer Code or Email already exists for another customer.");
                     }

                    $sql = "UPDATE customers SET
                                customer_code = :customer_code,
                                name = :name,
                                address_line1 = :address_line1,
                                address_line2 = :address_line2,
                                city_location = :city_location,
                                phone = :phone,
                                email = :email,
                                tpin_no = :tpin_no,
                                updated_by_user_id = :updated_by
                                -- updated_at is handled by DB trigger/default
                            WHERE id = :id";
                    $params = [
                        ':customer_code' => $customer_code,
                        ':name' => $name,
                        ':address_line1' => $address_line1 ?: null,
                        ':address_line2' => $address_line2 ?: null,
                        ':city_location' => $city_location ?: null,
                        ':phone' => $phone ?: null,
                        ':email' => $email,
                        ':tpin_no' => $tpin_no ?: null,
                        ':updated_by' => $current_user_id,
                        ':id' => $customer_id,
                    ];
                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "Customer '" . esc($name) . "' updated successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_customers.php'); // Redirect
                    exit;

                } catch (PDOException $e) {
                    DatabaseConfig::rollbackTransaction($pdo);
                    $feedback_message = "Error updating customer: " . esc($e->getMessage());
                    $feedback_type = 'error';
                    $form_data = $_POST; // Keep form data
                    $action = 'edit'; // Stay on edit form
                    $customer_id_to_edit = $customer_id; // Ensure ID is available for edit view
                }
            } else {
                $feedback_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
                $feedback_type = 'error';
                $form_data = $_POST; // Keep form data
                $action = 'edit'; // Stay on edit form
                 $customer_id_to_edit = $customer_id; // Ensure ID is available for edit view
            }
        }

        // -- Delete Customer --
        elseif ($post_action === 'delete' && isset($_POST['customer_id'])) {
            $customer_id_to_delete = (int)$_POST['customer_id'];

            DatabaseConfig::beginTransaction($pdo);
            try {
                // Optional: Check for related records (e.g., orders) before deleting
                // $checkRelatedSql = "SELECT COUNT(*) FROM orders WHERE customer_id = :id"; ...

                $sql = "DELETE FROM customers WHERE id = :id";
                $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $customer_id_to_delete]);

                if ($stmt->rowCount() > 0) {
                    DatabaseConfig::commitTransaction($pdo);
                    $_SESSION['feedback_message'] = "Customer deleted successfully.";
                    $_SESSION['feedback_type'] = 'success';
                } else {
                    DatabaseConfig::rollbackTransaction($pdo);
                    $_SESSION['feedback_message'] = "Customer not found or could not be deleted.";
                    $_SESSION['feedback_type'] = 'warning'; // Use warning as it might not be an error
                }
            } catch (PDOException $e) {
                DatabaseConfig::rollbackTransaction($pdo);
                // Check for foreign key constraint error (e.g., SQLSTATE[23000])
                 if ($e->getCode() == '23000') {
                     $_SESSION['feedback_message'] = "Cannot delete customer. They may have associated records (e.g., orders).";
                 } else {
                     $_SESSION['feedback_message'] = "Error deleting customer: " . esc($e->getMessage());
                 }
                $_SESSION['feedback_type'] = 'error';
            }
            header('Location: admin_customers.php'); // Redirect back to list
            exit;
        }
    } // End POST handling

    // == Prepare Data for Views (List, Add, Edit) ==
    $customers = [];
    $customer_to_edit = null;

    if ($action === 'list') {
        // Select necessary columns, potentially join with users for creator/updater names
        $sql = "SELECT c.*, uc.username as creator_username, uu.username as updater_username
                FROM customers c
                LEFT JOIN users uc ON c.created_by_user_id = uc.id
                LEFT JOIN users uu ON c.updated_by_user_id = uu.id
                ORDER BY c.name ASC";
        $stmt = DatabaseConfig::executeQuery($pdo, $sql);
        $customers = $stmt->fetchAll();
    }
    elseif (($action === 'edit' || $action === 'view') && $customer_id_to_edit) {
        // 'view' action could be added for read-only display if needed
        if (!isset($form_data)) { // If not coming from a failed POST
            $sql = "SELECT * FROM customers WHERE id = :id";
            $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $customer_id_to_edit]);
            $customer_to_edit = $stmt->fetch();

            if (!$customer_to_edit) {
                $_SESSION['feedback_message'] = "Customer not found.";
                $_SESSION['feedback_type'] = 'error';
                header('Location: admin_customers.php');
                exit;
            }
            $form_data = $customer_to_edit; // Use fetched data for the form
        }
        // If form submission failed, $form_data is already set from POST block
    }
    elseif ($action === 'add') {
        if (!isset($form_data)) { // If not coming from a failed POST
            $form_data = [ // Default values for add form
                'customer_code' => '', 'name' => '', 'address_line1' => '',
                'address_line2' => '', 'city_location' => '', 'phone' => '',
                'email' => '', 'tpin_no' => '',
            ];
        }
    }

} catch (PDOException $e) {
    $feedback_message = "Database error: " . esc($e->getMessage());
    $feedback_type = 'error';
    // Logged by db_connect.php usually
    $action = 'list'; // Fallback to list view
} finally {
    DatabaseConfig::closeConnection($pdo);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Customers</title>
    <!-- Adjust path to your CSS file -->
    <link rel="stylesheet" href="../css/admin.css">
    <script>
        // Confirmation for delete action
        function confirmDelete(customerId, customerName) {
            // Use backticks for template literals if desired, or simple concatenation
            if (confirm('Are you sure you want to delete the customer "' + customerName + '" (ID: ' + customerId + ')?')) {
                document.getElementById('delete-form-' + customerId).submit();
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="user-info">
             Logged in as: <strong><?php echo esc($_SESSION['username']); ?></strong> (<?php echo esc($_SESSION['user_role']); ?>)
             | <a href="logout.php">Logout</a>
        </div>

        <h1>Manage Customers</h1>

        <?php if ($feedback_message): ?>
            <div class="message <?php echo esc($feedback_type); ?>">
                <?php echo $feedback_message; // Can contain <br> from validation errors ?>
            </div>
        <?php endif; ?>

        <?php // --- Display Add/Edit Form --- ?>
        <?php if (($action === 'add' || $action === 'edit') && $can_edit_delete ): ?>
            <h2><?php echo ($action === 'edit' ? 'Edit Customer' : 'Add New Customer'); ?></h2>
            <form action="admin_customers.php" method="POST">
                <input type="hidden" name="action" value="<?php echo ($action === 'edit' ? 'update' : 'create'); ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="customer_id" value="<?php echo (int)($form_data['id'] ?? 0); ?>">
                <?php endif; ?>

                <div>
                    <label for="customer_code">Customer Code: <span class="required">*</span></label>
                    <input type="text" id="customer_code" name="customer_code" value="<?php echo esc($form_data['customer_code'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="name">Customer Name: <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo esc($form_data['name'] ?? ''); ?>" required>
                </div>
                 <div>
                    <label for="email">Email: <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="<?php echo esc($form_data['email'] ?? ''); ?>" required>
                </div>
                 <div>
                    <label for="phone">Phone:</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo esc($form_data['phone'] ?? ''); ?>">
                </div>
                 <div>
                    <label for="tpin_no">TPIN:</label>
                    <input type="text" id="tpin_no" name="tpin_no" value="<?php echo esc($form_data['tpin_no'] ?? ''); ?>">
                </div>
                <div>
                    <label for="address_line1">Address Line 1:</label>
                    <input type="text" id="address_line1" name="address_line1" value="<?php echo esc($form_data['address_line1'] ?? ''); ?>">
                </div>
                <div>
                    <label for="address_line2">Address Line 2:</label>
                    <input type="text" id="address_line2" name="address_line2" value="<?php echo esc($form_data['address_line2'] ?? ''); ?>">
                </div>
                <div>
                    <label for="city_location">City / Location:</label>
                    <input type="text" id="city_location" name="city_location" value="<?php echo esc($form_data['city_location'] ?? ''); ?>">
                </div>

                <div>
                    <button type="submit"><?php echo ($action === 'edit' ? 'Update Customer' : 'Create Customer'); ?></button>
                    <a href="admin_customers.php" class="button-link" style="background-color: #aaa;">Cancel</a>
                </div>
            </form>
        <?php endif; // End Add/Edit Form ?>


        <?php // --- Display Customer List --- ?>
        <?php if ($action === 'list'): ?>
            <?php if ($can_edit_delete): // Show 'Add New' only if user has rights ?>
                <a href="admin_customers.php?action=add" class="button-link">Add New Customer</a>
            <?php endif; ?>

            <h2>Current Customers</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>TPIN</th>
                        <th>City</th>
                        <th>Created</th>
                        <!-- <th>Updated</th> -->
                        <?php if ($can_edit_delete): ?>
                           <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="<?php echo $can_edit_delete ? 9 : 8; ?>">No customers found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo (int)$customer['id']; ?></td>
                            <td><?php echo esc($customer['customer_code']); ?></td>
                            <td><?php echo esc($customer['name']); ?></td>
                            <td><?php echo esc($customer['email']); ?></td>
                            <td><?php echo esc($customer['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo esc($customer['tpin_no'] ?? 'N/A'); ?></td>
                            <td><?php echo esc($customer['city_location'] ?? 'N/A'); ?></td>
                            <td>
                                <?php echo formatRelativeTime($customer['created_at']); ?>
                                <small>(by <?php echo esc($customer['creator_username'] ?? 'Unknown'); ?>)</small>
                            </td>
                           <!-- Optional: Show Updated Info
                           <td>
                                <?php //echo formatRelativeTime($customer['updated_at']); ?>
                                <small>(by <?php //echo esc($customer['updater_username'] ?? 'Unknown'); ?>)</small>
                           </td>
                           -->
                           <?php if ($can_edit_delete): ?>
                               <td class="actions">
                                   <a href="admin_customers.php?action=edit&id=<?php echo (int)$customer['id']; ?>" class="button-link edit">Edit</a>
                                   <!-- Hidden form for delete action -->
                                   <form id="delete-form-<?php echo (int)$customer['id']; ?>" action="admin_customers.php" method="POST" style="display: none;">
                                       <input type="hidden" name="action" value="delete">
                                       <input type="hidden" name="customer_id" value="<?php echo (int)$customer['id']; ?>">
                                   </form>
                                    <!-- The button triggers JavaScript which submits the hidden form -->
                                   <button type="button" onclick="confirmDelete(<?php echo (int)$customer['id']; ?>, '<?php echo esc(addslashes($customer['name'])); ?>')" class="button-link delete">Delete</button>
                               </td>
                           <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; // End Customer List ?>

    </div>
</body>
</html>