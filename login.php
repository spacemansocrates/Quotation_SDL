<?php
// login.php
session_start(); // Start the session at the very beginning

// If user is already logged in, redirect them
if (isset($_SESSION['user_id'])) {
    // Redirect admin to admin page, others to a default dashboard (create dashboard.php if needed)
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin/admin_users.php');
    } else {
        // For staff, redirect to a hypothetical dashboard or show a simple message
         // header('Location: dashboard.php'); // Example redirection
         echo "<p>Welcome, " . htmlspecialchars($_SESSION['username']) . "! You are already logged in.</p>";
         echo '<p><a href="logout.php">Logout</a></p>';
    }
    exit;
}

require_once 'includes/db_connect.php';

$error_message = '';
$username_value = ''; // Retain username on failed login attempt

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $username_value = htmlspecialchars($username); // Store for redisplaying in form

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        $pdo = null; // Initialize PDO variable
        try {
            $pdo = DatabaseConfig::getConnection();

            // Prepare query to find the user
            $sql = "SELECT id, username, password_hash, role, is_active
                    FROM users
                    WHERE username = :username";
            $stmt = DatabaseConfig::executeQuery($pdo, $sql, [':username' => $username]);
            $user = $stmt->fetch();

            if ($user) {
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Check if user account is active
                    if ($user['is_active']) {
                        // Password is correct & user is active - Login successful
                        session_regenerate_id(true); // Prevent session fixation

                        // Store user data in session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];

                        // Update last_login_at timestamp
                        $updateSql = "UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id";
                        DatabaseConfig::executeQuery($pdo, $updateSql, [':id' => $user['id']]);

                        // Redirect based on role
                        if ($user['role'] === 'admin') {
                            header('Location: admin/admin_users.php');
                        } else {
                            // Redirect staff to a different page or show welcome message here
                            // header('Location: dashboard.php'); // Example
                            // For now, just stay on this page and show a success message (or redirect above)
                             echo "<p>Welcome, " . htmlspecialchars($user['username']) . "!</p>";
                             echo '<p><a href="logout.php">Logout</a></p>';

                        }
                        DatabaseConfig::closeConnection($pdo);
                        exit; // Important: stop script execution after redirection

                    } else {
                        // Account is inactive
                        $error_message = 'Your account is deactivated. Please contact an administrator.';
                    }
                } else {
                    // Invalid password
                    $error_message = 'Invalid username or password.';
                }
            } else {
                // User not found
                $error_message = 'Invalid username or password.';
            }

        } catch (PDOException $e) {
            // $e is already logged by DatabaseConfig::executeQuery if it happens there
            // Logged by DatabaseConfig::getConnection if it happens there
            $error_message = 'An error occurred during login. Please try again later.';
            // Optionally log this specific context error as well
            error_log("Login page error for user '{$username}': " . $e->getMessage());
        } finally {
            // Ensure connection is closed
            DatabaseConfig::closeConnection($pdo);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>User Login</h1>

        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo $username_value; ?>" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">Login</button>
            </div>
        </form>
        
        <?php
        // Example link to create first admin if none exists (remove in production)
        // echo '<p><small>Need to create first admin? <a href="setup_admin.php">Setup here</a></small></p>'; 
        ?>
    </div>
</body>
</html>