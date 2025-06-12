<?php
// print_invoice.php
// --- (Same data fetching logic as view_invoice_details.php: Get ID, connect to DB, fetch invoice, items, customer, shop) ---

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("No invoice ID specified.");
}
$invoice_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$invoice_id) {
    die("Invalid invoice ID.");
}

// Check if images should be included (from GET parameter)
$include_images = isset($_GET['include_images']) && $_GET['include_images'] === '1';

// Database connection and queries to fetch invoice data
try {
    $conn = new PDO("mysql:host=localhost;dbname=supplies", "root", ""); // Replace with your actual connection
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch Invoice Details
    $sql_invoice = "SELECT i.*, 
                      c.name AS customer_name, c.customer_code, 
                      c.address_line1 AS customer_address_line1, c.email AS customer_email, c.phone AS customer_phone,
                      s.name AS shop_name, s.shop_code, 
                      u.username AS created_by_username
                      FROM invoices i
                      LEFT JOIN customers c ON i.customer_id = c.id
                      LEFT JOIN shops s ON i.shop_id = s.id
                      LEFT JOIN users u ON i.created_by_user_id = u.id
                      WHERE i.id = :invoice_id";
    $stmt_invoice = $conn->prepare($sql_invoice);
    $stmt_invoice->bindParam(':invoice_id', $invoice_id, PDO::PARAM_INT);
    $stmt_invoice->execute();
    $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) { die("Invoice not found."); }

    // Fetch Invoice Items
    $sql_items = "SELECT ii.*, 
                  p.name as product_name, 
                  p.sku as product_sku, 
                  p.default_image_path, /* Fetch default image path from products table */
                  uom.name as uom_name
                  FROM invoice_items ii
                  LEFT JOIN products p ON ii.product_id = p.id
                  LEFT JOIN units_of_measurement uom ON ii.unit_of_measurement = uom.name
                  WHERE ii.invoice_id = :invoice_id 
                  ORDER BY ii.item_number ASC";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bindParam(':invoice_id', $invoice_id, PDO::PARAM_INT);
    $stmt_items->execute();
    $invoice_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

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
$display_customer_name = $invoice['customer_name_override'] ?? $invoice['customer_name'];
$display_customer_address = $invoice['customer_address_override'] ?? $invoice['customer_address_line1'];
$formatted_date = date('d/m/Y', strtotime($invoice['invoice_date']));
$formatted_due_date = !empty($invoice['due_date']) ? date('d/m/Y', strtotime($invoice['due_date'])) : '';

// PPDA Levy: Use from DB if explicitly set and numeric. Percentage for display.
$ppda_levy_amount = (float)($invoice['ppda_levy_amount'] ?? 0);
$ppda_levy_percentage = (float)($invoice['ppda_levy_percentage'] ?? 0); // For display

// Calculate values for summary section
$gross_total_amount = (float)($invoice['gross_total_amount'] ?? 0);
$vat_percentage = (float)($invoice['vat_percentage'] ?? 16.5);
$vat_amount = (float)($invoice['vat_amount'] ?? ($gross_total_amount * ($vat_percentage / 100)));
$total_net_amount = (float)($invoice['total_net_amount'] ?? ($gross_total_amount + $vat_amount + $ppda_levy_amount));
$total_paid = (float)($invoice['total_paid'] ?? 0);
$balance_due = (float)($invoice['balance_due'] ?? ($total_net_amount - $total_paid));

