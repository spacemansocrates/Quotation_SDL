<?php
// --- CONFIGURATION ---
define('DB_SERVER', '127.0.0.1:3306'); // Your MySQL host and port, e.g., 'localhost' or '127.0.0.1:3306'
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'supplies');

// --- DATABASE CONNECTION ---
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . ". Please ensure MySQL is running and credentials are correct.");
}
$conn->set_charset("utf8mb4");

// --- SIMULATE USER ROLE & ID (In a real app, this comes from session after login) ---
$currentUser = [
    'id' => 1, // Example User ID
    'full_name' => 'Admin User',
    'role' => 'admin' // 'admin', 'manager', 'staff', 'supervisor', 'viewer'
];


// --- DATA FETCHING FUNCTIONS ---

function getKpis($conn) {
    $kpis = [
        'total_quotations_month' => 0,
        'pending_approval' => 0,
        'approved_value_mtd' => 0,
        'draft_quotations' => 0,
        'draft_value' => 0,
    ];

    $currentMonth = date('m');
    $currentYear = date('Y');

    // Total Quotations (This Month)
    $stmt = $conn->prepare("SELECT COUNT(id) as count FROM quotations WHERE MONTH(quotation_date) = ? AND YEAR(quotation_date) = ?");
    $stmt->bind_param("ss", $currentMonth, $currentYear);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $kpis['total_quotations_month'] = $row['count'];
    $stmt->close();

    // Pending Approval
    $stmt = $conn->prepare("SELECT COUNT(id) as count FROM quotations WHERE status = 'Pending Approval'"); // Or your equivalent status
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $kpis['pending_approval'] = $row['count'];
    $stmt->close();

    // Approved Value (MTD)
    $stmt = $conn->prepare("SELECT SUM(total_net_amount) as total_value FROM quotations WHERE status = 'Approved' AND MONTH(quotation_date) = ? AND YEAR(quotation_date) = ?");
    $stmt->bind_param("ss", $currentMonth, $currentYear);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $kpis['approved_value_mtd'] = $row['total_value'] ?? 0;
    $stmt->close();

    // Draft Quotations & Value
    $stmt = $conn->prepare("SELECT COUNT(id) as count, SUM(total_net_amount) as total_value FROM quotations WHERE status = 'Draft'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $kpis['draft_quotations'] = $row['count'];
        $kpis['draft_value'] = $row['total_value'] ?? 0;
    }
    $stmt->close();
    return $kpis;
}

function getQuotationStatusOverview($conn) {
    $sql = "SELECT status, COUNT(id) as count FROM quotations GROUP BY status";
    $result = $conn->query($sql);
    $statusData = ['labels' => [], 'data' => []];
    $colors = [
        'Draft' => '#78909c', 'Sent' => '#42a5f5', 'Pending Approval' => '#ffca28',
        'Approved' => '#66bb6a', 'Rejected' => '#ef5350', 'Expired' => '#a1887f',
        // Add any other statuses you use with a color
    ];
    $backgroundColors = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $statusData['labels'][] = $row['status'];
            $statusData['data'][] = (int)$row['count'];
            $backgroundColors[] = $colors[$row['status']] ?? '#bdbdbd'; // Default grey
        }
    }
    $statusData['colors'] = $backgroundColors;
    return $statusData;
}

function getRecentQuotations($conn, $limit = 5) {
    $sql = "SELECT q.id, q.quotation_number, IFNULL(q.customer_name_override, c.name) as customer_name, q.total_net_amount, q.status, q.quotation_date
            FROM quotations q
            LEFT JOIN customers c ON q.customer_id = c.id
            ORDER BY q.created_at DESC
            LIMIT " . (int)$limit;
    $result = $conn->query($sql);
    $quotations = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $quotations[] = $row;
        }
    }
    return $quotations;
}

