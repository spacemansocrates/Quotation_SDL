<?php
// controllers/InvoiceController.php

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/InvoiceItem.php';
require_once __DIR__ . '/../includes/invoice_functions.php';

class InvoiceController {
    private $conn;
    private $invoice;
    private $invoiceItem;
    private $current_user_id; // To store the logged-in user ID

    public function __construct($db) {
        $this->conn = $db;
        $this->invoice = new Invoice($db);
        $this->invoiceItem = new InvoiceItem($db);

        // Assume session is started and user_id is available
        if (isset($_SESSION['user_id'])) {
            $this->current_user_id = $_SESSION['user_id'];
        } else {
            // Fallback or handle unauthenticated access, perhaps redirect to login
            // For now, let's hardcode a default if not found (for testing, but remove in production)
            $this->current_user_id = 1; // Default user ID for demonstration
            // header('Location: /login.php'); exit(); // Uncomment for production
        }
    }

    public function handleRequest() {
        $action = $_GET['action'] ?? 'list'; // Default action is 'list'

        switch ($action) {
            case 'create':
                $this->createInvoice();
                break;
            case 'store': // Handle POST for creation
                $this->storeInvoice();
                break;
            case 'edit':
                $this->editInvoice();
                break;
            case 'update': // Handle POST for update
                $this->updateInvoice();
                break;
            case 'view':
                $this->viewInvoice();
                break;
            case 'delete':
                $this->deleteInvoice();
                break;
            case 'destroy': // Handle POST for delete
                $this->destroyInvoice();
                break;
            case 'list':
            default:
                $this->listInvoices();
                break;
        }
    }

    private function listInvoices() {
        $search = $_GET['search'] ?? '';
        $status_filter = $_GET['status'] ?? '';
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $invoices = $this->invoice->read($search, $status_filter, $limit, $offset);
        $total_rows = $this->invoice->count($search, $status_filter);
        $total_pages = ceil($total_rows / $limit);

        include __DIR__ . '/../invoices/index.php';
    }

    private function createInvoice() {
        $customers = $this->invoice->getCustomers();
        $shops = $this->invoice->getShops();
        $invoice = null; // No existing invoice object for creation
        include __DIR__ . '/../invoices/create.php';
    }

    private function storeInvoice() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->invoice->shop_id = $_POST['shop_id'] ?? null;
            $this->invoice->customer_id = $_POST['customer_id'] ?? null;
            $this->invoice->customer_name_override = trim($_POST['customer_name_override']) ?: null;
            $this->invoice->customer_address_override = trim($_POST['customer_address_override']) ?: null;
            $this->invoice->invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
            $this->invoice->due_date = $_POST['due_date'] ?? null;
            $this->invoice->company_tpin = $_POST['company_tpin'] ?? null;
            $this->invoice->notes_general = $_POST['notes_general'] ?? null;
            $this->invoice->delivery_period = $_POST['delivery_period'] ?? null;
            $this->invoice->payment_terms = $_POST['payment_terms'] ?? null;
            $this->invoice->apply_ppda_levy = isset($_POST['apply_ppda_levy']) ? 1 : 0;
            $this->invoice->ppda_levy_percentage = 1.00; // Fixed as per requirements
            $this->invoice->vat_percentage = 16.50; // Fixed as per requirements
            $this->invoice->total_paid = 0.00; // New invoice, nothing paid yet
            $this->invoice->status = 'Draft'; // Default status
            $this->invoice->created_by_user_id = $this->current_user_id;
            $this->invoice->updated_by_user_id = $this->current_user_id;
            $this->invoice->quotation_id = $_POST['quotation_id'] ?? null;


            // Generate invoice number
            if ($this->invoice->shop_id && $this->invoice->customer_id) {
                $this->invoice->invoice_number = generateInvoiceNumber($this->invoice->shop_id, $this->invoice->customer_id, $this->conn);
            } else {
                $_SESSION['error_message'] = "Shop and Customer are required to generate invoice number.";
                header('Location: ?action=create');
                exit();
            }

