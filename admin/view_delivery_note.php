<?php
session_start();
// ACCESS CHECK
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

$deliveryNoteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($deliveryNoteId === 0) {
    die("Invalid Delivery Note ID.");
}

require_once __DIR__ . '/../includes/db_connect.php';

$pdo = getDatabaseConnection();

try {
    // Fetch delivery note, linked invoice, customer, and shop details in one go
    $query = "
        SELECT 
            dn.id, dn.delivery_note_number, dn.delivery_date,
            i.invoice_number, i.customer_name_override, i.customer_address_override,
            c.name as customer_name, c.address_line1, c.address_line2, c.city_location, c.phone as customer_phone, c.email as customer_email,
            s.name as shop_name, s.address_line1 as shop_address1, s.address_line2 as shop_address2, s.phone as shop_phone, s.email as shop_email, s.logo_path, s.tpin_no
        FROM delivery_notes dn
        JOIN invoices i ON dn.invoice_id = i.id
        JOIN customers c ON i.customer_id = c.id
        JOIN shops s ON i.shop_id = s.id
        WHERE dn.id = :deliveryNoteId
    ";
    $stmt = DatabaseConfig::executeQuery($pdo, $query, [':deliveryNoteId' => $deliveryNoteId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Delivery Note not found.");
    }

    // Fetch items for the delivery note
    $itemsQuery = "SELECT * FROM delivery_note_items WHERE delivery_note_id = :deliveryNoteId ORDER BY id ASC";
    $itemsStmt = DatabaseConfig::executeQuery($pdo, $itemsQuery, [':deliveryNoteId' => $deliveryNoteId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total quantity
    $totalQty = 0;
    foreach ($items as $item) {
        $totalQty += $item['quantity'];
    }

} catch (PDOException $e) {
    error_log("Error in view_delivery_note.php: " . $e->getMessage());
    die("A database error occurred.");
} finally {
    DatabaseConfig::closeConnection($pdo);
}

// Determine customer details to display (use override if available)
$customerDisplayName = !empty($data['customer_name_override']) ? $data['customer_name_override'] : $data['customer_name'];

// Safely handle potentially null address parts
if (!empty($data['customer_address_override'])) {
    $customerDisplayAddress = nl2br(htmlspecialchars($data['customer_address_override']));
} else {
    $addressParts = [];
    if (!empty($data['address_line1'])) $addressParts[] = htmlspecialchars($data['address_line1']);
    if (!empty($data['address_line2'])) $addressParts[] = htmlspecialchars($data['address_line2']);
    if (!empty($data['city_location'])) $addressParts[] = htmlspecialchars($data['city_location']);
    $customerDisplayAddress = implode('<br>', $addressParts);
}

// Safely handle potentially null email and phone
$customerDisplayEmail = htmlspecialchars($data['customer_email'] ?? '');
$customerDisplayPhone = htmlspecialchars($data['customer_phone'] ?? '');

?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Delivery Note: <?php echo htmlspecialchars($data['delivery_note_number']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; color: #333; }
        .header { text-align: left; margin-bottom: 10px; }
        .header img { max-height: 80px; }
        .contact-line { margin-top: 5px; font-size: 12px; }
        hr { margin: 20px 0; border: 0; border-top: 1px solid #ccc; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #000; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .summary-row td { font-weight: bold; text-align: right; }
        .summary-row td:first-child { text-align: left; }
        .signature { margin-top: 40px; }
        .signature img { height: 60px; }
        .print-button-container { position: fixed; top: 10px; right: 10px; z-index: 1000; background-color: #f8f9fa; padding: 10px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .btn { padding: 8px 15px; margin: 5px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-secondary { background-color: #6c757d; }
        .delivery-signatures { display: flex; justify-content: space-between; margin-top: 60px; }
        .delivery-signature-section { width: 45%; text-align: center; }
        .signature-line { border-bottom: 1px solid #000; height: 60px; margin: 40px 0 10px 0; }
        .info-block p { margin: 4px 0; }
        @media print { .print-button-container { display: none; } }
    </style>
</head>
<body>
    <div class="print-button-container">
        <button onclick="window.print()" class="btn">Print</button>
        <a href="admin_manage_invoices.php" class="btn btn-secondary">Back to Invoices</a>
    </div>

<div class="header">
    <img src="images/logo.png" alt="Company Logo">
    <div class="contact-line">
    <?php echo htmlspecialchars($data['shop_address1'] ?? '') . ' ' . htmlspecialchars($data['shop_address2'] ?? ''); ?> — CELL NO: <?php echo htmlspecialchars($data['shop_phone'] ?? ''); ?> — Email: <?php echo htmlspecialchars($data['shop_email'] ?? ''); ?>
</div>
    <hr>
    <h2>Delivery Note</h2>
    <div class="info-block">
        <p><strong>Delivery Note No:</strong> <?php echo htmlspecialchars($data['delivery_note_number']); ?></p>
        <p><strong>Invoice No:</strong> <?php echo htmlspecialchars($data['invoice_number']); ?></p>
        <p><strong>TPIN No:</strong> <?php echo htmlspecialchars($data['tpin_no']); ?></p>
        <p><strong>Date:</strong> <?php echo date("d/m/Y", strtotime($data['delivery_date'])); ?></p>
        <br>
        <p><strong>Customer:</strong><br>
        <strong><?php echo htmlspecialchars($customerDisplayName); ?></strong><br>
        <?php echo $customerDisplayAddress; ?><br>
        Email: <?php echo $customerDisplayEmail; ?><br>
        Phone: <?php echo $customerDisplayPhone; ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:10%;">Item No.</th>
                <th>Description</th>
                <th style="width:10%;">Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php $itemNumber = 1; ?>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo $itemNumber++; ?></td>
                <td><?php echo htmlspecialchars($item['description']); ?></td>
                <td><?php echo htmlspecialchars(number_format($item['quantity'])); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="summary-row">
                <td colspan="2">Total Qty</td>
                <td><?php echo htmlspecialchars(number_format($totalQty)); ?></td>
            </tr>
        </tbody>
    </table>

<!-- <div class="signature">
    <p>For <?php //echo htmlspecialchars($data['shop_name'] ?? 'the Company'); ?></p>
    <img src="images/signature.png" alt="Authorized Signature" style="height: 60px;">
</div> -->

    <div class="delivery-signatures">
        <div class="delivery-signature-section">
            <p><strong>DELIVERED BY:</strong></p>
            <div class="signature-line"></div>
            <p>NAME & SIGNATURE</p>
        </div>
        <div class="delivery-signature-section">
            <p><strong>RECEIVED BY:</strong></p>
            <div class="signature-line"></div>
            <p>NAME, SIGNATURE & STAMP</p>
        </div>
    </div>
</body>
</html>