function getQuotationValueOverTime($conn, $months = 6) {
    $sql = "SELECT DATE_FORMAT(quotation_date, '%Y-%m') as month_year, SUM(total_net_amount) as total_value
            FROM quotations
            WHERE status = 'Approved' AND quotation_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY month_year
            ORDER BY month_year ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $months);
    $stmt->execute();
    $result = $stmt->get_result();
    $valueData = ['labels' => [], 'data' => []];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $valueData['labels'][] = $row['month_year'];
            $valueData['data'][] = (float)$row['total_value'];
        }
    }
    $stmt->close();
    return $valueData;
}

function getMyRecentActivity($conn, $userId, $limit = 5) {
    $activity = [];
    $sql = "SELECT action_type, description, timestamp, target_entity, target_entity_id, username_snapshot
            FROM activity_log
            WHERE user_id = ?
            ORDER BY timestamp DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activity[] = $row;
        }
    }
    $stmt->close();
    return $activity;
}

// Fetch all data
$kpisData = getKpis($conn);
$quotationStatusData = getQuotationStatusOverview($conn);
$recentQuotationsData = getRecentQuotations($conn);
$quotationValueOverTimeData = getQuotationValueOverTime($conn);
$myRecentActivityData = [];
if (in_array($currentUser['role'], ['admin', 'manager', 'staff', 'supervisor'])) {
    $myRecentActivityData = getMyRecentActivity($conn, $currentUser['id']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        /* Reset and Base Styles */
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
    --primary: 221.2 83.2% 53.3%;
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
    --ring: 221.2 83.2% 53.3%;
    --sidebar-width: 280px;
    --sidebar-collapsed: 70px;
    --topbar-height: 64px;
    --radius: 0.5rem;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background-color: hsl(var(--background));
    color: hsl(var(--foreground));
    line-height: 1.5;
    font-size: 14px;
    overflow-x: hidden;
}

/* Sidebar Styles */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    width: var(--sidebar-width);
    background: hsl(var(--card));
    border-right: 1px solid hsl(var(--border));
    z-index: 1000;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}

.sidebar.collapsed {
    width: var(--sidebar-collapsed);
}

.sidebar-brand {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid hsl(var(--border));
    text-decoration: none;
    color: hsl(var(--foreground));
    font-weight: 600;
    font-size: 1.125rem;
    gap: 0.75rem;
    transition: all 0.3s ease;
}

.sidebar-brand i {
    font-size: 1.25rem;
    color: hsl(var(--primary));
    min-width: 20px;
}

.sidebar.collapsed .sidebar-brand span {
    opacity: 0;
    transform: translateX(-10px);
}

/* Navigation Styles */
.nav-list {
    list-style: none;
    padding: 1rem 0;
    flex: 1;
    overflow-y: auto;
}

.nav-item {
    position: relative;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    color: hsl(var(--muted-foreground));
    text-decoration: none;
    transition: all 0.2s ease;
    gap: 0.75rem;
    position: relative;
    font-weight: 500;
}

.nav-link:hover {
    background-color: hsl(var(--accent));
    color: hsl(var(--accent-foreground));
}

.nav-link.active {
    background-color: hsl(var(--primary));
    color: hsl(var(--primary-foreground));
    font-weight: 600;
}

.nav-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 3px;
    background: hsl(var(--primary-foreground));
}

.nav-link i {
    min-width: 20px;
    font-size: 1rem;
}

.nav-link .arrow {
    margin-left: auto;
    transition: transform 0.2s ease;
    font-size: 0.875rem;
}

.nav-link.has-submenu:hover .arrow {
    transform: rotate(90deg);
}

.sidebar.collapsed .nav-link span {
    opacity: 0;
    transform: translateX(-10px);
}

.sidebar.collapsed .nav-link .arrow {
    display: none;
}

/* Submenu Styles */
.submenu {
    list-style: none;
    background-color: hsl(var(--muted));
    border-left: 2px solid hsl(var(--border));
    margin-left: 1rem;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.nav-item:hover .submenu {
    max-height: 200px;
}

.submenu .nav-link {
    padding: 0.5rem 1rem 0.5rem 2rem;
    font-size: 0.875rem;
    font-weight: 400;
}

/* Sidebar Toggle */
.sidebar-toggle-container {
    padding: 1rem;
    border-top: 1px solid hsl(var(--border));
}

#sidebarToggle {
    width: 100%;
    padding: 0.5rem;
    background: hsl(var(--secondary));
    border: 1px solid hsl(var(--border));
    border-radius: var(--radius);
    color: hsl(var(--secondary-foreground));
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

#sidebarToggle:hover {
    background: hsl(var(--accent));
}

