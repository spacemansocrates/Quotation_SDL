<?php
// customer_statement.php
// session_start(); // If any session-based info is needed (e.g., for company details if not tied to customer)

require_once __DIR__ . '/../includes/db_connect.php'; // Adjust path as needed

// --- Get Input Parameters ---
$customer_id = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);
$as_of_date_input = filter_input(INPUT_GET, 'as_of_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Debug: Check what we're receiving
error_log("Debug - customer_id received: " . var_export($customer_id, true));
error_log("Debug - as_of_date_input received: " . var_export($as_of_date_input, true));
error_log("Debug - GET parameters: " . var_export($_GET, true));

if (!$customer_id) {
    // More detailed error message for debugging
    die("Error: Customer ID is required. Received: " . var_export($_GET['customer_id'] ?? 'NOT SET', true));
}

// Default as_of_date to today if not provided or invalid
$as_of_date = date('Y-m-d'); // Default to today
if ($as_of_date_input) {
    $d = DateTime::createFromFormat('Y-m-d', $as_of_date_input);
    if ($d && $d->format('Y-m-d') === $as_of_date_input) {
        $as_of_date = $as_of_date_input;
    } else {
        // Optionally set an error or just use default
        // die("Error: Invalid 'as of' date format. Please use YYYY-MM-DD.");
    }
}

$currency_symbol = "MWK"; // Or fetch from settings

// --- Data Fetching ---
$customer = null;
$company_settings_raw = [];
$statement_items = []; // Will hold invoices and payments
$aging_summary = [
    'current' => 0, // Not overdue or due within 0-30 days from issue date if no due_date
    '0–30' => 0,
    '31–60' => 0,
    '61–90' => 0,
    'Over 90' => 0,
    'total_outstanding' => 0
];

try {
    $pdo = getDatabaseConnection();

    // 1. Fetch Customer Details
    $stmt_customer = DatabaseConfig::executeQuery($pdo, "SELECT * FROM customers WHERE id = :id", [':id' => $customer_id]);
    $customer = $stmt_customer->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        die("Error: Customer not found.");
    }

    // 2. Fetch Company/Shop Settings (assuming statement is from a generic company perspective or default shop)
    $company_settings_raw = DatabaseConfig::executeQuery($pdo, "SELECT setting_key, setting_value FROM company_settings")->fetchAll(PDO::FETCH_KEY_PAIR);


    // 3. Fetch Invoices and Payments up to as_of_date
    $sql_invoices = "
        SELECT 
            i.id AS invoice_id,
            i.invoice_number,
            i.invoice_date,
            i.due_date,
            i.total_net_amount AS original_amount,
            COALESCE(SUM(p.amount_paid), 0) AS total_paid_as_of_date
        FROM invoices i
        LEFT JOIN payments p ON p.invoice_id = i.id AND p.payment_date <= :as_of_date_for_payments
        WHERE i.customer_id = :customer_id 
          AND i.invoice_date <= :as_of_date_for_invoices
        GROUP BY i.id, i.invoice_number, i.invoice_date, i.due_date, i.total_net_amount
        HAVING (i.total_net_amount - COALESCE(SUM(p.amount_paid), 0)) > 0.005
        ORDER BY i.invoice_date ASC, i.id ASC
    ";

    $stmt_invoices_data = DatabaseConfig::executeQuery($pdo, $sql_invoices, [
        ':customer_id' => $customer_id,
        ':as_of_date_for_payments' => $as_of_date,
        ':as_of_date_for_invoices' => $as_of_date
    ]);

    $running_balance = 0;

    while ($row = $stmt_invoices_data->fetch(PDO::FETCH_ASSOC)) {
        $balance_remaining_on_invoice = round($row['original_amount'] - $row['total_paid_as_of_date'], 2);

        if ($balance_remaining_on_invoice <= 0) {
            continue;
        }
        
        $statement_items[] = [
            'date' => $row['invoice_date'],
            'doc_type' => 'INVOICE',
            'doc_number' => $row['invoice_number'],
            'reference' => $row['invoice_number'],
            'original_amount' => (float)$row['original_amount'],
            'amount_paid_on_doc' => (float)$row['total_paid_as_of_date'],
            'balance_on_doc' => $balance_remaining_on_invoice,
            'due_date_for_aging' => $row['due_date'] ?: $row['invoice_date']
        ];
        
        $aging_summary['total_outstanding'] += $balance_remaining_on_invoice;

        // Aging Calculation
        $date_for_aging_calc = $row['due_date'] ?: $row['invoice_date'];
        
        $days_diff_obj = date_diff(date_create($date_for_aging_calc), date_create($as_of_date));
        $days_overdue = 0;
        if ($as_of_date >= $date_for_aging_calc) {
            $days_overdue = (int)$days_diff_obj->format('%a');
        }


        if ($as_of_date < $date_for_aging_calc) {
             $aging_summary['current'] += $balance_remaining_on_invoice;
        } elseif ($days_overdue <= 0 && $date_for_aging_calc <= $as_of_date) {
            $aging_summary['current'] += $balance_remaining_on_invoice;
        } elseif ($days_overdue <= 30) {
            $aging_summary['0–30'] += $balance_remaining_on_invoice;
        } elseif ($days_overdue <= 60) {
            $aging_summary['31–60'] += $balance_remaining_on_invoice;
        } elseif ($days_overdue <= 90) {
            $aging_summary['61–90'] += $balance_remaining_on_invoice;
        } else {
            $aging_summary['Over 90'] += $balance_remaining_on_invoice;
        }
    }

} catch (PDOException $e) {
    error_log("Customer Statement DB Error: CustID {$customer_id}, AsOf {$as_of_date} - " . $e->getMessage());
    die("Database error. Please check system logs.");
}
DatabaseConfig::closeConnection($pdo);


