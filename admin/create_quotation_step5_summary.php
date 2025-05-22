<?php
// create_quotation_step5_summary.php
require_once 'config.php'; // Includes session_start() and DB connection
$pdo = getDBConnection();

// Ensure previous step was completed (optional_fields might be empty if user skipped and went back)
if (empty($_SESSION['quotation_data']['items'])) {
    header('Location: create_quotation_step3_items.php');
    exit;
}
$_SESSION['quotation_data']['current_step'] = 5;

$data = $_SESSION['quotation_data'];

// --- Fetch Shop Details ---
$shop_name = "N/A";
if (!empty($data['shop_id'])) {
    $stmt = $pdo->prepare("SELECT name FROM shops WHERE id = :id");
    $stmt->execute(['id' => $data['shop_id']]);
    $shop = $stmt->fetch();
    if ($shop) $shop_name = $shop['name'];
}

// --- Fetch/Prepare Customer Details ---
$customer_details = "New Customer (details below)";
if ($data['customer_id'] && !$data['customer_is_new']) {
    $stmt = $pdo->prepare("SELECT name, customer_code, address_line1, address_line2, city_location, phone, email, tpin_no FROM customers WHERE id = :id");
    $stmt->execute(['id' => $data['customer_id']]);
    $customer = $stmt->fetch();
    if ($customer) {
        $customer_details = "<strong>Name:</strong> " . htmlspecialchars($customer['name']) . " (".htmlspecialchars($customer['customer_code']).")<br>";
        $customer_details .= "<strong>Address:</strong> " . htmlspecialchars($customer['address_line1']) . ($customer['address_line2'] ? ', '.htmlspecialchars($customer['address_line2']) : '') . ", " . htmlspecialchars($customer['city_location']) ."<br>";
        $customer_details .= "<strong>Phone:</strong> " . htmlspecialchars($customer['phone']) . "<br>";
        $customer_details .= "<strong>Email:</strong> " . htmlspecialchars($customer['email']) . "<br>";
        $customer_details .= "<strong>TPIN:</strong> " . htmlspecialchars($customer['tpin_no']) . "<br>";
    }
} elseif ($data['customer_is_new'] && !empty($data['new_customer_data'])) {
    $nc = $data['new_customer_data'];
    $customer_details = "<strong>Name:</strong> " . htmlspecialchars($nc['name']) . " (".htmlspecialchars($nc['customer_code']).")<br>";
    $customer_details .= "<strong>Address:</strong> " . htmlspecialchars($nc['address_line1']) . ($nc['address_line2'] ? ', '.htmlspecialchars($nc['address_line2']) : '') . ", " . htmlspecialchars($nc['city_location']) ."<br>";
    $customer_details .= "<strong>Phone:</strong> " . htmlspecialchars($nc['phone']) . "<br>";
    $customer_details .= "<strong>Email:</strong> " . htmlspecialchars($nc['email']) . "<br>";
    $customer_details .= "<strong>TPIN:</strong> " . htmlspecialchars($nc['tpin_no']) . "<br>";
}

// --- Calculations ---
$gross_total_amount = 0;
foreach ($data['items'] as $item) {
    $gross_total_amount += $item['quantity'] * $item['rate_per_unit'];
}

$ppda_levy_percentage = DEFAULT_PPDA_LEVY_PERCENTAGE;
$apply_ppda_levy = !empty($data['optional_fields']['apply_ppda_levy']);
$ppda_levy_amount = 0;
if ($apply_ppda_levy) {
    $ppda_levy_amount = $gross_total_amount * ($ppda_levy_percentage / 100);
}

$amount_before_vat = $gross_total_amount + $ppda_levy_amount;

$vat_percentage = DEFAULT_VAT_PERCENTAGE; // Could come from shop settings or company settings
$vat_amount = $amount_before_vat * ($vat_percentage / 100);

