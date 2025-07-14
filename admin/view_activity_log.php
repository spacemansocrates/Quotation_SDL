<?php
// view_activity_log.php

// Always start the session to check for login credentials.
session_start();

// --- SECURITY CHECK ---
// This is a critical security measure. Only users with the 'admin' role can view this page.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // If not an admin, send a forbidden header and stop script execution.
    header('HTTP/1.1 403 Forbidden');
    die('<h1>Access Denied</h1><p>You do not have permission to view this page. Please log in as an administrator.</p>');
}

// Check if this is an AJAX request for data only
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// --- DATABASE CONFIGURATION ---
$host = 'localhost';
$dbname = 'supplies';
$user = 'root';
$pass = ''; // No password as specified
$charset = 'utf8mb4';

// --- PDO Connection ---
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // If connection fails, stop everything and show a detailed error.
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
    die("Database connection failed: " . $e->getMessage());
}

// --- NATURAL LANGUAGE DESCRIPTION GENERATOR ---
function generateNaturalDescription($log) {
    $username = $log['username_snapshot'] ?? 'System';
    $action = strtolower($log['action_type']);
    $entity = $log['target_entity'] ?? '';
    $entity_id = $log['target_entity_id'] ?? '';
    
    // Parse details JSON if available
    $details = null;
    if (!empty($log['details'])) {
        $details = json_decode($log['details'], true);
    }
    
    // Generate natural language based on action and entity
    switch ($action) {
        case 'create':
            return generateCreateDescription($username, $entity, $entity_id, $details);
        case 'update':
            return generateUpdateDescription($username, $entity, $entity_id, $details);
        case 'delete':
            return generateDeleteDescription($username, $entity, $entity_id, $details);
        case 'login':
            return "$username logged into the system";
        case 'logout':
            return "$username logged out of the system";
        case 'view':
            return "$username viewed " . getEntityName($entity) . " #$entity_id";
        case 'export':
            return "$username exported " . getEntityName($entity) . " data";
        case 'import':
            return "$username imported " . getEntityName($entity) . " data";
        default:
            return "$username performed $action on " . getEntityName($entity) . " #$entity_id";
    }
}

function generateCreateDescription($username, $entity, $entity_id, $details) {
    $entityName = getEntityName($entity);
    
    switch ($entity) {
        case 'quotation':
            $clientName = $details['client_name'] ?? 'Unknown Client';
            return "$username created a new quotation (#$entity_id) for $clientName";
        
        case 'product':
            $productName = $details['product_name'] ?? $details['name'] ?? 'Unknown Product';
            return "$username added a new product '$productName' (#$entity_id) to the catalog";
        
        case 'quotation_item':
            $productName = $details['product_name'] ?? 'Product';
            $quotationId = $details['quotation_id'] ?? 'Unknown';
            $quantity = $details['quantity'] ?? 1;
            return "$username added $quantity x '$productName' to quotation #$quotationId";
        
        case 'user':
            $newUsername = $details['username'] ?? 'Unknown User';
            $role = $details['role'] ?? 'user';
            return "$username created a new $role account for '$newUsername' (#$entity_id)";
        
        case 'supplier':
            $supplierName = $details['company_name'] ?? $details['name'] ?? 'Unknown Supplier';
            return "$username added supplier '$supplierName' (#$entity_id) to the system";
        
        default:
            return "$username created a new $entityName (#$entity_id)";
    }
}

