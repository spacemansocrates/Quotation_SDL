<?php
require_once 'Database.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Adjusted path for vendor autoload

use Picqer\Barcode\BarcodeGeneratorPNG;

class InventoryManager {
    private $conn;
    private $db; // Database class instance

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        if (!$this->conn) {
            // Handle connection failure, perhaps by throwing an exception
            // or ensuring methods check $this->conn before proceeding.
            // For simplicity here, we assume connect() will echo/log and exit or return null.
            // Subsequent operations will fail if $this->conn is null.
            // Consider a more robust error handling strategy for production.
            throw new Exception("Failed to connect to the database.");
        }
    }

    /**
     * Generate barcode for a product (if not exists)
     * or retrieve existing one.
     */
    public function generateProductBarcode($product_id) {
        $stmt = $this->conn->prepare("SELECT barcode FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !empty($result['barcode'])) {
            return $result['barcode'];
        }

        // Generate unique barcode content
        // Example: PROD_000001 (ensure this meets your barcode scanner's symbology requirements)
        $barcode_content = "PROD_" . str_pad($product_id, 6, "0", STR_PAD_LEFT);

        // Update product with the newly generated barcode
        $stmt_update = $this->conn->prepare("UPDATE products SET barcode = ? WHERE id = ?");
        $stmt_update->execute([$barcode_content, $product_id]);

        return $barcode_content;
    }

    /**
     * Add stock to inventory
     */
    public function addStock($product_id, $quantity, $user_id, $reference_type = 'receipt', $reference_id = null, $reference_number = null, $notes = '') {
        if ($quantity <= 0) {
            return ['success' => false, 'error' => 'Quantity must be positive.'];
        }
        try {
            $this->conn->beginTransaction();

            $current_stock_data = $this->getCurrentStockInfo($product_id);
            $current_quantity_in_stock = $current_stock_data['quantity_in_stock'];
            $new_quantity_in_stock = $current_quantity_in_stock + $quantity;

            if ($current_stock_data['exists']) {
                 $stmt_update_stock = $this->conn->prepare("
                    UPDATE inventory_stock
                    SET quantity_in_stock = quantity_in_stock + ?,
                        total_received = total_received + ?
                    WHERE product_id = ?
                ");
                $stmt_update_stock->execute([$quantity, $quantity, $product_id]);
            } else {
                // If product is not in inventory_stock yet, insert it.
                // Default minimum_stock_level to 0, can be set elsewhere.
                $stmt_insert_stock = $this->conn->prepare("
                    INSERT INTO inventory_stock (product_id, quantity_in_stock, total_received, minimum_stock_level)
                    VALUES (?, ?, ?, 0)
                ");
                $stmt_insert_stock->execute([$product_id, $quantity, $quantity]);
            }

            // Record the transaction
            $this->recordTransaction(
                $product_id,
                'stock_in',
                $quantity,
                $new_quantity_in_stock, // This is the running balance after this transaction
                $reference_type,
                $reference_id,
                $reference_number,
                $user_id,
                $notes
            );

            $this->conn->commit();
            return ['success' => true, 'new_stock' => $new_quantity_in_stock];

        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Remove stock from inventory (when scanned out)
     */
    public function removeStock($barcode, $quantity_to_remove, $user_id, $reference_type = 'sale', $reference_id = null, $reference_number = null, $notes = '') {
        if ($quantity_to_remove <= 0) {
            return ['success' => false, 'error' => 'Quantity to remove must be positive.'];
        }
        try {
            $this->conn->beginTransaction();

            // Get product ID from barcode
            $stmt_product = $this->conn->prepare("SELECT id, name, sku FROM products WHERE barcode = ?");
            $stmt_product->execute([$barcode]);
            $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception("Product not found for barcode: " . htmlspecialchars($barcode));
            }
            $product_id = $product['id'];

            $current_stock_info = $this->getCurrentStockInfo($product_id);
            $current_quantity_in_stock = $current_stock_info['quantity_in_stock'];

            if (!$current_stock_info['exists'] || $current_quantity_in_stock < $quantity_to_remove) {
                throw new Exception("Not enough stock available for product: " . htmlspecialchars($product['name']) . ". Available: " . $current_quantity_in_stock . ", Requested: " . $quantity_to_remove);
            }

            $new_quantity_in_stock = $current_quantity_in_stock - $quantity_to_remove;

            // Update inventory_stock
            $stmt_update_stock = $this->conn->prepare("
                UPDATE inventory_stock
                SET quantity_in_stock = quantity_in_stock - ?,
                    total_sold = total_sold + ?
                WHERE product_id = ?
            ");
            $stmt_update_stock->execute([$quantity_to_remove, $quantity_to_remove, $product_id]);

            // Record transaction
            $this->recordTransaction(
                $product_id,
                'stock_out',
                $quantity_to_remove,
                $new_quantity_in_stock, // Running balance after this transaction
                $reference_type,
                $reference_id,
                $reference_number,
                $user_id,
                $notes
            );

            $this->conn->commit();
            return [
                'success' => true,
                'product' => $product,
                'new_stock' => $new_quantity_in_stock,
                'removed_quantity' => $quantity_to_remove,
                'message' => $quantity_to_remove . " item(s) of '" . htmlspecialchars($product['name']) . "' scanned out successfully. Remaining stock: " . $new_quantity_in_stock
            ];

        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get current stock quantity for a product
     */
    public function getCurrentStock($product_id) {
        $stmt = $this->conn->prepare("SELECT quantity_in_stock FROM inventory_stock WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['quantity_in_stock'] : 0;
    }

    /**
     * Helper to get full stock info or check existence
     */
    private function getCurrentStockInfo($product_id) {
        $stmt = $this->conn->prepare("SELECT quantity_in_stock, total_received, total_sold, minimum_stock_level FROM inventory_stock WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return [
                'exists' => true,
                'quantity_in_stock' => (int)$result['quantity_in_stock'],
                'total_received' => (int)$result['total_received'],
                'total_sold' => (int)$result['total_sold'],
                'minimum_stock_level' => (int)$result['minimum_stock_level']
            ];
        }
        return [
            'exists' => false,
            'quantity_in_stock' => 0,
            'total_received' => 0,
            'total_sold' => 0,
            'minimum_stock_level' => 0 // Default if not found
        ];
    }

    /**
     * Record stock transaction
     */
    private function recordTransaction($product_id, $transaction_type, $quantity, $running_balance, $reference_type, $reference_id, $reference_number, $user_id, $notes) {
        $stmt = $this->conn->prepare("
            INSERT INTO stock_transactions
            (product_id, transaction_type, quantity, running_balance, reference_type, reference_id, reference_number, scanned_by_user_id, notes, transaction_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$product_id, $transaction_type, $quantity, $running_balance, $reference_type, $reference_id, $reference_number, $user_id, $notes]);
    }

    /**
     * Generate printable Barcodes for stock addition
     */
    public function generatePrintableBarcodes($product_id, $quantity_to_print, $user_id) {
        if ($quantity_to_print <= 0) {
             return ['success' => false, 'error' => 'Quantity to print must be positive.'];
        }
        try {
            $this->conn->beginTransaction();

            // Generate or retrieve barcode for product
            $barcode_content = $this->generateProductBarcode($product_id);

            // Get product details
            $stmt_product = $this->conn->prepare("SELECT name, sku, description FROM products WHERE id = ?");
            $stmt_product->execute([$product_id]);
            $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception("Product not found for ID: " . $product_id);
            }

            // Create batch record
            $batch_reference = 'BATCH_' . date('YmdHis') . '_' . $product_id . '_' . uniqid();
            $stmt_batch = $this->conn->prepare("
                INSERT INTO barcode_print_batches (product_id, batch_reference, quantity_printed, printed_by_user_id, print_date)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt_batch->execute([$product_id, $batch_reference, $quantity_to_print, $user_id]);

            // Generate barcode image using Picqer library
            $generator = new BarcodeGeneratorPNG();
            // Common barcode types: TYPE_CODE_128, TYPE_CODE_39, TYPE_EAN_13, TYPE_UPC_A
            // TYPE_CODE_128 is versatile for alphanumeric data.
            // Parameters: code, type, widthFactor, height, foregroundColor (array [R,G,B])
            $barcode_image_data = $generator->getBarcode($barcode_content, $generator::TYPE_CODE_128, 2, 50, [0, 0, 0]);
            $barcode_image_base64 = base64_encode($barcode_image_data);

            $this->conn->commit();

            return [
                'success' => true,
                'barcode_content' => $barcode_content,
                'barcode_image_base64' => $barcode_image_base64,
                'product' => $product,
                'quantity' => $quantity_to_print,
                'batch_reference' => $batch_reference
            ];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get stock report
     */
    public function getStockReport() {
        $stmt = $this->conn->prepare("
            SELECT
                p.id,
                p.name,
                p.sku,
                p.barcode,
                COALESCE(i.quantity_in_stock, 0) as current_stock,
                COALESCE(i.total_received, 0) as total_received,
                COALESCE(i.total_sold, 0) as total_sold,
                COALESCE(i.minimum_stock_level, 0) as minimum_stock_level,
                i.last_updated,
                CASE
                    WHEN COALESCE(i.quantity_in_stock, 0) <= 0 THEN 'OUT_OF_STOCK'
                    WHEN COALESCE(i.quantity_in_stock, 0) <= COALESCE(i.minimum_stock_level, 0) THEN 'LOW_STOCK'
                    ELSE 'IN_STOCK'
                END as stock_status
            FROM products p
            LEFT JOIN inventory_stock i ON p.id = i.product_id
            ORDER BY p.name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get transaction history for a product
     */
    public function getTransactionHistory($product_id, $limit = 50) {
        // Assuming you have a 'users' table with 'id' and 'username' columns.
        // Adjust join if your users table structure is different.
        $stmt = $this->conn->prepare("
            SELECT
                st.id as transaction_id,
                st.transaction_type,
                st.quantity,
                st.running_balance,
                st.reference_type,
                st.reference_id,
                st.reference_number,
                st.notes,
                st.transaction_date,
                st.created_at,
                u.username as scanned_by_username, -- Or u.name, u.email, etc.
                p.name as product_name,
                p.sku as product_sku
            FROM stock_transactions st
            JOIN products p ON st.product_id = p.id
            LEFT JOIN users u ON st.scanned_by_user_id = u.id
            WHERE st.product_id = ?
            ORDER BY st.transaction_date DESC, st.id DESC
            LIMIT ?
        ");
        $stmt->bindParam(1, $product_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Set minimum stock level for a product
     */
    public function setMinimumStockLevel($product_id, $minimum_level) {
        if ($minimum_level < 0) {
             return ['success' => false, 'error' => 'Minimum stock level cannot be negative.'];
        }
        try {
            // Ensure product exists in inventory_stock, otherwise insert it with the min level
            $stmt = $this->conn->prepare("
                INSERT INTO inventory_stock (product_id, minimum_stock_level, quantity_in_stock, total_received, total_sold)
                VALUES (?, ?, 0, 0, 0)
                ON DUPLICATE KEY UPDATE
                minimum_stock_level = VALUES(minimum_stock_level)
            ");
            $stmt->execute([$product_id, $minimum_level]);
            return ['success' => true, 'message' => 'Minimum stock level updated.'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get product details by barcode
     */
    public function getProductByBarcode($barcode) {
        $stmt = $this->conn->prepare("
            SELECT 
                p.id, p.name, p.sku, p.description, p.category_id, p.price, p.barcode,
                c.name as category_name,
                COALESCE(i.quantity_in_stock, 0) as current_stock,
                COALESCE(i.minimum_stock_level, 0) as minimum_stock_level
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id -- Assuming you have a categories table
            LEFT JOIN inventory_stock i ON p.id = i.product_id
            WHERE p.barcode = ?
        ");
        $stmt->execute([$barcode]);
        return $stmt->fetch(PDO::FETCH_ASSOC); // Returns false if not found
    }
}
?>