            // Handle invoice items and calculate totals
            $invoice_items_data = [];
            if (isset($_POST['item_description'])) {
                for ($i = 0; $i < count($_POST['item_description']); $i++) {
                    if (!empty(trim($_POST['item_description'][$i]))) {
                        $invoice_items_data[] = [
                            'product_id' => !empty($_POST['item_product_id'][$i]) ? $_POST['item_product_id'][$i] : null,
                            'item_number' => $i + 1,
                            'description' => $_POST['item_description'][$i],
                            'quantity' => $_POST['item_quantity'][$i],
                            'unit_of_measurement' => $_POST['item_uom'][$i],
                            'rate_per_unit' => $_POST['item_rate'][$i]
                        ];
                    }
                }
            }

            $calculated_totals = calculateInvoiceTotals(
                $invoice_items_data,
                $this->invoice->apply_ppda_levy,
                $this->invoice->ppda_levy_percentage,
                $this->invoice->vat_percentage
            );

            $this->invoice->gross_total_amount = $calculated_totals['gross_total_amount'];
            $this->invoice->ppda_levy_amount = $calculated_totals['ppda_levy_amount'];
            $this->invoice->amount_before_vat = $calculated_totals['amount_before_vat'];
            $this->invoice->vat_amount = $calculated_totals['vat_amount'];
            $this->invoice->total_net_amount = $calculated_totals['total_net_amount'];

