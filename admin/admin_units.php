<?php
// admin_units.php
session_start();

// Adjust paths if necessary
require_once __DIR__ . '/../includes/time_formating_helper.php'; // Add this for time formatting functions
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/nav.php';

// --- Authorization Check ---
$allowed_manage_roles = ['admin', 'manager', 'supervisor'];
$is_viewer = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'viewer';
$can_manage = isset($_SESSION['user_id']) && in_array($_SESSION['user_role'], $allowed_manage_roles);

if (!$can_manage && !$is_viewer) {
    $_SESSION['feedback_message'] = "Access denied. Please log in with appropriate permissions.";
    $_SESSION['feedback_type'] = 'error';
    header('Location: login.php');
    exit;
}
if ($is_viewer && ($_GET['action'] ?? 'list') !== 'list' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    $_SESSION['feedback_message'] = "Access denied. Viewers can only list units.";
    $_SESSION['feedback_type'] = 'error';
    header('Location: admin_units.php');
    exit;
}

// --- Initial Setup ---
$pdo = null;
$action = $_GET['action'] ?? 'list';
$unit_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : null;

$feedback_message = '';
$feedback_type = '';
$form_data = []; // Holds form data, useful on validation errors

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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
        $post_action = $_POST['action'] ?? '';
        $form_data = $_POST; // Preserve form data on error

        // -- Create Unit --
        if ($post_action === 'create') {
            $name = trim($_POST['name'] ?? '');

            // Basic Validation
            $errors = [];
            if (empty($name)) {
                $errors[] = "Unit Name is required.";
            } elseif (strlen($name) > 50) {
                 $errors[] = "Unit Name cannot exceed 50 characters.";
            }

            if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                    // Check uniqueness (name is unique key)
                    $checkSql = "SELECT id FROM units_of_measurement WHERE name = :name";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkSql, [':name' => $name]);
                    if ($checkStmt->fetch()) {
                        throw new PDOException("A unit with this name already exists.");
                    }

                    $sql = "INSERT INTO units_of_measurement (name) VALUES (:name)";
                    $params = [':name' => $name];
                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "Unit '" . esc($name) . "' created successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_units.php');
                    exit;

                } catch (PDOException $e) {
                    DatabaseConfig::rollbackTransaction($pdo);
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false || $e->getMessage() === "A unit with this name already exists.") {
                         $feedback_message = "Error creating unit: A unit with this name already exists.";
                    } else {
                         $feedback_message = "Error creating unit: " . esc($e->getMessage());
                    }
                    $feedback_type = 'error';
                    // $form_data is already set
                    $action = 'add';
                }
            } else {
                $feedback_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
                $feedback_type = 'error';
                // $form_data is already set
                $action = 'add';
            }
        }

        // -- Update Unit --
        elseif ($post_action === 'update' && isset($_POST['unit_id'])) {
            $unit_id = (int)$_POST['unit_id'];
            $name = trim($_POST['name'] ?? '');

            // Validation
            $errors = [];
             if (empty($name)) {
                $errors[] = "Unit Name is required.";
            } elseif (strlen($name) > 50) {
                 $errors[] = "Unit Name cannot exceed 50 characters.";
            }

            if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                    // Check uniqueness (excluding self)
                    $checkSql = "SELECT id FROM units_of_measurement WHERE name = :name AND id != :id";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkSql, [
                        ':name' => $name,
                        ':id' => $unit_id
                    ]);
                    if ($checkStmt->fetch()) {
                        throw new PDOException("Another unit with this name already exists.");
                    }

                    $sql = "UPDATE units_of_measurement SET name = :name WHERE id = :id";
                    $params = [
                        ':name' => $name,
                        ':id' => $unit_id,
                    ];
                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "Unit '" . esc($name) . "' updated successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_units.php');
                    exit;

                } catch (PDOException $e) {
                    DatabaseConfig::rollbackTransaction($pdo);
                     if (strpos($e->getMessage(), 'Duplicate entry') !== false || $e->getMessage() === "Another unit with this name already exists.") {
                         $feedback_message = "Error updating unit: Another unit with this name already exists.";
                    } else {
                        $feedback_message = "Error updating unit: " . esc($e->getMessage());
                    }
                    $feedback_type = 'error';
                    // $form_data is already set
                    $action = 'edit';
                    $unit_id_to_edit = $unit_id;
                }
            } else {
                $feedback_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
                $feedback_type = 'error';
                // $form_data is already set
                $action = 'edit';
                $unit_id_to_edit = $unit_id;
            }
        }

        // -- Delete Unit --
        elseif ($post_action === 'delete' && isset($_POST['unit_id'])) {
            $unit_id_to_delete = (int)$_POST['unit_id'];

            DatabaseConfig::beginTransaction($pdo);
            try {
                // CRITICAL: Check if any products are using this unit.
                // Assumes products table has a 'default_unit_of_measurement' TEXT/VARCHAR field
                // that stores the unit name (not ID). This is less ideal than a foreign key to units.id.
                // If products.default_unit_of_measurement stores the ID, change the check.
                // For now, assuming it stores the NAME.

                // First, get the name of the unit being deleted.
                $unitNameStmt = DatabaseConfig::executeQuery($pdo, "SELECT name FROM units_of_measurement WHERE id = :id", [':id' => $unit_id_to_delete]);
                $unit_name_to_delete = $unitNameStmt->fetchColumn();

                if ($unit_name_to_delete) {
                    $checkProductsSql = "SELECT COUNT(*) as product_count FROM products WHERE default_unit_of_measurement = :unit_name";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkProductsSql, [':unit_name' => $unit_name_to_delete]);
                    $result = $checkStmt->fetch();

                    if ($result && $result['product_count'] > 0) {
                        DatabaseConfig::rollbackTransaction($pdo);
                        $_SESSION['feedback_message'] = "Cannot delete unit '{$unit_name_to_delete}'. {$result['product_count']} product(s) are using it.";
                        $_SESSION['feedback_type'] = 'error';
                    } else {
                        // No products assigned, proceed with deletion
                        $sql = "DELETE FROM units_of_measurement WHERE id = :id";
                        $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $unit_id_to_delete]);

                        if ($stmt->rowCount() > 0) {
                            DatabaseConfig::commitTransaction($pdo);
                            $_SESSION['feedback_message'] = "Unit '{$unit_name_to_delete}' deleted successfully.";
                            $_SESSION['feedback_type'] = 'success';
                        } else {
                            DatabaseConfig::rollbackTransaction($pdo);
                            $_SESSION['feedback_message'] = "Unit not found or could not be deleted.";
                            $_SESSION['feedback_type'] = 'warning';
                        }
                    }
                } else {
                     DatabaseConfig::rollbackTransaction($pdo);
                     $_SESSION['feedback_message'] = "Unit to delete not found.";
                     $_SESSION['feedback_type'] = 'error';
                }

            } catch (PDOException $e) {
                DatabaseConfig::rollbackTransaction($pdo);
                $_SESSION['feedback_message'] = "Error deleting unit: " . esc($e->getMessage());
                $_SESSION['feedback_type'] = 'error';
            }
            header('Location: admin_units.php');
            exit;
        }
    } // End POST handling

    // == Prepare Data for Views (List, Add, Edit) ==
    $units = [];
    $unit_to_edit = null;

    if ($action === 'list') {
        $sql = "SELECT id, name FROM units_of_measurement ORDER BY name ASC";
        $stmt = DatabaseConfig::executeQuery($pdo, $sql);
        $units = $stmt->fetchAll();
    }
    elseif ($action === 'edit' && $unit_id_to_edit && $can_manage) {
        if (empty($form_data)) { // If not coming from a failed POST
            $sql = "SELECT id, name FROM units_of_measurement WHERE id = :id";
            $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $unit_id_to_edit]);
            $unit_to_edit = $stmt->fetch();

            if (!$unit_to_edit) {
                $_SESSION['feedback_message'] = "Unit not found.";
                $_SESSION['feedback_type'] = 'error';
                header('Location: admin_units.php');
                exit;
            }
            $form_data = $unit_to_edit; // Populate form data
        }
    }
    elseif ($action === 'add' && $can_manage) {
        if (empty($form_data)) { // Initialize empty if not a POST error reload
            $form_data = ['name' => ''];
        }
    } elseif (($action === 'add' || $action === 'edit') && !$can_manage) {
        $_SESSION['feedback_message'] = "Access denied.";
        $_SESSION['feedback_type'] = 'error';
        header('Location: admin_units.php');
        exit;
    }

} catch (PDOException $e) {
    $feedback_message = "Database error: " . esc($e->getMessage());
    $feedback_type = 'error';
    $action = 'list';
} catch (Exception $e) { // Catch other general exceptions
    $feedback_message = "An error occurred: " . esc($e->getMessage());
    $feedback_type = 'error';
    $action = 'list';
} finally {
    DatabaseConfig::closeConnection($pdo);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Units of Measurement</title>
    <link rel="stylesheet" href="../css/admin.css">
    <script>
        function confirmDelete(unitId, unitName) {
            if (confirm('Are you sure you want to delete the unit "' + unitName + '" (ID: ' + unitId + ')?\nThis action cannot be undone and might affect products using this unit.')) {
                document.getElementById('delete-form-' + unitId).submit();
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <?php require_once __DIR__ . '/../includes/nav.php'; // Include navigation ?>
        

        <h1>Manage Units of Measurement</h1>

        <?php if ($feedback_message): ?>
            <div class="message <?php echo esc($feedback_type); ?>">
                <?php echo $feedback_message; ?>
            </div>
        <?php endif; ?>

        <?php // --- Display Add/Edit Form --- ?>
        <?php if (($action === 'add' || $action === 'edit') && $can_manage): ?>
            <h2><?php echo ($action === 'edit' ? 'Edit Unit' : 'Add New Unit'); ?></h2>
            <form action="admin_units.php" method="POST">
                <input type="hidden" name="action" value="<?php echo ($action === 'edit' ? 'update' : 'create'); ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="unit_id" value="<?php echo (int)($form_data['id'] ?? 0); ?>">
                <?php endif; ?>

                <div>
                    <label for="name">Unit Name (e.g., kg, pcs, liter): <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo esc($form_data['name'] ?? ''); ?>" required maxlength="50">
                </div>

                <div>
                    <button type="submit"><?php echo ($action === 'edit' ? 'Update Unit' : 'Create Unit'); ?></button>
                    <a href="admin_units.php" class="button-link" style="background-color: #aaa;">Cancel</a>
                </div>
            </form>
        <?php endif; ?>

        <?php // --- Display Unit List --- ?>
        <?php if ($action === 'list'): ?>
            <?php if ($can_manage): ?>
                <a href="admin_units.php?action=add" class="button-link">Add New Unit</a>
            <?php endif; ?>

            <h2>Current Units</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <?php if ($can_manage): ?>
                           <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($units)): ?>
                        <tr>
                             <td colspan="<?php echo $can_manage ? 3 : 2; ?>">No units found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($units as $unit): ?>
                        <tr>
                            <td><?php echo (int)$unit['id']; ?></td>
                            <td><?php echo esc($unit['name']); ?></td>
                           <?php if ($can_manage): ?>
                               <td class="actions">
                                   <a href="admin_units.php?action=edit&id=<?php echo (int)$unit['id']; ?>" class="button-link edit">Edit</a>
                                   <form id="delete-form-<?php echo (int)$unit['id']; ?>" action="admin_units.php" method="POST" style="display: none;">
                                       <input type="hidden" name="action" value="delete">
                                       <input type="hidden" name="unit_id" value="<?php echo (int)$unit['id']; ?>">
                                   </form>
                                   <button type="button" onclick="confirmDelete(<?php echo (int)$unit['id']; ?>, '<?php echo esc(addslashes($unit['name'])); ?>')" class="button-link delete">Delete</button>
                               </td>
                           <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</body>
</html>