function generateUpdateDescription($username, $entity, $entity_id, $details) {
    $entityName = getEntityName($entity);
    
    if (!$details || !isset($details['changes'])) {
        return "$username updated $entityName #$entity_id";
    }
    
    $changes = $details['changes'];
    $changeDescriptions = [];
    
    foreach ($changes as $field => $change) {
        $oldValue = $change['old'] ?? 'empty';
        $newValue = $change['new'] ?? 'empty';
        
        switch ($field) {
            case 'price':
            case 'unit_price':
                $changeDescriptions[] = "changed price from $" . number_format($oldValue, 2) . " to $" . number_format($newValue, 2);
                break;
            case 'quantity':
                $changeDescriptions[] = "changed quantity from $oldValue to $newValue";
                break;
            case 'status':
                $changeDescriptions[] = "changed status from '$oldValue' to '$newValue'";
                break;
            case 'name':
            case 'product_name':
            case 'client_name':
                $changeDescriptions[] = "changed name from '$oldValue' to '$newValue'";
                break;
            case 'email':
                $changeDescriptions[] = "changed email from '$oldValue' to '$newValue'";
                break;
            case 'role':
                $changeDescriptions[] = "changed role from '$oldValue' to '$newValue'";
                break;
            default:
                $changeDescriptions[] = "changed $field from '$oldValue' to '$newValue'";
        }
    }
    
    if (empty($changeDescriptions)) {
        return "$username updated $entityName #$entity_id";
    }
    
    $changesText = implode(', ', $changeDescriptions);
    
    switch ($entity) {
        case 'quotation':
            $clientName = $details['client_name'] ?? '';
            $clientText = $clientName ? " for $clientName" : '';
            return "$username updated quotation #$entity_id$clientText - $changesText";
        
        case 'product':
            $productName = $details['product_name'] ?? '';
            $productText = $productName ? " '$productName'" : '';
            return "$username updated product$productText (#$entity_id) - $changesText";
        
        case 'quotation_item':
            $quotationId = $details['quotation_id'] ?? 'Unknown';
            return "$username updated an item in quotation #$quotationId - $changesText";
        
        default:
            return "$username updated $entityName #$entity_id - $changesText";
    }
}

function generateDeleteDescription($username, $entity, $entity_id, $details) {
    $entityName = getEntityName($entity);
    
    switch ($entity) {
        case 'quotation':
            $clientName = $details['client_name'] ?? 'Unknown Client';
            return "$username deleted quotation #$entity_id for $clientName";
        
        case 'product':
            $productName = $details['product_name'] ?? $details['name'] ?? 'Unknown Product';
            return "$username removed product '$productName' (#$entity_id) from the catalog";
        
        case 'quotation_item':
            $productName = $details['product_name'] ?? 'Product';
            $quotationId = $details['quotation_id'] ?? 'Unknown';
            return "$username removed '$productName' from quotation #$quotationId";
        
        case 'user':
            $deletedUsername = $details['username'] ?? 'Unknown User';
            return "$username deleted user account '$deletedUsername' (#$entity_id)";
        
        case 'supplier':
            $supplierName = $details['company_name'] ?? $details['name'] ?? 'Unknown Supplier';
            return "$username removed supplier '$supplierName' (#$entity_id)";
        
        default:
            return "$username deleted $entityName #$entity_id";
    }
}

function getEntityName($entity) {
    $entityNames = [
        'quotation' => 'quotation',
        'quotation_item' => 'quotation item',
        'product' => 'product',
        'user' => 'user',
        'supplier' => 'supplier',
        'client' => 'client',
        'category' => 'category',
        'inventory' => 'inventory item'
    ];
    
    return $entityNames[$entity] ?? $entity;
}

// --- PAGINATION & FILTERING LOGIC ---

// 1. Define pagination variables
$limit = 25; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 2. Get filter values from the URL (GET request)
$filter_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$filter_action_type = isset($_GET['action_type']) ? $_GET['action_type'] : null;
$filter_target_entity = isset($_GET['target_entity']) ? $_GET['target_entity'] : null;
$filter_start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : null;
$filter_end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : null;

// 3. Build the WHERE clause dynamically and securely
$where_clauses = [];
$params = [];