            // Create invoice
            $invoice_id = $this->invoice->create();
            if ($invoice_id) {
                // Add invoice items
                foreach ($invoice_items_data as $item_data) {
                    $this->invoiceItem->invoice_id = $invoice_id;
                    $this->invoiceItem->product_id = $item_data['product_id'];
                    $this->invoiceItem->item_number = $item_data['item_number'];
                    $this->invoiceItem->description = $item_data['description'];
                    $this->invoiceItem->quantity = $item_data['quantity'];
                    $this->invoiceItem->unit_of_measurement = $item_data['unit_of_measurement'];
                    $this->invoiceItem->rate_per_unit = $item_data['rate_per_unit'];
                    $this->invoiceItem->created_by_user_id = $this->current_user_id;
                    $this->invoiceItem->updated_by_user_id = $this->current_user_id;
                    $this->invoiceItem->create();
                }

                $_SESSION['success_message'] = "Invoice created successfully!";
                header('Location: ?action=view&id=' . $invoice_id);
                exit();
            } else {
                $_SESSION['error_message'] = "Failed to create invoice. Please try again.";
                header('Location: ?action=create');
                exit();
            }
        }
    }

    private function editInvoice() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            header('Location: ?action=list');
            exit();
        }

        $this->invoice->id = $id;
        if ($this->invoice->readOne()) {
            $customers = $this->invoice->getCustomers();
            $shops = $this->invoice->getShops();
            $this->invoiceItem->invoice_id = $id;
            $invoice_items = $this->invoiceItem->readByInvoice(); // Get items for this invoice
            include __DIR__ . '/../invoices/edit.php';
        } else {
            $_SESSION['error_message'] = "Invoice not found.";
            header('Location: ?action=list');
            exit();
        }
    }

    private function updateInvoice() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->invoice->id = $_POST['id'] ?? null;
            if (!$this->invoice->id) {
                $_SESSION['error_message'] = "Invoice ID is missing for update.";
                header('Location: ?action=list');
                exit();
            }

            // Fetch current invoice to preserve its number and other calculated fields initially
            $current_invoice = new Invoice($this->conn);
            $current_invoice->id = $this->invoice->id;
            if (!$current_invoice->readOne()) {
                 $_SESSION['error_message'] = "Invoice not found for update.";
                header('Location: ?action=list');
                exit();
            }

            $this->invoice->invoice_number = $current_invoice->invoice_number; // Keep existing number
            $this->invoice->quotation_id = $_POST['quotation_id'] ?? null;
            $this->invoice->shop_id = $_POST['shop_id'] ?? null;
            $this->invoice->customer_id = $_POST['customer_id'] ?? null;
            $this->invoice->customer_name_override = trim($_POST['customer_name_override']) ?: null;
            $this->invoice->customer_address_override = trim($_POST['customer_address_override']) ?: null;
            $this->invoice->invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
            $this->invoice->due_date = $_POST['due_date'] ?? null;
            $this->invoice->company_tpin = $_POST['company_tpin'] ?? null;
            $this->invoice->notes_general = $_POST['notes_general'] ?? null;
            $this->invoice->delivery_period = $_POST['delivery_period'] ?? null;
            $this->invoice->payment_terms = $_POST['payment_terms'] ?? null;
            $this->invoice->apply_ppda_levy = isset($_POST['apply_ppda_levy']) ? 1 : 0;
            $this->invoice->ppda_levy_percentage = 1.00; // Fixed
            $this->invoice->vat_percentage = 16.50; // Fixed
            $this->invoice->total_paid = $current_invoice->total_paid; // Preserve current total_paid
            $this->invoice->status = $_POST['status'] ?? $current_invoice->status; // Allow status update, but calculations might override
            $this->invoice->updated_by_user_id = $this->current_user_id;

            // Handle invoice items update
            $invoice_items_data = [];
            $existing_item_ids = [];
            if (isset($_POST['item_id'])) { // Collect existing item IDs to identify deletions
                foreach($_POST['item_id'] as $itemId) {
                    if (!empty($itemId)) {
                        $existing_item_ids[] = $itemId;
                    }
                }
            }

            $new_item_ids_to_keep = []; // Track items submitted in the form
            if (isset($_POST['item_description'])) {
                for ($i = 0; $i < count($_POST['item_description']); $i++) {
                    if (!empty(trim($_POST['item_description'][$i]))) {
                        $item_id = !empty($_POST['item_id'][$i]) ? $_POST['item_id'][$i] : null;
                        $invoice_items_data[] = [
                            'id' => $item_id, // If existing, will have an ID
                            'product_id' => !empty($_POST['item_product_id'][$i]) ? $_POST['item_product_id'][$i] : null,
                            'item_number' => $i + 1, // Re-index item numbers
                            'description' => $_POST['item_description'][$i],
                            'quantity' => $_POST['item_quantity'][$i],
                            'unit_of_measurement' => $_POST['item_uom'][$i],
                            'rate_per_unit' => $_POST['item_rate'][$i]
                        ];
                        if ($item_id) {
                            $new_item_ids_to_keep[] = $item_id;
                        }
                    }
                }
            }

            // Delete items not in the submitted list
            $items_to_delete = array_diff($existing_item_ids, $new_item_ids_to_keep);
            foreach ($items_to_delete as $itemIdToDelete) {
                $delete_item = new InvoiceItem($this->conn);
                $delete_item->id = $itemIdToDelete;
                $delete_item->invoice_id = $this->invoice->id;
                $delete_item->delete();
            }

            // Calculate totals before updating invoice
            $calculated_totals = calculateInvoiceTotals(
                $invoice_items_data,
                $this->invoice->apply_ppda_levy,
                $this->invoice->ppda_levy_percentage,
                $this->invoice->vat_percentage
            );

            $this->invoice->gross_total_amount = $calculated_totals['gross_total_amount'];
            $this->invoice->ppda_levy_amount = $calculated_totals['ppda_levy_amount'];
            $this->invoice->amount_before_vat = $calculated_totals['amount_before_vat'];
            $this->invoice->vat_amount = $calculated_totals['vat_amount'];
            $this->invoice->total_net_amount = $calculated_totals['total_net_amount'];

            if ($this->invoice->update()) {
                // Update or create invoice items
                foreach ($invoice_items_data as $item_data) {
                    $this->invoiceItem->invoice_id = $this->invoice->id;
                    $this->invoiceItem->product_id = $item_data['product_id'];
                    $this->invoiceItem->item_number = $item_data['item_number'];
                    $this->invoiceItem->description = $item_data['description'];
                    $this->invoiceItem->quantity = $item_data['quantity'];
                    $this->invoiceItem->unit_of_measurement = $item_data['unit_of_measurement'];
                    $this->invoiceItem->rate_per_unit = $item_data['rate_per_unit'];
                    $this->invoiceItem->updated_by_user_id = $this->current_user_id;

                    if ($item_data['id']) { // Existing item
                        $this->invoiceItem->id = $item_data['id'];
                        $this->invoiceItem->update();
                    } else { // New item
                        $this->invoiceItem->created_by_user_id = $this->current_user_id;
                        $this->invoiceItem->create();
                    }
                }

                // Recalculate and update status if needed (e.g., if total_net_amount changed and affects balance_due)
                // The status update for overdue is handled by updateInvoiceStatus
                updateInvoiceStatus($this->invoice->id, $this->conn, $this->invoice->total_paid, $this->invoice->total_net_amount, $this->invoice->due_date);

                $_SESSION['success_message'] = "Invoice updated successfully!";
                header('Location: ?action=view&id=' . $this->invoice->id);
                exit();
            } else {
                $_SESSION['error_message'] = "Failed to update invoice. Please try again.";
                header('Location: ?action=edit&id=' . $this->invoice->id);
                exit();
            }
        }
    }

    private function viewInvoice() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            header('Location: ?action=list');
            exit();
        }

        $this->invoice->id = $id;
        if ($this->invoice->readOne()) {
            $this->invoiceItem->invoice_id = $id;
            $invoice_items = $this->invoiceItem->readByInvoice();

            // Fetch payments for this invoice
            $payments_query = "SELECT p.*, u.full_name as recorded_by_user FROM payments p LEFT JOIN users u ON p.recorded_by_user_id = u.id WHERE p.invoice_id = ? ORDER BY p.payment_date DESC";
            $stmt_payments = $this->conn->prepare($payments_query);
            $stmt_payments->bind_param("i", $id);
            $stmt_payments->execute();
            $payments = $stmt_payments->get_result();

            include __DIR__ . '/../invoices/view.php';
        } else {
            $_SESSION['error_message'] = "Invoice not found.";
            header('Location: ?action=list');
            exit();
        }
    }

    private function deleteInvoice() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            header('Location: ?action=list');
            exit();
        }

        $this->invoice->id = $id;
        if ($this->invoice->readOne()) {
            // Confirmation page
            include __DIR__ . '/../invoices/delete.php';
        } else {
            $_SESSION['error_message'] = "Invoice not found.";
            header('Location: ?action=list');
            exit();
        }
    }

    private function destroyInvoice() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
            $invoice_id = $_POST['id'];

            // Start transaction
            $this->conn->begin_transaction();
            try {
                // 1. Delete associated invoice items first
                $this->invoiceItem->invoice_id = $invoice_id;
                $this->invoiceItem->deleteAllByInvoiceId();

                // 2. Delete associated payments
                $delete_payments_query = "DELETE FROM payments WHERE invoice_id = ?";
                $stmt_payments = $this->conn->prepare($delete_payments_query);
                $stmt_payments->bind_param("i", $invoice_id);
                $stmt_payments->execute();

                // 3. Delete the invoice itself
                $this->invoice->id = $invoice_id;
                if ($this->invoice->delete()) {
                    $this->conn->commit();
                    $_SESSION['success_message'] = "Invoice and all associated data deleted successfully!";
                } else {
                    $this->conn->rollback();
                    $_SESSION['error_message'] = "Failed to delete invoice.";
                }
            } catch (Exception $e) {
                $this->conn->rollback();
                $_SESSION['error_message'] = "Error deleting invoice: " . $e->getMessage();
            }
            header('Location: ?action=list');
            exit();
        } else {
            $_SESSION['error_message'] = "Invalid request to delete invoice.";
            header('Location: ?action=list');
            exit();
        }
    }

    // Helper for fetching dropdowns if needed directly by controller or views
    public function getCustomersList() {
        return $this->invoice->getCustomers();
    }

    public function getShopsList() {
        return $this->invoice->getShops();
    }
}

// Instantiate and handle request if directly accessed
// This part assumes index.php or a central router will call this.
// For direct access, uncomment the following:
/*
session_start(); // Ensure session is started for user_id
require_once 'db_connect.php';
$controller = new InvoiceController($conn);
$controller->handleRequest();
*/
?>