<?php
require_once(__DIR__ . '/includes/db_connect.php');
$pdo = getDatabaseConnection(); // << NEW: Get connection

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die("Invalid Quotation ID.");
}
$quotation_id = (int)$_GET['id'];

// Fetch Quotation Details
$sql_quote = "SELECT q.*, s.name as shop_name, s.address as shop_address, s.logo_path as shop_logo, s.tpin as shop_tpin, s.contact_details as shop_contact,
              c.name as customer_db_name, c.address as customer_db_address, c.tpin as customer_db_tpin
              FROM quotations q
              JOIN shops s ON q.shop_id = s.id
              LEFT JOIN customers c ON q.customer_id = c.id
              WHERE q.id = ?";
// $stmt = $pdo->prepare($sql_quote); // Old
// $stmt->execute([$quotation_id]); // Old
$stmt = DatabaseConfig::executeQuery($pdo, $sql_quote, [$quotation_id]); // << MODIFIED
$quote = $stmt->fetch();

if (!$quote) {
    die("Quotation not found.");
}

// ... (customer details logic) ...

// Fetch Quotation Items
$sql_items = "
    SELECT qi.*, p.name as product_name, p.default_image_path as product_default_image,
           uom.name as unit_name, uom.abbreviation as unit_abbreviation
    FROM quotation_items qi
    LEFT JOIN products p ON qi.product_id = p.id
    LEFT JOIN units_of_measurement uom ON qi.unit_of_measurement_id = uom.id
    WHERE qi.quotation_id = ?
    ORDER BY qi.id ASC
";
// $items_stmt = $pdo->prepare($sql_items); // Old
// $items_stmt->execute([$quotation_id]); // Old
$items_stmt = DatabaseConfig::executeQuery($pdo, $sql_items, [$quotation_id]); // << MODIFIED
$items = $items_stmt->fetchAll();


// Fetch company settings for signature (example)
$default_signature_path = null;
try {
    // $sig_stmt = $pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'default_signature_path'"); // Old
    $sig_stmt = DatabaseConfig::executeQuery($pdo, "SELECT setting_value FROM company_settings WHERE setting_key = ?", ['default_signature_path']); // << MODIFIED
    $sig_path = $sig_stmt->fetchColumn();
    if ($sig_path) {
        $default_signature_path = $sig_path;
    }
} catch (PDOException $e) {
    // Your class logs. Maybe display placeholder or note.
    error_log("Error fetching signature for print: " . $e->getMessage());
}

// ... rest of the HTML ...
?>
<!-- At the very end of the PHP script part, or before any major HTML output -->
<?php DatabaseConfig::closeConnection($pdo); // << NEW: Close connection ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation <?php echo htmlspecialchars($quote['quotation_number']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Additional styles specific to print preview if needed */
        #print-button-on-preview { margin: 20px; }
    </style>
