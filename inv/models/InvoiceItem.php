<?php
// models/InvoiceItem.php

class InvoiceItem {
    private $conn;
    private $table_name = "invoice_items";

    // Object properties
    public $id;
    public $invoice_id;
    public $product_id;
    public $item_number;
    public $description;
    public $image_path_override;
    public $quantity;
    public $unit_of_measurement;
    public $rate_per_unit;
    public $total_amount; // This is a GENERATED ALWAYS column
    public $created_by_user_id;
    public $updated_by_user_id;
    public $created_at;
    public $updated_at;

    // Constructor
    public function __construct($db){
        $this->conn = $db;
    }

    // Create invoice item
    public function create() {
        $query = "INSERT INTO
                    " . $this->table_name . "
                SET
                    invoice_id=?, product_id=?, item_number=?, description=?, image_path_override=?,
                    quantity=?, unit_of_measurement=?, rate_per_unit=?, created_by_user_id=?, updated_by_user_id=?";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->invoice_id = htmlspecialchars(strip_tags($this->invoice_id));
        $this->product_id = htmlspecialchars(strip_tags($this->product_id));
        $this->item_number = htmlspecialchars(strip_tags($this->item_number));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->image_path_override = htmlspecialchars(strip_tags($this->image_path_override));
        $this->quantity = htmlspecialchars(strip_tags($this->quantity));
        $this->unit_of_measurement = htmlspecialchars(strip_tags($this->unit_of_measurement));
        $this->rate_per_unit = htmlspecialchars(strip_tags($this->rate_per_unit));
        $this->created_by_user_id = htmlspecialchars(strip_tags($this->created_by_user_id));
        $this->updated_by_user_id = htmlspecialchars(strip_tags($this->updated_by_user_id));


        // Bind values
        $stmt->bind_param("iisssdsdii",
            $this->invoice_id,
            $this->product_id,
            $this->item_number,
            $this->description,
            $this->image_path_override,
            $this->quantity,
            $this->unit_of_measurement,
            $this->rate_per_unit,
            $this->created_by_user_id,
            $this->updated_by_user_id
        );

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Read invoice items for a specific invoice
    public function readByInvoice() {
        $query = "SELECT
                    ii.*, p.name as product_name, p.sku as product_sku, p.default_image_path as product_image_path
                  FROM
                    " . $this->table_name . " ii
                  LEFT JOIN
                    products p ON ii.product_id = p.id
                  WHERE
                    ii.invoice_id = ?
                  ORDER BY ii.item_number ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->invoice_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result;
    }

    // Update invoice item
    public function update() {
        $query = "UPDATE
                    " . $this->table_name . "
                SET
                    product_id=?, item_number=?, description=?, image_path_override=?,
                    quantity=?, unit_of_measurement=?, rate_per_unit=?, updated_by_user_id=?
                WHERE
                    id = ? AND invoice_id = ?";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->product_id = htmlspecialchars(strip_tags($this->product_id));
        $this->item_number = htmlspecialchars(strip_tags($this->item_number));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->image_path_override = htmlspecialchars(strip_tags($this->image_path_override));
        $this->quantity = htmlspecialchars(strip_tags($this->quantity));
        $this->unit_of_measurement = htmlspecialchars(strip_tags($this->unit_of_measurement));
        $this->rate_per_unit = htmlspecialchars(strip_tags($this->rate_per_unit));
        $this->updated_by_user_id = htmlspecialchars(strip_tags($this->updated_by_user_id));
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->invoice_id = htmlspecialchars(strip_tags($this->invoice_id));

        // Bind values
        $stmt->bind_param("isssdsdiii",
            $this->product_id,
            $this->item_number,
            $this->description,
            $this->image_path_override,
            $this->quantity,
            $this->unit_of_measurement,
            $this->rate_per_unit,
            $this->updated_by_user_id,
            $this->id,
            $this->invoice_id
        );

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete invoice item
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ? AND invoice_id = ?";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->invoice_id = htmlspecialchars(strip_tags($this->invoice_id));

        $stmt->bind_param("ii", $this->id, $this->invoice_id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete all items for a given invoice_id
    public function deleteAllByInvoiceId() {
        $query = "DELETE FROM " . $this->table_name . " WHERE invoice_id = ?";
        $stmt = $this->conn->prepare($query);
        $this->invoice_id = htmlspecialchars(strip_tags($this->invoice_id));
        $stmt->bind_param("i", $this->invoice_id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>