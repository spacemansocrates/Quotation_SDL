<?php
// create_quotation_step3_items.php
session_start(); // Ensure session is started
require_once 'config.php'; // Ensure this file correctly establishes a PDO connection
$pdo = getDBConnection();

// Ensure previous steps were completed
// We need a shop_id AND (either an existing customer_id OR an indication that it's a new customer)
if (
    !isset($_SESSION['quotation_data']['shop_id']) ||
    (
        !isset($_SESSION['quotation_data']['customer_id']) &&
        // Ensure 'customer_is_new' is explicitly checked for true, as it might not be set if no customer action was taken
        // or could be false if an existing customer was deselected for a new one that wasn't fully processed.
        // The key is that we must have *some* customer context.
        (!isset($_SESSION['quotation_data']['customer_is_new']) || $_SESSION['quotation_data']['customer_is_new'] !== true)
    )
) {
    // If 'customer_id' is not set, and 'customer_is_new' is not true, we are missing customer context.
    if (!isset($_SESSION['quotation_data']['customer_id']) && (!isset($_SESSION['quotation_data']['customer_is_new']) || $_SESSION['quotation_data']['customer_is_new'] !== true) ) {
        $_SESSION['error_message'] = "Customer information is missing. Please select or add a customer.";
    } else if (!isset($_SESSION['quotation_data']['shop_id'])) {
         $_SESSION['error_message'] = "Shop information is missing. Please select a shop.";
    }
    header('Location: create_quotation_step2_customer.php');
    exit;
}
$_SESSION['quotation_data']['current_step'] = 3;

$quotation_items = $_SESSION['quotation_data']['items'] ?? [];

// For product lookup
$products = [];
$search_product_term = isset($_GET['search_product']) ? trim($_GET['search_product']) : '';

