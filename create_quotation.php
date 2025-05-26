<?php
require_once(__DIR__ . '/includes/db_connect.php');
require_once(__DIR__ . '/includes/nav.php');
// Rest of your code...

$pdo = getDatabaseConnection(); // << NEW: Get connection

// Fetch initial data for dropdowns
// Option 1: Keep using $pdo->query for simple, no-parameter queries
// $shops_stmt = $pdo->query("SELECT id, name, shop_code FROM shops ORDER BY name");
// Option 2: Use DatabaseConfig::executeQuery for consistency
$shops_stmt = DatabaseConfig::executeQuery($pdo, "SELECT id, name, shop_code FROM shops ORDER BY name");
$shops = $shops_stmt->fetchAll();

// $customers_stmt = $pdo->query("SELECT id, name, customer_code FROM customers ORDER BY name");
$customers_stmt = DatabaseConfig::executeQuery($pdo, "SELECT id, name, customer_code FROM customers ORDER BY name");
$customers = $customers_stmt->fetchAll();

// $units_stmt = $pdo->query("SELECT id, name, abbreviation FROM units_of_measurement ORDER BY name");
$units_stmt = DatabaseConfig::executeQuery($pdo, "SELECT id, name FROM units_of_measurement ORDER BY name");
$units = $units_stmt->fetchAll();

// Fetch company settings (example)
$default_vat_percentage = 16.50;
$default_mra_note = "This is the default MRA Withholding Tax Exemption Note. Please edit as needed.";
$company_tpin = "DEFAULT_TPIN";

try {
    // $stmt = $pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'default_vat_percentage'"); // Old
    $stmt = DatabaseConfig::executeQuery($pdo, "SELECT setting_value FROM company_settings WHERE setting_key = ?", ['default_vat_percentage']); // << MODIFIED
    $vat_setting = $stmt->fetchColumn();
    if ($vat_setting !== false) {
        $default_vat_percentage = floatval($vat_setting);
    }

    // $stmt = $pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'default_mra_wht_note'"); // Old
    $stmt = DatabaseConfig::executeQuery($pdo, "SELECT setting_value FROM company_settings WHERE setting_key = ?", ['default_mra_wht_note']); // << MODIFIED
    $mra_setting = $stmt->fetchColumn();
    if ($mra_setting !== false) {
        $default_mra_note = $mra_setting;
    }
    
    // $stmt = $pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'company_tpin'"); // Old
    $stmt = DatabaseConfig::executeQuery($pdo, "SELECT setting_value FROM company_settings WHERE setting_key = ?", ['company_tpin']); // << MODIFIED
    $company_tpin_setting = $stmt->fetchColumn();
    if ($company_tpin_setting !== false) {
        $company_tpin = $company_tpin_setting;
    }

} catch (PDOException $e) {
    // Your new db_connect.php already logs. You might want to display a user-friendly message here or re-throw.
    error_log("Error fetching company settings on page: " . $e->getMessage()); // Or handle more gracefully
}

