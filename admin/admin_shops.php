<?php
// admin_shops.php
ob_start(); 
session_start();

// Adjust paths if necessary
require_once __DIR__ . '/../includes/time_formating_helper.php'; // Add this for time formatting functions
require_once __DIR__ . '/../includes/nav.php';
require_once __DIR__ . '/../includes/db_connect.php';


// --- Configuration for Logo Uploads ---
define('SHOP_LOGO_UPLOAD_DIR', __DIR__ . '/uploads/shop_logos/'); // Absolute path
define('SHOP_LOGO_UPLOAD_URL', 'uploads/shop_logos/');          // Relative URL path
define('ALLOWED_LOGO_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml']); // Allow SVG?
define('MAX_LOGO_SIZE', 1 * 1024 * 1024); // 1 MB limit for logos? Adjust as needed

// Ensure upload directory exists and is writable
if (!file_exists(SHOP_LOGO_UPLOAD_DIR)) {
    if (!mkdir(SHOP_LOGO_UPLOAD_DIR, 0775, true)) {
        die('Failed to create upload directory. Please create "uploads/shop_logos/" manually and make it writable.');
    }
} elseif (!is_writable(SHOP_LOGO_UPLOAD_DIR)) {
    die('Upload directory "uploads/shop_logos/" is not writable. Please check permissions.');
}

// --- Authorization Check ---
$allowed_manage_roles = ['admin', 'manager', 'supervisor'];
$is_viewer = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'viewer';
$can_manage = isset($_SESSION['user_id']) && in_array($_SESSION['user_role'], $allowed_manage_roles);
$current_user_id = $_SESSION['user_id'] ?? null; // Needed for creator/updater IDs

if (!$can_manage && !$is_viewer) {
    $_SESSION['feedback_message'] = "Access denied. Please log in with appropriate permissions.";
    $_SESSION['feedback_type'] = 'error';
    header('Location: login.php');
    exit;
}
if ($is_viewer && ($_GET['action'] ?? 'list') !== 'list' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    $_SESSION['feedback_message'] = "Access denied. Viewers can only list shops.";
    $_SESSION['feedback_type'] = 'error';
    header('Location: admin_shops.php');
    exit;
}

// --- Initial Setup ---
$pdo = null;
$action = $_GET['action'] ?? 'list';
$shop_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : null;

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

// --- Logo Handling Functions ---
// Reusing the image upload logic, just using the specific constants
function handleLogoUpload(array $file_input, ?string $current_logo_path = null): ?string {
    if (isset($file_input['name']) && $file_input['error'] === UPLOAD_ERR_OK) {
        $file_name = $file_input['name'];
        $file_tmp_name = $file_input['tmp_name'];
        $file_size = $file_input['size'];
        $file_type = mime_content_type($file_tmp_name);

        if (!in_array($file_type, ALLOWED_LOGO_TYPES)) {
            throw new Exception("Invalid logo file type. Allowed: JPG, PNG, GIF, SVG.");
        }
        if ($file_size > MAX_LOGO_SIZE) {
            throw new Exception("Logo file size exceeds limit (1MB).");
        }

        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_file_name = uniqid('logo_', true) . '.' . $file_extension;
        $destination = SHOP_LOGO_UPLOAD_DIR . $new_file_name;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            // Delete old logo if replacing
            if ($current_logo_path && file_exists(SHOP_LOGO_UPLOAD_DIR . basename($current_logo_path))) {
                unlink(SHOP_LOGO_UPLOAD_DIR . basename($current_logo_path));
            }
            return SHOP_LOGO_UPLOAD_URL . $new_file_name; // Relative path for DB
        } else {
            throw new Exception("Failed to move uploaded logo file.");
        }
    } elseif ($file_input['error'] !== UPLOAD_ERR_NO_FILE) {
        throw new Exception("Logo upload error code: " . $file_input['error']);
    }
    return $current_logo_path; // No new file, return existing path
}

function deleteShopLogo(?string $logo_path): void {
    if ($logo_path && file_exists(SHOP_LOGO_UPLOAD_DIR . basename($logo_path))) {
        unlink(SHOP_LOGO_UPLOAD_DIR . basename($logo_path));
    }
}

