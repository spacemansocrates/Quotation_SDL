<?php
// login.php
session_start(); // Start the session at the very beginning

// FIXED: Add a check for the source of the request to break the redirect loop
$comingFromAdmin = isset($_GET['from']) && $_GET['from'] === 'admin';

// Only redirect if user is already logged in AND they're not being redirected from admin page
if (isset($_SESSION['user_id']) && !$comingFromAdmin) {
    // Redirect admin to admin page, others to a default dashboard (create dashboard.php if needed)
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin_quotations.php');
        exit;
    } else {
        // For staff, redirect to a hypothetical dashboard or show a simple message
        header('Location: admin_quotations.php'); // Example redirection
        exit;
    }
}

require_once __DIR__ . '/../includes/db_connect.php';

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
                            header('Location: admin_quotations.php');
                        } else {
                            // All users redirected to admin_quotations.php for now
                            header('Location: admin_quotations.php');
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
    <style>
        /* Base Variables */
        :root {
            --background: #f8fafc;
            --foreground: #0f172a;
            --card: #ffffff;
            --card-foreground: #0f172a;
            --primary: #0f172a;
            --primary-foreground: #ffffff;
            --secondary: #f1f5f9;
            --secondary-foreground: #0f172a;
            --muted: #f1f5f9;
            --muted-foreground: #64748b;
            --accent: #f1f5f9;
            --accent-foreground: #0f172a;
            --destructive: #ef4444;
            --destructive-foreground: #ffffff;
            --border: #e2e8f0;
            --input: #e2e8f0;
            --ring: #0284c7;
            --radius: 0.5rem;
        }

        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--background);
            color: var(--foreground);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 28rem;
            padding: 1.5rem;
        }

        .card {
            background-color: var(--card);
            border-radius: var(--radius);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
            color: var(--foreground);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--foreground);
        }

        input {
            width: 100%;
            padding: 0.625rem;
            font-size: 0.875rem;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            background-color: var(--card);
            color: var(--foreground);
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        input:focus {
            border-color: var(--ring);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.2);
        }

        button {
            width: 100%;
            padding: 0.625rem;
            background-color: var(--primary);
            color: var(--primary-foreground);
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: opacity 0.15s;
        }

        button:hover {
            opacity: 0.9;
        }

        button:active {
            transform: translateY(1px);
        }

        .message {
            margin-bottom: 1.5rem;
            padding: 0.75rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
        }

        .error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--destructive);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Animation for the card */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            animation: fadeIn 0.3s ease-out;
        }

        /* Logo */
/* Replace the existing logo style with this: */
.logo {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background-color: var(--card);
    overflow: hidden;
}
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
           <div class="logo">
    <img src="images/logo.png" alt="Company Logo" style="width: 100%; height: 100%; object-fit: contain;">
</div>
            
            <h1>ADMIN Login</h1>

            <?php if (!empty($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo $username_value; ?>" required>
                </div>
                <div class="form-group">
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
    </div>
</body>
</html>