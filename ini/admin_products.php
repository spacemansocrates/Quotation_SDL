<?php
// admin_products.php
session_start();

// Adjust paths if this file is not in the web root
require_once __DIR__ . '/../includes/time_formating_helper.php'; // Add this for time formatting functions
require_once __DIR__ . '/../includes/db_connect.php';


// --- Configuration for Image Uploads ---
define('UPLOAD_DIR', __DIR__ . '/uploads/product_images/'); // Absolute path to upload directory
define('UPLOAD_URL', 'uploads/product_images/');          // Relative URL path for displaying images
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('MAX_IMAGE_SIZE', 2 * 1024 * 1024); // 2 MB

// Ensure upload directory exists and is writable
if (!file_exists(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0775, true)) {
        die('Failed to create upload directory. Please create "uploads/product_images/" manually and make it writable.');
    }
} elseif (!is_writable(UPLOAD_DIR)) {
    die('Upload directory "uploads/product_images/" is not writable. Please check permissions.');
}


// --- Authorization Check ---
$allowed_manage_roles = ['admin', 'manager', 'supervisor'];
$is_viewer = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'viewer';
$can_manage = isset($_SESSION['user_id']) && in_array($_SESSION['user_role'], $allowed_manage_roles);
$current_user_id = $_SESSION['user_id'] ?? null;

if (!$can_manage && !$is_viewer) {
    $_SESSION['feedback_message'] = "Access denied. Please log in with appropriate permissions.";
    $_SESSION['feedback_type'] = 'error';
    header('Location: login.php');
    exit;
}
if ($is_viewer && ($_GET['action'] ?? 'list') !== 'list' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    $_SESSION['feedback_message'] = "Access denied. Viewers can only list products.";
    $_SESSION['feedback_type'] = 'error';
    header('Location: admin_products.php');
    exit;
}

// --- Initial Setup ---
$pdo = null;
$action = $_GET['action'] ?? 'list';
$product_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : null;

$feedback_message = '';
$feedback_type = '';
$form_data = []; // To hold form data, especially on errors

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

// --- Image Handling Functions ---
function handleImageUpload(array $file_input, ?string $current_image_path = null): ?string {
    if (isset($file_input['name']) && $file_input['error'] === UPLOAD_ERR_OK) {
        $file_name = $file_input['name'];
        $file_tmp_name = $file_input['tmp_name'];
        $file_size = $file_input['size'];
        $file_type = mime_content_type($file_tmp_name); // More reliable than $file_input['type']

        // Validate type and size
        if (!in_array($file_type, ALLOWED_IMAGE_TYPES)) {
            throw new Exception("Invalid file type. Allowed: JPG, PNG, GIF.");
        }
        if ($file_size > MAX_IMAGE_SIZE) {
            throw new Exception("File size exceeds limit (2MB).");
        }

        // Generate unique filename
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_file_name = uniqid('prod_', true) . '.' . $file_extension;
        $destination = UPLOAD_DIR . $new_file_name;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            // Delete old image if a new one is uploaded and an old one exists
            if ($current_image_path && file_exists(UPLOAD_DIR . basename($current_image_path))) {
                unlink(UPLOAD_DIR . basename($current_image_path));
            }
            return UPLOAD_URL . $new_file_name; // Return relative path for DB
        } else {
            throw new Exception("Failed to move uploaded file.");
        }
    } elseif ($file_input['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors
        throw new Exception("Image upload error: " . $file_input['error']);
    }
    return $current_image_path; // No new file uploaded, or error but not critical enough to stop, return current path
}

function deleteProductImage(?string $image_path): void {
    if ($image_path && file_exists(UPLOAD_DIR . basename($image_path))) {
        unlink(UPLOAD_DIR . basename($image_path));
    }
}