/* Main Content Area */
.main-wrapper {
    margin-left: var(--sidebar-width);
    min-height: 100vh;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}

.main-wrapper.collapsed {
    margin-left: var(--sidebar-collapsed);
}

/* Topbar Styles */
.topbar {
    height: var(--topbar-height);
    background: hsl(var(--card));
    border-bottom: 1px solid hsl(var(--border));
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2rem;
    position: sticky;
    top: 0;
    z-index: 100;
}

.topbar-search {
    flex: 1;
    max-width: 400px;
}

.topbar-search input {
    width: 100%;
    padding: 0.5rem 1rem;
    border: 1px solid hsl(var(--border));
    border-radius: var(--radius);
    background: hsl(var(--background));
    color: hsl(var(--foreground));
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.topbar-search input:focus {
    outline: none;
    border-color: hsl(var(--primary));
    box-shadow: 0 0 0 2px hsl(var(--primary) / 0.2);
}

.topbar-search input::placeholder {
    color: hsl(var(--muted-foreground));
}

.topbar-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: hsl(var(--foreground));
    font-weight: 500;
    font-size: 0.875rem;
}

.topbar-profile i {
    font-size: 1.5rem;
    color: hsl(var(--muted-foreground));
}

/* Content Area */
.content-area {
    flex: 1;
    padding: 2rem;
    background: hsl(var(--muted) / 0.3);
}

.content-area h1 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 2rem;
    color: hsl(var(--foreground));
}

/* Grid System */
.grid-container {
    display: grid;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.kpi-grid {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}

.charts-grid {
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
}

/* Card Styles */
.card {
    background: hsl(var(--card));
    border: 1px solid hsl(var(--border));
    border-radius: var(--radius);
    box-shadow: 0 1px 3px hsl(var(--foreground) / 0.1);
    transition: all 0.2s ease;
    overflow: hidden;
}

.card:hover {
    box-shadow: 0 4px 12px hsl(var(--foreground) / 0.15);
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid hsl(var(--border));
}

.card-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: hsl(var(--card-foreground));
}

/* KPI Card Specific Styles */
.kpi-card {
    padding: 1.5rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, hsl(var(--primary)), hsl(var(--primary) / 0.6));
}

.kpi-card h3 {
    font-size: 0.875rem;
    font-weight: 500;
    color: hsl(var(--muted-foreground));
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.kpi-card .value {
    font-size: 2rem;
    font-weight: 700;
    color: hsl(var(--foreground));
    margin-bottom: 0.25rem;
    line-height: 1;
}

.kpi-card .sub-value {
    font-size: 0.875rem;
    color: hsl(var(--muted-foreground));
    font-weight: 500;
}

/* Chart Container */
.chart-container {
    padding: 1.5rem;
    height: 300px;
}

/* List Styles */
.list-unstyled {
    list-style: none;
    padding: 0;
    margin: 0;
}

.recent-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid hsl(var(--border));
    transition: background-color 0.2s ease;
}

.recent-item:hover {
    background-color: hsl(var(--muted) / 0.5);
}

.recent-item:last-child {
    border-bottom: none;
}

.item-info strong {
    color: hsl(var(--foreground));
    font-weight: 600;
}

.item-info small {
    color: hsl(var(--muted-foreground));
    font-size: 0.8125rem;
}

/* Status Badges */
.item-status {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-draft {
    background: hsl(210 40% 96%);
    color: hsl(213 27% 34%);
}

.status-pending {
    background: hsl(48 96% 89%);
    color: hsl(25 95% 53%);
}

.status-approved {
    background: hsl(143 85% 96%);
    color: hsl(140 100% 27%);
}

.status-rejected {
    background: hsl(0 93% 94%);
    color: hsl(0 84% 60%);
}

.status-sent {
    background: hsl(221 91% 96%);
    color: hsl(221 83% 53%);
}

/* Activity Log */
.activity-log-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid hsl(var(--border));
    font-size: 0.875rem;
    line-height: 1.6;
}