?>
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <title>Invoice: <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>
    body {
      font-family: 'Poppins', Arial, sans-serif; /* Modern font */
      padding: 0;
      margin: 0;
      background-color: #f8f9fa; /* Light gray background for contrast */
      color: #333;
      line-height: 1.6;
    }

    .invoice-container {
      max-width: 800px; /* Constrain width for better readability */
      margin: 30px auto;
      padding: 40px;
      background-color: #fff;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      border-radius: 8px;
    }

    .header {
      display: flex; /* Use flexbox for better alignment */
      justify-content: space-between;
      align-items: flex-start; /* Align items to the top */
      margin-bottom: 30px; /* Increased spacing */
      flex-wrap: wrap; /* Allow wrapping on smaller screens */
    }

    .header .company-logo img {
      max-height: 80px; /* Slightly smaller logo, adjust as needed */
      margin-bottom: 10px;
    }

    .header .company-details {
      text-align: right; /* Align company details to the right */
      font-size: 13px;
      color: #555;
    }
    .company-details .company-name {
      font-weight: 600;
      font-size: 16px;
      color: #333;
      margin-bottom: 5px;
    }

    .contact-line {
      margin-top: 5px;
      font-size: 13px; /* Adjusted for Poppins */
    }

    hr.separator { /* Custom styled separator */
      border: 0;
      height: 1px;
      background-image: linear-gradient(to right, rgba(0, 0, 0, 0), rgba(0, 0, 0, 0.15), rgba(0, 0, 0, 0));
      margin: 30px 0;
    }

    .invoice-title {
      font-size: 28px; /* Larger title */
      font-weight: 600;
      color: #2c3e50; /* Dark blue for title */
      margin-bottom: 20px;
      text-align: center;
    }

    .invoice-meta-customer {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        font-size: 14px;
    }

    .invoice-meta-customer div {
        flex-basis: 48%; /* Distribute space */
    }

    .invoice-meta-customer strong {
        font-weight: 500;
        color: #555;
    }
    .invoice-meta-customer p, .customer-details p {
        margin: 4px 0;
    }
    .customer-details {
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 5px;
        border-left: 3px solid #007bff;
    }
    .customer-details strong {
        display: block;
        margin-bottom: 5px;
        color: #007bff;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      font-size: 14px; /* Consistent table font size */
    }

    table th, table td {
      border: 1px solid #e0e0e0; /* Lighter borders */
      padding: 12px 10px; /* More padding */
      text-align: left;
    }

    table th {
      background-color: #f0f2f5; /* Light background for headers */
      font-weight: 600; /* Bolder headers */
      color: #333;
    }

    table tr:nth-child(even) {
      background-color: #f9f9f9; /* Zebra striping for rows */
    }

    .summary-section {
        margin-top: 20px;
        float: right; /* Align summary to the right */
        width: 50%; /* Take up half the width */
    }
    .summary-section table td {
        border: none; /* Remove borders for summary table for cleaner look */
        padding: 8px 10px;
    }
    .summary-section .summary-row td {
      font-weight: 500;
    }
    .summary-section .summary-row td:first-child {
      text-align: right;
      color: #555;
    }
    .summary-section .summary-row td:last-child {
      text-align: right;
      font-weight: 600;
      color: #333;
    }
    .summary-section .balance-due-row td {
        font-size: 16px;
        font-weight: bold;
        color: #007bff; /* Highlight balance due */
    }
    .summary-section .balance-due-row td:first-child {
        color: #007bff;
    }

    .signature {
      margin-top: 60px; /* More space before signature */
      padding-top: 20px;
      border-top: 1px dashed #ccc;
      text-align: left; /* Or center, if preferred */
    }

    .signature p {
        margin-bottom:10px;
        font-size: 14px;
        color: #555;
    }
    .signature img {
      height: 60px; /* Keep signature size */
      opacity: 0.85;
    }

    /* Print Button Container - kept similar functionality but improved aesthetics */
    .print-button-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      background-color: #fff;
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.15);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .print-button-container label {
        font-size: 14px;
        display: flex;
        align-items: center;
        cursor: pointer;
    }
    .print-button-container input[type="checkbox"] {
        margin-right: 5px;
        accent-color: #007bff; /* Modern accent for checkbox */
    }

    .btn {
      padding: 10px 18px;
      font-size: 14px;
      font-weight: 500;
      background-color: #007bff; /* Primary button color */
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex; /* For icon alignment */
      align-items: center;
      gap: 8px; /* Space between icon and text */
      transition: background-color 0.3s ease;
    }
    .btn:hover {
      background-color: #0056b3; /* Darker shade on hover */
    }
    .btn-secondary {
      background-color: #6c757d; /* Secondary button color */
    }
    .btn-secondary:hover {
      background-color: #545b62;
    }

    /* Image Column Styles */
    .image-column { display: none; } /* Hidden by default */
    body.show-images .image-column { display: table-cell; text-align: center; }
    .product-image-cell img {
      max-width: 60px;
      max-height: 60px;
      object-fit: contain;
      border-radius: 4px;
      border: 1px solid #eee;
    }

    /* Image Upload related styles from original - kept for completeness,
       though upload is typically for an edit page */
    .image-upload-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
    }
    .image-upload-container input[type="file"] { display: none; }
    .image-upload-container .upload-btn {
        background-color: #007bff; color: white; padding: 5px 10px;
        border: none; border-radius: 3px; cursor: pointer; font-size: 12px;
    }
    .image-upload-container .upload-btn:hover { background-color: #0056b3; }
    .upload-feedback { font-size: 10px; color: green; }
    .upload-error { font-size: 10px; color: red; }

    .clearfix::after { /* To clear floats for the summary section */
        content: "";
        clear: both;
        display: table;
    }

    /* Print specific styles */
    @media print {
      body {
        background-color: #fff; /* White background for printing */
        padding: 0;
        margin: 0;
        font-size: 12pt; /* Ensure readable font size for print */
      }
      .invoice-container {
        box-shadow: none;
        margin: 0;
        padding: 20px; /* Reduce padding for print to maximize space */
        border-radius: 0;
        max-width: 100%;
      }
      .print-button-container {
        display: none !important; /* Hide buttons when printing */
      }
      hr.separator {
          margin: 20px 0;
      }
      table th, table td {
        padding: 8px; /* Slightly less padding for print */
      }
      .summary-section {
          width: 45%; /* Adjust for print if needed */
          float: right;
      }
      /* If images are included via URL param, they will be part of the content to print.
         The body.show-images rule will handle their display. */
      @page {
        margin: 20mm; /* Standard print margins */
      }
    }
  </style>
</head>
<body>
  <div class="print-button-container">
    <label>
      <input type="checkbox" id="include_images" onchange="toggleImageColumn()" <?php echo $include_images ? 'checked' : ''; ?>> Include Images
    </label>
    <button onclick="window.print()" class="btn">
      <i class="fas fa-print"></i> Print
    </button>
    <a href="admin_invoices.php" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Back
    </a>
  </div>

  <div class="invoice-container">
    <div class="header">
      <div class="company-logo">
        <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="Company Logo">
      </div>
      <div class="company-details">
        <div class="company-name"><?php echo htmlspecialchars($company_name); ?></div>
        <?php echo htmlspecialchars($company_address); ?><br>
        CELL NO: <?php echo htmlspecialchars($company_phone); ?><br>
        Email: <?php echo htmlspecialchars($company_email); ?>
      </div>
    </div>

    <hr class="separator">

    <div class="invoice-title">INVOICE</div>

    <div class="invoice-meta-customer">
        <div class="invoice-meta">
            <p><strong>Invoice No:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
            <p><strong>TPIN No:</strong> <?php echo htmlspecialchars($company_tpin); ?></p>
            <p><strong>Invoice Date:</strong> <?php echo htmlspecialchars($formatted_date); ?></p>
            <?php if (!empty($formatted_due_date)): ?>
            <p><strong>Due Date:</strong> <?php echo htmlspecialchars($formatted_due_date); ?></p>
            <?php endif; ?>
        </div>
        <div class="customer-details">
            <strong>Bill To:</strong>
            <p><?php echo htmlspecialchars($display_customer_name); ?><br>
            <?php echo nl2br(htmlspecialchars($display_customer_address)); ?></p>
        </div>
    </div>

    <?php if (!empty($invoice['notes_general'])): ?>
    <p><strong>NOTE:</strong> <?php echo nl2br(htmlspecialchars($invoice['notes_general'])); ?></p>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>Item No.</th>
          <th class="image-column">Image</th>
          <th>Description</th>
          <th>Qty</th>
          <th>Rate Per <?php echo htmlspecialchars($invoice_items[0]['unit_of_measurement'] ?? 'PC'); ?></th>
          <th>Total Amount</th>
        </tr>
      </thead>
 <tbody>
    <?php if (empty($invoice_items)): ?>
        <tr><td colspan="6" style="text-align: center;">No items found.</td></tr>
    <?php else: ?>
        <?php $item_counter = 1; // 1. Initialize the counter ?>
        <?php foreach ($invoice_items as $item): 
            $item_image_path = $item['image_path_override'] ?? $item['default_image_path'] ?? null;
            $item_id_for_js = htmlspecialchars($item['id']);
        ?>
        <tr id="item-row-<?php echo $item_id_for_js; ?>">
            
            <!-- 2. Use the counter for the sequential number -->
            <td><?php echo $item_counter; ?></td>
            
            <td class="image-column product-image-cell">
                <div class="image-display-area" id="image-display-<?php echo $item_id_for_js; ?>">
                    <?php if (!empty($item_image_path) && file_exists($item_image_path)): ?>
                        <img src="<?php echo htmlspecialchars($item_image_path); ?>" alt="Product Image">
                    <?php endif; ?>
                </div>
                <?php if (!($item_image_path && file_exists($item_image_path))): ?>
                <div class="image-upload-container">
                    <input type="file" id="upload-input-<?php echo $item_id_for_js; ?>" 
                           data-item-id="<?php echo $item_id_for_js; ?>" 
                           accept="image/*" 
                           onchange="handleImageUpload(this)">
                    <button class="upload-btn" onclick="document.getElementById('upload-input-<?php echo $item_id_for_js; ?>').click()">
                        Add Image
                    </button>
                    <div class="upload-feedback" id="feedback-<?php echo $item_id_for_js; ?>"></div>
                </div>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($item['product_name'] ?? $item['description'] ?? 'Item'); ?></td>
            <td><?php echo htmlspecialchars(number_format($item['quantity'] ?? 0)); ?></td>
            <td><?php echo htmlspecialchars(number_format($item['rate_per_unit'] ?? 0, 2)); ?></td>
            <td><?php echo htmlspecialchars(number_format($item['total_amount'] ?? 0, 2)); ?></td>
        </tr>
        
        <?php $item_counter++; // 3. Increment the counter ?>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
    </table>

    <div class="clearfix">
        <div class="summary-section">
            <table>
                <tbody>
                    <tr class="summary-row">
                        <td>Gross Total Amount</td>
                        <td><?php echo number_format($gross_total_amount, 2); ?></td>
                    </tr>
                    <?php if ($ppda_levy_amount > 0): ?>
                    <tr class="summary-row">
                        <td>PPDA Levy (<?php echo htmlspecialchars($ppda_levy_percentage); ?>%)</td>
                        <td><?php echo number_format($ppda_levy_amount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="summary-row">
                        <td>VAT <?php echo htmlspecialchars($vat_percentage); ?>%</td>
                        <td><?php echo number_format($vat_amount, 2); ?></td>
                    </tr>
                    <tr class="summary-row">
                        <td>Total Net Amount</td>
                        <td><?php echo number_format($total_net_amount, 2); ?></td>
                    </tr>
                    <tr class="summary-row">
                        <td>Total Paid</td>
                        <td><?php echo number_format($total_paid, 2); ?></td>
                    </tr>
                    <tr class="summary-row balance-due-row">
                        <td>Balance Due</td>
                        <td><strong><?php echo number_format($balance_due, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($invoice['delivery_period'])): ?>
    <p><strong>Delivery Period:</strong> <?php echo htmlspecialchars($invoice['delivery_period']); ?></p>
    <?php endif; ?>
    <?php if (!empty($invoice['payment_terms'])): ?>
    <p><strong>Payment Terms:</strong> <?php echo htmlspecialchars($invoice['payment_terms']); ?></p>
    <?php endif; ?>

    <div class="signature">
      <p>For <?php echo htmlspecialchars($company_name); ?></p>
      <img src="<?php echo htmlspecialchars($company_signature); ?>" alt="Authorized Signature">
    </div>
  </div>

  <script>
    // This function reloads the page with a query parameter.
    function toggleImageColumn() {
        const includeImages = document.getElementById('include_images').checked;
        const currentUrl = new URL(window.location.href);
        if (includeImages) {
            currentUrl.searchParams.set('include_images', '1');
        } else {
            currentUrl.searchParams.delete('include_images');
        }
        window.location.href = currentUrl.toString();
    }

    // On page load, check URL parameters to set checkbox and body class
    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const imagesCheckbox = document.getElementById('include_images');
        if (params.get('include_images') === '1') {
            if(imagesCheckbox) imagesCheckbox.checked = true;
            document.body.classList.add('show-images');
        } else {
            if(imagesCheckbox) imagesCheckbox.checked = false;
            document.body.classList.remove('show-images');
        }
    });

    // Function to handle image upload via AJAX
    async function handleImageUpload(input) {
        const itemId = input.dataset.itemId;
        const file = input.files[0];
        const feedbackDiv = document.getElementById(`feedback-${itemId}`);
        const imageDisplayArea = document.getElementById(`image-display-${itemId}`);

        if (!file) {
            feedbackDiv.textContent = '';
            return;
        }

        feedbackDiv.textContent = 'Uploading...';
        feedbackDiv.style.color = 'blue';

        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('item_image', file);

        try {
            const response = await fetch('upload_invoice_item_image.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                feedbackDiv.textContent = 'Upload successful!';
                feedbackDiv.style.color = 'green';
                // Update the image immediately without full page reload
                imageDisplayArea.innerHTML = `<img src="${data.image_path}" alt="Product Image">`;
                // Hide the upload controls after successful upload
                input.closest('.image-upload-container').style.display = 'none';

            } else {
                feedbackDiv.textContent = `Upload failed: ${data.message}`;
                feedbackDiv.style.color = 'red';
            }
        } catch (error) {
            console.error('Error during upload:', error);
            feedbackDiv.textContent = 'An error occurred during upload.';
            feedbackDiv.style.color = 'red';
        }
    }
  </script>
</body>
</html>