// --- CRUD Operations ---
try {
    $pdo = DatabaseConfig::getConnection();

    // Fetch categories for dropdown (used in add/edit forms and list view)
    $categories_list_stmt = DatabaseConfig::executeQuery($pdo, "SELECT id, name FROM categories ORDER BY name ASC");
    $categories_list = $categories_list_stmt->fetchAll(PDO::FETCH_KEY_PAIR);


    // == Handle POST Requests (Create/Update/Delete) ==
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
        $post_action = $_POST['action'] ?? '';
        $form_data = $_POST; // Preserve form data on error

        // -- Create Product --
        if ($post_action === 'create') {
            $sku = trim($_POST['sku'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $default_unit_price = $_POST['default_unit_price'] ?? '';
            $default_unit_of_measurement = trim($_POST['default_unit_of_measurement'] ?? '');
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $image_path = null;

            // Validation
            $errors = [];
            if (empty($sku)) $errors[] = "SKU is required.";
            if (empty($name)) $errors[] = "Product Name is required.";
            if (empty($default_unit_price) || !is_numeric($default_unit_price) || $default_unit_price <= 0) {
                $errors[] = "Valid Default Unit Price (positive number) is required.";
            }
            if (empty($default_unit_of_measurement)) $errors[] = "Unit of Measurement is required.";
            if ($category_id !== null && !isset($categories_list[$category_id])) $errors[] = "Invalid Category selected.";


            try {
                 if (isset($_FILES['default_image']) && $_FILES['default_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $image_path = handleImageUpload($_FILES['default_image']);
                 }
            } catch (Exception $e) {
                $errors[] = "Image upload error: " . $e->getMessage();
            }


            if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                    // Check SKU uniqueness
                    $checkSkuSql = "SELECT id FROM products WHERE sku = :sku";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkSkuSql, [':sku' => $sku]);
                    if ($checkStmt->fetch()) {
                        throw new PDOException("SKU '{$sku}' already exists.");
                    }

                    $sql = "INSERT INTO products (sku, name, description, default_unit_price, default_unit_of_measurement, default_image_path, category_id, created_by_user_id, updated_by_user_id)
                            VALUES (:sku, :name, :description, :price, :uom, :image, :cat_id, :creator, :updater)";
                    $params = [
                        ':sku' => $sku,
                        ':name' => $name,
                        ':description' => $description ?: null,
                        ':price' => $default_unit_price,
                        ':uom' => $default_unit_of_measurement,
                        ':image' => $image_path,
                        ':cat_id' => $category_id,
                        ':creator' => $current_user_id,
                        ':updater' => $current_user_id,
                    ];
                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "Product '" . esc($name) . "' created successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_products.php');
                    exit;

                } catch (PDOException | Exception $e) { // Catch PDO and custom image exceptions
                    DatabaseConfig::rollbackTransaction($pdo);
                    if ($image_path) deleteProductImage($image_path); // Rollback image upload on DB error
                    $feedback_message = "Error creating product: " . esc($e->getMessage());
                    $feedback_type = 'error';
                    // $form_data is already set
                    $action = 'add';
                }
            } else {
                $feedback_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
                $feedback_type = 'error';
                if ($image_path) deleteProductImage($image_path); // Delete uploaded image if validation failed
                // $form_data is already set
                $action = 'add';
            }
        }

        // -- Update Product --
        elseif ($post_action === 'update' && isset($_POST['product_id'])) {
            $product_id = (int)$_POST['product_id'];
            $sku = trim($_POST['sku'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $default_unit_price = $_POST['default_unit_price'] ?? '';
            $default_unit_of_measurement = trim($_POST['default_unit_of_measurement'] ?? '');
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $remove_image = isset($_POST['remove_image']);

            // Fetch current product to get current image path
            $currentProductStmt = DatabaseConfig::executeQuery($pdo, "SELECT default_image_path FROM products WHERE id = :id", [':id' => $product_id]);
            $currentProduct = $currentProductStmt->fetch();
            $current_image_path = $currentProduct ? $currentProduct['default_image_path'] : null;
            $new_image_path = $current_image_path; // Start with current

            // Validation
            $errors = [];
            if (empty($sku)) $errors[] = "SKU is required.";
            if (empty($name)) $errors[] = "Product Name is required.";
            if (empty($default_unit_price) || !is_numeric($default_unit_price) || $default_unit_price <= 0) {
                $errors[] = "Valid Default Unit Price (positive number) is required.";
            }
            if (empty($default_unit_of_measurement)) $errors[] = "Unit of Measurement is required.";
            if ($category_id !== null && !isset($categories_list[$category_id])) $errors[] = "Invalid Category selected.";

            try {
                if (isset($_FILES['default_image']) && $_FILES['default_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $new_image_path = handleImageUpload($_FILES['default_image'], $current_image_path); // Pass current to delete old if new is uploaded
                } elseif ($remove_image && $current_image_path) {
                    deleteProductImage($current_image_path);
                    $new_image_path = null;
                }
            } catch (Exception $e) {
                $errors[] = "Image processing error: " . $e->getMessage();
            }

            if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                    // Check SKU uniqueness (excluding self)
                    $checkSkuSql = "SELECT id FROM products WHERE sku = :sku AND id != :id";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkSkuSql, [':sku' => $sku, ':id' => $product_id]);
                    if ($checkStmt->fetch()) {
                        throw new PDOException("SKU '{$sku}' already exists for another product.");
                    }

                    $sql = "UPDATE products SET
                                sku = :sku, name = :name, description = :description,
                                default_unit_price = :price, default_unit_of_measurement = :uom,
                                default_image_path = :image, category_id = :cat_id,
                                updated_by_user_id = :updater
                            WHERE id = :id";
                    $params = [
                        ':sku' => $sku,
                        ':name' => $name,
                        ':description' => $description ?: null,
                        ':price' => $default_unit_price,
                        ':uom' => $default_unit_of_measurement,
                        ':image' => $new_image_path,
                        ':cat_id' => $category_id,
                        ':updater' => $current_user_id,
                        ':id' => $product_id,
                    ];
                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "Product '" . esc($name) . "' updated successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_products.php');
                    exit;

                } catch (PDOException | Exception $e) {
                    DatabaseConfig::rollbackTransaction($pdo);
                    // If a new image was uploaded but DB failed, we might need to delete it if it wasn't the original
                    // This part is tricky: if handleImageUpload replaced an old one, the old one is gone.
                    // If handleImageUpload created a new one, and DB failed, we should delete the new one.
                    // The current handleImageUpload returns the path of the *newly effective* image.
                    // If $new_image_path is different from $current_image_path and the error occurred,
                    // it implies a new image was involved.
                    if ($new_image_path !== $current_image_path && $new_image_path !== null && file_exists(UPLOAD_DIR . basename($new_image_path))) {
                        // This logic is complex, might be simpler to just leave image and let user re-upload
                        // Or, if a new file was uploaded, and handleImageUpload deleted old, and DB failed,
                        // the new one is on disk. Reverting this is harder.
                        // For now, we just log error. User can re-edit.
                    }
                    $feedback_message = "Error updating product: " . esc($e->getMessage());
                    $feedback_type = 'error';
                    // $form_data is already set
                    $action = 'edit';
                    $product_id_to_edit = $product_id; // Keep for form
                }
            } else {
                $feedback_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
                $feedback_type = 'error';
                // $form_data is already set
                $action = 'edit';
                $product_id_to_edit = $product_id; // Keep for form
            }
        }

        // -- Delete Product --
        elseif ($post_action === 'delete' && isset($_POST['product_id'])) {
            $product_id_to_delete = (int)$_POST['product_id'];

            DatabaseConfig::beginTransaction($pdo);
            try {
                // Get image path before deleting record
                $imgStmt = DatabaseConfig::executeQuery($pdo, "SELECT default_image_path FROM products WHERE id = :id", [':id' => $product_id_to_delete]);
                $product_image = $imgStmt->fetchColumn();

                $sql = "DELETE FROM products WHERE id = :id";
                $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $product_id_to_delete]);

                if ($stmt->rowCount() > 0) {
                    deleteProductImage($product_image); // Delete image from server
                    DatabaseConfig::commitTransaction($pdo);
                    $_SESSION['feedback_message'] = "Product deleted successfully.";
                    $_SESSION['feedback_type'] = 'success';
                } else {
                    DatabaseConfig::rollbackTransaction($pdo);
                    $_SESSION['feedback_message'] = "Product not found or could not be deleted.";
                    $_SESSION['feedback_type'] = 'warning';
                }
            } catch (PDOException $e) {
                DatabaseConfig::rollbackTransaction($pdo);
                 if (strpos($e->getCode(), '23000') !== false) { // Foreign key constraint
                    $_SESSION['feedback_message'] = "Cannot delete product. It might be referenced in orders or other records.";
                } else {
                    $_SESSION['feedback_message'] = "Error deleting product: " . esc($e->getMessage());
                }
                $_SESSION['feedback_type'] = 'error';
            }
            header('Location: admin_products.php');
            exit;
        }
    } // End POST handling

    // == Prepare Data for Views (List, Add, Edit) ==
    $products = [];
    $product_to_edit = null;

    if ($action === 'list') {
        $sql = "SELECT p.*, c.name as category_name, uc.username as creator_username, uu.username as updater_username
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN users uc ON p.created_by_user_id = uc.id
                LEFT JOIN users uu ON p.updated_by_user_id = uu.id
                ORDER BY p.name ASC";
        $stmt = DatabaseConfig::executeQuery($pdo, $sql);
        $products = $stmt->fetchAll();
    }
    elseif ($action === 'edit' && $product_id_to_edit && $can_manage) {
        if (empty($form_data)) { // If not coming from a failed POST (where $form_data is already set)
            $sql = "SELECT * FROM products WHERE id = :id";
            $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $product_id_to_edit]);
            $product_to_edit = $stmt->fetch();

            if (!$product_to_edit) {
                $_SESSION['feedback_message'] = "Product not found.";
                $_SESSION['feedback_type'] = 'error';
                header('Location: admin_products.php');
                exit;
            }
            $form_data = $product_to_edit;
        }
    }
    elseif ($action === 'add' && $can_manage) {
        if (empty($form_data)) {
            $form_data = [ // Default values for add form
                'sku' => '', 'name' => '', 'description' => '',
                'default_unit_price' => '', 'default_unit_of_measurement' => '',
                'category_id' => null, 'default_image_path' => null
            ];
        }
    } elseif (($action === 'add' || $action === 'edit') && !$can_manage) {
        $_SESSION['feedback_message'] = "Access denied.";
        $_SESSION['feedback_type'] = 'error';
        header('Location: admin_products.php');
        exit;
    }

} catch (PDOException $e) {
    $feedback_message = "Database error: " . esc($e->getMessage());
    $feedback_type = 'error';
    $action = 'list';
} catch (Exception $e) { // Catch other general exceptions (like from image handling setup)
    $feedback_message = "An error occurred: " . esc($e->getMessage());
    $feedback_type = 'error';
    $action = 'list';
} finally {
    DatabaseConfig::closeConnection($pdo);
}
require_once __DIR__ . '/../includes/nav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Products</title>
    <link rel="stylesheet" href="../css/admin.css">
    <script>
        function confirmDelete(productId, productName) {
            if (confirm('Are you sure you want to delete the product "' + productName + '" (ID: ' + productId + ')? This action cannot be undone.')) {
                document.getElementById('delete-form-' + productId).submit();
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="user-info">
             Logged in as: <strong><?php echo esc($_SESSION['username'] ?? 'Guest'); ?></strong>
             (<?php echo esc($_SESSION['user_role'] ?? 'N/A'); ?>)
             | <a href="logout.php">Logout</a>
        </div>

        <h1>Manage Products</h1>

        <?php if ($feedback_message): ?>
            <div class="message <?php echo esc($feedback_type); ?>">
                <?php echo $feedback_message; ?>
            </div>
        <?php endif; ?>

        <?php // --- Display Add/Edit Form --- ?>
        <?php if (($action === 'add' || $action === 'edit') && $can_manage): ?>
            <h2><?php echo ($action === 'edit' ? 'Edit Product' : 'Add New Product'); ?></h2>
            <form action="admin_products.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo ($action === 'edit' ? 'update' : 'create'); ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="product_id" value="<?php echo (int)($form_data['id'] ?? 0); ?>">
                <?php endif; ?>

                <div>
                    <label for="sku">SKU: <span class="required">*</span></label>
                    <input type="text" id="sku" name="sku" value="<?php echo esc($form_data['sku'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="name">Product Name: <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo esc($form_data['name'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="3"><?php echo esc($form_data['description'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label for="default_unit_price">Default Unit Price: <span class="required">*</span></label>
                    <input type="number" id="default_unit_price" name="default_unit_price" value="<?php echo esc($form_data['default_unit_price'] ?? ''); ?>" step="0.01" min="0.01" required>
                </div>
                <div>
                    <label for="default_unit_of_measurement">Unit of Measurement (e.g., kg, pcs, pack): <span class="required">*</span></label>
                    <input type="text" id="default_unit_of_measurement" name="default_unit_of_measurement" value="<?php echo esc($form_data['default_unit_of_measurement'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="category_id">Category:</label>
                    <select id="category_id" name="category_id">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories_list as $cat_id => $cat_name): ?>
                            <option value="<?php echo (int)$cat_id; ?>" <?php echo (($form_data['category_id'] ?? null) == $cat_id ? 'selected' : ''); ?>>
                                <?php echo esc($cat_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="default_image">Product Image (Max 2MB, JPG/PNG/GIF):</label>
                    <input type="file" id="default_image" name="default_image" accept="image/jpeg,image/png,image/gif">
                    <?php if ($action === 'edit' && !empty($form_data['default_image_path'])): ?>
                        <p>Current Image: <br>
                           <img src="<?php echo esc($form_data['default_image_path']); ?>" alt="<?php echo esc($form_data['name'] ?? ''); ?>" style="max-width: 100px; max-height: 100px; margin-top: 5px;">
                           <br><label><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
                        </p>
                    <?php endif; ?>
                </div>

                <div>
                    <button type="submit"><?php echo ($action === 'edit' ? 'Update Product' : 'Create Product'); ?></button>
                    <a href="admin_products.php" class="button-link" style="background-color: #aaa;">Cancel</a>
                </div>
            </form>
        <?php endif; ?>


        <?php // --- Display Product List --- ?>
        <?php if ($action === 'list'): ?>
            <?php if ($can_manage): ?>
                <a href="admin_products.php?action=add" class="button-link">Add New Product</a>
            <?php endif; ?>

            <h2>Current Products</h2>
            <div style="overflow-x: auto;"> <!-- For responsiveness on small screens -->
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>UoM</th>
                        <th>Created</th>
                        <!-- <th>Updated</th> -->
                        <?php if ($can_manage): ?>
                           <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="<?php echo $can_manage ? 9 : 8; ?>">No products found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo (int)$product['id']; ?></td>
                            <td>
                                <?php if (!empty($product['default_image_path'])): ?>
                                    <img src="<?php echo esc($product['default_image_path']); ?>" alt="<?php echo esc($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc($product['sku']); ?></td>
                            <td><?php echo esc($product['name']); ?></td>
                            <td><?php echo esc($product['category_name'] ?? 'N/A'); ?></td>
                            <td><?php echo esc(number_format((float)($product['default_unit_price'] ?? 0), 2)); ?></td>
                            <td><?php echo esc($product['default_unit_of_measurement']); ?></td>
                            <td>
                                <?php echo formatRelativeTime($product['created_at']); ?>
                                <small><br>(by <?php echo esc($product['creator_username'] ?? 'System'); ?>)</small>
                            </td>
                            <!-- Optional: Updated Info
                            <td>
                                <?php //echo formatRelativeTime($product['updated_at']); ?>
                                <small><br>(by <?php //echo esc($product['updater_username'] ?? 'System'); ?>)</small>
                            </td>
                            -->
                           <?php if ($can_manage): ?>
                               <td class="actions">
                                   <a href="admin_products.php?action=edit&id=<?php echo (int)$product['id']; ?>" class="button-link edit">Edit</a>
                                   <form id="delete-form-<?php echo (int)$product['id']; ?>" action="admin_products.php" method="POST" style="display: none;">
                                       <input type="hidden" name="action" value="delete">
                                       <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                   </form>
                                   <button type="button" onclick="confirmDelete(<?php echo (int)$product['id']; ?>, '<?php echo esc(addslashes($product['name'])); ?>')" class="button-link delete">Delete</button>
                               </td>
                           <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>