// ... rest of the HTML and JS code ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create/Edit Quotation</title>
    <link rel="stylesheet" href="css/admin.css">
    <!-- Consider jQuery for easier AJAX and DOM, or a modern alternative -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Create/Edit Quotation</h1>
        <form id="quotation-form" enctype="multipart/form-data">

            <div class="form-section" id="header-preview">
                <h3>Preview / Shop Details</h3>
                <img src="" alt="Shop Logo" id="shop-logo-preview" class="shop-logo-preview" style="display:none;">
                <p id="shop-address-preview"></p>
                <p>TPIN: <span id="shop-tpin-preview"></span></p>
            </div>

            <div class="form-section">
                <h3>Header Section</h3>
                <div>
                    <label for="shop_id">Shop Selection:</label>
                    <select id="shop_id" name="shop_id" required>
                        <option value="">-- Select Shop --</option>
                        <?php foreach ($shops as $shop): ?>
                            <option value="<?php echo $shop['id']; ?>" data-shop-code="<?php echo htmlspecialchars($shop['shop_code']); ?>">
                                <?php echo htmlspecialchars($shop['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="customer_id">Customer Selection:</label>
                    <select id="customer_id" name="customer_id">
                        <option value="">-- Select Existing Customer --</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" data-customer-code="<?php echo htmlspecialchars($customer['customer_code']); ?>">
                                <?php echo htmlspecialchars($customer['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="checkbox" id="enter_new_customer_toggle"> <label for="enter_new_customer_toggle" style="display:inline;">Enter New Customer</label>
                </div>
                <div id="new-customer-fields" class="hidden">
                    <h4>New Customer Details</h4>
                    <label for="customer_name_override">Customer Name:</label>
                    <input type="text" id="customer_name_override" name="customer_name_override">
                    <label for="customer_address_override">Customer Address:</label>
                    <textarea id="customer_address_override" name="customer_address_override"></textarea>
                    <label for="new_customer_tpin">Customer TPIN (New):</label>
                    <input type="text" id="new_customer_tpin" name="new_customer_tpin">
                     <label for="new_customer_code">Customer Code (New - Optional, leave blank to auto-generate if saving new):</label>
                    <input type="text" id="new_customer_code" name="new_customer_code">
                    <input type="checkbox" id="save_new_customer_checkbox" name="save_new_customer"> <label for="save_new_customer_checkbox" style="display:inline;">Save as new customer in database</label>
                </div>
                 <div id="existing-customer-details" class="hidden">
                    <h4>Selected Customer Details</h4>
                    <p>Name: <span id="selected_customer_name"></span></p>
                    <p>Address: <span id="selected_customer_address"></span></p>
                    <p>TPIN: <span id="selected_customer_tpin"></span></p>
                    <p>Code: <span id="selected_customer_code_display"></span></p>
                </div>


                <div>
                    <label for="quotation_date">Quotation Date:</label>
                    <input type="date" id="quotation_date" name="quotation_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div>
                    <label for="quotation_number">Quotation Number:</label>
                    <input type="text" id="quotation_number" name="quotation_number_display" value="SDL/SHOPCODE/CUSTCODE-###" readonly>
                    <input type="hidden" id="customer_code_hidden" name="customer_code_hidden">
                    <input type="hidden" id="shop_code_hidden" name="shop_code_hidden">
                </div>
                <div>
                    <label for="company_tpin">Company TPIN (from Shop/Settings):</label>
                    <input type="text" id="company_tpin" name="company_tpin" value="<?php echo htmlspecialchars($company_tpin); ?>">
                </div>
            </div>

            <div class="form-section">
                <h3>Items Table</h3>
                <table id="items-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Product/Description</th>
                            <th>Image</th>
                            <th>Qty</th>
                            <th>Unit</th>
                            <th>Rate/Unit</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody">
                        <!-- Item rows will be added here by JS -->
                    </tbody>
                </table>
                <button type="button" id="add-item-btn">Add Item</button>
            </div>

            <div class="form-section">
                <h3>Summary</h3>
                <table class="summary-table">
                    <tr>
                        <td>Gross Total Amount:</td>
                        <td><span id="gross_total_amount">0.00</span></td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <input type="checkbox" id="apply_ppda_levy" name="apply_ppda_levy">
                            <label for="apply_ppda_levy" style="display:inline;">Apply PPDA Levy (1%)</label>
                        </td>
                    </tr>
                    <tr>
                        <td>PPDA Levy Amount:</td>
                        <td><span id="ppda_levy_amount">0.00</span></td>
                    </tr>
                    <tr>
                        <td>Subtotal before VAT:</td>
                        <td><span id="subtotal_before_vat">0.00</span></td>
                    </tr>
                    <tr>
                        <td>VAT (%):</td>
                        <td>
                            <input type="number" step="0.01" id="vat_percentage" name="vat_percentage" value="<?php echo $default_vat_percentage; ?>" style="width:80px; display:inline;">
                            <span id="vat_amount">0.00</span>
                        </td>
                    </tr>
                    <tr>
                        <td>Total Net Amount:</td>
                        <td><span id="total_net_amount">0.00</span></td>
                    </tr>
                </table>
            </div>

            <div class="form-section">
                <h3>Optional Fields</h3>
                <div>
                    <label for="general_note">General Note:</label>
                    <textarea id="general_note" name="general_note" rows="3"></textarea>
                </div>
                <div>
                    <label for="delivery_period">Delivery Period:</label>
                    <input type="text" id="delivery_period" name="delivery_period">
                </div>
                <div>
                    <label for="payment_terms">Payment Terms:</label>
                    <input type="text" id="payment_terms" name="payment_terms">
                </div>
                <div>
                    <label for="quotation_validity_days">Quotation Validity (days):</label>
                    <input type="number" id="quotation_validity_days" name="quotation_validity_days" value="30">
                </div>
                <div>
                    <input type="checkbox" id="include_mra_wht_note" name="include_mra_wht_note">
                    <label for="include_mra_wht_note" style="display:inline;">Include MRA WHT Exemption Note</label>
                    <div id="mra_wht_note_div" class="hidden">
                        <label for="mra_wht_note_content">MRA Note Content:</label>
                        <textarea id="mra_wht_note_content" name="mra_wht_note_content" rows="3"><?php echo htmlspecialchars($default_mra_note); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="button" id="save-draft-btn">Save Draft</button>
                <button type="button" id="generate-quotation-btn">Generate Quotation</button>
            </div>
        </form>
    </div>

    <script>
        // Pass PHP variables to JS
        const unitsData = <?php echo json_encode($units); ?>;
        const defaultMraNote = <?php echo json_encode($default_mra_note); ?>;
    </script>
    <script src="js/quotation.js"></script>
</body>
</html>