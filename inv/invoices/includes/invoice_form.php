<?php
// invoices/includes/invoice_form.php
// This file is included by create.php and edit.php
// Variables expected: $invoice (object or null), $customers (mysqli_result), $shops (mysqli_result), $invoice_items (mysqli_result for edit)
?>

<div class="row">
    <div class="col-half">
        <div class="form-group">
            <label for="shop_id">Shop:</label>
            <select id="shop_id" name="shop_id" required>
                <option value="">Select Shop</option>
                <?php
                if ($shops && $shops->num_rows > 0) {
                    $shops->data_seek(0); // Reset pointer for potential re-use
                    while ($shop_row = $shops->fetch_assoc()) {
                        $selected = (isset($invoice) && $invoice->shop_id == $shop_row['id']) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($shop_row['id']) . '" data-shop-code="' . htmlspecialchars($shop_row['shop_code']) . '" data-tpin="' . htmlspecialchars($shop_row['tpin_no']) . '" ' . $selected . '>' . htmlspecialchars($shop_row['name']) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
    </div>
    <div class="col-half">
        <div class="form-group">
            <label for="customer_id">Customer:</label>
            <select id="customer_id" name="customer_id" required>
                <option value="">Select Customer</option>
                <?php
                if ($customers && $customers->num_rows > 0) {
                    $customers->data_seek(0); // Reset pointer
                    while ($customer_row = $customers->fetch_assoc()) {
                        $selected = (isset($invoice) && $invoice->customer_id == $customer_row['id']) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($customer_row['id']) . '" data-customer-code="' . htmlspecialchars($customer_row['customer_code']) . '" ' . $selected . '>' . htmlspecialchars($customer_row['name']) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-half">
        <div class="form-group">
            <label for="customer_name_override">Customer Name Override (Optional):</label>
            <input type="text" id="customer_name_override" name="customer_name_override" value="<?php echo htmlspecialchars($invoice->customer_name_override ?? ''); ?>">
        </div>
    </div>
    <div class="col-half">
        <div class="form-group">
            <label for="customer_address_override">Customer Address Override (Optional):</label>
            <textarea id="customer_address_override" name="customer_address_override" rows="3"><?php echo htmlspecialchars($invoice->customer_address_override ?? ''); ?></textarea>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-half">
        <div class="form-group">
            <label for="invoice_date">Invoice Date:</label>
            <input type="date" id="invoice_date" name="invoice_date" value="<?php echo htmlspecialchars($invoice->invoice_date ?? date('Y-m-d')); ?>" required>
        </div>
    </div>
    <div class="col-half">
        <div class="form-group">
            <label for="due_date">Due Date:</label>
            <input type="date" id="due_date" name="due_date" value="<?php echo htmlspecialchars($invoice->due_date ?? ''); ?>">
        </div>
    </div>
</div>

<div class="row">
     <div class="col-half">
        <div class="form-group">
            <label for="quotation_id">Link to Quotation (Optional):</label>
            <input type="number" id="quotation_id" name="quotation_id" value="<?php echo htmlspecialchars($invoice->quotation_id ?? ''); ?>" placeholder="Enter Quotation ID">
            <button type="button" id="loadQuotationBtn" class="btn btn-sm" style="margin-top: 5px;">Load Items from Quotation</button>
        </div>
    </div>
    <div class="col-half">
        <div class="form-group">
            <label for="company_tpin">Company TPIN (from Shop):</label>
            <input type="text" id="company_tpin" name="company_tpin" value="<?php echo htmlspecialchars($invoice->company_tpin ?? ''); ?>" readonly>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-half">
        <div class="form-group">
            <label for="delivery_period">Delivery Period:</label>
            <input type="text" id="delivery_period" name="delivery_period" value="<?php echo htmlspecialchars($invoice->delivery_period ?? ''); ?>">
        </div>
    </div>
    <div class="col-half">
        <div class="form-group">
            <label for="payment_terms">Payment Terms:</label>
            <input type="text" id="payment_terms" name="payment_terms" value="<?php echo htmlspecialchars($invoice->payment_terms ?? ''); ?>">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-full">
        <div class="form-group">
            <label for="notes_general">General Notes:</label>
            <textarea id="notes_general" name="notes_general" rows="3"><?php echo htmlspecialchars($invoice->notes_general ?? ''); ?></textarea>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-full">
        <div class="form-group">
            <label>
                <input type="checkbox" id="apply_ppda_levy" name="apply_ppda_levy" value="1" <?php echo (isset($invoice) && $invoice->apply_ppda_levy) ? 'checked' : ''; ?>>
                Apply PPDA Levy (1.00%)
            </label>
        </div>
    </div>
</div>

<hr>
<h3>Invoice Items</h3>
<table class="item-table" id="invoiceItemsTable">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 30%;">Description (Product Name/Custom)</th>
            <th style="width: 15%;">Product SKU (Search)</th>
            <th style="width: 10%;">Quantity</th>
            <th style="width: 10%;">UoM</th>
            <th style="width: 10%;">Rate/Unit</th>
            <th style="width: 10%;">Total</th>
            <th style="width: 10%;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $item_index = 0;
        if (isset($invoice_items) && $invoice_items->num_rows > 0) {
            while ($item_row = $invoice_items->fetch_assoc()) {
                ?>
                <tr class="item-row">
                    <td><span class="item-number"><?php echo $item_index + 1; ?></span></td>
                    <td>
                        <input type="hidden" name="item_id[]" value="<?php echo htmlspecialchars($item_row['id']); ?>">
                        <input type="hidden" name="item_product_id[]" class="item-product-id" value="<?php echo htmlspecialchars($item_row['product_id']); ?>">
                        <textarea name="item_description[]" class="item-description" rows="2" required><?php echo htmlspecialchars($item_row['description']); ?></textarea>
                    </td>
                    <td>
                        <input type="text" class="product-search-input" value="<?php echo htmlspecialchars($item_row['product_sku'] ?? ''); ?>" placeholder="Search SKU/Name">
                        <div class="product-suggestions"></div>
                    </td>
                    <td><input type="number" name="item_quantity[]" class="item-quantity" step="0.01" min="0.01" value="<?php echo htmlspecialchars($item_row['quantity']); ?>" required></td>
                    <td><input type="text" name="item_uom[]" class="item-uom" value="<?php echo htmlspecialchars($item_row['unit_of_measurement']); ?>"></td>
                    <td><input type="number" name="item_rate[]" class="item-rate" step="0.01" min="0" value="<?php echo htmlspecialchars($item_row['rate_per_unit']); ?>" required></td>
                    <td><input type="text" class="item-total" value="<?php echo number_format($item_row['total_amount'], 2); ?>" readonly></td>
                    <td class="item-actions"><button type="button" class="remove-item-btn">X</button></td>
                </tr>
                <?php
                $item_index++;
            }
        } else {
            // Add at least one empty row for new invoices
            ?>
             <tr class="item-row">
                <td><span class="item-number">1</span></td>
                <td>
                    <input type="hidden" name="item_id[]" value="">
                    <input type="hidden" name="item_product_id[]" class="item-product-id" value="">
                    <textarea name="item_description[]" class="item-description" rows="2" required></textarea>
                </td>
                <td>
                    <input type="text" class="product-search-input" placeholder="Search SKU/Name">
                    <div class="product-suggestions"></div>
                </td>
                <td><input type="number" name="item_quantity[]" class="item-quantity" step="0.01" min="0.01" value="1" required></td>
                <td><input type="text" name="item_uom[]" class="item-uom" value=""></td>
                <td><input type="number" name="item_rate[]" class="item-rate" step="0.01" min="0" value="0.00" required></td>
                <td><input type="text" class="item-total" value="0.00" readonly></td>
                <td class="item-actions"><button type="button" class="remove-item-btn">X</button></td>
            </tr>
            <?php
        }
        ?>
    </tbody>
</table>
<button type="button" id="addItemBtn" class="add-item-btn">Add New Item</button>

<div class="total-section">
    <div class="total-row">
        <span>Gross Total Amount:</span>
        <span id="grossTotalAmount"><?php echo number_format($invoice->gross_total_amount ?? 0, 2); ?></span>
        <input type="hidden" name="gross_total_amount" value="<?php echo htmlspecialchars($invoice->gross_total_amount ?? 0); ?>">
    </div>
    <div class="total-row">
        <span>PPDA Levy (1.00%):</span>
        <span id="ppdaLevyAmount"><?php echo number_format($invoice->ppda_levy_amount ?? 0, 2); ?></span>
        <input type="hidden" name="ppda_levy_amount" value="<?php echo htmlspecialchars($invoice->ppda_levy_amount ?? 0); ?>">
    </div>
    <div class="total-row">
        <span>Amount Before VAT:</span>
        <span id="amountBeforeVat"><?php echo number_format($invoice->amount_before_vat ?? 0, 2); ?></span>
        <input type="hidden" name="amount_before_vat" value="<?php echo htmlspecialchars($invoice->amount_before_vat ?? 0); ?>">
    </div>
    <div class="total-row">
        <span>VAT (16.50%):</span>
        <span id="vatAmount"><?php echo number_format($invoice->vat_amount ?? 0, 2); ?></span>
        <input type="hidden" name="vat_amount" value="<?php echo htmlspecialchars($invoice->vat_amount ?? 0); ?>">
    </div>
    <div class="total-row">
        <span>Total Net Amount:</span>
        <span id="totalNetAmount"><?php echo number_format($invoice->total_net_amount ?? 0, 2); ?></span>
        <input type="hidden" name="total_net_amount" value="<?php echo htmlspecialchars($invoice->total_net_amount ?? 0); ?>">
    </div>
    <div class="total-row">
        <span>Total Paid:</span>
        <span id="totalPaid"><?php echo number_format($invoice->total_paid ?? 0, 2); ?></span>
        <input type="hidden" name="total_paid" value="<?php echo htmlspecialchars($invoice->total_paid ?? 0); ?>">
    </div>
    <div class="total-row">
        <span>Balance Due:</span>
        <span id="balanceDue"><?php echo number_format($invoice->balance_due ?? ($invoice->total_net_amount ?? 0) - ($invoice->total_paid ?? 0), 2); ?></span>
        <input type="hidden" name="balance_due" value="<?php echo htmlspecialchars($invoice->balance_due ?? ($invoice->total_net_amount ?? 0) - ($invoice->total_paid ?? 0)); ?>">
    </div>
</div>

<?php if (isset($invoice)): // Only show status dropdown on edit ?>
<div class="row">
    <div class="col-half">
        <div class="form-group">
            <label for="status">Invoice Status:</label>
            <select id="status" name="status">
                <option value="Draft" <?php echo ($invoice->status == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                <option value="Finalized" <?php echo ($invoice->status == 'Finalized') ? 'selected' : ''; ?>>Finalized</option>
                <option value="Paid" <?php echo ($invoice->status == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                <option value="Partially Paid" <?php echo ($invoice->status == 'Partially Paid') ? 'selected' : ''; ?>>Partially Paid</option>
                <option value="Overdue" <?php echo ($invoice->status == 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                <option value="Cancelled" <?php echo ($invoice->status == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
    </div>
</div>
<?php endif; ?>