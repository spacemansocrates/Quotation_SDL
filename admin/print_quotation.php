<?php
// print_quotation.php
// --- (Same data fetching logic as view_quotation.php: Get ID, connect to DB, fetch quotation, items, customer, shop) ---

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("No quotation ID specified.");
}
$quotation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$quotation_id) {
    die("Invalid quotation ID.");
}

// Database connection and queries to fetch quotation data
try {
    $conn = new PDO("mysql:host=localhost;dbname=supplies", "root", ""); // Replace with your actual connection
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch Quotation Details
    $sql_quotation = "SELECT q.*, 
                      c.name AS customer_name, c.customer_code, 
                      c.address_line1 AS customer_address_line1, c.email AS customer_email, c.phone AS customer_phone,
                      s.name AS shop_name, s.shop_code, 
                      u.username AS created_by_username
                      FROM quotations q
                      LEFT JOIN customers c ON q.customer_id = c.id
                      LEFT JOIN shops s ON q.shop_id = s.id
                      LEFT JOIN users u ON q.created_by_user_id = u.id
                      WHERE q.id = :quotation_id";
    $stmt_quotation = $conn->prepare($sql_quotation);
    $stmt_quotation->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
    $stmt_quotation->execute();
    $quotation = $stmt_quotation->fetch(PDO::FETCH_ASSOC);

    if (!$quotation) { die("Quotation not found."); }

    // Fetch Quotation Items
    $sql_items = "SELECT qi.*, 
                  p.name as product_name, 
                  p.sku as product_sku, 
                  uom.name as uom_name
                  FROM quotation_items qi
                  LEFT JOIN products p ON qi.product_id = p.id
                  LEFT JOIN units_of_measurement uom ON qi.unit_of_measurement = uom.name
                  WHERE qi.quotation_id = :quotation_id 
                  ORDER BY qi.item_number ASC";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
    $stmt_items->execute();
    $quotation_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Company Information - you may need to create this table
    $sql_company = "SELECT * FROM company_settings WHERE id = 1 LIMIT 1";
    $stmt_company = $conn->prepare($sql_company);
    $stmt_company->execute();
    $company_info = $stmt_company->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) { die("Database error: " . $e->getMessage()); }
$conn = null;

// Company information - replace with database values or define constants
$company_name = $company_info['company_name'] ?? 'Supplies Direct Limited';
$company_address = $company_info['address_line1'] ?? 'P.O.BOX NO.5206, LIMBE, MALAWI';
$company_phone = $company_info['phone'] ?? '0991168991 / 0997398298';
$company_email = $company_info['email'] ?? 'info@suppliesdirectmw.com';
$company_tpin = $company_info['tpin'] ?? '70030009';
$company_logo = $company_info['logo_path'] ?? 'images/logo.png';
$company_signature = $company_info['signature_path'] ?? 'images/signature.png';

// Format display values
$display_customer_name = $quotation['customer_name_override'] ?? $quotation['customer_name'];
$display_customer_address = $quotation['customer_address_override'] ?? $quotation['customer_address_line1'];
$formatted_date = date('d/m/Y', strtotime($quotation['quotation_date']));

// Calculate VAT amount if not already provided
$vat_amount = $quotation['vat_amount'] ?? ($quotation['gross_total_amount'] * ($quotation['vat_percentage'] / 100));
$total_net_amount = $quotation['total_net_amount'] ?? ($quotation['gross_total_amount'] + $vat_amount);

