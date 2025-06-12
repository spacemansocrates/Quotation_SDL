<?php
// models/Invoice.php

class Invoice {
    private $conn;
    private $table_name = "invoices";

    // Object properties
    public $id;
    public $invoice_number;
    public $quotation_id;
    public $shop_id;
    public $customer_id;
    public $customer_name_override;
    public $customer_address_override;
    public $invoice_date;
    public $due_date;
    public $company_tpin;
    public $notes_general;
    public $delivery_period;
    public $payment_terms;
    public $apply_ppda_levy;
    public $ppda_levy_percentage;
    public $vat_percentage;
    public $gross_total_amount;
    public $ppda_levy_amount;
    public $amount_before_vat;
    public $vat_amount;
    public $total_net_amount;
    public $total_paid;
    public $balance_due; // This is a GENERATED ALWAYS column
    public $status;
    public $created_by_user_id;
    public $updated_by_user_id;
    public $created_at;
    public $updated_at;

    // Constructor with $db as database connection
    public function __construct($db){
        $this->conn = $db;
    }

    // Read invoices
    public function read($search = '', $status = '', $limit = 10, $offset = 0) {
        $query = "SELECT
                    i.id, i.invoice_number, i.invoice_date, i.due_date, i.total_net_amount, i.total_paid, i.balance_due, i.status,
                    c.name as customer_name, s.name as shop_name, u.full_name as created_by_user
                  FROM
                    " . $this->table_name . " i
                  LEFT JOIN
                    customers c ON i.customer_id = c.id
                  LEFT JOIN
                    shops s ON i.shop_id = s.id
                  LEFT JOIN
                    users u ON i.created_by_user_id = u.id";

        $conditions = [];
        $params = [];
        $types = '';

        if (!empty($search)) {
            $conditions[] = "(i.invoice_number LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $types .= 'sss';
        }
        if (!empty($status)) {
            $conditions[] = "i.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY i.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result;
    }

    // Get total count for pagination
    public function count($search = '', $status = '') {
        $query = "SELECT COUNT(*) as total_rows
                  FROM " . $this->table_name . " i
                  LEFT JOIN customers c ON i.customer_id = c.id
                  LEFT JOIN shops s ON i.shop_id = s.id";

        $conditions = [];
        $params = [];
        $types = '';

        if (!empty($search)) {
            $conditions[] = "(i.invoice_number LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $types .= 'sss';
        }
        if (!empty($status)) {
            $conditions[] = "i.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total_rows'];
    }


    // Create invoice
    public function create() {
        // query to insert record
        $query = "INSERT INTO
                    " . $this->table_name . "
                SET
                    invoice_number=?, quotation_id=?, shop_id=?, customer_id=?, customer_name_override=?,
                    customer_address_override=?, invoice_date=?, due_date=?, company_tpin=?, notes_general=?,
                    delivery_period=?, payment_terms=?, apply_ppda_levy=?, ppda_levy_percentage=?,
                    vat_percentage=?, gross_total_amount=?, ppda_levy_amount=?, amount_before_vat=?,
                    vat_amount=?, total_net_amount=?, total_paid=?, status=?, created_by_user_id=?, updated_by_user_id=?";

        $stmt = $this->conn->prepare($query);

        // sanitize inputs
        $this->invoice_number = htmlspecialchars(strip_tags($this->invoice_number));
        $this->quotation_id = htmlspecialchars(strip_tags($this->quotation_id));
        $this->shop_id = htmlspecialchars(strip_tags($this->shop_id));
        $this->customer_id = htmlspecialchars(strip_tags($this->customer_id));
        $this->customer_name_override = htmlspecialchars(strip_tags($this->customer_name_override));
        $this->customer_address_override = htmlspecialchars(strip_tags($this->customer_address_override));
        $this->invoice_date = htmlspecialchars(strip_tags($this->invoice_date));
        $this->due_date = htmlspecialchars(strip_tags($this->due_date));
        $this->company_tpin = htmlspecialchars(strip_tags($this->company_tpin));
        $this->notes_general = htmlspecialchars(strip_tags($this->notes_general));
        $this->delivery_period = htmlspecialchars(strip_tags($this->delivery_period));
        $this->payment_terms = htmlspecialchars(strip_tags($this->payment_terms));
        $this->apply_ppda_levy = htmlspecialchars(strip_tags($this->apply_ppda_levy));
        $this->ppda_levy_percentage = htmlspecialchars(strip_tags($this->ppda_levy_percentage));
        $this->vat_percentage = htmlspecialchars(strip_tags($this->vat_percentage));
        $this->gross_total_amount = htmlspecialchars(strip_tags($this->gross_total_amount));
        $this->ppda_levy_amount = htmlspecialchars(strip_tags($this->ppda_levy_amount));
        $this->amount_before_vat = htmlspecialchars(strip_tags($this->amount_before_vat));
        $this->vat_amount = htmlspecialchars(strip_tags($this->vat_amount));
        $this->total_net_amount = htmlspecialchars(strip_tags($this->total_net_amount));
        $this->total_paid = htmlspecialchars(strip_tags($this->total_paid));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->created_by_user_id = htmlspecialchars(strip_tags($this->created_by_user_id));
        $this->updated_by_user_id = htmlspecialchars(strip_tags($this->updated_by_user_id));

        // bind values
        $stmt->bind_param("sissssssssssiddiiiiisiii",
            $this->invoice_number,
            $this->quotation_id,
            $this->shop_id,
            $this->customer_id,
            $this->customer_name_override,
            $this->customer_address_override,
            $this->invoice_date,
            $this->due_date,
            $this->company_tpin,
            $this->notes_general,
            $this->delivery_period,
            $this->payment_terms,
            $this->apply_ppda_levy,
            $this->ppda_levy_percentage,
            $this->vat_percentage,
            $this->gross_total_amount,
            $this->ppda_levy_amount,
            $this->amount_before_vat,
            $this->vat_amount,
            $this->total_net_amount,
            $this->total_paid,
            $this->status,
            $this->created_by_user_id,
            $this->updated_by_user_id
        );

        if ($stmt->execute()) {
            return $this->conn->insert_id; // Return the ID of the newly created invoice
        }
        return false;
    }

    // Read a single invoice
    public function readOne() {
        $query = "SELECT
                    i.*,
                    c.name as customer_name, c.address_line1 as customer_addr1, c.address_line2 as customer_addr2, c.city_location as customer_city, c.phone as customer_phone, c.email as customer_email, c.tpin_no as customer_tpin,
                    s.name as shop_name, s.address_line1 as shop_addr1, s.address_line2 as shop_addr2, s.city as shop_city, s.phone as shop_phone, s.email as shop_email, s.tpin_no as shop_tpin,
                    u_created.full_name as created_by_user_name, u_updated.full_name as updated_by_user_name
                  FROM
                    " . $this->table_name . " i
                  LEFT JOIN
                    customers c ON i.customer_id = c.id
                  LEFT JOIN
                    shops s ON i.shop_id = s.id
                  LEFT JOIN
                    users u_created ON i.created_by_user_id = u_created.id
                  LEFT JOIN
                    users u_updated ON i.updated_by_user_id = u_updated.id
                  WHERE
                    i.id = ?
                  LIMIT
                    0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row) {
            // Set object properties from fetched row
            foreach ($row as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
            // Also store joined table data for easy access
            $this->customer_data = ['name' => $row['customer_name'], 'address_line1' => $row['customer_addr1'], 'address_line2' => $row['customer_addr2'], 'city_location' => $row['customer_city'], 'phone' => $row['customer_phone'], 'email' => $row['customer_email'], 'tpin_no' => $row['customer_tpin']];
            $this->shop_data = ['name' => $row['shop_name'], 'address_line1' => $row['shop_addr1'], 'address_line2' => $row['shop_addr2'], 'city' => $row['shop_city'], 'phone' => $row['shop_phone'], 'email' => $row['shop_email'], 'tpin_no' => $row['shop_tpin']];
            $this->created_by_user_name = $row['created_by_user_name'];
            $this->updated_by_user_name = $row['updated_by_user_name'];
            return true;
        }
        return false;
    }

    // Update the invoice
    public function update() {
        $query = "UPDATE
                    " . $this->table_name . "
                SET
                    invoice_number=?, quotation_id=?, shop_id=?, customer_id=?, customer_name_override=?,
                    customer_address_override=?, invoice_date=?, due_date=?, company_tpin=?, notes_general=?,
                    delivery_period=?, payment_terms=?, apply_ppda_levy=?, ppda_levy_percentage=?,
                    vat_percentage=?, gross_total_amount=?, ppda_levy_amount=?, amount_before_vat=?,
                    vat_amount=?, total_net_amount=?, total_paid=?, status=?, updated_by_user_id=?
                WHERE
                    id = ?";

        $stmt = $this->conn->prepare($query);

        // sanitize
        $this->invoice_number = htmlspecialchars(strip_tags($this->invoice_number));
        $this->quotation_id = htmlspecialchars(strip_tags($this->quotation_id));
        $this->shop_id = htmlspecialchars(strip_tags($this->shop_id));
        $this->customer_id = htmlspecialchars(strip_tags($this->customer_id));
        $this->customer_name_override = htmlspecialchars(strip_tags($this->customer_name_override));
        $this->customer_address_override = htmlspecialchars(strip_tags($this->customer_address_override));
        $this->invoice_date = htmlspecialchars(strip_tags($this->invoice_date));
        $this->due_date = htmlspecialchars(strip_tags($this->due_date));
        $this->company_tpin = htmlspecialchars(strip_tags($this->company_tpin));
        $this->notes_general = htmlspecialchars(strip_tags($this->notes_general));
        $this->delivery_period = htmlspecialchars(strip_tags($this->delivery_period));
        $this->payment_terms = htmlspecialchars(strip_tags($this->payment_terms));
        $this->apply_ppda_levy = htmlspecialchars(strip_tags($this->apply_ppda_levy));
        $this->ppda_levy_percentage = htmlspecialchars(strip_tags($this->ppda_levy_percentage));
        $this->vat_percentage = htmlspecialchars(strip_tags($this->vat_percentage));
        $this->gross_total_amount = htmlspecialchars(strip_tags($this->gross_total_amount));
        $this->ppda_levy_amount = htmlspecialchars(strip_tags($this->ppda_levy_amount));
        $this->amount_before_vat = htmlspecialchars(strip_tags($this->amount_before_vat));
        $this->vat_amount = htmlspecialchars(strip_tags($this->vat_amount));
        $this->total_net_amount = htmlspecialchars(strip_tags($this->total_net_amount));
        $this->total_paid = htmlspecialchars(strip_tags($this->total_paid));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->updated_by_user_id = htmlspecialchars(strip_tags($this->updated_by_user_id));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // bind values
        $stmt->bind_param("sissssssssssiddiiiiisiii",
            $this->invoice_number,
            $this->quotation_id,
            $this->shop_id,
            $this->customer_id,
            $this->customer_name_override,
            $this->customer_address_override,
            $this->invoice_date,
            $this->due_date,
            $this->company_tpin,
            $this->notes_general,
            $this->delivery_period,
            $this->payment_terms,
            $this->apply_ppda_levy,
            $this->ppda_levy_percentage,
            $this->vat_percentage,
            $this->gross_total_amount,
            $this->ppda_levy_amount,
            $this->amount_before_vat,
            $this->vat_amount,
            $this->total_net_amount,
            $this->total_paid,
            $this->status,
            $this->updated_by_user_id,
            $this->id
        );

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete the invoice
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bind_param("i", $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update total_paid and status from payments
    public function updateTotalsAndStatus() {
        // Calculate total_paid for this invoice
        $query = "SELECT SUM(amount_paid) as total_paid_sum FROM payments WHERE invoice_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $new_total_paid = $row['total_paid_sum'] ? $row['total_paid_sum'] : 0.00;

        // Fetch current total_net_amount
        $query = "SELECT total_net_amount FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_net_amount = $row['total_net_amount'];

        // Determine new status
        $new_status = $this->status; // Keep current status if not changing
        if ($new_total_paid >= $total_net_amount && $total_net_amount > 0) {
            $new_status = 'Paid';
        } elseif ($new_total_paid > 0 && $new_total_paid < $total_net_amount) {
            $new_status = 'Partially Paid';
        } elseif ($new_total_paid == 0 && $new_status != 'Draft' && $new_status != 'Cancelled') {
             // Check if it's overdue
            $current_invoice_details_query = "SELECT due_date FROM " . $this->table_name . " WHERE id = ?";
            $stmt = $this->conn->prepare($current_invoice_details_query);
            $stmt->bind_param("i", $this->id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            if ($row && strtotime($row['due_date']) < time() && $new_status == 'Finalized') {
                $new_status = 'Overdue';
            } else {
                 $new_status = 'Finalized'; // Or whatever default 'unpaid' status is
            }
        }


        // Update invoice table
        $query = "UPDATE " . $this->table_name . " SET total_paid = ?, status = ?, updated_by_user_id = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $user_id = $_SESSION['user_id']; // Assuming user_id is in session
        $stmt->bind_param("dsii", $new_total_paid, $new_status, $user_id, $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }


    // Get all customers (for dropdowns)
    public function getCustomers() {
        $query = "SELECT id, name, customer_code, address_line1, address_line2, city_location, phone, email, tpin_no FROM customers ORDER BY name ASC";
        $result = $this->conn->query($query);
        return $result;
    }

    // Get all shops (for dropdowns)
    public function getShops() {
        $query = "SELECT id, name, shop_code, address_line1, address_line2, city, phone, email, tpin_no FROM shops ORDER BY name ASC";
        $result = $this->conn->query($query);
        return $result;
    }

     // Get a specific shop's details
    public function getShopDetails($shop_id) {
        $query = "SELECT name, address_line1, address_line2, city, phone, email, tpin_no FROM shops WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $shop_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Get a specific customer's details
    public function getCustomerDetails($customer_id) {
        $query = "SELECT name, customer_code, address_line1, address_line2, city_location, phone, email, tpin_no FROM customers WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Get Quotation Details
    public function getQuotationDetails($quotation_id) {
        $query = "SELECT * FROM quotations WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $quotation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Get Quotation Items
    public function getQuotationItems($quotation_id) {
        $query = "SELECT * FROM quotation_items WHERE quotation_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $quotation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result; // Returns mysqli_result object
    }
}
?>