<?php
// Assuming classes are autoloaded (e.g., via Composer)
// If not, uncomment these lines:
// require_once __DIR__ . '/Database.php';
// require_once __DIR__ . '/../models/Customer.php'; // Or however you get customer details

class StatementGenerator {
    private $conn;
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        if (!$this->conn) {
            error_log("StatementGenerator: Failed to establish database connection.");
            // Methods should handle $this->conn being null
        }
    }

    /**
     * Generates customer statement data including opening balance, transactions, and closing balance.
     *
     * @param int $customer_id The ID of the customer.
     * @param string $start_date The start date of the statement period (Y-m-d).
     * @param string $end_date The end date of the statement period (Y-m-d).
     * @return array|null An array containing statement data or null on error.
     *                    Structure:
     *                    [
     *                        'customer_details' => [...], // Customer info
     *                        'statement_period' => ['start' => 'Y-m-d', 'end' => 'Y-m-d'],
     *                        'opening_balance' => float,
     *                        'transactions' => [
     *                            ['date', 'type', 'description', 'reference', 'debit', 'credit', 'running_balance'],
     *                            ...
     *                        ],
     *                        'closing_balance' => float,
     *                        'summary' => [
     *                            'total_invoiced' => float,
     *                            'total_paid' => float,
     *                            'net_movement' => float
     *                        ]
     *                    ]
     */
    public function generateCustomerStatementData(int $customer_id, string $start_date, string $end_date): ?array {
        if (!$this->conn) {
            error_log("Database connection not available for generateCustomerStatementData.");
            return null;
        }

        try {
            // Validate dates
            if (strtotime($start_date) === false || strtotime($end_date) === false || strtotime($start_date) > strtotime($end_date)) {
                error_log("Invalid date range provided for statement generation.");
                return null; // Or throw an Exception
            }
            
            // Fetch Customer Details
            $stmt_customer = $this->conn->prepare("SELECT id, name, customer_code, email, phone, address, tpin_number FROM customers WHERE id = ?");
            $stmt_customer->execute([$customer_id]);
            $customer_details = $stmt_customer->fetch(PDO::FETCH_ASSOC);
            if (!$customer_details) {
                error_log("Customer not found for ID: " . $customer_id);
                return null;
            }

            $statement = [
                'customer_details' => $customer_details,
                'statement_period' => ['start' => $start_date, 'end' => $end_date],
                'opening_balance' => 0.00,
                'transactions' => [],
                'closing_balance' => 0.00,
                'summary' => [
                    'total_invoiced_period' => 0.00,
                    'total_paid_period' => 0.00,
                    'net_movement_period' => 0.00
                ]
            ];

            // --- Calculate Opening Balance ---
            // Sum of (total_net_amount - total_paid) for all invoices for this customer
            // where invoice_date is BEFORE the $start_date.
            // We only consider 'Sent', 'Paid', 'Partially Paid', 'Overdue' invoices for balance calculation.
            // 'Draft', 'Cancelled', 'Void' invoices should not contribute to the balance.
            $stmt_ob = $this->conn->prepare("
                SELECT SUM(COALESCE(total_net_amount, 0) - COALESCE(total_paid, 0)) as opening_balance
                FROM invoices
                WHERE customer_id = :customer_id 
                  AND invoice_date < :start_date
                  AND status IN ('Sent', 'Paid', 'Partially Paid', 'Overdue') 
            ");
            $stmt_ob->execute([':customer_id' => $customer_id, ':start_date' => $start_date]);
            $ob_result = $stmt_ob->fetch(PDO::FETCH_ASSOC);
            $statement['opening_balance'] = $ob_result && $ob_result['opening_balance'] !== null ? (float)$ob_result['opening_balance'] : 0.00;


            // --- Fetch Invoices within the period ---
            // Only include invoices that affect the balance (not Draft, Cancelled, Void)
            $stmt_invoices = $this->conn->prepare("
                SELECT id, invoice_number, invoice_date, total_net_amount, status
                FROM invoices
                WHERE customer_id = :customer_id 
                  AND invoice_date BETWEEN :start_date AND :end_date
                  AND status IN ('Sent', 'Paid', 'Partially Paid', 'Overdue') 
                ORDER BY invoice_date ASC, id ASC
            ");
            $stmt_invoices->execute([
                ':customer_id' => $customer_id,
                ':start_date' => $start_date,
                ':end_date' => $end_date
            ]);
            $invoices = $stmt_invoices->fetchAll(PDO::FETCH_ASSOC);

            // --- Fetch Payments within the period ---
            // Payments are always relevant if they fall within the date range
            $stmt_payments = $this->conn->prepare("
                SELECT p.id, p.invoice_id, p.payment_date, p.amount_paid, p.payment_method, p.reference_number, i.invoice_number
                FROM payments p
                JOIN invoices i ON p.invoice_id = i.id  -- To get invoice_number for description
                WHERE p.customer_id = :customer_id 
                  AND p.payment_date BETWEEN :start_date AND :end_date
                ORDER BY p.payment_date ASC, p.id ASC
            ");
            $stmt_payments->execute([
                ':customer_id' => $customer_id,
                ':start_date' => $start_date,
                ':end_date' => $end_date
            ]);
            $payments = $stmt_payments->fetchAll(PDO::FETCH_ASSOC);

            // --- Combine and Sort Transactions ---
            $transactions = [];
            foreach ($invoices as $inv) {
                $transactions[] = [
                    'date' => $inv['invoice_date'],
                    'type' => 'Invoice',
                    'description' => 'Invoice #' . $inv['invoice_number'],
                    'reference' => $inv['invoice_number'], // Could also be inv['id']
                    'debit' => (float)$inv['total_net_amount'],
                    'credit' => 0.00,
                    'timestamp' => strtotime($inv['invoice_date'] . ' ' . '00:00:00'), // For sorting with payments on same day
                    'source_id' => $inv['id']
                ];
                $statement['summary']['total_invoiced_period'] += (float)$inv['total_net_amount'];
            }

            foreach ($payments as $pay) {
                $transactions[] = [
                    'date' => $pay['payment_date'],
                    'type' => 'Payment',
                    'description' => 'Payment Received' . ($pay['invoice_number'] ? ' (for Inv #' . $pay['invoice_number'] . ')' : '') . ($pay['reference_number'] ? ' - Ref: ' . $pay['reference_number'] : '') . ($pay['payment_method'] ? ' via ' . $pay['payment_method'] : ''),
                    'reference' => $pay['reference_number'] ?? ('PMT-' . $pay['id']),
                    'debit' => 0.00,
                    'credit' => (float)$pay['amount_paid'],
                    // Add time to payment date if available, otherwise use midday to sort after invoices on same day.
                    // Assuming payment_date is just date, use a time that typically comes after EOD for invoices.
                    'timestamp' => strtotime($pay['payment_date'] . ' ' . '12:00:00'), 
                    'source_id' => $pay['id']
                ];
                $statement['summary']['total_paid_period'] += (float)$pay['amount_paid'];
            }

            // Sort transactions by date (and time if available, or by type: Invoices before Payments on the same day)
            // The timestamp approach above helps.
            usort($transactions, function ($a, $b) {
                if ($a['timestamp'] == $b['timestamp']) {
                    // If timestamps are identical (e.g. same date, one invoice one payment),
                    // potentially prioritize invoices (debits) before payments (credits)
                    // This depends on desired statement appearance.
                    // For now, basic timestamp sort is fine. If more granular, compare types.
                    return 0; 
                }
                return $a['timestamp'] < $b['timestamp'] ? -1 : 1;
            });

            // --- Calculate Running Balance and Add to Transactions ---
            $running_balance = $statement['opening_balance'];
            foreach ($transactions as &$txn) { // Pass by reference to add 'running_balance'
                $running_balance = $running_balance + $txn['debit'] - $txn['credit'];
                $txn['running_balance'] = round($running_balance, 2);
                unset($txn['timestamp']); // Remove temporary sort key
            }
            unset($txn); // Unset reference to last element

            $statement['transactions'] = $transactions;
            $statement['closing_balance'] = round($running_balance, 2);
            
            $statement['summary']['net_movement_period'] = round($statement['summary']['total_invoiced_period'] - $statement['summary']['total_paid_period'], 2);

            // Final check on closing balance: OB + Total Debits (Invoiced) - Total Credits (Paid) in period
            $calculated_closing_balance = round($statement['opening_balance'] + $statement['summary']['total_invoiced_period'] - $statement['summary']['total_paid_period'], 2);
            if (abs($statement['closing_balance'] - $calculated_closing_balance) > 0.005) { // Tolerance for float
                 error_log("Statement Closing Balance Mismatch for customer {$customer_id}: Running Balance ({$statement['closing_balance']}) vs Calculated ({$calculated_closing_balance})");
                 // This might indicate an issue in logic or data.
            }


            return $statement;

        } catch (PDOException $e) {
            error_log("PDOException in generateCustomerStatementData: " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log("Exception in generateCustomerStatementData: " . $e->getMessage());
            return null;
        }
    }

    /**
     * (Optional) Helper to get current outstanding balance for a customer.
     * This is essentially the sum of balance_due for all non-cancelled/void invoices.
     */
    public function getCurrentCustomerBalance(int $customer_id): float {
        if (!$this->conn) {
            return 0.00;
        }
        try {
            $stmt = $this->conn->prepare("
                SELECT SUM(balance_due) as current_balance
                FROM invoices
                WHERE customer_id = :customer_id
                  AND status NOT IN ('Draft', 'Cancelled', 'Void')
            ");
            // The `balance_due` is a generated column (total_net_amount - total_paid)
            $stmt->execute([':customer_id' => $customer_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && $result['current_balance'] !== null ? (float)$result['current_balance'] : 0.00;
        } catch (PDOException $e) {
            error_log("PDOException in getCurrentCustomerBalance: " . $e->getMessage());
            return 0.00;
        }
    }
}