?>
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <title>Quotation: <?php echo htmlspecialchars($quotation['quotation_number']); ?></title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 40px;
    }
    .header {
      text-align: left;
      margin-bottom: 10px;
    }
    .header img {
      max-height: 100px;
    }
    .contact-line {
      margin-top: 5px;
      font-size: 14px;
    }
    hr {
      margin: 20px 0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    table, th, td {
      border: 1px solid #000;
    }
    th, td {
      padding: 8px;
      text-align: left;
    }
    .summary-row td {
      font-weight: bold;
      text-align: right;
    }
    .summary-row td:first-child {
      text-align: left;
    }
    .signature {
      margin-top: 40px;
    }
    .signature img {
      height: 60px;
    }
    .print-button-container {
      position: fixed;
      top: 10px;
      right: 10px;
      z-index: 1000;
    }
    .btn {
      padding: 8px 15px;
      margin: 5px;
      background-color: #4CAF50;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
    }
    .btn-secondary {
      background-color: #6c757d;
    }
    @media print {
      .print-button-container {
        display: none;
      }
    }
  </style>
</head>
<body>
  <div class="print-button-container">
    <button onclick="window.print()" class="btn">Print</button>
    <a href="view_quotation.php?id=<?php echo $quotation_id; ?>" class="btn btn-secondary">Back to View</a>
  </div>

  <div class="header">
    <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="Company Logo">
    <div class="contact-line">
      <?php echo htmlspecialchars($company_address); ?> — CELL NO: <?php echo htmlspecialchars($company_phone); ?> — Email: <?php echo htmlspecialchars($company_email); ?>
    </div>
  </div>
  <hr>
  <h2>Quotation</h2>
  <p><strong>Quotation No:</strong> <?php echo htmlspecialchars($quotation['quotation_number']); ?></p>
  <p><strong>TPIN No:</strong> <?php echo htmlspecialchars($company_tpin); ?></p>
  <p><strong>Date:</strong> <?php echo htmlspecialchars($formatted_date); ?></p>
  <p><strong>Customer Name:</strong><br>
  <?php echo htmlspecialchars($display_customer_name); ?><br>
  <?php echo nl2br(htmlspecialchars($display_customer_address)); ?></p>
  
  <?php if (!empty($quotation['notes_general'])): ?>
  <p><strong>NOTE:</strong> <?php echo nl2br(htmlspecialchars($quotation['notes_general'])); ?></p>
  <?php endif; ?>
  
  <table>
    <tr>
      <th>Item No.</th>
      <th>Description</th>
      <th>Qty</th>
      <th>Rate Per <?php echo htmlspecialchars($quotation_items[0]['uom_name'] ?? 'PC'); ?></th>
      <th>Total Amount</th>
    </tr>
    <?php if (empty($quotation_items)): ?>
      <tr><td colspan="5" style="text-align: center;">No items found.</td></tr>
    <?php else: ?>
      <?php foreach ($quotation_items as $item): ?>
      <tr>
        <td><?php echo htmlspecialchars($item['item_number']); ?></td>
        <td><?php echo htmlspecialchars($item['product_name'] ?? $item['description']); ?></td>
        <td><?php echo htmlspecialchars(number_format($item['quantity'])); ?></td>
        <td><?php echo htmlspecialchars(number_format($item['rate_per_unit'], 2)); ?></td>
        <td><?php echo htmlspecialchars(number_format($item['total_amount'], 2)); ?></td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    
    <tr class="summary-row">
      <td colspan="4">Gross Total Amount</td>
      <td><?php echo number_format($quotation['gross_total_amount'], 2); ?></td>
    </tr>
    <tr class="summary-row">
      <td colspan="4">VAT <?php echo htmlspecialchars($quotation['vat_percentage']); ?>%</td>
      <td><?php echo number_format($vat_amount, 2); ?></td>
    </tr>
    <tr class="summary-row">
      <td colspan="4">Total Net Amount</td>
      <td><?php echo number_format($total_net_amount, 2); ?></td>
    </tr>
  </table>
  
  <?php if (!empty($quotation['delivery_period'])): ?>
  <p><strong>Delivery Period:</strong> <?php echo htmlspecialchars($quotation['delivery_period']); ?></p>
  <?php endif; ?>
  
  <?php if (!empty($quotation['payment_terms'])): ?>
  <p><strong>Payment Terms:</strong> <?php echo htmlspecialchars($quotation['payment_terms']); ?></p>
  <?php endif; ?>
  
  <p><strong>Quotation Validity:</strong> <?php echo htmlspecialchars($quotation['quotation_validity_days']); ?> Days</p>
  
  <div class="signature">
    <p>For <?php echo htmlspecialchars($company_name); ?></p>
    <img src="<?php echo htmlspecialchars($company_signature); ?>" alt="Authorized Signature">
  </div>
</body>
</html>