if ($filter_user_id) {
    $where_clauses[] = "al.user_id = :user_id";
    $params[':user_id'] = $filter_user_id;
}
if ($filter_action_type) {
    $where_clauses[] = "al.action_type = :action_type";
    $params[':action_type'] = $filter_action_type;
}
if ($filter_target_entity) {
    $where_clauses[] = "al.target_entity = :target_entity";
    $params[':target_entity'] = $filter_target_entity;
}
if ($filter_start_date) {
    $where_clauses[] = "al.timestamp >= :start_date";
    $params[':start_date'] = $filter_start_date . ' 00:00:00';
}
if ($filter_end_date) {
    $where_clauses[] = "al.timestamp <= :end_date";
    $params[':end_date'] = $filter_end_date . ' 23:59:59';
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// 4. Get the total number of records for pagination
$total_sql = "SELECT COUNT(*) FROM activity_log al $where_sql";
$total_stmt = $pdo->prepare($total_sql);
$total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// 5. Fetch the records for the current page
$sql = "SELECT al.* FROM activity_log al $where_sql ORDER BY al.id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);

// Bind pagination params
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

// Bind filter params
foreach ($params as $key => &$val) {
    $stmt->bindValue($key, $val);
}

$stmt->execute();
$logs = $stmt->fetchAll();

// If this is an AJAX request, return JSON data only
if ($is_ajax) {
    header('Content-Type: application/json');
    
    // Generate the table rows HTML
    $table_html = '';
    if (count($logs) > 0) {
        foreach ($logs as $log) {
            $action = htmlspecialchars($log['action_type']);
            $badge_class = 'bg-secondary';
            if ($action === 'CREATE') $badge_class = 'bg-success';
            if ($action === 'UPDATE') $badge_class = 'bg-warning text-dark';
            if ($action === 'DELETE') $badge_class = 'bg-danger';
            if ($action === 'LOGIN') $badge_class = 'bg-info text-dark';
            
            // Generate natural language description
            $naturalDescription = generateNaturalDescription($log);
            
            $details_html = 'N/A';
            if (!empty($log['details'])) {
                $details_json = json_decode($log['details']);
                $details_html = '<pre>' . htmlspecialchars(json_encode($details_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
            }
            
            $table_html .= '<tr>';
            $table_html .= '<td>' . $log['id'] . '</td>';
            $table_html .= '<td>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['timestamp']))) . '</td>';
            $table_html .= '<td>' . htmlspecialchars($log['username_snapshot'] ?? 'System/Unknown') . '</td>';
            $table_html .= '<td><span class="badge ' . $badge_class . '">' . $action . '</span></td>';
            $table_html .= '<td>' . htmlspecialchars($log['target_entity'] ?? 'N/A') . '#' . htmlspecialchars($log['target_entity_id'] ?? '') . '</td>';
            $table_html .= '<td style="min-width: 350px;"><strong>' . htmlspecialchars($naturalDescription) . '</strong></td>';
            $table_html .= '<td style="min-width: 300px;">' . $details_html . '</td>';
            $table_html .= '<td>' . htmlspecialchars($log['ip_address'] ?? 'N/A') . '</td>';
            $table_html .= '</tr>';
        }
    } else {
        $table_html = '<tr><td colspan="8" class="text-center">No activity logs found matching the criteria.</td></tr>';
    }
    
    // Generate pagination HTML
    $pagination_html = '';
    if ($total_pages > 1) {
        $query_params = $_GET;
        unset($query_params['page']);
        unset($query_params['ajax']);
        $query_string = http_build_query($query_params);
        
        $pagination_html .= '<nav aria-label="Page navigation" class="mt-4">';
        $pagination_html .= '<ul class="pagination justify-content-center">';
        
        // Previous Page Link
        $disabled = ($page <= 1) ? 'disabled' : '';
        $pagination_html .= '<li class="page-item ' . $disabled . '">';
        $pagination_html .= '<a class="page-link" href="#" onclick="loadPage(' . ($page - 1) . ')">Previous</a>';
        $pagination_html .= '</li>';
        
        // Page Number Links
        for ($i = 1; $i <= $total_pages; $i++) {
            $active = ($page == $i) ? 'active' : '';
            $pagination_html .= '<li class="page-item ' . $active . '">';
            $pagination_html .= '<a class="page-link" href="#" onclick="loadPage(' . $i . ')">' . $i . '</a>';
            $pagination_html .= '</li>';
        }
        
        // Next Page Link
        $disabled = ($page >= $total_pages) ? 'disabled' : '';
        $pagination_html .= '<li class="page-item ' . $disabled . '">';
        $pagination_html .= '<a class="page-link" href="#" onclick="loadPage(' . ($page + 1) . ')">Next</a>';
        $pagination_html .= '</li>';
        
        $pagination_html .= '</ul>';
        $pagination_html .= '</nav>';
    }
    
    echo json_encode([
        'table_html' => $table_html,
        'pagination_html' => $pagination_html,
        'total_records' => $total_records,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// 6. Fetch distinct values for filter dropdowns (only for full page load)
$users = $pdo->query("SELECT DISTINCT user_id, username_snapshot FROM activity_log WHERE user_id IS NOT NULL ORDER BY username_snapshot ASC")->fetchAll();
$action_types = $pdo->query("SELECT DISTINCT action_type FROM activity_log ORDER BY action_type ASC")->fetchAll();
$target_entities = $pdo->query("SELECT DISTINCT target_entity FROM activity_log WHERE target_entity IS NOT NULL ORDER BY target_entity ASC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Activity Log</title>
    <!-- Using Bootstrap 5 for clean styling from a CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 1400px; }
        .table-responsive { max-height: 80vh; }
        pre { background-color: #e9ecef; padding: 10px; border-radius: 4px; white-space: pre-wrap; word-break: break-all; }
        .filter-form { background-color: #ffffff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .badge { font-size: 0.9em; }
        .auto-refresh-controls { 
            background-color: #ffffff; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-active { background-color: #28a745; }
        .status-paused { background-color: #dc3545; }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .table-container { position: relative; }
        .natural-description { 
            font-weight: 600; 
            color: #495057; 
            line-height: 1.4;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <h1 class="mb-4">System Activity Log</h1>

    <!-- Auto-refresh Controls -->
    <div class="auto-refresh-controls">
        <div>
            <span class="status-indicator" id="statusIndicator"></span>
            <strong>Auto-refresh:</strong>
            <span id="refreshStatus">Paused</span>
        </div>
        <div>
            <button id="toggleRefresh" class="btn btn-success btn-sm">Start Auto-refresh</button>
            <button id="refreshNow" class="btn btn-primary btn-sm">Refresh Now</button>
        </div>
        <div class="flex-grow-1 text-end">
            <small class="text-muted">
                Last updated: <span id="lastUpdated">Never</span> | 
                Total records: <span id="totalRecords"><?= $total_records ?></span>
            </small>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="filter-form">
        <form id="filterForm" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label for="user_id" class="form-label">User</label>
                <select name="user_id" id="user_id" class="form-select">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user_option): ?>
                        <option value="<?= (int)$user_option['user_id'] ?>" <?= ($filter_user_id == $user_option['user_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user_option['username_snapshot']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="action_type" class="form-label">Action Type</label>
                <select name="action_type" id="action_type" class="form-select">
                    <option value="">All Actions</option>
                    <?php foreach ($action_types as $action): ?>
                        <option value="<?= htmlspecialchars($action['action_type']) ?>" <?= ($filter_action_type == $action['action_type']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($action['action_type']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="target_entity" class="form-label">Target Entity</label>
                <select name="target_entity" id="target_entity" class="form-select">
                    <option value="">All Entities</option>
                    <?php foreach ($target_entities as $entity): ?>
                        <option value="<?= htmlspecialchars($entity['target_entity']) ?>" <?= ($filter_target_entity == $entity['target_entity']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($entity['target_entity']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($filter_start_date ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($filter_end_date ?? '') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <button type="button" id="resetFilters" class="btn btn-secondary w-100 mt-2">Reset</button>
            </div>
        </form>
    </div>

    <!-- Log Table -->
    <div class="table-container">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>What Happened</th>
                        <th>Technical Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody id="logTableBody">
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= $log['id'] ?></td>
                                <td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['timestamp']))) ?></td>
                                <td><?= htmlspecialchars($log['username_snapshot'] ?? 'System/Unknown') ?></td>
                                <td>
                                    <?php 
                                    $action = htmlspecialchars($log['action_type']);
                                    $badge_class = 'bg-secondary';
                                    if ($action === 'CREATE') $badge_class = 'bg-success';
                                    if ($action === 'UPDATE') $badge_class = 'bg-warning text-dark';
                                    if ($action === 'DELETE') $badge_class = 'bg-danger';
                                    if ($action === 'LOGIN') $badge_class = 'bg-info text-dark';
                                    echo "<span class='badge {$badge_class}'>{$action}</span>";
                                    ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($log['target_entity'] ?? 'N/A') ?>
                                    #<?= htmlspecialchars($log['target_entity_id'] ?? '') ?>
                                </td>
                                <td style="min-width: 350px;" class="natural-description">
                                    <?= htmlspecialchars(generateNaturalDescription($log)) ?>
                                </td>
                                <td style="min-width: 300px;">
                                    <?php if (!empty($log['details'])): ?>
                                        <pre><?php
                                            // Pretty-print the JSON details for readability
                                            $details_json = json_decode($log['details']);
                                            echo htmlspecialchars(json_encode($details_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                        ?></pre>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No activity logs found matching the criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Controls -->
    <div id="paginationContainer">
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    // Build the query string for pagination links, preserving filters
                    $query_params = $_GET;
                    unset($query_params['page']);
                    $query_string = http_build_query($query_params);
                    ?>

                    <!-- Previous Page Link -->
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="#" onclick="loadPage(<?= $page - 1 ?>)">Previous</a>
                    </li>

                    <!-- Page Number Links -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="#" onclick="loadPage(<?= $i ?>)"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- Next Page Link -->
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="#" onclick="loadPage(<?= $page + 1 ?>)">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

</div>

<script>
// Auto-refresh functionality
let refreshInterval = null;
let isRefreshing = false;
const REFRESH_INTERVAL_MS = 5000; // 5 seconds

// DOM Elements
const toggleRefreshBtn = document.getElementById('toggleRefresh');
const refreshNowBtn = document.getElementById('refreshNow');
const statusIndicator = document.getElementById('statusIndicator');
const refreshStatus = document.getElementById('refreshStatus');
const lastUpdated = document.getElementById('lastUpdated');
const totalRecords = document.getElementById('totalRecords');
const logTableBody = document.getElementById('logTableBody');
const paginationContainer = document.getElementById('paginationContainer');
const loadingOverlay = document.getElementById('loadingOverlay');
const filterForm = document.getElementById('filterForm');

// Current page tracking
let currentPage = <?= $page ?>;

// Initialize
updateStatusDisplay(false);
updateLastUpdated();

// Toggle auto-refresh
toggleRefreshBtn.addEventListener('click', function() {
    if (refreshInterval) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});

// Manual refresh
refreshNowBtn.addEventListener('click', function() {
    loadData();
});

// Filter form submission
filterForm.addEventListener('submit', function(e) {
    e.preventDefault();
    currentPage = 1; // Reset to first page when filtering
    loadData();
});

// Reset filters
document.getElementById('resetFilters').addEventListener('click', function() {
    filterForm.reset();
    currentPage = 1;
    loadData();
});

// Start auto-refresh
function startAutoRefresh() {
    refreshInterval = setInterval(loadData, REFRESH_INTERVAL_MS);
    updateStatusDisplay(true);
}

// Stop auto-refresh
function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
    updateStatusDisplay(false);
}

// Update status display
function updateStatusDisplay(isActive) {
    if (isActive) {
        statusIndicator.className = 'status-indicator status-active';
        refreshStatus.textContent = 'Active (every 5s)';
        toggleRefreshBtn.textContent = 'Stop Auto-refresh';
        toggleRefreshBtn.className = 'btn btn-danger btn-sm';
    } else {
        statusIndicator.className = 'status-indicator status-paused';
        refreshStatus.textContent = 'Paused';
        toggleRefreshBtn.textContent = 'Start Auto-refresh';
        toggleRefreshBtn.className = 'btn btn-success btn-sm';
    }
}

// Update last updated timestamp
function updateLastUpdated() {
    const now = new Date();
    lastUpdated.textContent = now.toLocaleString();
}

// Load specific page
function loadPage(page) {
    if (page < 1) return;
    currentPage = page;
    loadData();
}

// Get current filters
function getFilters() {
    const formData = new FormData(filterForm);
    const params = new URLSearchParams();
    
    for (let [key, value] of formData.entries()) {
        if (value.trim() !== '') {
            params.append(key, value);
        }
    }
    
    params.append('page', currentPage);
    params.append('ajax', '1');
    
    return params.toString();
}

// Load data via AJAX
function loadData() {
    if (isRefreshing) return; // Prevent multiple simultaneous requests
    
    isRefreshing = true;
    loadingOverlay.style.display = 'flex';
    
    const params = getFilters();
    
    fetch(`${window.location.pathname}?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error loading data:', data.error);
                return;
            }
            
            // Update table content
            logTableBody.innerHTML = data.table_html;
            
            // Update pagination
            paginationContainer.innerHTML = data.pagination_html;
            
            // Update stats
            totalRecords.textContent = data.total_records;
            
            // Update timestamp
            updateLastUpdated();
        })
        .catch(error => {
            console.error('Error loading data:', error);
        })
        .finally(() => {
            isRefreshing = false;
            loadingOverlay.style.display = 'none';
        });
}

// Pause auto-refresh when page is not visible
document.addEventListener('visibilitychange', function() {
    if (document.hidden && refreshInterval) {
        stopAutoRefresh();
    }
});

// Resume auto-refresh when page becomes visible (if it was active before)
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && !refreshInterval) {
        // You might want to add logic here to remember if auto-refresh was active before
    }
});
</script>

</body>
</html>