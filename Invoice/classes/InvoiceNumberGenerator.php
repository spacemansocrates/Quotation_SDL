<?php
/**
 * InvoiceNumberGenerator Class
 * 
 * Generates unique invoice numbers using the format: I-ShopCode/CustomerCode-SequentialNumber
 * Example: I-MAIN/CUST001-001, I-MAIN/CUST001-002, etc.
 * 
 * Features:
 * - Thread-safe sequence generation using database locks
 * - Automatic sequence initialization for new shop/customer combinations
 * - Customizable formatting options
 * - Error handling and logging
 */

// Uncomment the line below if not using autoloading
// require_once 'Database.php';

class InvoiceNumberGenerator {
    private $conn;
    private $db;
    private $prefix;
    private $sequenceLength;
    private $separator;

    public function __construct($prefix = 'I-', $sequenceLength = 3, $separator = '-') {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        $this->prefix = $prefix;
        $this->sequenceLength = $sequenceLength;
        $this->separator = $separator;
    }

    /**
     * Generate a new invoice number for the given shop and customer
     * 
     * @param int $shop_id Shop ID
     * @param int $customer_id Customer ID
     * @return string Generated invoice number
     * @throws Exception If generation fails
     */
    public function generate($shop_id, $customer_id) {
        if (!$this->conn) {
            throw new Exception("Database connection failed for InvoiceNumberGenerator.");
        }

        if (!$shop_id || !$customer_id) {
            throw new Exception("Valid shop_id and customer_id are required for invoice number generation.");
        }

        try {
            $this->conn->beginTransaction();

            // Get shop code with validation
            $shop_code = $this->getShopCode($shop_id);
            if (!$shop_code) {
                throw new Exception("Shop not found or shop_code is empty for shop_id: $shop_id");
            }

            // Get customer code with validation
            $customer_code = $this->getCustomerCode($customer_id);
            if (!$customer_code) {
                throw new Exception("Customer not found or customer_code is empty for customer_id: $customer_id");
            }

            // Get and increment sequence number with row locking
            $next_sequence = $this->getNextSequenceNumber($shop_id, $customer_id);

            $this->conn->commit();

            // Format the invoice number
            $formatted_sequence = str_pad($next_sequence, $this->sequenceLength, "0", STR_PAD_LEFT);
            $invoice_number = $this->prefix . $shop_code . "/" . $customer_code . $this->separator . $formatted_sequence;

            // Log successful generation
            error_log("InvoiceNumberGenerator: Generated invoice number '$invoice_number' for shop_id: $shop_id, customer_id: $customer_id");

            return $invoice_number;

        } catch (Exception $e) {
            if ($this->conn && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("InvoiceNumberGenerator: Error generating invoice number - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get shop code from database
     * 
     * @param int $shop_id Shop ID
     * @return string|null Shop code or null if not found
     * @throws Exception If database error occurs
     */
    private function getShopCode($shop_id) {
        try {
            $stmt = $this->conn->prepare("SELECT shop_code FROM shops WHERE id = ? AND shop_code IS NOT NULL AND shop_code != ''");
            $stmt->execute([$shop_id]);
            $shop = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $shop ? trim($shop['shop_code']) : null;
        } catch (PDOException $e) {
            throw new Exception("Error retrieving shop code: " . $e->getMessage());
        }
    }

    /**
     * Get customer code from database
     * 
     * @param int $customer_id Customer ID
     * @return string|null Customer code or null if not found
     * @throws Exception If database error occurs
     */
    private function getCustomerCode($customer_id) {
        try {
            $stmt = $this->conn->prepare("SELECT customer_code FROM customers WHERE id = ? AND customer_code IS NOT NULL AND customer_code != ''");
            $stmt->execute([$customer_id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $customer ? trim($customer['customer_code']) : null;
        } catch (PDOException $e) {
            throw new Exception("Error retrieving customer code: " . $e->getMessage());
        }
    }

    /**
     * Get next sequence number with row locking for thread safety
     * 
     * @param int $shop_id Shop ID
     * @param int $customer_id Customer ID
     * @return int Next sequence number
     * @throws Exception If database error occurs
     */
    private function getNextSequenceNumber($shop_id, $customer_id) {
        try {
            // Use SELECT FOR UPDATE to lock the row and prevent race conditions
            $stmt_seq = $this->conn->prepare(
                "SELECT last_sequence_number FROM invoice_sequences WHERE shop_id = ? AND customer_id = ? FOR UPDATE"
            );
            $stmt_seq->execute([$shop_id, $customer_id]);
            $sequence_data = $stmt_seq->fetch(PDO::FETCH_ASSOC);

            $next_sequence = $sequence_data ? $sequence_data['last_sequence_number'] + 1 : 1;

            // Insert or update the sequence record
            $stmt_update_seq = $this->conn->prepare(
                "INSERT INTO invoice_sequences (shop_id, customer_id, last_sequence_number) 
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE last_sequence_number = ?"
            );
            $stmt_update_seq->execute([$shop_id, $customer_id, $next_sequence, $next_sequence]);

            return $next_sequence;
        } catch (PDOException $e) {
            throw new Exception("Error managing sequence number: " . $e->getMessage());
        }
    }

    /**
     * Get current sequence number without incrementing
     * 
     * @param int $shop_id Shop ID
     * @param int $customer_id Customer ID
     * @return int Current sequence number (0 if none exists)
     */
    public function getCurrentSequence($shop_id, $customer_id) {
        if (!$this->conn) {
            return 0;
        }

        try {
            $stmt = $this->conn->prepare("SELECT last_sequence_number FROM invoice_sequences WHERE shop_id = ? AND customer_id = ?");
            $stmt->execute([$shop_id, $customer_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? (int)$result['last_sequence_number'] : 0;
        } catch (PDOException $e) {
            error_log("InvoiceNumberGenerator: Error getting current sequence - " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Preview what the next invoice number would be without generating it
     * 
     * @param int $shop_id Shop ID
     * @param int $customer_id Customer ID
     * @return string|null Preview of next invoice number or null if error
     */
    public function previewNext($shop_id, $customer_id) {
        if (!$this->conn) {
            return null;
        }

        try {
            $shop_code = $this->getShopCode($shop_id);
            $customer_code = $this->getCustomerCode($customer_id);
            
            if (!$shop_code || !$customer_code) {
                return null;
            }

            $current_sequence = $this->getCurrentSequence($shop_id, $customer_id);
            $next_sequence = $current_sequence + 1;
            $formatted_sequence = str_pad($next_sequence, $this->sequenceLength, "0", STR_PAD_LEFT);
            
            return $this->prefix . $shop_code . "/" . $customer_code . $this->separator . $formatted_sequence;
        } catch (Exception $e) {
            error_log("InvoiceNumberGenerator: Error previewing next invoice number - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if an invoice number already exists
     * 
     * @param string $invoice_number Invoice number to check
     * @return bool True if exists, false otherwise
     */
    public function invoiceNumberExists($invoice_number) {
        if (!$this->conn) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = ?");
            $stmt->execute([$invoice_number]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("InvoiceNumberGenerator: Error checking invoice number existence - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset sequence for a shop/customer combination (use with caution)
     * 
     * @param int $shop_id Shop ID
     * @param int $customer_id Customer ID
     * @param int $new_sequence New sequence number (default: 0)
     * @return bool Success status
     */
    public function resetSequence($shop_id, $customer_id, $new_sequence = 0) {
        if (!$this->conn) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO invoice_sequences (shop_id, customer_id, last_sequence_number) 
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE last_sequence_number = ?"
            );
            $stmt->execute([$shop_id, $customer_id, $new_sequence, $new_sequence]);
            
            error_log("InvoiceNumberGenerator: Reset sequence for shop_id: $shop_id, customer_id: $customer_id to $new_sequence");
            return true;
        } catch (PDOException $e) {
            error_log("InvoiceNumberGenerator: Error resetting sequence - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all sequences for a specific shop (useful for reporting)
     * 
     * @param int $shop_id Shop ID
     * @return array Array of sequences with customer details
     */
    public function getShopSequences($shop_id) {
        if (!$this->conn) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    iseq.customer_id,
                    iseq.last_sequence_number,
                    c.customer_code,
                    c.name as customer_name
                FROM invoice_sequences iseq
                JOIN customers c ON iseq.customer_id = c.id
                WHERE iseq.shop_id = ?
                ORDER BY c.name
            ");
            $stmt->execute([$shop_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("InvoiceNumberGenerator: Error getting shop sequences - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Validate invoice number format
     * 
     * @param string $invoice_number Invoice number to validate
     * @return bool True if format is valid, false otherwise
     */
    public function validateFormat($invoice_number) {
        // Basic format validation: PREFIX-SHOPCODE/CUSTOMERCODE-SEQUENCE
        $pattern = '/^' . preg_quote($this->prefix, '/') . '[A-Z0-9]+\/[A-Z0-9]+' . preg_quote($this->separator, '/') . '\d{' . $this->sequenceLength . ',}$/';
        return preg_match($pattern, $invoice_number) === 1;
    }

    /**
     * Parse invoice number to extract components
     * 
     * @param string $invoice_number Invoice number to parse
     * @return array|null Array with components or null if invalid format
     */
    public function parseInvoiceNumber($invoice_number) {
        if (!$this->validateFormat($invoice_number)) {
            return null;
        }

        // Remove prefix
        $without_prefix = substr($invoice_number, strlen($this->prefix));
        
        // Split by separator to get shop/customer part and sequence
        $parts = explode($this->separator, $without_prefix);
        if (count($parts) !== 2) {
            return null;
        }

        // Split shop/customer part
        $shop_customer_parts = explode('/', $parts[0]);
        if (count($shop_customer_parts) !== 2) {
            return null;
        }

        return [
            'prefix' => $this->prefix,
            'shop_code' => $shop_customer_parts[0],
            'customer_code' => $shop_customer_parts[1],
            'sequence' => (int)$parts[1],
            'full_number' => $invoice_number
        ];
    }

    /**
     * Set custom formatting options
     * 
     * @param string $prefix Invoice number prefix (default: 'I-')
     * @param int $sequenceLength Number of digits for sequence (default: 3)
     * @param string $separator Separator before sequence (default: '-')
     */
    public function setFormat($prefix = 'I-', $sequenceLength = 3, $separator = '-') {
        $this->prefix = $prefix;
        $this->sequenceLength = max(1, (int)$sequenceLength); // Ensure at least 1 digit
        $this->separator = $separator;
    }

    /**
     * Get current format settings
     * 
     * @return array Current format configuration
     */
    public function getFormat() {
        return [
            'prefix' => $this->prefix,
            'sequence_length' => $this->sequenceLength,
            'separator' => $this->separator
        ];
    }
}
?>