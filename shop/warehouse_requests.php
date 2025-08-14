<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['warehouse_id'])) { header('Location: /login.php'); exit(); }
// DB Connection... (same as before)
$host = '127.0.0.1'; $dbname = 'supplies'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset"; $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
try { $pdo = new PDO($dsn, $user, $pass, $options); } catch (\PDOException $e) { throw new \PDOException($e->getMessage(), (int)$e->getCode()); }

$user_id = $_SESSION['user_id'];
$warehouse_id = $_SESSION['warehouse_id'];

// --- HANDLE PROCESSING A REQUEST (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['process_request'])) {
    $stock_transfer_id = filter_input(INPUT_POST, 'stock_transfer_id', FILTER_VALIDATE_INT);
    $item_ids = $_POST['item_ids'] ?? [];
    $quantities_shipped = $_POST['quantities_shipped'] ?? [];
    
    $pdo->beginTransaction();
    try {
        // 1. Update quantities shipped for each item
        $sql_update_item = "UPDATE stock_transfer_items SET quantity_shipped = ? WHERE id = ?";
        $stmt_update_item = $pdo->prepare($sql_update_item);

        // 2. Update warehouse stock and log transaction
        $sql_update_stock = "UPDATE warehouse_stock SET quantity_in_stock = quantity_in_stock - ? WHERE warehouse_id = ? AND product_id = ?";
        $stmt_update_stock = $pdo->prepare($sql_update_stock);
        
        $sql_log_trans = "INSERT INTO stock_transactions (product_id, transaction_type, quantity, reference_type, reference_id, scanned_by_user_id, notes) VALUES (?, 'stock_out', ?, 'stock_transfer', ?, ?, ?)";
        $stmt_log_trans = $pdo->prepare($sql_log_trans);

        foreach ($item_ids as $index => $item_id) {
            $shipped_qty = (int)($quantities_shipped[$index] ?? 0);
            if ($shipped_qty > 0) {
                // Fetch product_id for this item
                $product_id = $pdo->query("SELECT product_id FROM stock_transfer_items WHERE id = $item_id")->fetchColumn();
                
                // Update item shipped quantity
                $stmt_update_item->execute([$shipped_qty, $item_id]);
                // Update warehouse stock
                $stmt_update_stock->execute([$shipped_qty, $warehouse_id, $product_id]);
                // Log transaction
                $stmt_log_trans->execute([$product_id, $shipped_qty, $stock_transfer_id, $user_id, "Shipped for request ID $stock_transfer_id"]);
            }
        }

        // 3. Update the main transfer status to 'In-Transit'
        $sql_update_transfer = "UPDATE stock_transfers SET status = 'In-Transit', shipped_by_user_id = ?, shipped_at = NOW() WHERE id = ?";
        $stmt_update_transfer = $pdo->prepare($sql_update_transfer);
        $stmt_update_transfer->execute([$user_id, $stock_transfer_id]);

        $pdo->commit();
        header("Location: warehouse_requests.php?status=processed");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Failed to process request: " . $e->getMessage();
    }
}
if(isset($_GET['status']) && $_GET['status'] == 'processed') $success_message = "Request processed and stock updated.";

// --- FETCH DATA FOR DISPLAY ---
// Main list of PENDING requests for THIS warehouse
$sql_list = "
    SELECT st.*, s.name as to_shop_name, u.full_name as requester_name
    FROM stock_transfers st
    JOIN shops s ON st.to_shop_id = s.id
    JOIN users u ON st.requested_by_user_id = u.id
    WHERE st.from_warehouse_id = ? AND st.status = 'Pending'
    ORDER BY st.created_at ASC
";
$stmt_list = $pdo->prepare($sql_list);
$stmt_list->execute([$warehouse_id]);
$pending_requests = $stmt_list->fetchAll();

