<?php
// Assuming classes are autoloaded (e.g., via Composer)
// If not, uncomment these lines:
// require_once __DIR__ . '/Database.php';
// require_once __DIR__ . '/InvoiceNumberGenerator.php';
// require_once __DIR__ . '/SettingsManager.php';
// require_once __DIR__ . '/InventoryManager.php'; // Assuming this exists for stock management
// require_once __DIR__ . '/../models/User.php'; // Or however you access user details

class InvoiceManager {
    private $conn;
    private $db;
    private $invNumGen;
    private $settingsManager;
    private $inventoryManager; // For stock updates

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        // Ensure dependencies are only instantiated if the connection is successful
        if ($this->conn) {
            $this->invNumGen = new InvoiceNumberGenerator(); // Assumes it connects its own DB or uses the same instance
            $this->settingsManager = new SettingsManager();
            $this->inventoryManager = new InventoryManager(); // Assumes it handles its own DB connection
        } else {
            // Handle connection failure for the InvoiceManager itself
            // This might involve logging the error and making methods return appropriate error states
            error_log("InvoiceManager: Failed to establish database connection.");
        }
    }

    /**
     * Creates a new invoice.
     * $data array includes: shop_id, customer_id, invoice_date, items (array of item data), etc.
     * $user_id for tracking who created it.
     */
    public function createInvoice(array $data, int $user_id): array {
        if (!$this->conn) {
            return ['success' => false, 'error' => 'Database connection failed in InvoiceManager.'];
        }

        // Validate required fields
        $required_fields = ['shop_id', 'customer_id', 'invoice_date', 'items'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                return ['success' => false, 'error' => "Missing required field: $field"];
            }
        }
        if (empty($data['items']) || !is_array($data['items'])) {
            return ['success' => false, 'error' => 'Invoice must have at least one item.'];
        }


        try {
            $this->conn->beginTransaction();

            $invoice_number = $this->invNumGen->generate((int)$data['shop_id'], (int)$data['customer_id']);

            // Fetch default tax/levy settings
            $defaults = $this->settingsManager->getInvoiceDefaults();

            // Determine effective tax/levy settings
            $apply_ppda = isset($data['apply_ppda_levy']) ? (bool)$data['apply_ppda_levy'] : $defaults['apply_ppda_levy'];
            $ppda_percentage = isset($data['ppda_levy_percentage']) ? (float)$data['ppda_levy_percentage'] : (float)$defaults['ppda_levy_percentage'];
            $vat_percentage = isset($data['vat_percentage']) ? (float)$data['vat_percentage'] : (float)$defaults['vat_percentage'];

            // Calculate totals
            $calculated_totals = $this->calculateInvoiceTotalsInternal($data['items'], $apply_ppda, $ppda_percentage, $vat_percentage);

            // Fetch company TPIN - ideally from shop or company settings if not explicitly passed
            // For now, assume it's in $data or null
            $company_tpin = $data['company_tpin'] ?? $this->settingsManager->getSetting('company_tpin'); // Example of fetching a default TPIN

            $stmt_invoice = $this->conn->prepare("
                INSERT INTO invoices (
                    invoice_number, quotation_id, shop_id, customer_id, customer_name_override,
                    customer_address_override, invoice_date, due_date, company_tpin, notes_general,
                    delivery_period, payment_terms, apply_ppda_levy, ppda_levy_percentage,
                    vat_percentage, gross_total_amount, ppda_levy_amount, amount_before_vat,
                    vat_amount, total_net_amount, status, created_by_user_id, updated_by_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_invoice->execute([
                $invoice_number,
                $data['quotation_id'] ?? null,
                (int)$data['shop_id'],
                (int)$data['customer_id'],
                $data['customer_name_override'] ?? null,
                $data['customer_address_override'] ?? null,
                $data['invoice_date'],
                $data['due_date'] ?? null,
                $company_tpin,
                $data['notes_general'] ?? null,
                $data['delivery_period'] ?? null,
                $data['payment_terms'] ?? null,
                (int)$apply_ppda, // Store as tinyint
                $ppda_percentage,
                $vat_percentage,
                $calculated_totals['gross_total_amount'],
                $calculated_totals['ppda_levy_amount'],
                $calculated_totals['amount_before_vat'],
                $calculated_totals['vat_amount'],
                $calculated_totals['total_net_amount'],
                $data['status'] ?? 'Draft',
                $user_id,
                $user_id // Set updated_by_user_id to creator on creation
            ]);
            $invoice_id = $this->conn->lastInsertId();

            // Insert invoice items
            $stmt_item = $this->conn->prepare("
                INSERT INTO invoice_items (
                    invoice_id, product_id, item_number, description, quantity,
                    unit_of_measurement, rate_per_unit, created_by_user_id, updated_by_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($data['items'] as $index => $item) {
                if (empty($item['description']) || !isset($item['quantity']) || !isset($item['rate_per_unit'])) {
                    throw new Exception("Invalid item data for item at index $index.");
                }
                $stmt_item->execute([
                    $invoice_id,
                    isset($item['product_id']) && $item['product_id'] ? (int)$item['product_id'] : null,
                    $index + 1, // item_number
                    $item['description'],
                    (float)$item['quantity'],
                    $item['unit_of_measurement'] ?? null,
                    (float)$item['rate_per_unit'],
                    $user_id,
                    $user_id
                ]);
            }

            $this->conn->commit();
            // TODO: ActivityLogger::log('invoice_created', $invoice_id, $user_id, "Invoice $invoice_number created.");
            return ['success' => true, 'invoice_id' => $invoice_id, 'invoice_number' => $invoice_number];

        } catch (PDOException $e) {
            if ($this->conn && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("PDOException in createInvoice: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred: ' . $e->getMessage()];
        } catch (Exception $e) {
            if ($this->conn && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Exception in createInvoice: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetches quotation data and prepares it for invoice creation.
     */
    public function prepareInvoiceFromQuotation(int $quotation_id): ?array {
        if (!$this->conn) {
            error_log("Database connection not available for prepareInvoiceFromQuotation.");
            return null;
        }
        // Assuming `quotations` table has shop_id, customer_id, customer_name_override etc.
        // and `quotation_items` has product_id, description, quantity, etc.
        // The query also assumes `quotations` has tax/levy flags and percentages.
        $stmt = $this->conn->prepare("
            SELECT
                q.id as quotation_id_val, q.shop_id, q.customer_id, q.customer_name_override,
                q.customer_address_override, q.notes_general, q.delivery_period, q.payment_terms,
                q.apply_ppda_levy, q.ppda_levy_percentage, q.vat_percentage,
                qi.product_id, qi.description, qi.quantity, qi.unit_of_measurement, qi.rate_per_unit, qi.item_number
            FROM quotations q
            LEFT JOIN quotation_items qi ON q.id = qi.quotation_id
            WHERE q.id = ?
            ORDER BY qi.item_number ASC
        ");
        $stmt->execute([$quotation_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return null;
        }

        $quotation_data = [
            'quotation_id' => $rows[0]['quotation_id_val'], // Original quotation ID
            'shop_id' => $rows[0]['shop_id'],
            'customer_id' => $rows[0]['customer_id'],
            'customer_name_override' => $rows[0]['customer_name_override'],
            'customer_address_override' => $rows[0]['customer_address_override'],
            'notes_general' => $rows[0]['notes_general'],
            'delivery_period' => $rows[0]['delivery_period'],
            'payment_terms' => $rows[0]['payment_terms'],
            'apply_ppda_levy' => (bool)$rows[0]['apply_ppda_levy'],
            'ppda_levy_percentage' => (float)$rows[0]['ppda_levy_percentage'],
            'vat_percentage' => (float)$rows[0]['vat_percentage'],
            'items' => []
        ];

        foreach ($rows as $row) {
            if ($row['description'] !== null) { // Check if item exists (due to LEFT JOIN)
                $quotation_data['items'][] = [
                    'product_id' => $row['product_id'],
                    'description' => $row['description'],
                    'quantity' => (float)$row['quantity'],
                    'unit_of_measurement' => $row['unit_of_measurement'],
                    'rate_per_unit' => (float)$row['rate_per_unit']
                    // 'image_path_override' => $row['image_path_override'] ?? null, // If on quotation_items
                ];
            }
        }
        return $quotation_data;
    }


    /**
     * Internal method to calculate gross, levy, VAT, and net totals.
     * $items is an array of ['quantity' => x, 'rate_per_unit' => y]
     */
    private function calculateInvoiceTotalsInternal(array $items, bool $apply_ppda_levy, float $ppda_levy_percentage, float $vat_percentage): array {
        $gross_total_amount = 0.00;
        foreach ($items as $item) {
            $quantity = isset($item['quantity']) ? (float)$item['quantity'] : 0;
            $rate_per_unit = isset($item['rate_per_unit']) ? (float)$item['rate_per_unit'] : 0;
            $gross_total_amount += $quantity * $rate_per_unit;
        }

        $ppda_levy_amount = 0.00;
        if ($apply_ppda_levy) {
            $ppda_levy_amount = $gross_total_amount * ($ppda_levy_percentage / 100.0);
        }

        $amount_before_vat = $gross_total_amount + $ppda_levy_amount;
        $vat_amount = $amount_before_vat * ($vat_percentage / 100.0);
        $total_net_amount = $amount_before_vat + $vat_amount;

        return [
            'gross_total_amount' => round($gross_total_amount, 2),
            'ppda_levy_amount' => round($ppda_levy_amount, 2),
            'amount_before_vat' => round($amount_before_vat, 2),
            'vat_amount' => round($vat_amount, 2),
            'total_net_amount' => round($total_net_amount, 2)
        ];
    }

    public function getInvoiceById(int $invoice_id): ?array {
        if (!$this->conn) {
            error_log("Database connection not available for getInvoiceById.");
            return null;
        }
        // Fetch invoice header
        $stmt_invoice = $this->conn->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt_invoice->execute([$invoice_id]);
        $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return null;
        }

        // Fetch invoice items
        $stmt_items = $this->conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY item_number ASC");
        $stmt_items->execute([$invoice_id]);
        $invoice['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        // Fetch customer details (assuming 'customers' table exists)
        $stmt_customer = $this->conn->prepare("SELECT id, name, customer_code, email, phone, address, tpin_number FROM customers WHERE id = ?");
        $stmt_customer->execute([$invoice['customer_id']]);
        $invoice['customer_details'] = $stmt_customer->fetch(PDO::FETCH_ASSOC);
        // If customer_name_override or address_override is set, use that
        if (!empty($invoice['customer_name_override'])) {
            $invoice['customer_details']['name'] = $invoice['customer_name_override'];
        }
        if (!empty($invoice['customer_address_override'])) {
            $invoice['customer_details']['address'] = $invoice['customer_address_override'];
        }


        // Fetch shop details (assuming 'shops' table exists)
        $stmt_shop = $this->conn->prepare("SELECT id, name, shop_code, address, phone, email, tpin FROM shops WHERE id = ?");
        $stmt_shop->execute([$invoice['shop_id']]);
        $invoice['shop_details'] = $stmt_shop->fetch(PDO::FETCH_ASSOC);
        // If invoice.company_tpin is set (snapshot), use it for the invoice display.
        // Otherwise, use the current shop TPIN.
        if (empty($invoice['company_tpin']) && isset($invoice['shop_details']['tpin'])) {
             $invoice['company_tpin_effective'] = $invoice['shop_details']['tpin'];
        } else {
             $invoice['company_tpin_effective'] = $invoice['company_tpin'];
        }


        // Fetch payments for this invoice
        $paymentManager = new PaymentManager(); // Or pass $this->conn if PaymentManager can accept it
        $invoice['payments'] = $paymentManager->getPaymentsByInvoice($invoice_id);


        // Convert numeric string types to actual numeric types for calculations if needed in frontend
        $numeric_fields_invoice = ['gross_total_amount', 'ppda_levy_percentage', 'ppda_levy_amount', 'vat_percentage', 'vat_amount', 'amount_before_vat', 'total_net_amount', 'total_paid', 'balance_due'];
        foreach ($numeric_fields_invoice as $field) {
            if (isset($invoice[$field])) {
                $invoice[$field] = (float)$invoice[$field];
            }
        }
        foreach ($invoice['items'] as &$item) {
            $numeric_fields_items = ['quantity', 'rate_per_unit', 'total_amount'];
            foreach ($numeric_fields_items as $field) {
                if (isset($item[$field])) {
                    $item[$field] = (float)$item[$field];
                }
            }
        }
        unset($item);


        return $invoice;
    }

    /**
     * Lists invoices with filtering and pagination.
     * $filters: ['customer_id' => X, 'shop_id' => Y, 'status' => 'Paid', 'date_from' => 'Y-m-d', 'date_to' => 'Y-m-d', 'search_term' => '...']
     * $pagination: ['page' => 1, 'per_page' => 20]
     * $orderBy: ['column' => 'invoice_date', 'direction' => 'DESC']
     */
    public function listInvoices(array $filters = [], array $pagination = ['page' => 1, 'per_page' => 20], array $orderBy = ['column' => 'invoice_date', 'direction' => 'DESC']): array {
        if (!$this->conn) {
            error_log("Database connection not available for listInvoices.");
            return ['data' => [], 'total_records' => 0, 'current_page' => $pagination['page'], 'per_page' => $pagination['per_page']];
        }

        $select_sql = "SELECT i.id, i.invoice_number, i.invoice_date, i.due_date, i.total_net_amount, i.status, i.total_paid, i.balance_due, 
                       c.name as customer_name, c.customer_code, s.name as shop_name, s.shop_code";
        $count_sql = "SELECT COUNT(i.id)";
        $from_sql = " FROM invoices i
                      JOIN customers c ON i.customer_id = c.id
                      JOIN shops s ON i.shop_id = s.id";
        $where_sql = " WHERE 1=1";
        $params = [];

        if (!empty($filters['customer_id'])) {
            $where_sql .= " AND i.customer_id = :customer_id";
            $params[':customer_id'] = (int)$filters['customer_id'];
        }
        if (!empty($filters['shop_id'])) {
            $where_sql .= " AND i.shop_id = :shop_id";
            $params[':shop_id'] = (int)$filters['shop_id'];
        }
        if (!empty($filters['status'])) {
            $where_sql .= " AND i.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where_sql .= " AND i.invoice_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where_sql .= " AND i.invoice_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        if (!empty($filters['search_term'])) {
            $where_sql .= " AND (i.invoice_number LIKE :search_term OR c.name LIKE :search_term OR s.name LIKE :search_term)";
            $params[':search_term'] = '%' . $filters['search_term'] . '%';
        }

        // Count total records for pagination
        $stmt_count = $this->conn->prepare($count_sql . $from_sql . $where_sql);
        $stmt_count->execute($params);
        $total_records = (int)$stmt_count->fetchColumn();

        // Order by
        $allowed_order_columns = ['invoice_date', 'invoice_number', 'total_net_amount', 'status', 'customer_name', 'shop_name', 'id'];
        $order_column = in_array($orderBy['column'], $allowed_order_columns) ? $orderBy['column'] : 'invoice_date';
        // Handle alias for customer_name and shop_name
        if ($order_column === 'customer_name') $order_column = 'c.name';
        if ($order_column === 'shop_name') $order_column = 's.name';

        $order_direction = strtoupper($orderBy['direction']) === 'ASC' ? 'ASC' : 'DESC';
        $order_sql = " ORDER BY " . $order_column . " " . $order_direction . ", i.id " . $order_direction;


        // Pagination
        $page = max(1, (int)$pagination['page']);
        $per_page = max(1, (int)$pagination['per_page']);
        $offset = ($page - 1) * $per_page;
        $limit_sql = " LIMIT :limit OFFSET :offset";

        $stmt_data = $this->conn->prepare($select_sql . $from_sql . $where_sql . $order_sql . $limit_sql);
        // Bind common params
        foreach ($params as $key => $value) {
            $stmt_data->bindValue($key, $value);
        }
        // Bind limit and offset separately (integer type)
        $stmt_data->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt_data->execute();
        $invoices = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $invoices,
            'total_records' => $total_records,
            'current_page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total_records / $per_page)
        ];
    }

    public function updateInvoiceStatus(int $invoice_id, string $new_status, int $user_id): array {
        if (!$this->conn) {
            return ['success' => false, 'error' => 'Database connection failed.'];
        }
        try {
            // Validate status
            $valid_statuses = ['Draft', 'Sent', 'Paid', 'Partially Paid', 'Overdue', 'Cancelled', 'Void']; // Add more if needed
            if (!in_array($new_status, $valid_statuses)) {
                return ['success' => false, 'error' => 'Invalid invoice status provided.'];
            }

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                UPDATE invoices 
                SET status = ?, updated_by_user_id = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $user_id, $invoice_id]);

            $affected_rows = $stmt->rowCount();
            if ($affected_rows === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'error' => 'Invoice not found or status already set.'];
            }

            // If status changes to a "finalized" state, trigger stock update.
            // "Sent" is a common trigger point for stock deduction.
            // "Paid" or "Partially Paid" might also be considered depending on business logic.
            $finalized_statuses_for_stock_deduction = ['Sent', 'Paid', 'Partially Paid'];
            if (in_array($new_status, $finalized_statuses_for_stock_deduction)) {
                 $this->recordStockOutForInvoice($invoice_id, $user_id); // This can throw an exception
            }
            
            // If status is 'Cancelled' or 'Void', consider reversing stock
            $statuses_for_stock_reversal = ['Cancelled', 'Void'];
            if (in_array($new_status, $statuses_for_stock_reversal)) {
                // $this->reverseStockForInvoice($invoice_id, $user_id); // Implement this if needed
            }


            $this->conn->commit();
            // TODO: ActivityLogger::log('invoice_status_updated', $invoice_id, $user_id, "Invoice status changed to $new_status.");
            return ['success' => true];
        } catch (Exception $e) {
            if ($this->conn && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Exception in updateInvoiceStatus: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Records stock_out transactions for items on a finalized invoice.
     * This should ideally be idempotent or check if stock has already been deducted.
     */
    public function recordStockOutForInvoice(int $invoice_id, int $user_id): bool {
        if (!$this->conn) {
            throw new Exception("DB connection failed for stock out operation.");
        }
        if (!$this->inventoryManager) {
             throw new Exception("InventoryManager not initialized.");
        }

        // Fetch items that have a product_id (meaning they are stockable)
        $stmt_items = $this->conn->prepare("
            SELECT ii.product_id, ii.quantity, ii.description, p.barcode
            FROM invoice_items ii
            JOIN products p ON ii.product_id = p.id
            WHERE ii.invoice_id = ? AND ii.product_id IS NOT NULL AND p.is_stock_item = 1
        ");
        $stmt_items->execute([$invoice_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return true; // No stockable items to process
        }

        // Get invoice_number for reference
        $stmt_inv_num = $this->conn->prepare("SELECT invoice_number, shop_id FROM invoices WHERE id = ?");
        $stmt_inv_num->execute([$invoice_id]);
        $invoice_info = $stmt_inv_num->fetch(PDO::FETCH_ASSOC);
        if (!$invoice_info) {
            throw new Exception("Invoice not found for stock deduction: ID {$invoice_id}");
        }
        $invoice_number = $invoice_info['invoice_number'];
        $shop_id = $invoice_info['shop_id'];


        $all_successful = true;
        foreach ($items as $item) {
            // IMPORTANT: Add idempotency check here.
            // e.g., Check stock_transactions table if a 'invoice_sale' type transaction
            // for this $invoice_id and $item['product_id'] already exists.
            // If it exists, skip or log warning, but don't deduct again.
            // For now, we assume it's a fresh deduction.

            if ($item['barcode']) {
                $result = $this->inventoryManager->removeStock(
                    $item['barcode'], // or $item['product_id'] if InventoryManager can use it
                    (float)$item['quantity'],
                    $shop_id, // Pass shop_id for context if InventoryManager needs it
                    $user_id,
                    'invoice_sale', // transaction_type or reference_type
                    $invoice_id,     // reference_id
                    $invoice_number, // reference_number
                    "Sold via Invoice " . $invoice_number . ": " . $item['description'] // notes
                );
                if (!$result['success']) {
                    $all_successful = false;
                    // Log error or collect errors to report back
                    error_log("Failed to remove stock for product_id {$item['product_id']} (Barcode: {$item['barcode']}) on invoice {$invoice_id}: {$result['error']}");
                    // Depending on business rules, you might throw an exception here to halt and rollback,
                    // or collect errors and continue. For now, we log and continue.
                }
            } else {
                $all_successful = false;
                error_log("No barcode found for product_id {$item['product_id']} for stock deduction on invoice {$invoice_id}. Item: {$item['description']}");
            }
        }
        if (!$all_successful) {
            // If strict atomicity is required for stock operations tied to invoice status,
            // you might throw an exception here to be caught by updateInvoiceStatus, causing a rollback.
            throw new Exception("One or more stock items could not be processed for invoice ID {$invoice_id}. Check logs.");
        }
        return $all_successful;
    }


    // Placeholder for updating an entire invoice. More complex than status update.
    // public function updateInvoice(int $invoice_id, array $data, int $user_id): array {
    //     if (!$this->conn) return ['success' => false, 'error' => 'Database connection failed.'];
    //     // 1. Check if invoice is in an editable state (e.g., 'Draft')
    //     // 2. Begin transaction
    //     // 3. Update 'invoices' table fields
    //     // 4. Recalculate totals based on new/updated items
    //     // 5. Delete existing 'invoice_items' for this invoice_id
    //     // 6. Insert new 'invoice_items' from $data['items']
    //     // 7. Commit or Rollback
    //     // 8. Return success/error
    //     // This is complex due to item management and recalculations.
    //     return ['success' => false, 'error' => 'UpdateInvoice not fully implemented.'];
    // }

    // Placeholder for deleting an invoice. Consider implications like stock reversal.
    // public function deleteInvoice(int $invoice_id, int $user_id): array {
    //     if (!$this->conn) return ['success' => false, 'error' => 'Database connection failed.'];
    //     // 1. Check permissions and invoice status (e.g., cannot delete 'Paid' invoices directly)
    //     // 2. Begin transaction
    //     // 3. If stock was deducted, reverse stock transactions (call a method like reverseStockForInvoice)
    //     // 4. Delete payments associated with the invoice (or disassociate) - business rule
    //     // 5. Delete invoice_items
    //     // 6. Delete invoice
    //     // 7. Commit or Rollback
    //     // 8. Log activity
    //     // Consider soft delete (setting status to 'Deleted' or 'Void') instead of hard delete.
    //     return ['success' => false, 'error' => 'DeleteInvoice not fully implemented.'];
    // }

}