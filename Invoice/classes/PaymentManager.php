<?php
// Assuming classes are autoloaded (e.g., via Composer)
// If not, uncomment these lines:
// require_once __DIR__ . '/Database.php';
// require_once __DIR__ . '/../models/User.php'; // Or however you access user details/names

class PaymentManager {
    private $conn;
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        if (!$this->conn) {
            // Handle connection failure for the PaymentManager itself
            error_log("PaymentManager: Failed to establish database connection.");
            // Optionally throw an exception or ensure methods handle $this->conn being null
        }
    }

    /**
     * Records a payment against an invoice.
     *
     * @param array $data Must include: invoice_id, customer_id, payment_date, amount_paid.
     *                    Optional: payment_method, reference_number, notes.
     * @param int $user_id The ID of the user recording the payment.
     * @return array ['success' => bool, 'payment_id' => int|null, 'error' => string|null]
     */
    public function recordPayment(array $data, int $user_id): array {
        if (!$this->conn) {
            return ['success' => false, 'error' => 'Database connection failed in PaymentManager.'];
        }

        // Validate required fields
        $required_fields = ['invoice_id', 'customer_id', 'payment_date', 'amount_paid'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                return ['success' => false, 'error' => "Missing required field for payment: $field"];
            }
        }
        if (!is_numeric($data['amount_paid']) || (float)$data['amount_paid'] <= 0) {
            return ['success' => false, 'error' => 'Payment amount must be a positive number.'];
        }
        
        // Optional: Check if invoice exists and can receive payments
        $stmt_check_invoice = $this->conn->prepare(
            "SELECT id, total_net_amount, total_paid, status FROM invoices WHERE id = ?"
        );
        $stmt_check_invoice->execute([(int)$data['invoice_id']]);
        $invoice = $stmt_check_invoice->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return ['success' => false, 'error' => 'Invoice not found.'];
        }
        // Business rule: Prevent payment on 'Cancelled' or 'Void' invoices.
        //                Allowing payment on 'Paid' invoices might signify overpayment or a new transaction.
        if (in_array($invoice['status'], ['Cancelled', 'Void'])) {
            return ['success' => false, 'error' => "Cannot record payment for an invoice with status '{$invoice['status']}'.'"];
        }


        try {
            $this->conn->beginTransaction();

            $stmt_payment = $this->conn->prepare("
                INSERT INTO payments (
                    invoice_id, customer_id, payment_date, amount_paid, 
                    payment_method, reference_number, notes, recorded_by_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_payment->execute([
                (int)$data['invoice_id'],
                (int)$data['customer_id'], // This should match the invoice's customer_id
                $data['payment_date'],
                (float)$data['amount_paid'],
                $data['payment_method'] ?? null,
                $data['reference_number'] ?? null,
                $data['notes'] ?? null,
                $user_id
            ]);
            $payment_id = $this->conn->lastInsertId();

            // Update invoice's total_paid and status
            // The balance_due column is GENERATED ALWAYS AS ((total_net_amount - total_paid)) STORED
            // So, when total_paid is updated, balance_due updates automatically.
            // The CASE statement uses the $data['amount_paid'] to predict the new balance.
            $new_payment_amount = (float)$data['amount_paid'];
            $stmt_update_invoice = $this->conn->prepare("
                UPDATE invoices
                SET total_paid = total_paid + :new_payment_amount,
                    status = CASE
                                -- Using a small tolerance for floating point comparison
                                WHEN (total_net_amount - (total_paid + :new_payment_amount_for_status)) <= 0.005 THEN 'Paid'
                                ELSE 'Partially Paid'
                             END,
                    updated_by_user_id = :user_id,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :invoice_id
            ");
            
            $stmt_update_invoice->bindParam(':new_payment_amount', $new_payment_amount, PDO::PARAM_STR); // PDO::PARAM_STR for decimals
            $stmt_update_invoice->bindParam(':new_payment_amount_for_status', $new_payment_amount, PDO::PARAM_STR);
            $stmt_update_invoice->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_update_invoice->bindParam(':invoice_id', $data['invoice_id'], PDO::PARAM_INT);
            $stmt_update_invoice->execute();

            $this->conn->commit();
            // TODO: ActivityLogger::log('payment_recorded', $payment_id, $user_id, "Payment of {$new_payment_amount} recorded for invoice ID {$data['invoice_id']}.");
            return ['success' => true, 'payment_id' => $payment_id];

        } catch (PDOException $e) {
            if ($this->conn && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("PDOException in recordPayment: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred while recording payment: ' . $e->getMessage()];
        } catch (Exception $e) {
            if ($this->conn && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Exception in recordPayment: " . $e->getMessage());
            return ['success' => false, 'error' => 'An unexpected error occurred: ' . $e->getMessage()];
        }
    }

    /**
     * Retrieves all payments associated with a specific invoice.
     *
     * @param int $invoice_id
     * @return array List of payment records, or empty array if none/error.
     */
    public function getPaymentsByInvoice(int $invoice_id): array {
        if (!$this->conn) {
            error_log("Database connection not available for getPaymentsByInvoice.");
            return [];
        }
        try {
            // Optionally join with users table to get recorder's name:
            // SELECT p.*, u.username as recorded_by_username FROM payments p 
            // LEFT JOIN users u ON p.recorded_by_user_id = u.id
            // WHERE p.invoice_id = ? ORDER BY p.payment_date DESC, p.id DESC
            $stmt = $this->conn->prepare(
                "SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC, id DESC"
            );
            $stmt->execute([$invoice_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("PDOException in getPaymentsByInvoice: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves all payments made by a specific customer.
     * Useful for customer account views or parts of statement generation.
     *
     * @param int $customer_id
     * @param string|null $date_from (Y-m-d)
     * @param string|null $date_to (Y-m-d)
     * @return array List of payment records, or empty array if none/error.
     */
    public function getPaymentsByCustomer(int $customer_id, ?string $date_from = null, ?string $date_to = null): array {
        if (!$this->conn) {
            error_log("Database connection not available for getPaymentsByCustomer.");
            return [];
        }
        try {
            $sql = "SELECT p.*, i.invoice_number 
                    FROM payments p
                    JOIN invoices i ON p.invoice_id = i.id
                    WHERE p.customer_id = :customer_id";
            
            $params = [':customer_id' => $customer_id];

            if ($date_from) {
                $sql .= " AND p.payment_date >= :date_from";
                $params[':date_from'] = $date_from;
            }
            if ($date_to) {
                $sql .= " AND p.payment_date <= :date_to";
                $params[':date_to'] = $date_to;
            }
            
            $sql .= " ORDER BY p.payment_date DESC, p.id DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("PDOException in getPaymentsByCustomer: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves a single payment by its ID.
     *
     * @param int $payment_id
     * @return array|null Payment record or null if not found/error.
     */
    public function getPaymentById(int $payment_id): ?array {
        if (!$this->conn) {
            error_log("Database connection not available for getPaymentById.");
            return null;
        }
        try {
            $stmt = $this->conn->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            return $payment ?: null;
        } catch (PDOException $e) {
            error_log("PDOException in getPaymentById: " . $e->getMessage());
            return null;
        }
    }

    // TODO: Implement updatePayment and deletePayment if necessary.
    // deletePayment would need to reverse the changes made to the invoice's total_paid and status,
    // which adds complexity and requires careful transaction management.
    // Updating a payment (e.g., amount or date) would also require recalculating invoice status and totals.

    /**
     * Deletes a payment and updates the associated invoice.
     * WARNING: This is a sensitive operation. Ensure proper authorization.
     *
     * @param int $payment_id The ID of the payment to delete.
     * @param int $user_id The ID of the user performing the deletion.
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function deletePayment(int $payment_id, int $user_id): array {
        if (!$this->conn) {
            return ['success' => false, 'error' => 'Database connection failed.'];
        }

        $payment = $this->getPaymentById($payment_id);
        if (!$payment) {
            return ['success' => false, 'error' => 'Payment not found.'];
        }

        try {
            $this->conn->beginTransaction();

            // Step 1: Delete the payment record
            $stmt_delete_payment = $this->conn->prepare("DELETE FROM payments WHERE id = ?");
            $stmt_delete_payment->execute([$payment_id]);
            
            if ($stmt_delete_payment->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'error' => 'Failed to delete payment record.'];
            }

            // Step 2: Update the invoice's total_paid and status
            $amount_reverted = (float)$payment['amount_paid'];
            $invoice_id = (int)$payment['invoice_id'];

            $stmt_update_invoice = $this->conn->prepare("
                UPDATE invoices
                SET total_paid = total_paid - :amount_reverted,
                    status = CASE
                                WHEN (total_net_amount - (total_paid - :amount_reverted_for_status)) <= 0.005 THEN 'Paid'
                                WHEN (total_paid - :amount_reverted_for_status) > 0 THEN 'Partially Paid'
                                ELSE 'Sent' -- Or 'Draft' or initial status if no other payments and not Sent. This logic might need refinement.
                             END,
                    updated_by_user_id = :user_id,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :invoice_id
            ");
            // Note: The status logic on payment deletion can be complex.
            // If all payments are removed, what should the status be?
            // 'Sent' if it was sent? 'Draft' if it was never sent?
            // For simplicity, if total_paid becomes 0, it might revert to 'Sent' (if it was sent) or 'Draft'.
            // A more robust solution might involve checking the invoice's history or having a "default_status_after_payment_reversal".
            // The current logic assumes if balance becomes due again, it's 'Partially Paid'. If total_paid becomes 0, it will need careful check.
            // If `total_paid - amount_reverted` is 0, status becomes 'Sent'.
            // If `total_paid - amount_reverted` < `total_net_amount` and > 0, status is 'Partially Paid'.
            // If `total_paid - amount_reverted` makes `balance_due` <= 0 (e.g. another payment covers it), status is 'Paid'.

            $stmt_update_invoice->bindParam(':amount_reverted', $amount_reverted, PDO::PARAM_STR);
            $stmt_update_invoice->bindParam(':amount_reverted_for_status', $amount_reverted, PDO::PARAM_STR);
            $stmt_update_invoice->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_update_invoice->bindParam(':invoice_id', $invoice_id, PDO::PARAM_INT);
            $stmt_update_invoice->execute();

            $this->conn->commit();
            // TODO: ActivityLogger::log('payment_deleted', $payment_id, $user_id, "Payment ID {$payment_id} of {$amount_reverted} for invoice ID {$invoice_id} deleted.");
            return ['success' => true];

        } catch (PDOException $e) {
            if ($this->conn && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("PDOException in deletePayment: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error during payment deletion: ' . $e->getMessage()];
        } catch (Exception $e) {
            if ($this->conn && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Exception in deletePayment: " . $e->getMessage());
            return ['success' => false, 'error' => 'An unexpected error occurred: ' . $e->getMessage()];
        }
    }
}