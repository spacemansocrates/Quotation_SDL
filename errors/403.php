<?php
session_start();
// Determine the user's dashboard URL based on their role
$dashboard_url = '/Quotation_SDL/index.php'; // Default fallback
if (isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 'admin': $dashboard_url = '/Quotation_SDL/admin/admin_dashboard.php'; break;
        case 'supervisor': $dashboard_url = '/Quotation_SDL/shop/warehouse_requests.php'; break;
        case 'manager': $dashboard_url = '/Quotation_SDL/shop/dashboard.php'; break;
        case 'staff': $dashboard_url = '/Quotation_SDL/admin/admin_quotations.php'; break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Access Denied</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background-color: #f8f9fa; color: #343a40; text-align: center; padding: 50px; }
        .container { max-width: 600px; margin: auto; }
        h1 { font-size: 4rem; color: #dc3545; margin-bottom: 0; }
        h2 { font-size: 1.5rem; margin-top: 0; }
        p { font-size: 1.1rem; }
        a { color: #007bff; text-decoration: none; font-weight: bold; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <h2>Access Denied / Forbidden</h2>
        <p>You do not have the necessary permissions to view this page.</p>
        <p>
            <a href="<?php echo htmlspecialchars($dashboard_url); ?>">Return to Your Dashboard</a> or 
            <a href="/Quotation_SDL/index.php?action=logout">Log Out</a>
        </p>
    </div>
</body>
</html>