$total_net_amount = $amount_before_vat + $vat_amount;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Quotation - Step 5: Summary</title>
    <style> /* Basic styling for summary */
        .summary-section { margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; }
        .summary-section h3 { margin-top: 0; }
    </style>
</head>
<body>
    <h1>Step 5: Quotation Summary & Generation</h1>
    <p><a href="create_quotation_step4_optional.php?continue=1">« Back to Optional Fields</a></p>

    <div class="summary-section">
        <h3>Shop</h3>
        <p><?php echo htmlspecialchars($shop_name); ?></p>
    </div>

    <div class="summary-section">
        <h3>Customer</h3>
        <?php echo $customer_details; // Already HTML formatted and escaped ?>
    </div>

    <div class="summary-section">
        <h3>Quotation Items</h3>
        <?php if (!empty($data['items'])): ?>
        <table border="1" cellpadding="5" cellspacing="0" style="width:100%;">
            <thead>
                <tr>
                    <th>#</th><th>Description</th><th>Qty</th><th>UoM</th><th>Rate</th><th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $item_no = 0; foreach ($data['items'] as $item): $item_no++; ?>
                <tr>
                    <td><?php echo $item_no; ?></td>
                    <td><?php echo nl2br(htmlspecialchars($item['description'])); ?></td>
                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($item['unit_of_measurement']); ?></td>
                    <td style="text-align:right;"><?php echo number_format($item['rate_per_unit'], 2); ?></td>
                    <td style="text-align:right;"><?php echo number_format($item['quantity'] * $item['rate_per_unit'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No items in quotation.</p>
        <?php endif; ?>
    </div>

    <div class="summary-section">
        <h3>Optional Fields</h3>
        <p><strong>General Note:</strong> <?php echo nl2br(htmlspecialchars($data['optional_fields']['notes_general'] ?? 'N/A')); ?></p>
        <p><strong>Delivery Period:</strong> <?php echo htmlspecialchars($data['optional_fields']['delivery_period'] ?? 'N/A'); ?></p>
        <p><strong>Payment Terms:</strong> <?php echo htmlspecialchars($data['optional_fields']['payment_terms'] ?? 'N/A'); ?></p>
        <p><strong>Quotation Validity:</strong> <?php echo htmlspecialchars($data['optional_fields']['quotation_validity_days'] ?? 'N/A'); ?> Days</p>
        <?php if (!empty($data['optional_fields']['mra_wht_note'])): ?>
        <p><strong>MRA WHT Note:</strong><br><?php echo nl2br(htmlspecialchars($data['optional_fields']['mra_wht_note'])); ?></p>
        <?php endif; ?>
    </div>

    <div class="summary-section">
        <h3>Totals</h3>
        <table cellpadding="5">
            <tr><td>Gross Total:</td><td style="text-align:right;"><?php echo number_format($gross_total_amount, 2); ?></td></tr>
            <?php if ($apply_ppda_levy): ?>
            <tr><td>PPDA Levy (<?php echo $ppda_levy_percentage; ?>%):</td><td style="text-align:right;"><?php echo number_format($ppda_levy_amount, 2); ?></td></tr>
            <?php endif; ?>
            <tr><td>Amount Before VAT:</td><td style="text-align:right;"><?php echo number_format($amount_before_vat, 2); ?></td></tr>
            <tr><td>VAT (<?php echo $vat_percentage; ?>%):</td><td style="text-align:right;"><?php echo number_format($vat_amount, 2); ?></td></tr>
            <tr><td><strong>Total Net Amount:</strong></td><td style="text-align:right;"><strong><?php echo number_format($total_net_amount, 2); ?></strong></td></tr>
        </table>
    </div>

    <form action="process_quotation.php" method="POST">
        <input type="hidden" name="action" value="generate_quotation">
        <button type="submit" onclick="return confirm('Are you sure you want to generate this quotation?');">Generate Quotation</button>
    </form>
    <br>
    <p><a href="create_quotation_step1_shop.php">Start New Quotation (Discards Current)</a></p>

</body>
</html>