</head>
<body>
    <button id="print-button-on-preview" onclick="window.print();">Print Quotation</button>

    <div class="print-container">
        <div class="print-header">
            <div class="shop-info">
                <?php if ($quote['shop_logo']): ?>
                    <img src="<?php echo htmlspecialchars($quote['shop_logo']); ?>" alt="<?php echo htmlspecialchars($quote['shop_name']); ?> Logo">
                <?php endif; ?>
                <h3><?php echo htmlspecialchars($quote['shop_name']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($quote['shop_address'])); ?></p>
                <?php if ($quote['shop_contact']): ?>
                     <p>Contact: <?php echo htmlspecialchars($quote['shop_contact']); ?></p>
                <?php endif; ?>
            </div>
            <div class="quote-title">
                <h2>QUOTATION</h2>
                <p><strong>Quotation No.:</strong> <?php echo htmlspecialchars($quote['quotation_number']); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars(date("d M Y", strtotime($quote['quotation_date']))); ?></p>
                <p><strong>TPIN:</strong> <?php echo htmlspecialchars($quote['company_tpin_override'] ?: $quote['shop_tpin']); ?></p>
            </div>
        </div>

        <div class="print-details">
            <div class="customer-info">
                <strong>To:</strong><br>
                <strong><?php echo htmlspecialchars($customer_name); ?></strong><br>
                <?php echo nl2br(htmlspecialchars($customer_address)); ?>
                <?php if ($customer_tpin): ?>
                    <br>TPIN: <?php echo htmlspecialchars($customer_tpin); ?>
                <?php endif; ?>
            </div>
            <div class="quotation-meta">
                <!-- Can add other meta info here if needed -->
            </div>
        </div>

        <?php if ($quote['general_note']): ?>
        <div class="print-notes">
            <p><em><?php echo nl2br(htmlspecialchars($quote['general_note'])); ?></em></p>
        </div>
        <?php endif; ?>


        <table class="print-items-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Rate</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $item_no = 1; foreach ($items as $item): ?>
                <tr>
                    <td><?php echo $item_no++; ?></td>
                    <td>
                        <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                        <?php
                            $image_to_display = $item['image_path_override'] ?: $item['product_default_image'];
                            if ($image_to_display):
                        ?>
                            <br><img src="<?php echo htmlspecialchars($image_to_display); ?>" alt="Item Image" class="item-thumbnail">
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($item['custom_unit_name'] ?: ($item['unit_abbreviation'] ?: $item['unit_name'])); ?></td>
                    <td><?php echo htmlspecialchars(number_format($item['rate_per_unit'], 2)); ?></td>
                    <td><?php echo htmlspecialchars(number_format($item['total_amount'], 2)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table class="print-summary">
            <tr>
                <td>Gross Total Amount:</td>
                <td><?php echo htmlspecialchars(number_format($quote['gross_total'], 2)); ?></td>
            </tr>
            <?php if ($quote['ppda_levy_applied'] == 1 && $quote['ppda_levy_amount'] > 0): ?>
            <tr>
                <td>PPDA Levy (1%):</td>
                <td><?php echo htmlspecialchars(number_format($quote['ppda_levy_amount'], 2)); ?></td>
            </tr>
            <tr>
                <td>Subtotal before VAT:</td>
                <td><?php echo htmlspecialchars(number_format($quote['subtotal_before_vat'], 2)); ?></td>
            </tr>
            <?php endif; ?>
             <?php if ($quote['vat_percentage'] > 0 && $quote['vat_amount'] > 0) : ?>
            <tr>
                <td>VAT (<?php echo htmlspecialchars($quote['vat_percentage']); ?>%):</td>
                <td><?php echo htmlspecialchars(number_format($quote['vat_amount'], 2)); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><strong>Total Net Amount:</strong></td>
                <td><strong><?php echo htmlspecialchars(number_format($quote['total_net_amount'], 2)); ?></strong></td>
            </tr>
        </table>

        <div class="print-terms">
            <?php if ($quote['delivery_period']): ?>
                <p><strong>Delivery Period:</strong> <?php echo htmlspecialchars($quote['delivery_period']); ?></p>
            <?php endif; ?>
            <?php if ($quote['payment_terms']): ?>
                <p><strong>Payment Terms:</strong> <?php echo htmlspecialchars($quote['payment_terms']); ?></p>
            <?php endif; ?>
            <?php if ($quote['quotation_validity_days']): ?>
                <p><strong>Quotation Validity:</strong> <?php echo htmlspecialchars($quote['quotation_validity_days']); ?> Days</p>
            <?php endif; ?>
        </div>

        <?php if ($quote['mra_wht_note_included'] == 1 && $quote['mra_wht_note_content']): ?>
        <div class="print-mra">
            <hr>
            <p><em><?php echo nl2br(htmlspecialchars($quote['mra_wht_note_content'])); ?></em></p>
        </div>
        <?php endif; ?>

        <div class="print-signature">
            <p>For <?php echo htmlspecialchars($quote['shop_name']); ?></p>
            <?php if ($default_signature_path): ?>
                 <img src="<?php echo htmlspecialchars($default_signature_path); ?>" alt="Signature"><br>
            <?php else: ?>
                <br><br><br>
                <p>_________________________</p>
            <?php endif; ?>
            <p>Authorized Signature</p>
        </div>

    </div>
</body>
</html>