.activity-log-item:last-child {
    border-bottom: none;
}

.action-time {
    display: block;
    color: hsl(var(--muted-foreground));
    font-size: 0.8125rem;
    margin-top: 0.25rem;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .kpi-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
    
    .main-wrapper {
        margin-left: 0;
    }
    
    .content-area {
        padding: 1rem;
    }
    
    .content-area h1 {
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .topbar {
        padding: 0 1rem;
    }
    
    .kpi-grid {
        grid-template-columns: 1fr;
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .recent-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: hsl(var(--muted));
}

::-webkit-scrollbar-thumb {
    background: hsl(var(--muted-foreground));
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: hsl(var(--foreground));
}

/* Focus Styles for Accessibility */
button:focus,
a:focus,
input:focus {
    outline: 2px solid hsl(var(--primary));
    outline-offset: 2px;
}

/* Animation for smooth transitions */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeIn 0.3s ease-out;
}

/* Loading states */
.loading {
    position: relative;
    overflow: hidden;
}

.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, hsl(var(--muted)), transparent);
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% {
        left: -100%;
    }
    100% {
        left: 100%;
    }
}
    </style>
</head>
<body>

    <aside class="sidebar" id="sidebar">
        <a href="#" class="sidebar-brand">
            <i class="fas fa-file-invoice-dollar"></i> <span>QuoteSys</span>
        </a>
        <ul class="nav-list">
            <li class="nav-item"><a href="#" class="nav-link active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li class="nav-item">
                <a href="#" class="nav-link has-submenu"><i class="fas fa-file-alt"></i><span>Quotations</span><i class="fas fa-angle-right arrow"></i></a>
                <ul class="submenu">
                    <li><a href="#" class="nav-link">All Quotations</a></li>
                    <li><a href="#" class="nav-link">Create New</a></li>
                    <?php if (in_array($currentUser['role'], ['admin', 'manager', 'supervisor'])): ?>
                    <li><a href="#" class="nav-link">Pending Approval</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <li class="nav-item"><a href="#" class="nav-link"><i class="fas fa-box-open"></i><span>Products</span></a></li>
            <li class="nav-item"><a href="#" class="nav-link"><i class="fas fa-users"></i><span>Customers</span></a></li>
             <?php if (in_array($currentUser['role'], ['admin', 'manager'])): ?>
            <li class="nav-item"><a href="#" class="nav-link"><i class="fas fa-chart-line"></i><span>Reports</span></a></li>
            <?php endif; ?>
            <?php if ($currentUser['role'] === 'admin'): ?>
            <li class="nav-item"><a href="#" class="nav-link"><i class="fas fa-history"></i><span>Activity Log</span></a></li>
            <li class="nav-item">
                <a href="#" class="nav-link has-submenu"><i class="fas fa-cog"></i><span>Settings</span><i class="fas fa-angle-right arrow"></i></a>
                <ul class="submenu">
                    <li><a href="#" class="nav-link">Company</a></li>
                    <li><a href="#" class="nav-link">Users</a></li>
                    <li><a href="#" class="nav-link">Categories</a></li>
                </ul>
            </li>
            <?php endif; ?>
        </ul>
        <div class="sidebar-toggle-container">
            <button id="sidebarToggle" title="Toggle Sidebar"><i class="fas fa-angle-left"></i></button>
        </div>
    </aside>

    <div class="main-wrapper" id="mainWrapper">
        <header class="topbar">
            <div class="topbar-search">
                <input type="text" placeholder="Search Quotations, Customers...">
            </div>
            <div class="topbar-profile">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($currentUser['full_name']); ?> (<?php echo htmlspecialchars(ucfirst($currentUser['role'])); ?>)</span>
                </div>
        </header>

        <main class="content-area">
            <h1>Dashboard Overview</h1>

            <section class="grid-container kpi-grid">
                <div class="card kpi-card">
                    <h3>Total Quotes (Month)</h3>
                    <div class="value"><?php echo number_format($kpisData['total_quotations_month']); ?></div>
                </div>
                <div class="card kpi-card">
                    <h3>Pending Approval</h3>
                    <div class="value"><?php echo number_format($kpisData['pending_approval']); ?></div>
                </div>
                <div class="card kpi-card">
                    <h3>Approved Value (MTD)</h3>
                    <div class="value">$<?php echo number_format($kpisData['approved_value_mtd'], 2); ?></div>
                </div>
                <div class="card kpi-card">
                    <h3>Draft Quotes</h3>
                    <div class="value"><?php echo number_format($kpisData['draft_quotations']); ?></div>
                    <div class="sub-value">Value: $<?php echo number_format($kpisData['draft_value'], 2); ?></div>
                </div>
            </section>

            <section class="grid-container charts-grid">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Quotation Status Overview</h3></div>
                    <div class="chart-container">
                        <canvas id="quotationStatusChart"></canvas>
                    </div>
                </div>
                <div class="card">
                     <div class="card-header"><h3 class="card-title">Recent Quotations</h3></div>
                    <ul class="list-unstyled" id="recentQuotationsList">
                        <?php if (empty($recentQuotationsData)): ?>
                            <li class="recent-item">No recent quotations found.</li>
                        <?php else: ?>
                            <?php foreach ($recentQuotationsData as $q): ?>
                            <li class="recent-item">
                                <div class="item-info">
                                    <strong><?php echo htmlspecialchars($q['quotation_number']); ?></strong> - <?php echo htmlspecialchars($q['customer_name'] ?? 'N/A'); ?><br>
                                    <small>Date: <?php echo date("M d, Y", strtotime($q['quotation_date'])); ?> | Amount: $<?php echo number_format($q['total_net_amount'], 2); ?></small>
                                </div>
                                <span class="item-status status-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $q['status']))); ?>">
                                    <?php echo htmlspecialchars($q['status']); ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </section>

            <section class="grid-container charts-grid">
                 <div class="card">
                    <div class="card-header"><h3 class="card-title">Approved Value (Last 6 Months)</h3></div>
                    <div class="chart-container">
                        <canvas id="quotationValueChart"></canvas>
                    </div>
                </div>
                <?php if (!empty($myRecentActivityData)): ?>
                <div class="card">
                    <div class="card-header"><h3 class="card-title">My Recent Activity</h3></div>
                     <ul class="list-unstyled">
                        <?php foreach ($myRecentActivityData as $activity): ?>
                        <li class="activity-log-item">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $activity['action_type']))); ?>
                            <?php if($activity['target_entity'] && $activity['target_entity_id']): ?>
                                on <?php echo htmlspecialchars($activity['target_entity']); ?> #<?php echo htmlspecialchars($activity['target_entity_id']); ?>
                            <?php elseif($activity['description']): ?>
                                : <?php echo htmlspecialchars(substr($activity['description'], 0, 60)) . (strlen($activity['description']) > 60 ? '...' : ''); ?>
                            <?php endif; ?>
                            <span class="action-time">by <?php echo htmlspecialchars($activity['username_snapshot'] ?? 'System'); ?> - <?php echo date("M d, Y H:i", strtotime($activity['timestamp'])); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </section>

        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const mainWrapper = document.getElementById('mainWrapper');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const toggleIcon = sidebarToggle.querySelector('i');

        // Function to toggle sidebar
        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            mainWrapper.classList.toggle('sidebar-collapsed');
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.classList.remove('fa-angle-left');
                toggleIcon.classList.add('fa-angle-right');
                // Close all open submenus when collapsing
                document.querySelectorAll('.submenu.open').forEach(submenu => {
                    submenu.classList.remove('open');
                    submenu.style.maxHeight = null;
                    submenu.previousElementSibling.classList.remove('open');
                });
            } else {
                toggleIcon.classList.remove('fa-angle-right');
                toggleIcon.classList.add('fa-angle-left');
            }
        }

        // Sidebar Toggle Button
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }

        // Sidebar Submenu Toggle
        document.querySelectorAll('.sidebar .nav-link.has-submenu').forEach(link => {
            link.addEventListener('click', function (e) {
                if (sidebar.classList.contains('collapsed')) {
                    // If sidebar is collapsed, hover will handle submenu display for desktop.
                    // For touch devices, this click might be needed, but requires more complex logic.
                    // For now, we prevent default and let CSS hover handle it.
                    // If you want click to expand popout menus on collapsed sidebar, logic here.
                    return;
                }
                e.preventDefault();
                const submenu = this.nextElementSibling;
                const parentItem = this.parentElement;

                // Close other open submenus
                document.querySelectorAll('.nav-item .submenu.open').forEach(openSubmenu => {
                    if (openSubmenu !== submenu) {
                        openSubmenu.classList.remove('open');
                        openSubmenu.style.maxHeight = null;
                        openSubmenu.previousElementSibling.classList.remove('open');
                    }
                });
                
                this.classList.toggle('open');
                submenu.classList.toggle('open');
                if (submenu.classList.contains('open')) {
                    submenu.style.maxHeight = submenu.scrollHeight + "px";
                } else {
                    submenu.style.maxHeight = null;
                }
            });
        });
        
        // Initial check for screen size (e.g., collapse for mobile)
        function checkScreenSize() {
            if (window.innerWidth <= 992) { // Corresponds to @media (max-width: 992px)
                if (!sidebar.classList.contains('collapsed')) { // if not already collapsed by preference
                   // sidebar.classList.add('collapsed'); // Auto-collapse
                   // mainWrapper.classList.add('sidebar-collapsed');
                   // if (toggleIcon) { // If toggleIcon exists
                   //    toggleIcon.classList.remove('fa-angle-left');
                   //    toggleIcon.classList.add('fa-angle-right');
                   // }
                }
                if (sidebarToggle) sidebarToggle.style.display = 'none'; // Hide toggle on small screens as per CSS logic
            } else {
                if (sidebarToggle) sidebarToggle.style.display = 'flex'; // Show toggle on larger screens
            }
        }
        checkScreenSize();
        window.addEventListener('resize', checkScreenSize);


        // --- CHARTS ---
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
        Chart.defaults.color = 'var(--text-muted-color)';
        Chart.defaults.borderColor = 'var(--border-color)';

        // 1. Quotation Status Chart (Doughnut)
        const quotationStatusCtx = document.getElementById('quotationStatusChart')?.getContext('2d');
        if (quotationStatusCtx) {
            const quotationStatusDataPHP = <?php echo json_encode($quotationStatusData); ?>;
            new Chart(quotationStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: quotationStatusDataPHP.labels,
                    datasets: [{
                        label: 'Quotation Status',
                        data: quotationStatusDataPHP.data,
                        backgroundColor: quotationStatusDataPHP.colors,
                        hoverOffset: 8,
                        borderWidth: 2,
                        borderColor: 'var(--card-bg-color)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { animateScale: true, animateRotate: true },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed !== null) {
                                        label += context.parsed;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        // 2. Quotation Value Over Time Chart (Bar)
        const quotationValueCtx = document.getElementById('quotationValueChart')?.getContext('2d');
        if (quotationValueCtx) {
            const quotationValueDataPHP = <?php echo json_encode($quotationValueOverTimeData); ?>;
            new Chart(quotationValueCtx, {
                type: 'bar',
                data: {
                    labels: quotationValueDataPHP.labels,
                    datasets: [{
                        label: 'Approved Quotation Value ($)',
                        data: quotationValueDataPHP.data,
                        backgroundColor: 'rgba(52, 152, 219, 0.7)', // Peter River with opacity
                        borderColor: 'var(--secondary-color)',
                        borderWidth: 1,
                        borderRadius: 4,
                        hoverBackgroundColor: 'var(--secondary-color)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { drawBorder: false }, ticks: { callback: value => '$' + value.toLocaleString() } },
                        x: { grid: { display: false } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed.y !== null) {
                                        label += '$' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
    });
    </script>
</body>
</html>
<?php
$conn->close();
?>