// --- CRUD Operations ---
try {
    $pdo = DatabaseConfig::getConnection();

    // == Handle POST Requests (Create/Update/Delete) ==
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
        $post_action = $_POST['action'] ?? '';
        $form_data = $_POST; // Preserve form data on error

        // -- Create Shop --
        if ($post_action === 'create') {
            $shop_code = trim($_POST['shop_code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $address_line1 = trim($_POST['address_line1'] ?? '');
            $address_line2 = trim($_POST['address_line2'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $country = trim($_POST['country'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $tpin_no = trim($_POST['tpin_no'] ?? '');
            $logo_path = null;

            // Validation
            $errors = [];
            if (empty($shop_code)) $errors[] = "Shop Code is required.";
            if (empty($name)) $errors[] = "Shop Name is required.";
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid Email format.";
            // Add more validation (e.g., phone format, TPIN format) if needed

            // Handle Logo Upload within try-catch
             try {
                 if (isset($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $logo_path = handleLogoUpload($_FILES['shop_logo']);
                 }
            } catch (Exception $e) {
                $errors[] = "Logo upload error: " . $e->getMessage();
            }

            if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                    // Check Shop Code uniqueness
                    $checkCodeSql = "SELECT id FROM shops WHERE shop_code = :shop_code";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkCodeSql, [':shop_code' => $shop_code]);
                    if ($checkStmt->fetch()) {
                        throw new PDOException("Shop Code '{$shop_code}' already exists.");
                    }

                    $sql = "INSERT INTO shops (shop_code, name, address_line1, address_line2, city, country, phone, email, logo_path, tpin_no, created_by_user_id, updated_by_user_id)
                            VALUES (:code, :name, :addr1, :addr2, :city, :country, :phone, :email, :logo, :tpin, :creator, :updater)";
                    $params = [
                        ':code' => $shop_code,
                        ':name' => $name,
                        ':addr1' => $address_line1 ?: null,
                        ':addr2' => $address_line2 ?: null,
                        ':city' => $city ?: null,
                        ':country' => $country ?: null,
                        ':phone' => $phone ?: null,
                        ':email' => $email ?: null,
                        ':logo' => $logo_path,
                        ':tpin' => $tpin_no ?: null,
                        ':creator' => $current_user_id,
                        ':updater' => $current_user_id, // Set updater on creation too
                    ];
                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "Shop '" . esc($name) . "' created successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_shops.php');
                    exit;

                } catch (PDOException | Exception $e) {
                    DatabaseConfig::rollbackTransaction($pdo);
                    if ($logo_path) deleteShopLogo($logo_path); // Clean up uploaded logo on DB failure
                    $feedback_message = "Error creating shop: " . esc($e->getMessage());
                    $feedback_type = 'error';
                    // $form_data is already set
                    $action = 'add';
                }
            } else {
                $feedback_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
                $feedback_type = 'error';
                 if ($logo_path) deleteShopLogo($logo_path); // Clean up uploaded logo on validation failure
                // $form_data is already set
                $action = 'add';
            }
        }

        // -- Update Shop --
        elseif ($post_action === 'update' && isset($_POST['shop_id'])) {
            $shop_id = (int)$_POST['shop_id'];
            $shop_code = trim($_POST['shop_code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $address_line1 = trim($_POST['address_line1'] ?? '');
            $address_line2 = trim($_POST['address_line2'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $country = trim($_POST['country'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $tpin_no = trim($_POST['tpin_no'] ?? '');
            $remove_logo = isset($_POST['remove_logo']);

            // Fetch current logo path
            $currentShopStmt = DatabaseConfig::executeQuery($pdo, "SELECT logo_path FROM shops WHERE id = :id", [':id' => $shop_id]);
            $currentShop = $currentShopStmt->fetch();
            $current_logo_path = $currentShop ? $currentShop['logo_path'] : null;
            $new_logo_path = $current_logo_path;

            // Validation
            $errors = [];
             if (empty($shop_code)) $errors[] = "Shop Code is required.";
            if (empty($name)) $errors[] = "Shop Name is required.";
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid Email format.";

            // Handle Logo Upload/Removal
            try {
                if (isset($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $new_logo_path = handleLogoUpload($_FILES['shop_logo'], $current_logo_path);
                } elseif ($remove_logo && $current_logo_path) {
                    deleteShopLogo($current_logo_path);
                    $new_logo_path = null;
                }
            } catch (Exception $e) {
                $errors[] = "Logo processing error: " . $e->getMessage();
            }


            if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                    // Check Shop Code uniqueness (excluding self)
                    $checkCodeSql = "SELECT id FROM shops WHERE shop_code = :shop_code AND id != :id";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkCodeSql, [':shop_code' => $shop_code, ':id' => $shop_id]);
                    if ($checkStmt->fetch()) {
                        throw new PDOException("Shop Code '{$shop_code}' already exists for another shop.");
                    }

                    $sql = "UPDATE shops SET
                                shop_code = :code, name = :name, address_line1 = :addr1, address_line2 = :addr2,
                                city = :city, country = :country, phone = :phone, email = :email,
                                logo_path = :logo, tpin_no = :tpin, updated_by_user_id = :updater
                            WHERE id = :id";
                    $params = [
                        ':code' => $shop_code,
                        ':name' => $name,
                        ':addr1' => $address_line1 ?: null,
                        ':addr2' => $address_line2 ?: null,
                        ':city' => $city ?: null,
                        ':country' => $country ?: null,
                        ':phone' => $phone ?: null,
                        ':email' => $email ?: null,
                        ':logo' => $new_logo_path,
                        ':tpin' => $tpin_no ?: null,
                        ':updater' => $current_user_id,
                        ':id' => $shop_id,
                    ];
                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "Shop '" . esc($name) . "' updated successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_shops.php');
                    exit;

                } catch (PDOException | Exception $e) {
                    DatabaseConfig::rollbackTransaction($pdo);
                    // Consider logo rollback logic if needed (complex)
                    $feedback_message = "Error updating shop: " . esc($e->getMessage());
                    $feedback_type = 'error';
                    // $form_data is already set
                    $action = 'edit';
                    $shop_id_to_edit = $shop_id;
                }
            } else {
                $feedback_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
                $feedback_type = 'error';
                // $form_data is already set
                $action = 'edit';
                $shop_id_to_edit = $shop_id;
            }
        }

        // -- Delete Shop --
        elseif ($post_action === 'delete' && isset($_POST['shop_id'])) {
             $shop_id_to_delete = (int)$_POST['shop_id'];

             DatabaseConfig::beginTransaction($pdo);
             try {
                 // Get logo path before deleting record
                $logoStmt = DatabaseConfig::executeQuery($pdo, "SELECT logo_path FROM shops WHERE id = :id", [':id' => $shop_id_to_delete]);
                $shop_logo = $logoStmt->fetchColumn();

                 // Optional: Add checks here if shops are linked to other critical tables

                 $sql = "DELETE FROM shops WHERE id = :id";
                 $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $shop_id_to_delete]);

                 if ($stmt->rowCount() > 0) {
                     deleteShopLogo($shop_logo); // Delete logo from server
                     DatabaseConfig::commitTransaction($pdo);
                     $_SESSION['feedback_message'] = "Shop deleted successfully.";
                     $_SESSION['feedback_type'] = 'success';
                 } else {
                     DatabaseConfig::rollbackTransaction($pdo);
                     $_SESSION['feedback_message'] = "Shop not found or could not be deleted.";
                     $_SESSION['feedback_type'] = 'warning';
                 }
             } catch (PDOException $e) {
                 DatabaseConfig::rollbackTransaction($pdo);
                 // Check for foreign key constraint error (example)
                 if (strpos($e->getCode(), '23000') !== false) {
                     $_SESSION['feedback_message'] = "Cannot delete shop. It might be referenced by other records (e.g., users, orders).";
                 } else {
                    $_SESSION['feedback_message'] = "Error deleting shop: " . esc($e->getMessage());
                 }
                 $_SESSION['feedback_type'] = 'error';
             }
             header('Location: admin_shops.php');
             exit;
        }
    } // End POST handling

    // == Prepare Data for Views (List, Add, Edit) ==
    $shops = [];
    $shop_to_edit = null;

    if ($action === 'list') {
        $sql = "SELECT s.*, uc.username as creator_username, uu.username as updater_username
                FROM shops s
                LEFT JOIN users uc ON s.created_by_user_id = uc.id
                LEFT JOIN users uu ON s.updated_by_user_id = uu.id
                ORDER BY s.name ASC";
        $stmt = DatabaseConfig::executeQuery($pdo, $sql);
        $shops = $stmt->fetchAll();
    }
    elseif ($action === 'edit' && $shop_id_to_edit && $can_manage) {
         if (empty($form_data)) { // Load from DB only if not repopulating after POST error
            $sql = "SELECT * FROM shops WHERE id = :id";
            $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $shop_id_to_edit]);
            $shop_to_edit = $stmt->fetch();

            if (!$shop_to_edit) {
                $_SESSION['feedback_message'] = "Shop not found.";
                $_SESSION['feedback_type'] = 'error';
                header('Location: admin_shops.php');
                exit;
            }
            $form_data = $shop_to_edit; // Populate form data
        }
    }
    elseif ($action === 'add' && $can_manage) {
        if (empty($form_data)) { // Initialize empty form data if not a POST error reload
            $form_data = [
                'shop_code' => '', 'name' => '', 'address_line1' => '', 'address_line2' => '',
                'city' => '', 'country' => '', 'phone' => '', 'email' => '',
                'tpin_no' => '', 'logo_path' => null,
            ];
        }
    } elseif (($action === 'add' || $action === 'edit') && !$can_manage) {
        // Prevent non-managers from accessing add/edit via URL manipulation
        $_SESSION['feedback_message'] = "Access denied.";
        $_SESSION['feedback_type'] = 'error';
        header('Location: admin_shops.php');
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
    <title>Admin - Manage Shops</title>
    <link rel="stylesheet" href="../css/admin.css">
    <script>
        function confirmDelete(shopId, shopName) {
            if (confirm('Are you sure you want to delete the shop "' + shopName + '" (ID: ' + shopId + ')? This action cannot be undone.')) {
                document.getElementById('delete-form-' + shopId).submit();
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <?php require_once __DIR__ . '/../includes/nav.php'; // Include navigation ?>

        <h1>Manage Shops</h1>

        <?php if ($feedback_message): ?>
            <div class="message <?php echo esc($feedback_type); ?>">
                <?php echo $feedback_message; ?>
            </div>
        <?php endif; ?>

        <?php // --- Display Add/Edit Form --- ?>
        <?php if (($action === 'add' || $action === 'edit') && $can_manage): ?>
            <h2><?php echo ($action === 'edit' ? 'Edit Shop' : 'Add New Shop'); ?></h2>
            <form action="admin_shops.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo ($action === 'edit' ? 'update' : 'create'); ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="shop_id" value="<?php echo (int)($form_data['id'] ?? 0); ?>">
                <?php endif; ?>

                <div>
                    <label for="shop_code">Shop Code: <span class="required">*</span></label>
                    <input type="text" id="shop_code" name="shop_code" value="<?php echo esc($form_data['shop_code'] ?? ''); ?>" required maxlength="10">
                </div>
                <div>
                    <label for="name">Shop Name: <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo esc($form_data['name'] ?? ''); ?>" required maxlength="255">
                </div>
                 <div>
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo esc($form_data['email'] ?? ''); ?>" maxlength="255">
                </div>
                 <div>
                    <label for="phone">Phone:</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo esc($form_data['phone'] ?? ''); ?>" maxlength="100">
                </div>
                <div>
                    <label for="address_line1">Address Line 1:</label>
                    <input type="text" id="address_line1" name="address_line1" value="<?php echo esc($form_data['address_line1'] ?? ''); ?>" maxlength="255">
                </div>
                <div>
                    <label for="address_line2">Address Line 2:</label>
                    <input type="text" id="address_line2" name="address_line2" value="<?php echo esc($form_data['address_line2'] ?? ''); ?>" maxlength="255">
                </div>
                <div>
                    <label for="city">City:</label>
                    <input type="text" id="city" name="city" value="<?php echo esc($form_data['city'] ?? ''); ?>" maxlength="100">
                </div>
                 <div>
                    <label for="country">Country:</label>
                    <input type="text" id="country" name="country" value="<?php echo esc($form_data['country'] ?? ''); ?>" maxlength="100">
                </div>
                <div>
                    <label for="tpin_no">TPIN:</label>
                    <input type="text" id="tpin_no" name="tpin_no" value="<?php echo esc($form_data['tpin_no'] ?? ''); ?>" maxlength="50">
                </div>
                <div>
                    <label for="shop_logo">Shop Logo (Max 1MB, JPG/PNG/GIF/SVG):</label>
                    <input type="file" id="shop_logo" name="shop_logo" accept="image/*"> <?php /* More permissive accept */ ?>
                    <?php if ($action === 'edit' && !empty($form_data['logo_path'])): ?>
                        <p>Current Logo: <br>
                           <img src="<?php echo esc($form_data['logo_path']); ?>" alt="<?php echo esc($form_data['name'] ?? 'Logo'); ?>" style="max-width: 100px; max-height: 100px; margin-top: 5px; background-color: #eee;">
                           <br><label><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>
                        </p>
                    <?php endif; ?>
                </div>

                <div>
                    <button type="submit"><?php echo ($action === 'edit' ? 'Update Shop' : 'Create Shop'); ?></button>
                    <a href="admin_shops.php" class="button-link" style="background-color: #aaa;">Cancel</a>
                </div>
            </form>
        <?php endif; ?>

        <?php // --- Display Shop List --- ?>
        <?php if ($action === 'list'): ?>
            <?php if ($can_manage): ?>
                <a href="admin_shops.php?action=add" class="button-link">Add New Shop</a>
            <?php endif; ?>

            <h2>Current Shops</h2>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Logo</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>City</th>
                        <th>Country</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Created</th>
                        <?php if ($can_manage): ?>
                           <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shops)): ?>
                        <tr>
                             <td colspan="<?php echo $can_manage ? 10 : 9; ?>">No shops found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($shops as $shop): ?>
                        <tr>
                            <td><?php echo (int)$shop['id']; ?></td>
                            <td>
                                <?php if (!empty($shop['logo_path'])): ?>
                                    <img src="<?php echo esc($shop['logo_path']); ?>" alt="<?php echo esc($shop['name']); ?> Logo" style="width: 50px; height: 50px; object-fit: contain; background-color:#f0f0f0;">
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc($shop['shop_code']); ?></td>
                            <td><?php echo esc($shop['name']); ?></td>
                            <td><?php echo esc($shop['city'] ?? 'N/A'); ?></td>
                            <td><?php echo esc($shop['country'] ?? 'N/A'); ?></td>
                            <td><?php echo esc($shop['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo esc($shop['email'] ?? 'N/A'); ?></td>
                            <td>
                                <?php echo formatRelativeTime($shop['created_at']); ?>
                                <small><br>(by <?php echo esc($shop['creator_username'] ?? 'System'); ?>)</small>
                            </td>
                           <?php if ($can_manage): ?>
                               <td class="actions">
                                   <a href="admin_shops.php?action=edit&id=<?php echo (int)$shop['id']; ?>" class="button-link edit">Edit</a>
                                   <form id="delete-form-<?php echo (int)$shop['id']; ?>" action="admin_shops.php" method="POST" style="display: none;">
                                       <input type="hidden" name="action" value="delete">
                                       <input type="hidden" name="shop_id" value="<?php echo (int)$shop['id']; ?>">
                                   </form>
                                   <button type="button" onclick="confirmDelete(<?php echo (int)$shop['id']; ?>, '<?php echo esc(addslashes($shop['name'])); ?>')" class="button-link delete">Delete</button>
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
<?php ob_end_flush(); ?>
