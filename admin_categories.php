<?php
// admin_categories.php
session_start();

// Adjust paths if this file is not in the web root alongside includes/ and css/
require_once __DIR__ . '/../includes/time_formating_helper.php'; // Add this for time formatting functions
require_once __DIR__ . '/../includes/db_connect.php';


// --- Authorization Check ---
$allowed_roles = ['admin', 'manager', 'supervisor']; // Roles allowed to perform actions
$is_viewer = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'viewer';
$can_manage = isset($_SESSION['user_id']) && in_array($_SESSION['user_role'], $allowed_roles);

if (!$can_manage && !$is_viewer) { // Not allowed and not a viewer
    $_SESSION['feedback_message'] = "Access denied. Please log in with appropriate permissions.";
    $_SESSION['feedback_type'] = 'error';
    header('Location: login.php');
    exit;
}
// Check if viewer tries to perform actions other than list
if ($is_viewer && ($_GET['action'] ?? 'list') !== 'list' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
     $_SESSION['feedback_message'] = "Access denied. Viewers can only list categories.";
     $_SESSION['feedback_type'] = 'error';
     header('Location: admin_categories.php'); // Redirect back to list view
     exit;
}


// --- Initial Setup ---
$pdo = null;
$action = $_GET['action'] ?? 'list'; // Default action
$category_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : null;

