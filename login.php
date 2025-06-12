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
            --foreground: #1e293b;
            --card: rgba(255, 255, 255, 0.95);
            --card-foreground: #1e293b;
            --primary: #0f172a;
            --primary-foreground: #ffffff;
            --secondary: #f1f5f9;
            --secondary-foreground: #0f172a;
            --muted: #f1f5f9;
            --muted-foreground: #64748b;
            --accent: #0284c7;
            --accent-foreground: #ffffff;
            --destructive: #ef4444;
            --destructive-foreground: #ffffff;
            --border: #e2e8f0;
            --input: #e2e8f0;
            --ring: #0284c7;
            --radius: 0.75rem;
            --shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.8), rgba(30, 41, 59, 0.6)), 
                        url('images/background.JPG') center/cover no-repeat fixed;
            color: var(--foreground);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }

        /* Logo Side */
        .logo-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }

        .logo-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            animation: pulse 4s ease-in-out infinite alternate;
        }

        @keyframes pulse {
            0% { transform: scale(1) rotate(0deg); opacity: 0.3; }
            100% { transform: scale(1.1) rotate(180deg); opacity: 0.1; }
        }

        .logo {
            width: 200px;
            height: 200px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: white;
        }

        .welcome-text {
            margin-top: 2rem;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .welcome-text h2 {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .welcome-text p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        /* Form Side */
        .form-section {
            background: var(--card);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--foreground);
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--muted-foreground);
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--foreground);
        }

        input {
            width: 100%;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            border-radius: var(--radius);
            border: 2px solid var(--border);
            background-color: white;
            color: var(--foreground);
            outline: none;
            transition: all 0.2s ease;
        }

        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
            transform: translateY(-1px);
        }

        button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), #334155);
            color: var(--primary-foreground);
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        button:hover::before {
            left: 100%;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        .message {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: var(--radius);
            font-size: 0.9rem;
            border-left: 4px solid;
        }

        .error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--destructive);
            border-color: var(--destructive);
        }

        .contact-info {
            margin-top: 2rem;
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .contact-info p {
            color: var(--muted-foreground);
            font-size: 0.9rem;
        }

        /* Animation for the container */
        @keyframes slideIn {
            from { 
                opacity: 0; 
                transform: translateY(30px) scale(0.95); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }

        .login-container {
            animation: slideIn 0.6s ease-out;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 400px;
            }

            .logo-section {
                padding: 2rem;
                min-height: 300px;
            }

            .logo {
                width: 120px;
                height: 120px;
            }

            .welcome-text h2 {
                font-size: 1.5rem;
            }

            .form-section {
                padding: 2rem;
            }

            .form-header h1 {
                font-size: 1.75rem;
            }
        }

        /* Loading animation for form submission */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s ease infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo">
                <img src="images/logo.png" alt="Company Logo">
            </div>
            <div class="welcome-text">
                <h2>Welcome Back</h2>
                <p>Sign in to access your dashboard</p>
            </div>
        </div>

        <!-- Form Section -->
        <div class="form-section">
            <div class="form-header">
                <h1>Sign In</h1>
                <p>Enter your credentials to continue</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo $username_value; ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div>
                    <button type="submit" id="loginBtn">Sign In</button>
                </div>
            </form>
            
            <div class="contact-info">
                <p>Having trouble signing in? Contact Admin at <strong>0883457480</strong></p>
            </div>
        </div>
    </div>

    <script>
        // Add loading state to button on form submission
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.textContent = 'Signing In...';
            btn.classList.add('loading');
        });

        // Add subtle animations to inputs
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateX(4px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>