<?php
// templates/pdf/statement_template.php
if (!isset($statementData)) {
    die('Statement data not provided for template.');
}
function formatCurrency($amount, $currencySymbol = 'USD ') { // Adjust currency
    return $currencySymbol . number_format((float)$amount, 2, '.', ',');
}
$customer = $statementData['customer_details'] ?? [];
$shop = $statementData['shop_details'] ?? []; // Assuming shop details are added to statementData
                                            // If not, you might need a SettingsManager to get company info
$companyName = $shop['name'] ?? 'Your Company Name';
$companyAddress = $shop['address'] ?? 'Your Company Address';
$companyLogoPath = $shop['logo_path'] ?? '/assets/images/logo.png'; // Default path
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Statement - <?php echo htmlspecialchars($customer['name'] ?? 'N/A'); ?></title>
    <style>
        <?php
        $cssPath = __DIR__ . '/../../../assets/css/pdf_styles.css'; // Update path!
        if (file_exists($cssPath)) {
            echo file_get_contents($cssPath);
        }
        ?>
    </style>
</head>
<body>
    <div class="footer-section">
        <?php echo htmlspecialchars($companyName); ?> | Statement of Account | Page <span class="page-number"></span>
    </div>

    <div class="statement-container">
        <div class="header-section">
            <?php if (!empty($companyLogoPath) && file_exists($_SERVER['DOCUMENT_ROOT'] . $companyLogoPath)): ?>
                <img src="<?php echo $_SERVER['DOCUMENT_ROOT'] . htmlspecialchars($companyLogoPath); ?>" alt="Company Logo" class="logo">
            <?php endif; ?>
            <div class="company-details">
                <h2><?php echo htmlspecialchars($companyName); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($companyAddress)); ?></p>
            </div>
        </div>

        <h1 class="document-title">Customer Statement</h1>

        <div class="details-grid">
            <div class="customer-details" style="width:100%; float:none; margin-bottom:15px;"> <!-- Full width for statement -->
                <h4>Account Details:</h4>
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($customer['name'] ?? 'N/A'); ?></p>
                <p><strong>Account #:</strong> <?php echo htmlspecialchars($customer['customer_code'] ?? 'N/A'); ?></p>
                <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($customer['address'] ?? 'N/A')); ?></p>
                <p><strong>Statement Period:</strong>
                    <?php echo htmlspecialchars(date('M d, Y', strtotime($statementData['statement_period']['start']))); ?>
                    to
                    <?php echo htmlspecialchars(date('M d, Y', strtotime($statementData['statement_period']['end']))); ?>
                </p>
                 <p><strong>Statement Date:</strong> <?php echo htmlspecialchars(date('M d, Y')); ?></p>
            </div>
        </div>

        <div class="statement-summary">
            <h4>Account Summary for Period</h4>
            <p><strong>Opening Balance (as of <?php echo htmlspecialchars(date('M d, Y', strtotime($statementData['statement_period']['start']))); ?>):</strong> <?php echo htmlspecialchars(formatCurrency($statementData['opening_balance'])); ?></p>
            <p><strong>Total Invoiced during period:</strong> <?php echo htmlspecialchars(formatCurrency($statementData['summary']['total_invoiced_period'])); ?></p>
            <p><strong>Total Payments received during period:</strong> <?php echo htmlspecialchars(formatCurrency($statementData['summary']['total_paid_period'])); ?></p>
            <p><strong>Closing Balance (as of <?php echo htmlspecialchars(date('M d, Y', strtotime($statementData['statement_period']['end']))); ?>):</strong> <?php echo htmlspecialchars(formatCurrency($statementData['closing_balance'])); ?></p>
        </div>


        <table class="statement-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description / Reference</th>
                    <th class="amount">Debit</th>
                    <th class="amount">Credit</th>
                    <th class="amount">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($statementData['statement_period']['start']))); ?></td>
                    <td colspan="4"><strong>Opening Balance</strong></td>
                    <td class="amount"><strong><?php echo htmlspecialchars(formatCurrency($statementData['opening_balance'])); ?></strong></td>
                </tr>
                <?php if (empty($statementData['transactions'])): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 20px;">No transactions during this period.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($statementData['transactions'] as $txn): ?>
                <tr>
                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($txn['date']))); ?></td>
                    <td><?php echo htmlspecialchars($txn['type']); ?></td>
                    <td><?php echo htmlspecialchars($txn['description']); ?></td>
                    <td class="amount"><?php echo ($txn['debit'] > 0) ? htmlspecialchars(formatCurrency($txn['debit'], '')) : '-'; ?></td>
                    <td class="amount"><?php echo ($txn['credit'] > 0) ? htmlspecialchars(formatCurrency($txn['credit'], '')) : '-'; ?></td>
                    <td class="amount"><?php echo htmlspecialchars(formatCurrency($txn['running_balance'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="5" style="text-align:right; font-weight:bold;">Closing Balance</td>
                    <td class="amount" style="font-weight:bold; border-top: 2px solid #333;"><?php echo htmlspecialchars(formatCurrency($statementData['closing_balance'])); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="notes-terms-section">
            <p>Please review this statement carefully and report any discrepancies within 15 days.</p>
            <p>Payments can be made via [Your Payment Methods].</p>
        </div>

    </div><!-- .statement-container -->
</body>
</html>