$feedback_message = '';
$feedback_type = ''; // 'success' or 'error' or 'warning'

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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) { // Only process POST if user has rights
        $post_action = $_POST['action'] ?? '';

        // -- Create Category --
        if ($post_action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            // Basic Validation
            $errors = [];
            if (empty($name)) {
                $errors[] = "Category Name is required.";
            } elseif (strlen($name) > 100) {
                 $errors[] = "Category Name cannot exceed 100 characters.";
            }

            if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                    // Check uniqueness (name is unique key)
                    $checkSql = "SELECT id FROM categories WHERE name = :name";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkSql, [':name' => $name]);
                    if ($checkStmt->fetch()) {
                        throw new PDOException("A category with this name already exists.");
                    }

                    $sql = "INSERT INTO categories (name, description) VALUES (:name, :description)";
                    $params = [
                        ':name' => $name,
                        ':description' => $description ?: null, // Store null if empty
                    ];
                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "Category '" . esc($name) . "' created successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_categories.php'); // Redirect
                    exit;

                } catch (PDOException $e) {
                    DatabaseConfig::rollbackTransaction($pdo);
                    // Use the specific exception message if it's about uniqueness
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false || $e->getMessage() === "A category with this name already exists.") {
                         $feedback_message = "Error creating category: A category with this name already exists.";
                    } else {
                         $feedback_message = "Error creating category: " . esc($e->getMessage());
                    }
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

        // -- Update Category --
        elseif ($post_action === 'update' && isset($_POST['category_id'])) {
            $category_id = (int)$_POST['category_id'];
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            // Validation
            $errors = [];
             if (empty($name)) {
                $errors[] = "Category Name is required.";
            } elseif (strlen($name) > 100) {
                 $errors[] = "Category Name cannot exceed 100 characters.";
            }


            if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                    // Check uniqueness (excluding self)
                    $checkSql = "SELECT id FROM categories WHERE name = :name AND id != :id";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkSql, [
                        ':name' => $name,
                        ':id' => $category_id
                    ]);
                    if ($checkStmt->fetch()) {
                        throw new PDOException("Another category with this name already exists.");
                    }

                    $sql = "UPDATE categories SET name = :name, description = :description WHERE id = :id";
                    $params = [
                        ':name' => $name,
                        ':description' => $description ?: null,
                        ':id' => $category_id,
                    ];
                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "Category '" . esc($name) . "' updated successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_categories.php'); // Redirect
                    exit;

                } catch (PDOException $e) {
                    DatabaseConfig::rollbackTransaction($pdo);
                     if (strpos($e->getMessage(), 'Duplicate entry') !== false || $e->getMessage() === "Another category with this name already exists.") {
                         $feedback_message = "Error updating category: Another category with this name already exists.";
                    } else {
                        $feedback_message = "Error updating category: " . esc($e->getMessage());
                    }
                    $feedback_type = 'error';
                    $form_data = $_POST; // Keep form data
                    $action = 'edit'; // Stay on edit form
                    $category_id_to_edit = $category_id; // Ensure ID is available for edit view
                }
            } else {
                $feedback_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
                $feedback_type = 'error';
                $form_data = $_POST; // Keep form data
                $action = 'edit'; // Stay on edit form
                $category_id_to_edit = $category_id; // Ensure ID is available for edit view
            }
        }

        // -- Delete Category --
        elseif ($post_action === 'delete' && isset($_POST['category_id'])) {
            $category_id_to_delete = (int)$_POST['category_id'];

            DatabaseConfig::beginTransaction($pdo);
            try {
                // **** CRUCIAL: Check if any products are using this category ****
                $checkProductsSql = "SELECT COUNT(*) as product_count FROM products WHERE category_id = :category_id";
                $checkStmt = DatabaseConfig::executeQuery($pdo, $checkProductsSql, [':category_id' => $category_id_to_delete]);
                $result = $checkStmt->fetch();

                if ($result && $result['product_count'] > 0) {
                    // Cannot delete - products exist
                    DatabaseConfig::rollbackTransaction($pdo); // No changes needed
                    $_SESSION['feedback_message'] = "Cannot delete category. {$result['product_count']} product(s) are assigned to it.";
                    $_SESSION['feedback_type'] = 'error';
                } else {
                    // No products assigned, proceed with deletion
                    $sql = "DELETE FROM categories WHERE id = :id";
                    $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $category_id_to_delete]);

                    if ($stmt->rowCount() > 0) {
                        DatabaseConfig::commitTransaction($pdo);
                        $_SESSION['feedback_message'] = "Category deleted successfully.";
                        $_SESSION['feedback_type'] = 'success';
                    } else {
                        DatabaseConfig::rollbackTransaction($pdo);
                        $_SESSION['feedback_message'] = "Category not found or could not be deleted.";
                        $_SESSION['feedback_type'] = 'warning';
                    }
                }
            } catch (PDOException $e) {
                DatabaseConfig::rollbackTransaction($pdo);
                $_SESSION['feedback_message'] = "Error deleting category: " . esc($e->getMessage());
                $_SESSION['feedback_type'] = 'error';
            }
            header('Location: admin_categories.php'); // Redirect back to list
            exit;
        }
    } // End POST handling

    // == Prepare Data for Views (List, Add, Edit) ==
    $categories = [];
    $category_to_edit = null;

    if ($action === 'list') {
        $sql = "SELECT id, name, description, created_at, updated_at FROM categories ORDER BY name ASC";
        $stmt = DatabaseConfig::executeQuery($pdo, $sql);
        $categories = $stmt->fetchAll();
    }
    elseif (($action === 'edit') && $category_id_to_edit && $can_manage) { // Can only edit if allowed
        if (!isset($form_data)) { // If not coming from a failed POST
            $sql = "SELECT id, name, description FROM categories WHERE id = :id";
            $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $category_id_to_edit]);
            $category_to_edit = $stmt->fetch();

            if (!$category_to_edit) {
                $_SESSION['feedback_message'] = "Category not found.";
                $_SESSION['feedback_type'] = 'error';
                header('Location: admin_categories.php');
                exit;
            }
            $form_data = $category_to_edit; // Use fetched data for the form
        }
        // If form submission failed, $form_data is already set from POST block
    }
    elseif ($action === 'add' && $can_manage) { // Can only add if allowed
        if (!isset($form_data)) { // If not coming from a failed POST
            $form_data = [ // Default values for add form
                'name' => '', 'description' => '',
            ];
        }
    } elseif (($action === 'add' || $action === 'edit') && !$can_manage) {
        // Tried to access add/edit form without permission (edge case if URL manipulated)
        $_SESSION['feedback_message'] = "Access denied.";
        $_SESSION['feedback_type'] = 'error';
        header('Location: admin_categories.php');
        exit;
    }


} catch (PDOException $e) {
    $feedback_message = "Database error: " . esc($e->getMessage());
    $feedback_type = 'error';
    // Logged by db_connect.php usually
    $action = 'list'; // Fallback to list view
} finally {
    DatabaseConfig::closeConnection($pdo);
}
require_once __DIR__ . '/../includes/quonav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Categories</title>
    <!-- Adjust path to your CSS file -->
    <link rel="stylesheet" href="../css/admin.css">
    <script>
        // Confirmation for delete action
        function confirmDelete(categoryId, categoryName) {
            if (confirm('Are you sure you want to delete the category "' + categoryName + '" (ID: ' + categoryId + ')?\nThis cannot be undone.')) {
                document.getElementById('delete-form-' + categoryId).submit();
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

        <h1>Manage Categories</h1>

        <?php if ($feedback_message): ?>
            <div class="message <?php echo esc($feedback_type); ?>">
                <?php echo $feedback_message; // Can contain <br> from validation errors ?>
            </div>
        <?php endif; ?>

        <?php // --- Display Add/Edit Form --- ?>
        <?php if (($action === 'add' || $action === 'edit') && $can_manage): ?>
            <h2><?php echo ($action === 'edit' ? 'Edit Category' : 'Add New Category'); ?></h2>
            <form action="admin_categories.php" method="POST">
                <input type="hidden" name="action" value="<?php echo ($action === 'edit' ? 'update' : 'create'); ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="category_id" value="<?php echo (int)($form_data['id'] ?? 0); ?>">
                <?php endif; ?>

                <div>
                    <label for="name">Category Name: <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo esc($form_data['name'] ?? ''); ?>" required maxlength="100">
                </div>
                <div>
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="4"><?php echo esc($form_data['description'] ?? ''); ?></textarea>
                </div>

                <div>
                    <button type="submit"><?php echo ($action === 'edit' ? 'Update Category' : 'Create Category'); ?></button>
                    <a href="admin_categories.php" class="button-link" style="background-color: #aaa;">Cancel</a>
                </div>
            </form>
        <?php endif; // End Add/Edit Form ?>


        <?php // --- Display Category List --- ?>
        <?php if ($action === 'list'): ?>
            <?php if ($can_manage): // Show 'Add New' only if user has rights ?>
                <a href="admin_categories.php?action=add" class="button-link">Add New Category</a>
            <?php endif; ?>

            <h2>Current Categories</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <?php if ($can_manage): ?>
                           <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="<?php echo $can_manage ? 6 : 5; ?>">No categories found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo (int)$category['id']; ?></td>
                            <td><?php echo esc($category['name']); ?></td>
                            <td><?php echo nl2br(esc($category['description'] ?? 'N/A')); // Use nl2br for multi-line display ?></td>
                            <td><?php echo formatRelativeTime($category['created_at']); ?></td>
                            <td><?php echo formatRelativeTime($category['updated_at']); ?></td>
                           <?php if ($can_manage): ?>
                               <td class="actions">
                                   <a href="admin_categories.php?action=edit&id=<?php echo (int)$category['id']; ?>" class="button-link edit">Edit</a>
                                   <!-- Hidden form for delete action -->
                                   <form id="delete-form-<?php echo (int)$category['id']; ?>" action="admin_categories.php" method="POST" style="display: none;">
                                       <input type="hidden" name="action" value="delete">
                                       <input type="hidden" name="category_id" value="<?php echo (int)$category['id']; ?>">
                                   </form>
                                    <!-- The button triggers JavaScript which submits the hidden form -->
                                   <button type="button" onclick="confirmDelete(<?php echo (int)$category['id']; ?>, '<?php echo esc(addslashes($category['name'])); ?>')" class="button-link delete">Delete</button>
                               </td>
                           <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; // End Category List ?>

    </div>
</body>
</html>