<?php
session_start();
// Optional: Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    // For testing, mock session. In production, redirect.
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Admin User'; // Or fetch from DB after login
    // header('Location: login.php');
    // exit;
}
$username = $_SESSION['username'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management Dashboard</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --background: 0 0% 100%;
            --foreground: 222.2 84% 4.9%;
            --card: 0 0% 100%;
            --card-foreground: 222.2 84% 4.9%;
            --popover: 0 0% 100%;
            --popover-foreground: 222.2 84% 4.9%;
            --primary: 222.2 47.4% 11.2%;
            --primary-foreground: 210 40% 98%;
            --secondary: 210 40% 96%;
            --secondary-foreground: 222.2 84% 4.9%;
            --muted: 210 40% 96%;
            --muted-foreground: 215.4 16.3% 46.9%;
            --accent: 210 40% 96%;
            --accent-foreground: 222.2 84% 4.9%;
            --destructive: 0 84.2% 60.2%;
            --destructive-foreground: 210 40% 98%;
            --border: 214.3 31.8% 91.4%;
            --input: 214.3 31.8% 91.4%;
            --ring: 222.2 84% 4.9%;
            --radius: 0.5rem;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background-color: hsl(var(--background));
            color: hsl(var(--foreground));
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Header */
        .header {
            border-bottom: 1px solid hsl(var(--border));
            background-color: hsl(var(--card));
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(10px);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, hsl(var(--primary)), hsl(222.2 47.4% 21.2%));
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, hsl(var(--primary)), hsl(222.2 47.4% 21.2%));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 500;
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: hsl(var(--muted-foreground));
            font-size: 1rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            padding: 1.5rem;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .stat-header {
            display: flex;
            items-center: space-between;
            margin-bottom: 1rem;
        }

        .stat-title {
            color: hsl(var(--muted-foreground));
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .stat-icon {
            width: 20px;
            height: 20px;
            color: hsl(var(--muted-foreground));
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-change {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.875rem;
        }

        .stat-change.positive {
            color: #10b981;
        }

        .stat-change.negative {
            color: #ef4444;
        }

        .stat-change.neutral {
            color: hsl(var(--muted-foreground));
        }

        /* Action Cards */
        .actions-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background-color: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            padding: 1.5rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .action-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
            border-color: hsl(var(--primary));
        }

        .action-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .action-icon.primary {
            background: linear-gradient(135deg, hsl(var(--primary)), hsl(222.2 47.4% 21.2%));
        }

        .action-icon.secondary {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .action-icon.accent {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .action-icon.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .action-title {
            font-weight: 600;
            font-size: 1.125rem;
        }

        .action-description {
            color: hsl(var(--muted-foreground));
            font-size: 0.875rem;
            line-height: 1.4;
        }

        /* Recent Activity */
        .recent-activity {
            background-color: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            padding: 1.5rem;
        }

        .activity-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .activity-list {
            space-y: 1rem;
        }

        .activity-item {
            display: flex;
            align-items: start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid hsl(var(--border));
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-icon.stock-in {
            background-color: #dcfce7;
            color: #16a34a;
        }

        .activity-icon.stock-out {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .activity-meta {
            color: hsl(var(--muted-foreground));
            font-size: 0.875rem;
        }

        .activity-time {
            color: hsl(var(--muted-foreground));
            font-size: 0.75rem;
            text-align: right;
            flex-shrink: 0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 0 0.75rem;
            }

            .header-content {
                padding: 0.75rem 0;
            }

            .main-content {
                padding: 1.5rem 0;
            }

            .page-title {
                font-size: 1.75rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .actions-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-card, .action-card, .recent-activity {
                padding: 1rem;
            }

            .activity-item {
                flex-direction: column;
                gap: 0.5rem;
            }

            .activity-time {
                text-align: left;
            }
        }

        @media (max-width: 480px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .user-menu {
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-value {
                font-size: 1.75rem;
            }
        }

        /* Loading Animation */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Dashboard</title>
    <!-- Link to your main CSS file -->
    <link rel="stylesheet" href="path/to/your/styles.css">
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        /* Add your CSS from the example here or ensure it's in styles.css */
        /* Basic CSS for the example to work if not in external file */
        :root {
            --primary: 210, 100%, 56%; /* Example Blue */
            --secondary: 215, 14%, 34%; /* Example Gray */
            --accent: 260, 100%, 65%; /* Example Purple */
            --warning: 35, 100%, 58%; /* Example Orange */
            --success: 145, 63%, 49%; /* Example Green */
            --danger: 0, 84%, 60%; /* Example Red */
            --text-primary: 215, 28%, 17%;
            --text-secondary: 215, 14%, 34%;
            --background: 0, 0%, 100%;
            --surface: 0, 0%, 97%;
            --border: 210, 16%, 93%;
        }
        body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif; background-color: hsl(var(--surface)); color: hsl(var(--text-primary)); line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        /* Header */
        .header { background-color: hsl(var(--background)); box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 1rem 0; position: sticky; top: 0; z-index: 100; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { display: flex; align-items: center; font-weight: 600; font-size: 1.25rem; }
        .logo-icon { margin-right: 0.5rem; color: hsl(var(--primary)); }
        .user-menu { display: flex; align-items: center; }
        .user-avatar { width: 36px; height: 36px; background-color: hsl(var(--primary)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        /* Main Content */
        .main-content { padding: 2rem 0; }
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 2rem; font-weight: 700; margin-bottom: 0.25rem; }
        .page-subtitle { font-size: 1rem; color: hsl(var(--text-secondary)); }
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .stat-card { background-color: hsl(var(--background)); padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
        .stat-title { font-weight: 500; color: hsl(var(--text-secondary)); }
        .stat-icon { color: hsl(var(--text-secondary)); }
        .stat-value { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        .stat-change { display: flex; align-items: center; font-size: 0.875rem; }
        .stat-change.positive { color: hsl(var(--success)); }
        .stat-change.negative { color: hsl(var(--danger)); }
        .stat-change.neutral { color: hsl(var(--text-secondary)); }
        /* Actions Section */
        .actions-section, .recent-activity { margin-bottom: 2.5rem; }
        .section-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem; }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        .action-card { background-color: hsl(var(--background)); padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); cursor: pointer; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .action-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
        .action-header { display: flex; align-items: center; gap: 1rem; }
        .action-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .action-icon.primary { background-color: hsla(var(--primary), 0.1); color: hsl(var(--primary)); }
        .action-icon.secondary { background-color: hsla(var(--secondary), 0.1); color: hsl(var(--secondary)); }
        .action-icon.accent { background-color: hsla(var(--accent), 0.1); color: hsl(var(--accent)); }
        .action-icon.warning { background-color: hsla(var(--warning), 0.1); color: hsl(var(--warning)); }
        .action-title { font-weight: 600; font-size: 1.125rem; margin-bottom: 0.25rem; }
        .action-description { font-size: 0.875rem; color: hsl(var(--text-secondary)); }
        /* Recent Activity */
        .activity-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .activity-list { display: flex; flex-direction: column; gap: 1rem; }
        .activity-item { display: flex; align-items: center; background-color: hsl(var(--background)); padding: 1rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .activity-icon { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; }
        .activity-icon.stock-in { background-color: hsla(var(--success), 0.1); color: hsl(var(--success)); }
        .activity-icon.stock-out { background-color: hsla(var(--danger), 0.1); color: hsl(var(--danger)); }
        .activity-content { flex-grow: 1; }
        .activity-title { font-weight: 500; }
        .activity-meta { font-size: 0.875rem; color: hsl(var(--text-secondary)); }
        .activity-time { font-size: 0.875rem; color: hsl(var(--text-secondary)); margin-left: auto; white-space: nowrap; }
        .loading-placeholder { color: #aaa; font-style: italic; }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-icon">
                        <i data-feather="package"></i>
                    </div>
                    <span>Inventory Manager</span>
                </div>
                <div class="user-menu">
                    <div class="user-avatar" title="Logged in as <?php echo htmlspecialchars($username); ?>">
                        <i data-feather="user"></i>
                    </div>
                    <!-- Optional: Logout link -->
                    <!-- <a href="logout.php" title="Logout"><i data-feather="log-out"></i></a> -->
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($username); ?>! Here's an overview of your inventory.</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Products</span>
                        <i data-feather="package" class="stat-icon"></i>
                    </div>
                    <div class="stat-value" id="total-products"><span class="loading-placeholder">Loading...</span></div>
                    <!-- <div class="stat-change positive"> Your dynamic change data here </div> -->
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Products In Stock</span>
                        <i data-feather="check-circle" class="stat-icon"></i>
                    </div>
                    <div class="stat-value" id="in-stock-count"><span class="loading-placeholder">Loading...</span></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Products Low Stock</span>
                        <i data-feather="alert-triangle" class="stat-icon"></i>
                    </div>
                    <div class="stat-value" id="low-stock-count"><span class="loading-placeholder">Loading...</span></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Products Out of Stock</span>
                        <i data-feather="x-circle" class="stat-icon"></i>
                    </div>
                    <div class="stat-value" id="out-of-stock-count"><span class="loading-placeholder">Loading...</span></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="actions-section">
                <h2 class="section-title">Quick Actions</h2>
                <div class="actions-grid">
                    <div class="action-card" onclick="location.href='stock_in.php'">
                        <div class="action-header">
                            <div class="action-icon primary"><i data-feather="plus-circle"></i></div>
                            <div>
                                <div class="action-title">Add Stock</div>
                                <div class="action-description">Receive new inventory</div>
                            </div>
                        </div>
                    </div>
                    <div class="action-card" onclick="location.href='stock_out.php'">
                        <div class="action-header">
                            <div class="action-icon secondary"><i data-feather="minus-circle"></i></div>
                            <div>
                                <div class="action-title">Scan Out</div>
                                <div class="action-description">Scan items from inventory</div>
                            </div>
                        </div>
                    </div>
                    <div class="action-card" onclick="location.href='stock_report.php'">
                        <div class="action-header">
                            <div class="action-icon accent"><i data-feather="bar-chart-2"></i></div> <!-- Changed icon -->
                            <div>
                                <div class="action-title">Stock Report</div>
                                <div class="action-description">View inventory levels</div>
                            </div>
                        </div>
                    </div>
                    <div class="action-card" onclick="location.href='transaction_history_all.php'"> <!-- Link to a page showing all transactions -->
                        <div class="action-header">
                            <div class="action-icon warning"><i data-feather="list"></i></div> <!-- Changed icon -->
                            <div>
                                <div class="action-title">All Transactions</div>
                                <div class="action-description">Review stock movements</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="activity-header">
                    <h2 class="section-title">Recent Activity</h2>
                    <a href="transaction_history_all.php" style="color: hsl(var(--primary)); text-decoration: none; font-size: 0.875rem;">View all</a>
                </div>
                <div class="activity-list" id="activity-list-container">
                    <div class="loading-placeholder">Loading recent activity...</div>
                    <!-- Activity items will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </main>

    <script>
        // Initialize Feather Icons
        feather.replace();

        function formatRelativeTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.round((now - date) / 1000);
            const minutes = Math.round(seconds / 60);
            const hours = Math.round(minutes / 60);
            const days = Math.round(hours / 24);

            if (seconds < 60) return `${seconds} seconds ago`;
            if (minutes < 60) return `${minutes} minutes ago`;
            if (hours < 24) return `${hours} hours ago`;
            return `${days} days ago`;
        }


        function loadDashboardData() {
            // Fetch Summary Stats
            fetch('api/dashboard_stats.php')
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('Error from dashboard_stats API:', data.error, data.details);
                        document.getElementById('total-products').innerHTML = '<span style="color:red;">Error</span>';
                        // Display error for other stats too
                        return;
                    }
                    document.getElementById('total-products').textContent = data.total_products || 0;
                    document.getElementById('in-stock-count').textContent = data.in_stock_count || 0;
                    document.getElementById('low-stock-count').textContent = data.low_stock_count || 0;
                    document.getElementById('out-of-stock-count').textContent = data.out_of_stock_count || 0;
                })
                .catch(error => {
                    console.error('Error loading dashboard stats:', error);
                    document.getElementById('total-products').innerHTML = '<span style="color:red;">Load failed</span>';
                    // Display error for other stats too
                });

            // Fetch Recent Activity
            fetch('api/recent_activity.php')
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    const activityListContainer = document.getElementById('activity-list-container');
                    activityListContainer.innerHTML = ''; // Clear loading placeholder or old data

                    if (data.error) {
                        console.error('Error from recent_activity API:', data.error, data.details);
                        activityListContainer.innerHTML = '<div style="color:red;">Error loading activity.</div>';
                        return;
                    }

                    if (data && data.length > 0) {
                        data.forEach(activity => {
                            const item = document.createElement('div');
                            item.className = 'activity-item';

                            let iconClass = '';
                            let iconType = 'alert-circle'; // default
                            let quantityPrefix = '';

                            if (activity.transaction_type === 'stock_in' || activity.transaction_type === 'return') {
                                iconClass = 'stock-in';
                                iconType = 'plus';
                                quantityPrefix = '+';
                            } else if (activity.transaction_type === 'stock_out') {
                                iconClass = 'stock-out';
                                iconType = 'minus';
                                quantityPrefix = '-';
                            } else if (activity.transaction_type === 'adjustment') {
                                iconClass = 'stock-adjustment'; // You'll need to style this
                                iconType = 'edit-2'; // Example icon for adjustment
                                quantityPrefix = activity.quantity > 0 ? '+' : ''; // Or based on notes
                            }

                            item.innerHTML = `
                                <div class="activity-icon ${iconClass}">
                                    <i data-feather="${iconType}" style="width: 16px; height: 16px;"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">${ucWords(activity.transaction_type.replace('_', ' '))}: ${activity.product_name || 'Unknown Product'}</div>
                                    <div class="activity-meta">
                                        ${quantityPrefix}${activity.quantity} units
                                        ${activity.scanned_by_username ? ' by ' + activity.scanned_by_username : ''}
                                        ${activity.reference_number ? ' (Ref: ' + activity.reference_number + ')' : ''}
                                        ${activity.notes ? ' - Notes: ' + truncateText(activity.notes, 50) : ''}
                                    </div>
                                </div>
                                <div class="activity-time">${formatRelativeTime(activity.transaction_date)}</div>
                            `;
                            activityListContainer.appendChild(item);
                        });
                    } else {
                        activityListContainer.innerHTML = '<div class="loading-placeholder">No recent activity found.</div>';
                    }
                    feather.replace(); // Re-initialize Feather Icons after adding new ones
                })
                .catch(error => {
                    console.error('Error loading recent activity:', error);
                    document.getElementById('activity-list-container').innerHTML = '<div style="color:red;">Failed to load recent activity.</div>';
                });
        }

        function ucWords(str) {
            return str.toLowerCase().replace(/\b[a-z]/g, function(letter) {
                return letter.toUpperCase();
            });
        }
        function truncateText(text, maxLength) {
            if (!text) return '';
            if (text.length <= maxLength) return text;
            return text.substr(0, maxLength) + '...';
        }


        // Smooth hover animations for action cards (already present in your code)
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('mouseenter', function() { this.style.transform = 'translateY(-2px)'; }); // Subtle lift
            card.addEventListener('mouseleave', function() { this.style.transform = 'translateY(0px)'; });
             // Click visual feedback already handled
        });


        // Load dashboard data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
        });
    </script>
</body>
</html>