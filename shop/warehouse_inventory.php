<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['warehouse_id'])) { header('Location: /login.php'); exit(); }
// DB Connection...
$host = '127.0.0.1'; $dbname = 'supplies'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset"; $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
try { $pdo = new PDO($dsn, $user, $pass, $options); } catch (\PDOException $e) { throw new \PDOException($e->getMessage(), (int)$e->getCode()); }

$user_id = $_SESSION['user_id'];
$warehouse_id = $_SESSION['warehouse_id'];

// --- HANDLE STOCK ADJUSTMENT (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['adjust_stock'])) {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $current_qty = filter_input(INPUT_POST, 'current_qty', FILTER_VALIDATE_INT);
    $new_physical_count = filter_input(INPUT_POST, 'new_physical_count', FILTER_VALIDATE_INT);
    $notes = trim(filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING));
    
    if ($new_physical_count !== false && $new_physical_count >= 0) {
        $adjustment_qty = $new_physical_count - $current_qty;
        
        $pdo->beginTransaction();
        try {
            // 1. Update the warehouse_stock table
            $sql_update = "UPDATE warehouse_stock SET quantity_in_stock = ? WHERE product_id = ? AND warehouse_id = ?";
            $pdo->prepare($sql_update)->execute([$new_physical_count, $product_id, $warehouse_id]);

            // 2. Log the adjustment in stock_transactions
            if ($adjustment_qty != 0) {
                $sql_log = "INSERT INTO stock_transactions (product_id, transaction_type, quantity, reference_type, scanned_by_user_id, notes) VALUES (?, 'adjustment', ?, 'stock_take', ?, ?)";
                $full_notes = "Stock take adjustment. " . $notes;
                $pdo->prepare($sql_log)->execute([$product_id, $adjustment_qty, $user_id, $full_notes]);
            }
            
            $pdo->commit();
            header("Location: warehouse_inventory.php?status=adjusted");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Failed to adjust stock: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid physical count provided.";
    }
}
if(isset($_GET['status']) && $_GET['status'] == 'adjusted') $success_message = "Stock count adjusted successfully.";

// --- FETCH DATA FOR DISPLAY ---
// Paginated list of all products in this warehouse
$search = trim($_GET['search'] ?? '');
$whereSql = 'ws.warehouse_id = ?';
$params = [$warehouse_id];
if(!empty($search)){
    $whereSql .= ' AND (p.name LIKE ? OR p.sku LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "
    SELECT p.id, p.name, p.sku, ws.quantity_in_stock, ws.minimum_stock_level
    FROM products p
    JOIN warehouse_stock ws ON p.id = ws.product_id
    WHERE $whereSql
    ORDER BY p.name
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventory = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Warehouse Inventory</title>
    <!-- Links to FontAwesome, Bootstrap CSS (same as before) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> /* Add the same shared styles from the previous examples here */
        body { background-color: #f8f9fa; } .wrapper { display: flex; }
        #content { width: 100%; padding: 20px 40px; } .card { border: none; border-radius: 0.5rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); }
        .table thead th { font-weight: 600; } .table td, .table th { vertical-align: middle; }
        .low-stock { background-color: #f8d7da !important; color: #721c24; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar-warehouse.php'; ?>

    <div id="content">
        <h2>Warehouse Inventory</h2>
        <p class="text-muted">View current stock levels and perform stock takes.</p>
        
        <?php if (!empty($error_message)): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
        <?php if (!empty($success_message)): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form class="mb-4"><div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by product name or SKU..." value="<?= htmlspecialchars($search) ?>">
                    <div class="input-group-append"><button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button></div>
                </div></form>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead><tr><th>PRODUCT</th><th>SKU</th><th class="text-right">QTY IN STOCK</th><th>ACTIONS</th></tr></thead>
                        <tbody>
                        <?php foreach ($inventory as $item): 
                            $is_low_stock = $item['quantity_in_stock'] < $item['minimum_stock_level'];
                        ?>
                            <tr class="<?= $is_low_stock ? 'low-stock' : '' ?>">
                                <td><?= htmlspecialchars($item['name']) ?> <?= $is_low_stock ? '<span class="badge badge-danger">Low</span>' : '' ?></td>
                                <td><?= htmlspecialchars($item['sku']) ?></td>
                                <td class="text-right"><strong><?= $item['quantity_in_stock'] ?></strong></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary adjust-btn" 
                                            data-toggle="modal" 
                                            data-target="#adjustStockModal"
                                            data-product-id="<?= $item['id'] ?>"
                                            data-product-name="<?= htmlspecialchars($item['name']) ?>"
                                            data-current-qty="<?= $item['quantity_in_stock'] ?>">
                                        <i class="fas fa-calculator"></i> Adjust
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="product_id" id="adj-product-id">
                <input type="hidden" name="current_qty" id="adj-current-qty">
                <div class="modal-header"><h5 class="modal-title">Stock Take</h5><button type="button" class="close" data-dismiss="modal">×</button></div>
                <div class="modal-body">
                    <h6>Product: <span id="adj-product-name"></span></h6>
                    <p>Current system quantity: <strong id="adj-current-qty-display"></strong></p>
                    <hr>
                    <div class="form-group">
                        <label for="new-physical-count"><strong>New Physical Count</strong></label>
                        <input type="number" name="new_physical_count" id="new-physical-count" class="form-control form-control-lg" required min="0">
                    </div>
                    <div class="form-group"><label for="notes">Reason / Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="adjust_stock" class="btn btn-primary">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    $('.adjust-btn').click(function() {
        $('#adj-product-id').val($(this).data('product-id'));
        $('#adj-product-name').text($(this).data('product-name'));
        $('#adj-current-qty').val($(this).data('current-qty'));
        $('#adj-current-qty-display').text($(this).data('current-qty'));
        $('#new-physical-count').val($(this).data('current-qty')); // Pre-fill for convenience
    });
});
</script>
</body>
</html>