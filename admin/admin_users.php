<?php
// admin_users.php
session_start();
require_once __DIR__ . '/../includes/time_formating_helper.php'; // Add this for time formatting functions
require_once __DIR__ . '/../includes/db_connect.php';


// --- Authorization Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // If not logged in or not an admin, redirect to login
    $_SESSION['error_message'] = "Access denied. Please log in as an administrator."; // Optional: Flash message
    header('Location: login.php');
    exit;
}

// --- Initial Setup ---
$pdo = null; // Initialize PDO variable outside try block
$action = $_GET['action'] ?? 'list'; // Default action is to list users
$user_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : null;

$feedback_message = ''; // For success/error messages after actions
$feedback_type = ''; // 'success' or 'error'

// Display and clear flash messages from session
if (isset($_SESSION['feedback_message'])) {
    $feedback_message = $_SESSION['feedback_message'];
    $feedback_type = $_SESSION['feedback_type'] ?? 'info';
    unset($_SESSION['feedback_message'], $_SESSION['feedback_type']);
}


// --- Helper Function to Sanitize Output ---
function esc(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}


// --- CRUD Operations ---
try {
    $pdo = DatabaseConfig::getConnection();

    // == Handle POST Requests (Create/Update/Delete) ==
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $post_action = $_POST['action'] ?? '';

        // -- Create User --
        if ($post_action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'staff';
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Basic Validation
            $errors = [];
            if (empty($username)) $errors[] = "Username is required.";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
            if (empty($password)) $errors[] = "Password is required.";
            if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
            if (!in_array($role, ['admin', 'staff','manager','supervisor','viewer'])) $errors[] = "Invalid role selected.";
             // Add check for username/email uniqueness before insert? (Good idea)

            if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                    // Check uniqueness (example)
                    $checkSql = "SELECT id FROM users WHERE username = :username OR email = :email";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkSql, [':username' => $username, ':email' => $email]);
                    if ($checkStmt->fetch()) {
                       throw new PDOException("Username or Email already exists.");
                    }

                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (username, password_hash, full_name, email, role, is_active)
                            VALUES (:username, :password_hash, :full_name, :email, :role, :is_active)";
                    $params = [
                        ':username' => $username,
                        ':password_hash' => $password_hash,
                        ':full_name' => $full_name ?: null, // Allow nullable full name
                        ':email' => $email,
                        ':role' => $role,
                        ':is_active' => $is_active,
                    ];
                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "User '".esc($username)."' created successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_users.php'); // Redirect to avoid form resubmission
                    exit;

                } catch (PDOException $e) {
                    DatabaseConfig::rollbackTransaction($pdo);
                    // Logged by executeQuery or here if needed
                     $feedback_message = "Error creating user: " . $e->getMessage(); // Show specific error for now
                     $feedback_type = 'error';
                    // Keep form data to redisplay - see form section below
                    $form_data = $_POST;
                    $action = 'add'; // Stay on add form
                }
            } else {
                $feedback_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
                $feedback_type = 'error';
                // Keep form data to redisplay
                $form_data = $_POST;
                $action = 'add'; // Stay on add form
            }
        }

        // -- Update User --
        elseif ($post_action === 'update' && isset($_POST['user_id'])) {
            $user_id = (int)$_POST['user_id'];
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? ''; // New password (optional)
            $confirm_password = $_POST['confirm_password'] ?? ''; // New password confirm
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'staff';
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Basic Validation
            $errors = [];
            if (empty($username)) $errors[] = "Username is required.";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
            if (!empty($password) && $password !== $confirm_password) $errors[] = "New passwords do not match.";
            if (!in_array($role, ['admin', 'staff','manager','supervisor','viewer'])) $errors[] = "Invalid role selected.";
             // Check uniqueness excluding the current user ID

             if (empty($errors)) {
                DatabaseConfig::beginTransaction($pdo);
                try {
                     // Check uniqueness (example) - make sure not to conflict with others
                    $checkSql = "SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id";
                    $checkStmt = DatabaseConfig::executeQuery($pdo, $checkSql, [
                        ':username' => $username,
                        ':email' => $email,
                        ':id' => $user_id
                    ]);
                    if ($checkStmt->fetch()) {
                       throw new PDOException("Username or Email already exists for another user.");
                    }

                    // Build query based on whether password is being changed
                    $sql = "UPDATE users SET
                                username = :username,
                                full_name = :full_name,
                                email = :email,
                                role = :role,
                                is_active = :is_active
                                -- Only update password if a new one is provided
                                " . (!empty($password) ? ", password_hash = :password_hash" : "") . "
                            WHERE id = :id";

                    $params = [
                        ':username' => $username,
                        ':full_name' => $full_name ?: null,
                        ':email' => $email,
                        ':role' => $role,
                        ':is_active' => $is_active,
                        ':id' => $user_id,
                    ];

                    if (!empty($password)) {
                        $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    }

                    DatabaseConfig::executeQuery($pdo, $sql, $params);
                    DatabaseConfig::commitTransaction($pdo);

                    $_SESSION['feedback_message'] = "User '".esc($username)."' updated successfully.";
                    $_SESSION['feedback_type'] = 'success';
                    header('Location: admin_users.php'); // Redirect to list
                    exit;

                } catch (PDOException $e) {
                    DatabaseConfig::rollbackTransaction($pdo);
                     $feedback_message = "Error updating user: " . $e->getMessage();
                     $feedback_type = 'error';
                    // Keep form data to redisplay
                    $form_data = $_POST;
                    $action = 'edit'; // Stay on edit form
                    $user_id_to_edit = $user_id; // Make sure ID is set for edit view
                }
            } else {
                $feedback_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
                $feedback_type = 'error';
                // Keep form data to redisplay
                $form_data = $_POST;
                $action = 'edit'; // Stay on edit form
                $user_id_to_edit = $user_id; // Make sure ID is set for edit view
            }
        }

         // -- Delete User (Using POST for safety) --
        elseif ($post_action === 'delete' && isset($_POST['user_id'])) {
             $user_id_to_delete = (int)$_POST['user_id'];

             // Prevent admin from deleting themselves
             if ($user_id_to_delete === $_SESSION['user_id']) {
                 $_SESSION['feedback_message'] = "You cannot delete your own account.";
                 $_SESSION['feedback_type'] = 'error';
             } else {
                 DatabaseConfig::beginTransaction($pdo);
                 try {
                     $sql = "DELETE FROM users WHERE id = :id";
                     $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $user_id_to_delete]);

                     if ($stmt->rowCount() > 0) {
                         DatabaseConfig::commitTransaction($pdo);
                         $_SESSION['feedback_message'] = "User deleted successfully.";
                         $_SESSION['feedback_type'] = 'success';
                     } else {
                         DatabaseConfig::rollbackTransaction($pdo); // Rollback if no rows affected (user might not exist)
                         $_SESSION['feedback_message'] = "User not found or could not be deleted.";
                         $_SESSION['feedback_type'] = 'error';
                     }
                 } catch (PDOException $e) {
                     DatabaseConfig::rollbackTransaction($pdo);
                     // Logged by executeQuery
                     $_SESSION['feedback_message'] = "Error deleting user. It might be referenced elsewhere.";
                     $_SESSION['feedback_type'] = 'error';
                 }
             }
             header('Location: admin_users.php'); // Redirect back to the list
             exit;
         }
    } // End POST handling


    // == Prepare Data for Views (List, Add, Edit) ==
    $users = [];
    $user_to_edit = null;

    if ($action === 'list') {
        $sql = "SELECT id, username, full_name, email, role, is_active, created_at, last_login_at
                FROM users
                ORDER BY username ASC";
        $stmt = DatabaseConfig::executeQuery($pdo, $sql);
        $users = $stmt->fetchAll();
    }
    elseif ($action === 'edit' && $user_id_to_edit) {
        // If form submission failed, use $form_data, else fetch from DB
        if (!isset($form_data)) {
            $sql = "SELECT id, username, full_name, email, role, is_active FROM users WHERE id = :id";
            $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':id' => $user_id_to_edit]);
            $user_to_edit = $stmt->fetch();

            if (!$user_to_edit) {
                $_SESSION['feedback_message'] = "User not found.";
                $_SESSION['feedback_type'] = 'error';
                header('Location: admin_users.php');
                exit;
            }
            // Use fetched data for the form
             $form_data = $user_to_edit;
        }
        // If form submission failed, $form_data is already set from the POST handling block
    }
    elseif ($action === 'add') {
        // If form submission failed, $form_data is already set.
        // Otherwise, initialize an empty array for the form.
        if (!isset($form_data)) {
             $form_data = [
                'username' => '',
                'full_name' => '',
                'email' => '',
                'role' => 'staff',
                'is_active' => 1 // Default to active
             ];
        }
    }


} catch (PDOException $e) {
    // Catch connection errors or query errors not caught within specific actions
    $feedback_message = "Database error: " . $e->getMessage(); // More detailed for admin
    $feedback_type = 'error';
    // Logged by db_connect.php usually
    $action = 'list'; // Fallback to list view on major error
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
    <title>Admin - Manage Users</title>
    <link rel="stylesheet" href="../css/admin.css">

    <script>
        // Simple confirmation for delete action
        function confirmDelete(userId, username) {
            if (confirm(`Are you sure you want to delete the user "${username}" (ID: ${userId})?`)) {
                // Submit the form
                document.getElementById('delete-form-' + userId).submit();
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

        <h1>Manage Users</h1>

        <?php if ($feedback_message): ?>
            <div class="message <?php echo esc($feedback_type); ?>">
                <?php echo $feedback_message; // Already contains HTML <br> if needed ?>
            </div>
        <?php endif; ?>

        <?php // --- Display Add/Edit Form --- ?>
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <h2><?php echo ($action === 'edit' ? 'Edit User' : 'Add New User'); ?></h2>
            <form action="admin_users.php" method="POST">
                <input type="hidden" name="action" value="<?php echo ($action === 'edit' ? 'update' : 'create'); ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="user_id" value="<?php echo (int)($form_data['id'] ?? 0); ?>">
                <?php endif; ?>

                <div>
                    <label for="username">Username: <span class="required">*</span></label>
                    <input type="text" id="username" name="username" value="<?php echo esc($form_data['username'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="full_name">Full Name:</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo esc($form_data['full_name'] ?? ''); ?>">
                </div>
                <div>
                    <label for="email">Email: <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="<?php echo esc($form_data['email'] ?? ''); ?>" required>
                </div>
                 <div>
                    <label for="password">Password: <?php echo ($action === 'add' ? '<span class="required">*</span>' : '(Leave blank to keep current)'); ?></label>
                    <input type="password" id="password" name="password" <?php echo ($action === 'add' ? 'required' : ''); ?>>
                </div>
                 <div>
                    <label for="confirm_password">Confirm Password: <?php echo ($action === 'add' ? '<span class="required">*</span>' : ''); ?></label>
                    <input type="password" id="confirm_password" name="confirm_password" <?php echo ($action === 'add' ? 'required' : ''); ?>>
                     <?php if ($action === 'edit'): ?>
                        <small>Required only if changing the password.</small>
                     <?php endif; ?>
                </div>
                <div>
    <label for="role">Role: <span class="required">*</span></label>
    <select id="role" name="role" required>
        <option value="staff" <?php echo (($form_data['role'] ?? 'staff') === 'staff' ? 'selected' : ''); ?>>Staff</option>
        <option value="admin" <?php echo (($form_data['role'] ?? '') === 'admin' ? 'selected' : ''); ?>>Admin</option>
        <option value="supervisor" <?php echo (($form_data['role'] ?? '') === 'supervisor' ? 'selected' : ''); ?>>Supervisor</option>
        <option value="manager" <?php echo (($form_data['role'] ?? '') === 'manager' ? 'selected' : ''); ?>>Manager</option>
        <option value="viewer" <?php echo (($form_data['role'] ?? '') === 'viewer' ? 'selected' : ''); ?>>Viewer</option>
    </select>
</div>
                <div>
                     <label for="is_active">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo (($form_data['is_active'] ?? 0) == 1 ? 'checked' : ''); ?>>
                        Active User
                     </label>
                </div>

                <div>
                    <button type="submit"><?php echo ($action === 'edit' ? 'Update User' : 'Create User'); ?></button>
                    <a href="admin_users.php" class="button-link" style="background-color: #aaa;">Cancel</a>
                </div>
            </form>
        <?php endif; // End Add/Edit Form ?>


        <?php // --- Display User List --- ?>
        <?php if ($action === 'list'): ?>
            <a href="admin_users.php?action=add" class="button-link">Add New User</a>

            <h2>Current Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
<tr>
    <td><?php echo (int)$user['id']; ?></td>
    <td><?php echo esc($user['username']); ?></td>
    <td><?php echo esc($user['full_name'] ?? 'N/A'); ?></td>
    <td><?php echo esc($user['email']); ?></td>
    <td><?php echo formatRoleDisplay($user['role']); ?></td>
    <td><?php echo formatStatusDisplay($user['is_active']); ?></td>
    <td><?php echo formatRelativeTime($user['last_login_at']); ?></td>
    <td><?php echo formatRelativeTime($user['created_at']); ?></td>
    <td class="actions">
        <a href="admin_users.php?action=edit&id=<?php echo (int)$user['id']; ?>" class="button-link edit">Edit</a>
        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
            <form id="delete-form-<?php echo (int)$user['id']; ?>" action="admin_users.php" method="POST" style="display: none;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
            </form>
            <button type="button" onclick="confirmDelete(<?php echo (int)$user['id']; ?>, '<?php echo esc(addslashes($user['username'])); ?>')" class="button-link delete">Delete</button>
        <?php else: ?>
            <span style="color:#999; font-size: 0.9em;">(Current User)</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; // End User List ?>

    </div>
</body>
</html>