if (!empty($search_product_term)) {
    try {
        $stmt = $pdo->prepare("SELECT id, sku, name, description, default_unit_price, default_unit_of_measurement 
                           FROM products 
                           WHERE name LIKE :name_term OR sku LIKE :sku_term");
        // Use the same placeholder value for both parameters
        $search_param = '%' . $search_product_term . '%';
        $stmt->execute([
            'name_term' => $search_param,
            'sku_term' => $search_param
        ]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
} else {
    // Load default products when no search term
    try {
        $stmt = $pdo->query("SELECT id, sku, name, description, default_unit_price, default_unit_of_measurement FROM products ORDER BY name ASC LIMIT 20");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Quotation - Step 3: Add Items</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        h1, h3 { color: #333; }
        form { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
        label { display: inline-block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"], textarea, select {
            width: calc(100% - 22px);
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 3px;
            box-sizing: border-box;
        }
        textarea { width: 95%; }
        select { width: auto; min-width: 200px; } /* Adjust select width */
        button, input[type="submit"] { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        button:hover, input[type="submit"]:hover { background-color: #0056b3; }
        button[disabled] { background-color: #ccc; cursor: not-allowed; }
        hr { margin-top: 20px; margin-bottom: 20px; border: 0; border-top: 1px solid #eee; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        #selected_product_name { margin-left: 10px; font-style: italic; color: #555; }
        .error-message { color: red; font-style: italic; margin-bottom: 10px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
    <script>
        // Fixed selectProduct function to properly update form fields
        function selectProduct(productId, productName, description, price, uom) {
            document.getElementById('product_id').value = productId;
            document.getElementById('item_description').value = description || ''; // Ensure empty string if null/undefined
            document.getElementById('rate_per_unit').value = price || '';
            document.getElementById('unit_of_measurement').value = uom || '';
            document.getElementById('selected_product_name').innerText = productId ? "Selected: " + productName : '';
            document.getElementById('quantity').focus(); // Focus quantity after selecting a product
        }
        
        // Add window.onload to ensure DOM is ready before any JavaScript executes
        window.onload = function() {
            var productDropdown = document.getElementById('product_select_dropdown');
            if (productDropdown) {
                productDropdown.addEventListener('change', function() {
                    var selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        selectProduct(
                            selectedOption.value,
                            selectedOption.getAttribute('data-name'),
                            selectedOption.getAttribute('data-description'),
                            selectedOption.getAttribute('data-price'),
                            selectedOption.getAttribute('data-uom')
                        );
                    } else {
                        // Clear fields if "-- Select Product --" is chosen
                        selectProduct('', '', '', '', '');
                    }
                });
            }
        };
    </script>
</head>
<body>
    <h1>Step 3: Add Quotation Items</h1>
    <p><a href="create_quotation_step2_customer.php?continue=1">« Back to Customer</a></p>

    <?php if (isset($_SESSION['error_message'])): ?>
        <p class="error-message"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
        <p style="color:green;"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
    <?php endif; ?>

    <!-- Product Search Form -->
    <form action="create_quotation_step3_items.php" method="GET">
        <label for="search_product_input">Search Product (by Name or SKU):</label>
        <input type="text" name="search_product" id="search_product_input" value="<?php echo htmlspecialchars($search_product_term); ?>">
        <button type="submit">Search</button>
    </form>

    <!-- Add Item Form -->
    <form action="process_quotation.php" method="POST">
        <input type="hidden" name="action" value="add_item">

        <?php if (!empty($products) || !empty($search_product_term)): // Show select if search was performed OR if products are loaded by default ?>
            <label for="product_select_dropdown">Select Product from Search:</label>
            <select id="product_select_dropdown">
                <option value="">-- Select Product --</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo htmlspecialchars($product['id']); ?>"
                            data-name="<?php echo htmlspecialchars($product['name']); ?>"
                            data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
                            data-price="<?php echo htmlspecialchars($product['default_unit_price'] ?? '0.00'); ?>"
                            data-uom="<?php echo htmlspecialchars($product['default_unit_of_measurement'] ?? ''); ?>">
                        <?php echo htmlspecialchars($product['name']); ?> (SKU: <?php echo htmlspecialchars($product['sku']); ?>) - Price: <?php echo htmlspecialchars(number_format($product['default_unit_price'] ?? 0, 2)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span id="selected_product_name"></span>
            <br>
        <?php elseif(!empty($search_product_term)): ?>
            <p>No products found matching "<?php echo htmlspecialchars($search_product_term); ?>". You can still add an item manually below.</p>
        <?php endif; ?>
        <br>

        <input type="hidden" name="item[product_id]" id="product_id">

        <label for="item_description">Item Description (will be pre-filled if product selected):</label><br>
        <textarea name="item[description]" id="item_description" rows="3" cols="50" required><?php echo htmlspecialchars($_SESSION['form_data']['item']['description'] ?? ''); unset($_SESSION['form_data']['item']['description']); ?></textarea><br>

        <label for="quantity">Quantity:</label>
        <input type="number" name="item[quantity]" id="quantity" value="<?php echo htmlspecialchars($_SESSION['form_data']['item']['quantity'] ?? '1'); unset($_SESSION['form_data']['item']['quantity']); ?>" step="0.01" min="0.01" required><br>

        <label for="unit_of_measurement">Unit of Measurement:</label>
        <input type="text" name="item[unit_of_measurement]" id="unit_of_measurement" value="<?php echo htmlspecialchars($_SESSION['form_data']['item']['unit_of_measurement'] ?? ''); unset($_SESSION['form_data']['item']['unit_of_measurement']); ?>"><br>

        <label for="rate_per_unit">Rate per Unit (Price):</label>
        <input type="number" name="item[rate_per_unit]" id="rate_per_unit" value="<?php echo htmlspecialchars($_SESSION['form_data']['item']['rate_per_unit'] ?? ''); unset($_SESSION['form_data']['item']['rate_per_unit']); ?>" step="0.01" min="0" required><br>

        <button type="submit">Add Item to Quotation</button>
    </form>

    <hr>
    <h3>Current Items in Quotation:</h3>
    <?php if (!empty($quotation_items)): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product ID</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>UoM</th>
                    <th>Rate</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $item_number = 0; $grand_total = 0; foreach ($quotation_items as $index => $item): $item_number++;
                $line_total = ($item['quantity'] ?? 0) * ($item['rate_per_unit'] ?? 0);
                $grand_total += $line_total;
                ?>
                <tr>
                    <td><?php echo $item_number; ?></td>
                    <td><?php echo htmlspecialchars($item['product_id'] ?? 'Custom'); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($item['description'])); ?></td>
                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($item['unit_of_measurement']); ?></td>
                    <td style="text-align: right;"><?php echo htmlspecialchars(number_format($item['rate_per_unit'], 2)); ?></td>
                    <td style="text-align: right;"><?php echo htmlspecialchars(number_format($line_total, 2)); ?></td>
                    <td>
                        <a href="process_quotation.php?action=remove_item&index=<?php echo $index; ?>"
                           onclick="return confirm('Are you sure you want to remove item #<?php echo $item_number; ?>: <?php echo htmlspecialchars(addslashes($item['description'])); ?>?');">Remove</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align: right; font-weight: bold;">Grand Total:</td>
                    <td style="text-align: right; font-weight: bold;"><?php echo htmlspecialchars(number_format($grand_total, 2)); ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <p>No items added yet.</p>
    <?php endif; ?>
    <br>

    <form action="process_quotation.php" method="POST" style="display:inline-block; margin-right: 10px;">
        <input type="hidden" name="action" value="go_to_optional_fields">
        <button type="submit" <?php echo empty($quotation_items) ? 'disabled title="Add at least one item to proceed"' : 'title="Proceed to optional fields"'; ?>>Next: Optional Fields »</button>
    </form>
    <form action="process_quotation.php" method="POST" style="display:inline-block;">
        <input type="hidden" name="action" value="preview_quotation">
        <button type="submit" <?php echo empty($quotation_items) ? 'disabled title="Add at least one item to preview"' : 'title="Preview the quotation"'; ?>>Preview Quotation</button>
    </form>

</body>
</html>