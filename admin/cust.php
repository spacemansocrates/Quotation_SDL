<?php
// --- DATABASE CONNECTION ---
$dbHost = 'srv582.hstgr.io';
$dbUser = 'u789944046_socrates';
$dbPass = 'Naho1386';
$dbName = 'u789944046_suppliesdirect';
$dbPort = 3306;


$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. ALWAYS Fetch all customers for the sidebar
$all_customers_result = $conn->query("SELECT id, name, customer_code FROM customers ORDER BY name ASC");
$all_customers = [];
while ($row = $all_customers_result->fetch_assoc()) {
    $all_customers[] = $row;
}

// 2. Get the currently selected customer ID from URL
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Initialize variables
$customer = null;
$transactions = [];
$related_documents = []; // <-- NEW: For invoices and quotations
$totalInvoiced = 0;
$totalPaid = 0;
$outstandingBalance = 0;
$overdueAmount = 0;

// 3. IF a customer is selected, fetch their detailed data
if ($customer_id > 0) {
    // Get Customer Details
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Proceed only if the customer exists
    if ($customer) {
        // Get Financial Summary (No changes here)
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_net_amount), 0) AS total_invoiced, COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('Paid', 'Cancelled') THEN balance_due ELSE 0 END), 0) AS overdue_amount FROM invoices WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $invoice_summary = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM payments WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $payment_summary = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $totalInvoiced = (float)$invoice_summary['total_invoiced'];
        $totalPaid = (float)$payment_summary['total_paid'];
        $outstandingBalance = $totalInvoiced - $totalPaid;
        $overdueAmount = (float)$invoice_summary['overdue_amount'];

        // Get Combined Transaction History (No changes here)
        $stmt = $conn->prepare("(SELECT id, invoice_date AS transaction_date, 'Invoice' AS type, invoice_number AS reference, CONCAT('Invoice #', invoice_number) AS description, total_net_amount AS debit, 0 AS credit FROM invoices WHERE customer_id = ?) UNION ALL (SELECT id, payment_date AS transaction_date, 'Payment' AS type, reference_number AS reference, CONCAT('Payment via ', payment_method) AS description, 0 AS debit, amount_paid AS credit FROM payments WHERE customer_id = ?) ORDER BY transaction_date ASC, type ASC");
        $stmt->bind_param("ii", $customer_id, $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $raw_transactions = [];
        while ($row = $result->fetch_assoc()) $raw_transactions[] = $row;
        $stmt->close();
        
        $running_balance = 0;
        foreach ($raw_transactions as $transaction) {
            $running_balance += (float)$transaction['debit'] - (float)$transaction['credit'];
            $transaction['balance'] = $running_balance;
            $transactions[] = $transaction;
        }
        $transactions = array_reverse($transactions);
        
        // --- NEW: Fetch Related Documents (Invoices & Quotations) ---
        $stmt = $conn->prepare("
            (SELECT id, 'Invoice' as type, invoice_number as number, invoice_date as date FROM invoices WHERE customer_id = ?)
            UNION ALL
            (SELECT id, 'Quotation' as type, quotation_number as number, quotation_date as date FROM quotations WHERE customer_id = ?)
            ORDER BY date DESC
            LIMIT 15 
        ");
        $stmt->bind_param("ii", $customer_id, $customer_id);
        $stmt->execute();
        $doc_result = $stmt->get_result();
        while($row = $doc_result->fetch_assoc()){
            $related_documents[] = $row;
        }
        $stmt->close();

    } else {
        $customer_id = 0; 
    }
}
$conn->close();

// Helper function
function format_currency($amount) { return 'MWK' . number_format($amount, 2); }