// --- Company Display Info ---
$company_name_to_display = $company_settings_raw['company_name'] ?? 'Supplies Direct Limited';
$company_logo_path = $company_settings_raw['company_logo_path'] ?? 'images/logo.png';
$company_tpin_to_display = $company_settings_raw['company_tpin_number'] ?? '70030009';
$company_address_parts = [];
if (!empty($company_settings_raw['company_address_line1'])) $company_address_parts[] = $company_settings_raw['company_address_line1'];
if (!empty($company_settings_raw['company_address_line2'])) $company_address_parts[] = $company_settings_raw['company_address_line2'];
if (!empty($company_settings_raw['company_city'])) $company_address_parts[] = $company_settings_raw['company_city'];
$company_address_to_display = implode(', ', $company_address_parts);
if (empty($company_address_to_display)) $company_address_to_display = 'P.O.BOX NO.5206, LIMBE, MALAWI
CELL NO: 0991168991 / 0997398298
Email: info@suppliesdirectmw.com';

$company_phone_to_display = $company_settings_raw['company_phone'] ?? 'Your Phone';
$company_email_to_display = $company_settings_raw['company_email_address'] ?? 'your.email@example.com';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Statement - <?php echo htmlspecialchars($customer['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=JetBrains+Mono:wght@400;700&family=Roboto:wght@700&display=swap" rel="stylesheet">
    <style>
        /* CHANGED: Updated the variable to reflect the new font. */
        :root {
            --font-body: 'Inter', sans-serif;
            --font-numeric: 'JetBrains Mono', monospace; /* This font has the dotted zero! */
            --font-header: 'Roboto', sans-serif;
        }
        
        /* CHANGED: Applied the new Inter font to the body. */
        body {            
            font-family: var(--font-body);
            font-size: 10pt;
            line-height: 1.5;
            color: #333;
        }
        .statement-container { width: 210mm; min-height: 297mm; margin: 20px auto; padding: 15mm; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header .logo img { max-height: 85px; }
        .header .company-details { font-size: 9pt; line-height: 1.4; text-align: right; }
        .header .company-details h2 { margin: 0 0 5px 0; font-size: 16pt; color: #000;}
        .statement-title { text-align: center; font-size: 18pt; font-weight: bold; margin-bottom: 15px; text-transform: uppercase; font-family: var(--font-header); }
        
        .customer-header { margin-bottom: 20px; padding-bottom:10px; border-bottom: 1px solid #eee;}
        .customer-header table { width: 100%; border-collapse: collapse; font-size: 9pt;}
        .customer-header td { padding: 3px 0; vertical-align: top; }
        .customer-header .label { font-weight: bold; width: 120px; }

        .section-title { font-size: 12pt; font-weight: bold; margin-top: 25px; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px;}
        
        .items-table, .aging-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9pt; }
        .items-table th, .items-table td, .aging-table th, .aging-table td { border: 1px solid #ccc; padding: 7px 6px; text-align: left; }
        .items-table th, .aging-table th { background-color: #f0f0f0; font-weight: bold; font-family: var(--font-header); }
        .items-table td.number, .items-table th.number, .aging-table td.number, .aging-table th.number { text-align: right; }
        .items-table .highlight-overdue { color: #d9534f; font-weight: bold; }
        
        .highlight-balance {
            color: #0056b3;
            font-weight: 700;
        }

        /* This style applies your special numeric font. */
        .font-numeric {
            font-family: var(--font-numeric);
            font-weight: 700; /* Make numbers bold */
        }

        .total-outstanding-section { text-align: right; margin-top: 5px; margin-bottom:20px; font-size: 12pt; font-weight: bold; }
        .total-outstanding-section .currency { font-size: 11pt; margin-right: 4px; }
        .notes { margin-top: 30px; font-size: 8pt; border-top: 1px solid #eee; padding-top:10px; }
        .print-controls { position: fixed; top: 10px; right: 10px; background: #fff; padding: 10px; box-shadow: 0 0 5px rgba(0,0,0,0.2); z-index:1000; font-family: var(--font-header); }
        .print-controls button, .print-controls a { margin-left: 5px; padding: 5px 10px; font-size: 9pt;}
        @media print {
            body { background-color: #fff; }
            .statement-container { margin: 0; width: auto; min-height:auto; box-shadow: none; padding: 10mm 5mm; }
            .print-controls { display: none; }
        }
    </style>
</head>
<body>
    <div class="print-controls">
        Statement Date: <input type="date" id="as_of_date_picker" value="<?php echo htmlspecialchars($as_of_date); ?>" style="font-size:9pt; padding:3px;">
        <button onclick="reloadWithDate()">Refresh</button>
        <button onclick="window.print()">Print Statement</button>
        <a href="select_customer_for_statement.php">Back</a> </div>

    <div class="statement-container">
        <div class="header">
            <div class="logo">
                <img src="images/logo.png" alt="Company Logo">
            </div>
            <div class="company-details">
                <p><?php echo nl2br(htmlspecialchars($company_address_to_display)); ?></p>
                <p>TPIN: <?php echo htmlspecialchars($company_tpin_to_display); ?></p>
            </div>
        </div>

        <div class="statement-title">CUSTOMER STATEMENT</div>

        <div class="customer-header">
            <table>
                <tr>
                    <td class="label">Customer:</td>
                    <td><strong><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                    <td class="label" style="text-align:right;">Statement Date:</td>
                    <td style="text-align:right;"><?php echo htmlspecialchars(date('F j, Y', strtotime($as_of_date))); ?></td>
                </tr>
                <tr>
                    <td class="label">Address:</td>
                    <td><?php echo nl2br(htmlspecialchars($customer['address_line1'] . (!empty($customer['address_line2']) ? "\n".$customer['address_line2'] : '') . (!empty($customer['city_location']) ? "\n".$customer['city_location'] : ''))); ?></td>
                    <td class="label" style="text-align:right;">Customer ID:</td>
                    <td style="text-align:right;"><?php echo htmlspecialchars($customer['customer_code'] ?: 'N/A'); ?></td>
                </tr>
                <tr>
                    <td class="label">Email:</td>
                    <td><?php echo htmlspecialchars($customer['email'] ?: 'N/A'); ?></td>
                    <td class="label" style="text-align:right;">Phone:</td>
                    <td style="text-align:right;"><?php echo htmlspecialchars($customer['phone'] ?: 'N/A'); ?></td>
                </tr>
                <?php if (!empty($customer['tpin_no'])): ?>
                <tr>
                    <td class="label">TPIN:</td>
                    <td><?php echo htmlspecialchars($customer['tpin_no']); ?></td>
                    <td></td><td></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="total-outstanding-section">
            <span>Total Outstanding Balance: </span>
            <span class="highlight-balance">
                <span class="currency"><?php echo $currency_symbol; ?></span>
                <span class="font-numeric"><?php echo htmlspecialchars(number_format($aging_summary['total_outstanding'], 2)); ?></span>
            </span>
        </div>

        <div class="section-title">Outstanding Invoices</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Document #</th>
                    <th>Reference</th>
                    <th class="number">Original Amount</th>
                    <th class="number">Amount Paid</th>
                    <th class="number">Balance Due</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($statement_items)): ?>
                    <tr><td colspan="7" style="text-align:center; padding: 15px;">No outstanding items as of <?php echo htmlspecialchars(date('F j, Y', strtotime($as_of_date))); ?>.</td></tr>
                <?php else: ?>
                    <?php foreach ($statement_items as $item): 
                        $is_overdue_item = false;
                        if ($item['doc_type'] === 'INVOICE' && !empty($item['due_date_for_aging']) && $item['due_date_for_aging'] < $as_of_date) {
                            $is_overdue_item = true;
                        }
                    ?>
                    <tr class="<?php if($is_overdue_item) echo 'highlight-overdue'; ?>">
                        <td><?php echo htmlspecialchars(date('d M Y', strtotime($item['date']))); ?></td>
                        <td><?php echo htmlspecialchars($item['doc_type']); ?></td>
                        <td><?php echo htmlspecialchars($item['doc_number']); ?></td>
                        <td><?php echo htmlspecialchars($item['reference']); ?></td>
                        <td class="number font-numeric"><?php echo htmlspecialchars(number_format($item['original_amount'], 2)); ?></td>
                        <td class="number font-numeric"><?php echo htmlspecialchars(number_format($item['amount_paid_on_doc'], 2)); ?></td>
                        <td class="number highlight-balance font-numeric"><?php echo htmlspecialchars(number_format($item['balance_on_doc'], 2)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align:right; font-weight:bold;">TOTAL OUTSTANDING:</td>
                    <td class="number highlight-balance font-numeric"><?php echo $currency_symbol; ?> <?php echo htmlspecialchars(number_format($aging_summary['total_outstanding'], 2)); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="section-title">Aging Summary</div>
        <table class="aging-table">
            <thead>
                <tr>
                    <th>Aging Bucket</th>
                    <th class="number">Amount (<?php echo $currency_symbol; ?>)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Current (Not Yet Due / Due Today)</td>
                    <td class="number font-numeric"><?php echo htmlspecialchars(number_format($aging_summary['current'], 2)); ?></td>
                </tr>
                <tr>
                    <td>1 – 30 Days Overdue</td>
                    <td class="number font-numeric"><?php echo htmlspecialchars(number_format($aging_summary['0–30'], 2)); ?></td>
                </tr>
                <tr>
                    <td>31 – 60 Days Overdue</td>
                    <td class="number font-numeric"><?php echo htmlspecialchars(number_format($aging_summary['31–60'], 2)); ?></td>
                </tr>
                <tr>
                    <td>61 – 90 Days Overdue</td>
                    <td class="number font-numeric"><?php echo htmlspecialchars(number_format($aging_summary['61–90'], 2)); ?></td>
                </tr>
                <tr>
                    <td>Over 90 Days Overdue</td>
                    <td class="number font-numeric"><?php echo htmlspecialchars(number_format($aging_summary['Over 90'], 2)); ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td style="text-align:right; font-weight:bold;">TOTAL OUTSTANDING:</td>
                    <td class="number highlight-balance font-numeric"><?php echo htmlspecialchars(number_format($aging_summary['total_outstanding'], 2)); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="notes">
            <p><strong>Please note:</strong></p>
            <ul>
                <li>Payments made after <?php echo htmlspecialchars(date('F j, Y', strtotime($as_of_date))); ?> may not be reflected on this statement.</li>
                <li>Please remit payment to the address or bank details provided on your invoice.</li>
                <li>For any queries regarding this statement, please contact our accounts department at <?php echo htmlspecialchars($company_email_to_display); ?> or <?php echo htmlspecialchars($company_phone_to_display); ?>.</li>
                <?php if ($aging_summary['total_outstanding'] > 0 && ($aging_summary['31–60'] > 0 || $aging_summary['61–90'] > 0 || $aging_summary['Over 90'] > 0)): ?>
                    <li style="color: #d9534f; font-weight:bold;">Your account has overdue balances. Prompt payment is appreciated to avoid service interruption.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <script>
        function reloadWithDate() {
            const datePicker = document.getElementById('as_of_date_picker');
            const selectedDate = datePicker.value;
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('as_of_date', selectedDate);
            if (!currentUrl.searchParams.get('customer_id')) {
                 currentUrl.searchParams.set('customer_id', '<?php echo $customer_id; ?>');
            }
            window.location.href = currentUrl.toString();
        }
    </script>
</body>
</html>