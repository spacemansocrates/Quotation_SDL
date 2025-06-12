<?php
// customer_statement.php
// session_start(); // Uncomment if session-based info is needed

require_once __DIR__ . '/../includes/db_connect.php'; // Adjust path as needed

// --- Get Input Parameters ---
$customer_id_from_url = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);
$as_of_date_input = filter_input(INPUT_GET, 'as_of_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Default as_of_date to today if not provided or invalid
$as_of_date_obj = new DateTime(); // Default to today object
if ($as_of_date_input) {
    $d = DateTime::createFromFormat('Y-m-d', $as_of_date_input);
    if ($d && $d->format('Y-m-d') === $as_of_date_input) {
        $as_of_date_obj = $d;
    }
}
$as_of_date = $as_of_date_obj->format('Y-m-d');

$currency_symbol = "MWK"; // Or fetch from settings

// --- Initialize Variables ---
$pdo = null;
$page_error_message = null;
$list_view_error_message = null;

$customer_for_statement = null;
$company_settings_raw = [];
$statement_items = [];
$aging_summary = [
    'current' => 0,
    '0–30' => 0,      // 0 to 30 days overdue
    '31–60' => 0,
    '61–90' => 0,
    'Over 90' => 0,
    'total_outstanding' => 0
];
$all_customers_for_list = [];


// --- Data Fetching Logic ---
try {
    $pdo = getDatabaseConnection();

    if ($customer_id_from_url) {
        // --- ATTEMPT TO LOAD DATA FOR A SINGLE CUSTOMER STATEMENT ---
        // Added credit_limit to the select
        $stmt_customer = $pdo->prepare("SELECT *, credit_limit FROM customers WHERE id = ?");
        $stmt_customer->execute([$customer_id_from_url]);
        $customer_for_statement = $stmt_customer->fetch(PDO::FETCH_ASSOC);

        if ($customer_for_statement) {
            $stmt_settings = $pdo->prepare("SELECT setting_key, setting_value FROM company_settings");
            $stmt_settings->execute();
            $company_settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

            $sql_invoices = "
                SELECT
                    i.id AS invoice_id,
                    i.invoice_number,
                    i.invoice_date,
                    i.due_date,
                    i.total_net_amount AS original_amount,
                    COALESCE(SUM(p.amount_paid), 0) AS total_paid_as_of_date
                FROM invoices i
                LEFT JOIN payments p ON p.invoice_id = i.id AND p.payment_date <= :as_of_date_param
                WHERE i.customer_id = :customer_id_param
                  AND i.invoice_date <= :as_of_date_param2 -- Invoices issued on or before as_of_date
                GROUP BY i.id, i.invoice_number, i.invoice_date, i.due_date, i.total_net_amount
                HAVING (i.total_net_amount - COALESCE(SUM(p.amount_paid), 0)) > 0.005 -- Check for outstanding balance
                ORDER BY i.invoice_date ASC, i.id ASC
            ";

            $stmt_invoices = $pdo->prepare($sql_invoices);
            $stmt_invoices->execute([
                ':as_of_date_param' => $as_of_date,
                ':customer_id_param' => $customer_id_from_url,
                ':as_of_date_param2' => $as_of_date
            ]);

            $statement_items = []; // Reset
            $aging_summary = ['current' => 0, '0–30' => 0, '31–60' => 0, '61–90' => 0, 'Over 90' => 0, 'total_outstanding' => 0]; // Reset

            $as_of_datetime = new DateTime($as_of_date);

            while ($row = $stmt_invoices->fetch(PDO::FETCH_ASSOC)) {
                $balance_remaining_on_invoice = round((float)$row['original_amount'] - (float)$row['total_paid_as_of_date'], 2);

                if ($balance_remaining_on_invoice <= 0) continue; // Should be caught by HAVING, but good to double check

                $statement_items[] = [
                    'date' => $row['invoice_date'],
                    'doc_type' => 'INVOICE', // As per current scope
                    'doc_number' => $row['invoice_number'],
                    'reference' => $row['invoice_number'], // Reference can be same as invoice number
                    'original_amount' => (float)$row['original_amount'],
                    'amount_paid_on_doc' => (float)$row['total_paid_as_of_date'],
                    'balance_on_doc' => $balance_remaining_on_invoice,
                    'due_date_for_aging' => $row['due_date'] ?: $row['invoice_date'] // Use due_date if available, else invoice_date
                ];

                $aging_summary['total_outstanding'] += $balance_remaining_on_invoice;

                $date_for_aging_calc_str = $row['due_date'] ?: $row['invoice_date'];
                $date_for_aging_calc_obj = new DateTime($date_for_aging_calc_str);

                if ($as_of_datetime < $date_for_aging_calc_obj) { // Due date is in the future relative to as_of_date
                    $aging_summary['current'] += $balance_remaining_on_invoice;
                } else {
                    $days_overdue_interval = $as_of_datetime->diff($date_for_aging_calc_obj);
                    $days_overdue = (int)$days_overdue_interval->format('%a'); // Absolute number of days

                    if ($days_overdue <= 0) { // Due today (0 days overdue) or still current (handled by above)
                        // This case technically means due today if it reached here.
                        $aging_summary['0–30'] += $balance_remaining_on_invoice;
                    } elseif ($days_overdue <= 30) { // 1 to 30 days overdue
                        $aging_summary['0–30'] += $balance_remaining_on_invoice;
                    } elseif ($days_overdue <= 60) { // 31 to 60 days overdue
                        $aging_summary['31–60'] += $balance_remaining_on_invoice;
                    } elseif ($days_overdue <= 90) { // 61 to 90 days overdue
                        $aging_summary['61–90'] += $balance_remaining_on_invoice;
                    } else { // Over 90 days overdue
                        $aging_summary['Over 90'] += $balance_remaining_on_invoice;
                    }
                }
            }
        } else {
            $list_view_error_message = "Customer with ID " . htmlspecialchars($customer_id_from_url) . " not found. Please select a customer from the list below.";
            $customer_id_from_url = null;
            $customer_for_statement = null;
        }
    }

    if (!$customer_id_from_url) {
        $stmt_all_customers = $pdo->prepare("SELECT id, customer_code, name, email, phone FROM customers ORDER BY name ASC");
        $stmt_all_customers->execute();
        $all_customers_for_list = $stmt_all_customers->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Customer Statement Page DB Error (customer_id: {$customer_id_from_url}, as_of_date: {$as_of_date}): " . $e->getMessage());
    $page_error_message = "A database error occurred. Please try again later or contact support. Details have been logged.";
    if (strpos($e->getMessage(), 'HY093') !== false) {
         $page_error_message .= " (Dev note: Parameter binding issue.)";
    }
    $customer_for_statement = null;
    $customer_id_from_url = null;
} finally {
    $pdo = null;
}

// --- Prepare Company Display Info ---
$company_name_to_display = 'Your Company Name';
$company_address_to_display = 'Your Company Address';
$company_phone_to_display = 'Your Phone';
$company_email_to_display = 'your.email@example.com';
$company_tpin_to_display = 'YOUR_TPIN';
$logo_web_path_statement = 'images/logo.png'; // Default logo path as per requirement

if ($customer_for_statement) {
    $company_name_to_display = $company_settings_raw['company_name'] ?? $company_name_to_display;
    $company_logo_db_path = $company_settings_raw['company_logo_path'] ?? null;

    $company_address_parts = [];
    if (!empty($company_settings_raw['company_address_line1'])) $company_address_parts[] = $company_settings_raw['company_address_line1'];
    if (!empty($company_settings_raw['company_address_line2'])) $company_address_parts[] = $company_settings_raw['company_address_line2'];
    if (!empty($company_settings_raw['company_city'])) $company_address_parts[] = $company_settings_raw['company_city'];
    if (!empty($company_address_parts)) {
        $company_address_to_display = implode(', ', $company_address_parts);
    }

    $company_phone_to_display = $company_settings_raw['company_phone'] ?? $company_phone_to_display;
    $company_email_to_display = $company_settings_raw['company_email_address'] ?? $company_email_to_display;
    $company_tpin_to_display = $company_settings_raw['company_tpin_number'] ?? $company_tpin_to_display;

    if (!function_exists('web_path_statement')) {
        function web_path_statement($db_path, $default_path = 'images/logo.png') {
            if (empty($db_path)) return $default_path; // Use default if DB path is empty
            // Basic assumption: db_path is relative to web root OR an absolute system path
            // If it's an absolute system path, we need to convert it to a web path
            if (strpos($db_path, $_SERVER['DOCUMENT_ROOT']) === 0) {
                return '/' . ltrim(str_replace($_SERVER['DOCUMENT_ROOT'], '', $db_path), '/');
            }
            // If it's already a relative web path (e.g., 'uploads/logos/logo.png')
            if (!preg_match('/^(http|\/\/)/', $db_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($db_path, '/'))) {
                 return '/' . ltrim($db_path, '/');
            }
            return $default_path; // Fallback to default if path is unusable
        }
    }
    $logo_web_path_statement = web_path_statement($company_logo_db_path, 'images/logo.png');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $customer_for_statement ? 'Statement: ' . htmlspecialchars($customer_for_statement['name']) : 'Customer Statements'; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 10pt; color: #333; margin:0; padding:0; background-color: #f0f2f5; }
        .page-container { max-width: 1200px; margin: 20px auto; padding: 20px; background-color: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-radius: 8px; }
        .error-message { color: #d9534f; background-color: #f2dede; border: 1px solid #ebccd1; padding: 10px 15px; margin-bottom: 15px; border-radius: 4px; }

        /* Customer List Styles */
        .customer-list-section h1 { color: #337ab7; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom:20px;}
        .customer-list-table { width: 100%; border-collapse: collapse; margin-top:15px; font-size: 9pt; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .customer-list-table th { background-color: #f9f9f9; font-weight: 600; color: #555; border-bottom: 2px solid #ddd; padding: 10px 8px; }
        .customer-list-table td { border: 1px solid #eee; padding: 8px; text-align: left; }
        .customer-list-table tr:nth-child(even) td { background-color: #fcfcfc; }
        .customer-list-table tr:hover td { background-color: #f5f5f5; }
        .customer-list-table .action-links a {
            margin-right: 5px; text-decoration: none; padding: 5px 10px; font-size: 8.5pt; cursor:pointer;
            border: 1px solid #337ab7; background-color: #337ab7; color:white; border-radius:4px; transition: background-color 0.2s ease;
        }
        .customer-list-table .action-links a:hover { background-color: #286090; border-color: #204d74;}
        #list_as_of_date_picker { padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 9pt; }

        /* Statement Styles */
        .statement-container { width: 210mm; min-height: 280mm; /* Adjusted for content and footer */ margin: 0 auto; padding: 15mm; background-color: white; box-shadow: 0 0 15px rgba(0,0,0,0.15); box-sizing: border-box; position: relative; }
        .statement-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px; }
        .statement-header .logo img { max-height: 75px; max-width: 220px; }
        .statement-header .logo h2 { margin: 0; font-size: 20pt; color: #333; }
        .statement-header .company-details { text-align: right; font-size: 9pt; line-height: 1.5; }
        .statement-header .company-details h2 { margin: 0 0 8px 0; font-size: 18pt; color: #2c3e50; font-weight:600;}
        .statement-title { text-align: center; font-size: 20pt; font-weight: bold; margin-bottom: 20px; text-transform: uppercase; color: #333; letter-spacing: 1px; }
        .customer-info-header { margin-bottom: 25px; padding-bottom:15px; border-bottom: 1px solid #eaeaea;}
        .customer-info-header table { width: 100%; border-collapse: collapse; font-size: 9.5pt;}
        .customer-info-header td { padding: 3px 0; vertical-align: top; }
        .customer-info-header .label { font-weight: 600; width: 130px; color: #555; }
        .section-title-stmt { font-size: 14pt; font-weight: 600; margin-top: 30px; margin-bottom: 12px; border-bottom: 1px solid #ccc; padding-bottom: 6px; color: #337ab7;}
        .items-table, .aging-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 9pt; }
        .items-table th, .items-table td, .aging-table th, .aging-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th, .aging-table th { background-color: #f5f5f5; font-weight: 600; color: #444; }
        .items-table td.number, .items-table th.number, .aging-table td.number, .aging-table th.number { text-align: right; }
        .items-table .highlight-overdue { color: #c9302c; /*font-weight: bold;*/} /* Red for overdue */
        .total-outstanding-section { text-align: right; margin: 10px 0 25px 0; font-size: 12pt; font-weight: bold; color: #333; padding: 10px; background-color: #f9f9f9; border-radius: 4px;}
        .statement-notes { margin-top: 35px; font-size: 8.5pt; border-top: 1px solid #eee; padding-top:15px; line-height: 1.6; color:#444; }
        .statement-notes ul { padding-left: 20px; margin-top: 5px; }
        .statement-footer {
            position: absolute; bottom: 10mm; left: 15mm; right: 15mm; /* Pinned to bottom within padding */
            text-align: center; font-size: 8pt; color: #777;
            border-top: 1px solid #eee; padding-top: 10px;
        }

        .print-controls {
            position: fixed; top: 15px; right: 15px; background: rgba(255,255,255,0.95); padding: 12px 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15); z-index:1000; border-radius: 6px; display: flex; align-items: center;
        }
        .print-controls label { margin-right: 5px; font-size: 9pt; color: #555;}
        .print-controls input[type="date"], .print-controls button, .print-controls a {
            margin-left: 8px; padding: 7px 12px; font-size: 9pt; cursor: pointer;
            border: 1px solid #ccc; border-radius:4px;
        }
        .print-controls input[type="date"] { border-color: #bbb; }
        .print-controls button { background-color: #5cb85c; color:white; border-color:#4cae4c; transition: background-color 0.2s ease;}
        .print-controls button:hover { background-color: #449d44; }
        .print-controls a { background-color: #777; color:white; border-color:#666; text-decoration:none; transition: background-color 0.2s ease;}
        .print-controls a:hover { background-color: #5e5e5e; }


        @media print {
            body { background-color: #fff; margin:0; padding:0; font-size: 9pt; /* Slightly smaller for print */ }
            .page-container { margin: 0; padding: 0; box-shadow: none; max-width:none; border-radius: 0; }
            .customer-list-section { display: none !important; }
            .print-controls { display: none !important; }
            .statement-container {
                margin: 0; width: 100%; min-height:auto; box-shadow: none;
                padding: 10mm 0mm; /* Reduce side padding for print to maximize content */
                border: none;
                position: static; /* Override absolute for footer */
            }
            .statement-footer { position: static; margin-top: 20px; /* Ensure footer is part of flow */ }
            .items-table th, .items-table td, .aging-table th, .aging-table td { padding: 5px; } /* Tighter padding for print */
            .statement-header .logo img { max-height: 60px; }
            .statement-title { font-size: 16pt; margin-bottom: 15px; }
            .section-title-stmt { font-size: 12pt; margin-bottom: 8px; }
            .total-outstanding-section { font-size: 11pt; padding: 8px; }
        }
    </style>
</head>
<body>

    <?php if (!empty($page_error_message)): ?>
        <div class="page-container">
            <p class="error-message"><?php echo htmlspecialchars($page_error_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($customer_for_statement && $customer_id_from_url && !$page_error_message): // --- STATEMENT VIEW --- ?>
        <div class="print-controls">
            <label for="as_of_date_picker">Statement Date:</label>
            <input type="date" id="as_of_date_picker" value="<?php echo htmlspecialchars($as_of_date); ?>">
            <button onclick="reloadStatementWithDate()">Refresh</button>
            <button onclick="window.print()">Print Statement</button>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Back to List</a>
        </div>

        <div class="statement-container">
            <div class="statement-header">
                <div class="logo">
                    <?php if (!empty($logo_web_path_statement) && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_web_path_statement)): ?>
                        <img src="<?php echo htmlspecialchars($logo_web_path_statement); ?>" alt="<?php echo htmlspecialchars($company_name_to_display); ?> Logo">
                    <?php else: ?>
                        <h2><?php echo htmlspecialchars($company_name_to_display); ?></h2>
                    <?php endif; ?>
                </div>
                <div class="company-details">
                    <h2><?php echo htmlspecialchars($company_name_to_display); ?></h2>
                    <p><?php echo htmlspecialchars($company_address_to_display); ?></p>
                    <p>Phone: <?php echo htmlspecialchars($company_phone_to_display); ?> | Email: <?php echo htmlspecialchars($company_email_to_display); ?></p>
                    <p>TPIN: <?php echo htmlspecialchars($company_tpin_to_display); ?></p>
                </div>
            </div>

            <div class="statement-title">Customer Statement</div>

            <div class="customer-info-header">
                <table>
                    <tr>
                        <td class="label">To:</td>
                        <td><strong><?php echo htmlspecialchars($customer_for_statement['name']); ?></strong></td>
                        <td class="label" style="text-align:right;">Statement Date:</td>
                        <td style="text-align:right;"><?php echo htmlspecialchars(date('F j, Y', strtotime($as_of_date))); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Address:</td>
                        <td><?php echo nl2br(htmlspecialchars(trim($customer_for_statement['address_line1'] . "\n" . $customer_for_statement['address_line2'] . "\n" . $customer_for_statement['city_location']))); ?></td>
                        <td class="label" style="text-align:right;">Customer ID:</td>
                        <td style="text-align:right;"><?php echo htmlspecialchars($customer_for_statement['customer_code'] ?: 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Email:</td>
                        <td><?php echo htmlspecialchars($customer_for_statement['email'] ?: 'N/A'); ?></td>
                        <td class="label" style="text-align:right;">Phone:</td>
                        <td style="text-align:right;"><?php echo htmlspecialchars($customer_for_statement['phone'] ?: 'N/A'); ?></td>
                    </tr>
                    <?php if (!empty($customer_for_statement['tpin_no'])): ?>
                    <tr>
                        <td class="label">TPIN:</td>
                        <td><?php echo htmlspecialchars($customer_for_statement['tpin_no']); ?></td>
                        <td></td><td></td>
                    </tr>
                    <?php endif; ?>
                     <?php if (isset($customer_for_statement['credit_limit']) && is_numeric($customer_for_statement['credit_limit'])): ?>
                    <tr>
                        <td class="label">Credit Limit:</td>
                        <td><?php echo $currency_symbol; ?> <?php echo htmlspecialchars(number_format((float)$customer_for_statement['credit_limit'], 2)); ?></td>
                        <td></td><td></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="total-outstanding-section">
                Total Outstanding Balance: <?php echo $currency_symbol; ?> <?php echo htmlspecialchars(number_format($aging_summary['total_outstanding'], 2)); ?>
            </div>

            <div class="section-title-stmt">Outstanding Invoices</div>
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
                        <tr><td colspan="7" style="text-align:center; padding: 20px;">No outstanding items as of <?php echo htmlspecialchars(date('F j, Y', strtotime($as_of_date))); ?>.</td></tr>
                    <?php else: ?>
                        <?php foreach ($statement_items as $item):
                            $is_overdue_item = false;
                            if ($item['doc_type'] === 'INVOICE' && !empty($item['due_date_for_aging'])) {
                                $due_date_obj_item = new DateTime($item['due_date_for_aging']);
                                if ($due_date_obj_item < $as_of_date_obj) { // due_date is before as_of_date
                                     $is_overdue_item = true;
                                }
                            }
                        ?>
                        <tr class="<?php if($is_overdue_item) echo 'highlight-overdue'; ?>">
                            <td><?php echo htmlspecialchars(date('d M Y', strtotime($item['date']))); ?></td>
                            <td><?php echo htmlspecialchars($item['doc_type']); ?></td>
                            <td><?php echo htmlspecialchars($item['doc_number']); ?></td>
                            <td><?php echo htmlspecialchars($item['reference']); ?></td>
                            <td class="number"><?php echo htmlspecialchars(number_format($item['original_amount'], 2)); ?></td>
                            <td class="number"><?php echo htmlspecialchars(number_format($item['amount_paid_on_doc'], 2)); ?></td>
                            <td class="number"><strong><?php echo htmlspecialchars(number_format($item['balance_on_doc'], 2)); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" style="text-align:right; font-weight:bold; padding-top: 10px; padding-bottom: 10px;">TOTAL OUTSTANDING:</td>
                        <td class="number" style="font-weight:bold;"><?php echo $currency_symbol; ?> <?php echo htmlspecialchars(number_format($aging_summary['total_outstanding'], 2)); ?></td>
                    </tr>
                </tfoot>
            </table>

            <div class="section-title-stmt">Aging Summary</div>
            <table class="aging-table">
                <thead>
                    <tr>
                        <th>Aging Bucket</th>
                        <th class="number">Amount (<?php echo $currency_symbol; ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Current (Not Yet Due)</td>
                        <td class="number"><?php echo htmlspecialchars(number_format($aging_summary['current'], 2)); ?></td>
                    </tr>
                    <tr>
                        <td>0 – 30 Days Overdue</td>
                        <td class="number"><?php echo htmlspecialchars(number_format($aging_summary['0–30'], 2)); ?></td>
                    </tr>
                    <tr>
                        <td>31 – 60 Days Overdue</td>
                        <td class="number"><?php echo htmlspecialchars(number_format($aging_summary['31–60'], 2)); ?></td>
                    </tr>
                    <tr>
                        <td>61 – 90 Days Overdue</td>
                        <td class="number"><?php echo htmlspecialchars(number_format($aging_summary['61–90'], 2)); ?></td>
                    </tr>
                    <tr>
                        <td>Over 90 Days Overdue</td>
                        <td class="number"><?php echo htmlspecialchars(number_format($aging_summary['Over 90'], 2)); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td style="text-align:right; font-weight:bold; padding-top: 10px; padding-bottom: 10px;">TOTAL OUTSTANDING:</td>
                        <td class="number" style="font-weight:bold;"><?php echo htmlspecialchars(number_format($aging_summary['total_outstanding'], 2)); ?></td>
                    </tr>
                </tfoot>
            </table>

            <div class="statement-notes">
                <p><strong>Please Note:</strong></p>
                <ul>
                    <li>Payments made after <?php echo htmlspecialchars(date('F j, Y', strtotime($as_of_date))); ?> may not be reflected on this statement.</li>
                    <li>Please remit payment to the address or bank details provided on your invoice.</li>
                    <li>For any queries regarding this statement, please contact our accounts department at <?php echo htmlspecialchars($company_email_to_display); ?> or <?php echo htmlspecialchars($company_phone_to_display); ?>.</li>
                    <?php if ($aging_summary['total_outstanding'] > 0 && ($aging_summary['31–60'] > 0 || $aging_summary['61–90'] > 0 || $aging_summary['Over 90'] > 0)): ?>
                        <li style="color: #c9302c; font-weight:bold;">Your account has overdue balances. Prompt payment is appreciated to avoid service interruption.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="statement-footer">
                Thank you for your business! | <?php echo htmlspecialchars($company_name_to_display); ?>
                <?php if(!empty($company_tpin_to_display) && $company_tpin_to_display !== 'YOUR_TPIN') echo ' | TPIN: ' . htmlspecialchars($company_tpin_to_display); ?>
            </div>
        </div>

        <script>
            function reloadStatementWithDate() {
                const datePicker = document.getElementById('as_of_date_picker');
                const selectedDate = datePicker.value;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('as_of_date', selectedDate);
                // customer_id is already part of the URL if we are in statement view
                window.location.href = currentUrl.toString();
            }
        </script>

    <?php else: // --- CUSTOMER LIST VIEW --- ?>
        <div class="page-container customer-list-section">
            <h1>Customer Statements</h1>

            <?php if (!empty($list_view_error_message)): ?>
                <p class="error-message"><?php echo htmlspecialchars($list_view_error_message); ?></p>
            <?php endif; ?>

            <p style="margin-bottom: 15px;">Select a customer and an "as of" date to generate their statement.</p>
            <div style="margin-bottom: 20px;">
                <label for="list_as_of_date_picker" style="font-weight: 600; margin-right: 5px;">Default As Of Date for Statements:</label>
                <input type="date" id="list_as_of_date_picker" value="<?php echo htmlspecialchars($as_of_date); ?>">
            </div>

            <?php if (!empty($all_customers_for_list)): ?>
                <table class="customer-list-table">
                    <thead>
                        <tr>
                            <th>Customer Code</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_customers_for_list as $cust): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cust['customer_code'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($cust['name']); ?></td>
                            <td><?php echo htmlspecialchars($cust['email'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($cust['phone'] ?: 'N/A'); ?></td>
                            <td class="action-links">
                                <a href="#" onclick="viewStatement(<?php echo $cust['id']; ?>); return false;" title="View Statement for <?php echo htmlspecialchars($cust['name']); ?>">View Statement</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (!$page_error_message): ?>
                <p>No customers found in the system.</p>
            <?php endif; ?>
        </div>
        <script>
            function viewStatement(customerId) {
                const datePicker = document.getElementById('list_as_of_date_picker');
                const selectedDate = datePicker.value;
                let targetUrl = `<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?customer_id=${customerId}`;
                if (selectedDate) {
                    targetUrl += `&as_of_date=${selectedDate}`;
                }
                window.location.href = targetUrl;
            }
        </script>

    <?php endif; ?>

</body>
</html>