// View variables
$customerName = $customer ? htmlspecialchars($customer['name']) : 'Select a Customer';
$accountNumber = $customer ? htmlspecialchars($customer['customer_code'] ?? 'N/A') : 'N/A';
$contactEmail = $customer ? htmlspecialchars($customer['email'] ?? 'N/A') : 'N/A';
$phoneNumber = $customer ? htmlspecialchars($customer['phone'] ?? 'N/A') : 'N/A';
$currentBalance = format_currency($outstandingBalance);
$invoicedAmount = format_currency($totalInvoiced);
$paidAmount = format_currency($totalPaid);
$outstandingBalanceFormatted = format_currency($outstandingBalance);
$overdueAmountFormatted = format_currency($overdueAmount);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Profiles</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f0f2f5; }
        .card { background-color: #ffffff; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); transition: transform 0.2s ease-in-out; }
        .card:hover { transform: translateY(-2px); }
        .table-header { background-color: #f9fafb; color: #4b5563; font-weight: 600; }
        .btn-primary { background-image: linear-gradient(to right, #4F46E5, #6366F1); transition: background-position 0.3s ease-in-out; background-size: 200% auto; }
        .btn-primary:hover { background-position: right center; }
        .btn-secondary { border: 1px solid #d1d5db; background-color: #f3f4f6; transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out; }
        .btn-secondary:hover { background-color: #e5e7eb; border-color: #9ca3af; }
        .sidebar-scroll::-webkit-scrollbar { width: 6px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px;}
        .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
        /* For horizontal scrollbar on document cards */
        .docs-scroll::-webkit-scrollbar { height: 6px; }
        .docs-scroll::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
        .docs-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px;}
    </style>
</head>
<body class="h-screen overflow-hidden">

    <div class="flex h-full">
        <!-- Sidebar -->
        <aside class="w-72 bg-white border-r border-gray-200 flex flex-col">
            <!-- NEW: Sidebar header with Add button -->
            <div class="p-4 border-b flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">Customers</h2>
                           <a href="admin_dashboard.php" title="Back" class="text-gray-500 hover:text-blue-600 transition-colors">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <a href="admin_customers.php?action=add" title="Add New Customer" class="text-gray-500 hover:text-blue-600 transition-colors">
                    <i class="fas fa-plus-circle text-2xl"></i>
                </a>
     
            </div>
            <nav class="flex-1 overflow-y-auto sidebar-scroll p-2">
                <ul>
                    <?php if (empty($all_customers)): ?>
                        <li class="p-4 text-center text-gray-500">No customers found.</li>
                    <?php else: ?>
                        <?php foreach ($all_customers as $c): ?>
                            <?php
                                $isActive = ($c['id'] == $customer_id);
                                $activeClass = $isActive ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100';
                            ?>
                            <li>
                                <a href="cust.php?id=<?php echo $c['id']; ?>" class="block p-3 rounded-md transition-colors duration-150 <?php echo $activeClass; ?>">
                                    <span class="block text-sm font-medium truncate"><?php echo htmlspecialchars($c['name']); ?></span>
                                    <span class="block text-xs <?php echo $isActive ? 'text-blue-600' : 'text-gray-500'; ?>"><?php echo htmlspecialchars($c['customer_code'] ?? 'N/A'); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 bg-gray-50 overflow-y-auto">
            <?php if ($customer): // If a customer is selected and found, show their profile ?>
            <div class="p-6">
                <div class="max-w-7xl mx-auto space-y-8">
                    <!-- Top Section -->
                    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                        <div class="card p-6 flex-grow-0 md:flex-grow w-full md:w-auto">
                            <h1 class="text-3xl font-extrabold text-gray-900 mb-2"><?php echo $customerName; ?></h1>
                            <p class="text-base text-gray-600 flex items-center mt-1"><i class="fas fa-user-circle mr-2 text-blue-500"></i> Account: <span class="font-medium text-gray-800 ml-1"><?php echo $accountNumber; ?></span></p>
                            <p class="text-base text-gray-600 flex items-center mt-1"><i class="fas fa-envelope mr-2 text-blue-500"></i> <span class="font-medium text-gray-800"><?php echo $contactEmail; ?></span></p>
                            <p class="text-base text-gray-600 flex items-center mt-1"><i class="fas fa-phone mr-2 text-blue-500"></i> <span class="font-medium text-gray-800"><?php echo $phoneNumber; ?></span></p>
                        </div>
                        <div class="card p-6 text-center md:text-right flex-shrink-0 w-full md:w-auto">
                            <p class="text-lg text-gray-600 mb-1">Current Balance</p>
                            <p class="text-4xl font-bold text-gray-900"><?php echo $currentBalance; ?></p>
                        </div>
                    </header>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Left Column: Transactions -->
                        <div class="lg:col-span-2 space-y-6">
                            <!-- Transaction History -->
                            <div class="card p-6">
                                <h2 class="text-xl font-bold text-gray-800 mb-4">Transaction History</h2>
                                <div class="overflow-x-auto rounded-lg border border-gray-200">
                                    <table class="min-w-full divide-y divide-gray-200"><thead class="table-header"><tr><th class="px-6 py-3 text-left text-xs uppercase">Date</th><th class="px-6 py-3 text-left text-xs uppercase">Type</th><th class="px-6 py-3 text-left text-xs uppercase">Reference</th><th class="px-6 py-3 text-left text-xs uppercase">Description</th><th class="px-6 py-3 text-left text-xs uppercase">Debit</th><th class="px-6 py-3 text-left text-xs uppercase">Credit</th><th class="px-6 py-3 text-left text-xs uppercase">Balance</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><?php if (empty($transactions)): ?><tr><td colspan="7" class="px-6 py-10 text-center text-gray-500">No transactions found.</td></tr><?php else: ?><?php foreach ($transactions as $tx): ?><tr class="hover:bg-gray-50"><td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars(date('Y-m-d', strtotime($tx['transaction_date']))); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($tx['type']); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-medium"><a href="#"><?php echo htmlspecialchars($tx['reference'] ?? 'N/A'); ?></a></td><td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($tx['description']); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $tx['debit'] > 0 ? format_currency($tx['debit']) : ''; ?></td><td class="px-6 py-4 whitespace-nowrap text-sm text-green-600"><?php echo $tx['credit'] > 0 ? format_currency($tx['credit']) : ''; ?></td><td class="px-6 py-4 whitespace-nowrap text-sm font-medium"><?php echo format_currency($tx['balance']); ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table>
                                </div>
                            </div>
                            
                            <!-- NEW: Related Documents Section -->
                            <div class="card p-6">
                                <h2 class="text-xl font-bold text-gray-800 mb-4">Related Documents</h2>
                                <?php if (!empty($related_documents)): ?>
                                <div class="flex space-x-4 overflow-x-auto pb-3 docs-scroll">
                                    <?php foreach ($related_documents as $doc): ?>
                                        <?php
                                            $is_invoice = $doc['type'] === 'Invoice';
                                            $link = ($is_invoice ? 'print_invoice.php' : 'print_quotation.php') . '?id=' . $doc['id'];
                                            $icon = $is_invoice ? 'fa-file-invoice-dollar' : 'fa-file-alt';
                                            $color = $is_invoice ? 'text-blue-500' : 'text-purple-500';
                                        ?>
                                        <a href="<?php echo $link; ?>" target="_blank" class="block flex-none w-48 p-4 rounded-lg border border-gray-200 bg-gray-50 hover:bg-white hover:shadow-md hover:border-blue-400 transition-all text-center">
                                            <i class="fas <?php echo $icon; ?> <?php echo $color; ?> text-4xl mb-3"></i>
                                            <p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($doc['number']); ?></p>
                                            <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars(date('M j, Y', strtotime($doc['date']))); ?></p>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p class="text-sm text-gray-500 text-center py-4">No recent invoices or quotations found.</p>
                                <?php endif; ?>
                            </div>

                            <?php if ($overdueAmount > 0): ?><div class="card p-4 flex items-start bg-yellow-50 text-yellow-800 border-l-4 border-yellow-400"><i class="fas fa-exclamation-triangle mr-3 text-xl text-yellow-500"></i><p class="text-sm font-medium">This customer has an overdue balance of <strong><?php echo $overdueAmountFormatted; ?></strong>. Please follow up.</p></div><?php endif; ?>
                        </div>

                        <!-- Right Column -->
                        <div class="lg:col-span-1 space-y-6">
                            <div class="card p-6">
                                <h2 class="text-xl font-bold text-gray-800 mb-4">Account Summary</h2>
                                <div class="space-y-3 text-base text-gray-700">
                                    <div class="flex justify-between items-center pb-2 border-b"><span>Total Invoiced</span><span class="font-semibold text-gray-900"><?php echo $invoicedAmount; ?></span></div>
                                    <div class="flex justify-between items-center pb-2 border-b"><span>Total Paid</span><span class="font-semibold text-green-600"><?php echo $paidAmount; ?></span></div>
                                    <div class="flex justify-between items-center pb-2 border-b"><span>Outstanding Balance</span><span class="font-semibold text-gray-900"><?php echo $outstandingBalanceFormatted; ?></span></div>
                                    <div class="flex justify-between items-center <?php echo ($overdueAmount > 0) ? 'text-red-600' : 'text-gray-700'; ?> font-bold pt-2"><span>Overdue Amount</span><span><?php echo $overdueAmountFormatted; ?></span></div>
                                </div>
                            </div>
                            <div class="card p-6">
                                <h2 class="text-xl font-bold text-gray-800 mb-4">Actions</h2>
                                <div class="space-y-3">
                                    <a href="record_payment.php?customer_id=<?php echo $customer_id; ?>" class="w-full btn-primary text-white py-2 px-4 rounded-lg flex items-center justify-center text-base shadow-md"><i class="fas fa-plus mr-2"></i> Add Payment</a>
                                    <a href="admin_customers.php?action=edit&id=<?php echo $customer_id; ?>" class="w-full btn-secondary text-gray-800 py-2 px-4 rounded-lg flex items-center justify-center text-base shadow-sm"><i class="fas fa-user-circle mr-2"></i> Edit Profile</a>
                                    <a href="select_customer_for_statement.php" class="w-full btn-secondary text-gray-800 py-2 px-4 rounded-lg flex items-center justify-center text-base shadow-sm"><i class="fas fa-envelope mr-2"></i> Send Statement</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="flex items-center justify-center h-full"><div class="text-center p-12 bg-white rounded-lg shadow-lg"><i class="fas fa-users text-6xl text-gray-400 mb-6"></i><h2 class="text-3xl font-bold text-gray-800">Welcome to Customer Profiles</h2><p class="mt-3 text-lg text-gray-500">Please select a customer from the sidebar to view their details.</p></div></div>
            <?php endif; ?>
        </main>
    </div>

</body>
</html>