// Pre-fetch items and stock levels for the modals
$request_ids = array_column($pending_requests, 'id');
$request_items = [];
if (!empty($request_ids)) {
    $in_clause = str_repeat('?,', count($request_ids) - 1) . '?';
    $sql_items = "
        SELECT 
            sti.id, sti.stock_transfer_id, sti.quantity_requested, 
            p.name as product_name, p.sku, p.id as product_id,
            ws.quantity_in_stock
        FROM stock_transfer_items sti
        JOIN products p ON sti.product_id = p.id
        LEFT JOIN warehouse_stock ws ON p.id = ws.product_id AND ws.warehouse_id = ?
        WHERE sti.stock_transfer_id IN ($in_clause)
    ";
    $stmt_items = $pdo->prepare($sql_items);
    $params = array_merge([$warehouse_id], $request_ids);
    $stmt_items->execute($params);
    $items_result = $stmt_items->fetchAll();
    foreach ($items_result as $item) {
        $request_items[$item['stock_transfer_id']][] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Process Stock Requests</title>
    <!-- Links to FontAwesome, Bootstrap CSS (same as before) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> /* Add the same shared styles from the previous examples here */
        body { background-color: #f8f9fa; } .wrapper { display: flex; }
        #content { width: 100%; padding: 20px 40px; } .card { border: none; border-radius: 0.5rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); }
        .table thead th { font-weight: 600; } .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar-warehouse.php'; ?>

    <div id="content">
        <h2>Incoming Stock Requests</h2>
        <p class="text-muted">Review and process pending requests from shops.</p>

        <?php if (!empty($error_message)): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
        <?php if (!empty($success_message)): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>REQUEST REF #</th><th>TO SHOP</th><th>REQUESTER</th><th>DATE</th><th>ACTIONS</th></tr></thead>
                        <tbody>
                        <?php if (empty($pending_requests)): ?>
                            <tr><td colspan="5" class="text-center p-5">No pending requests. Great work!</td></tr>
                        <?php else: ?>
                            <?php foreach ($pending_requests as $request): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($request['transfer_reference']) ?></strong></td>
                                <td><?= htmlspecialchars($request['to_shop_name']) ?></td>
                                <td><?= htmlspecialchars($request['requester_name']) ?></td>
                                <td><?= date('M j, Y H:i', strtotime($request['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary process-btn" 
                                            data-toggle="modal" 
                                            data-target="#processRequestModal"
                                            data-request='<?= htmlspecialchars(json_encode($request)) ?>'
                                            data-items='<?= isset($request_items[$request['id']]) ? htmlspecialchars(json_encode($request_items[$request['id']])) : "[]" ?>'>
                                        <i class="fas fa-edit"></i> Process
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Process Request Modal -->
<div class="modal fade" id="processRequestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="stock_transfer_id" id="modal-transfer-id">
                <div class="modal-header"><h5 class="modal-title">Process Request: <span id="modal-ref"></span></h5><button type="button" class="close" data-dismiss="modal">×</button></div>
                <div class="modal-body">
                    <p>For shop: <strong id="modal-shop-name"></strong></p>
                    <table class="table table-sm">
                        <thead><tr><th>Product</th><th class="text-right">In Stock</th><th class="text-right">Requested</th><th>Qty to Ship</th></tr></thead>
                        <tbody id="modal-items-tbody"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="process_request" class="btn btn-success">Confirm and Ship Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    $('.process-btn').click(function() {
        var request = $(this).data('request');
        var items = $(this).data('items');

        $('#modal-transfer-id').val(request.id);
        $('#modal-ref').text(request.transfer_reference);
        $('#modal-shop-name').text(request.to_shop_name);
        
        var itemsTbody = $('#modal-items-tbody');
        itemsTbody.empty();
        items.forEach(function(item) {
            var inStock = item.quantity_in_stock || 0;
            var row = '<tr>' +
                '<td>' + item.product_name + '<br><small class="text-muted">' + item.sku + '</small></td>' +
                '<td class="text-right">' + inStock + '</td>' +
                '<td class="text-right">' + item.quantity_requested + '</td>' +
                '<td>' +
                    '<input type="hidden" name="item_ids[]" value="' + item.id + '">' +
                    '<input type="number" name="quantities_shipped[]" class="form-control form-control-sm" value="' + Math.min(inStock, item.quantity_requested) + '" min="0" max="' + inStock + '">' +
                '</td>' +
                '</tr>';
            itemsTbody.append(row);
        });
    });
});
</script>
</body>
</html>