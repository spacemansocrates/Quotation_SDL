<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Invoice</title>
    <style>
        /* Basic styling for clarity */
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 1000px; margin: auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
        .form-section { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .form-section h2 { margin-top: 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        select[multiple] { height: 100px; }
        button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover { background-color: #0056b3; }
        .item-row { display: flex; align-items: center; margin-bottom: 10px; }
        .item-row > * { margin-right: 10px; }
        .item-row .remove-item { color: red; cursor: pointer; }
        #summary-area { background-color: #f9f9f9; padding: 15px; border-radius: 4px; }
    </style>
</head>
<body>

  <div class="header-actions">
    <a href="admin_invoices.php" class="btn btn-ghost">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
            <path d="M19 12H5"></path>
            <path d="M12 19l-7-7 7-7"></path>
        </svg>
        <span>Back to Invoices</span>
    </a>
</div>

<style>
    /* Shadcn-inspired button styles */
    :root {
        --primary: hsl(220, 14%, 96%);
        --primary-hover: hsl(220, 13%, 91%);
        --primary-foreground: hsl(220, 9%, 12%);
        --muted: hsl(220, 9%, 46%);
        --border: hsl(220, 13%, 91%);
        --ring: hsl(224, 76%, 48%);
    }
    
    .header-actions {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .header-actions h1 {
        margin: 0;
        font-size: 1.875rem;
        font-weight: 600;
        letter-spacing: -0.025em;
        line-height: 1.2;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        height: 2.25rem;
        padding-left: 1rem;
        padding-right: 1rem;
        transition: all 150ms cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        text-decoration: none;
        white-space: nowrap;
        user-select: none;
    }
    
    .btn-ghost {
        background-color: transparent;
        color: var(--primary-foreground);
        border: 1px solid transparent;
    }
    
    .btn-ghost:hover {
        background-color: var(--primary);
        color: var(--primary-foreground);
    }
    
    .btn-ghost:focus {
        outline: 2px solid transparent;
        outline-offset: 2px;
        box-shadow: 0 0 0 2px var(--ring);
    }
    
    .icon {
        margin-right: 0.5rem;
        width: 1rem;
        height: 1rem;
    }
</style>
<div class="container">
    <h1>Create New Invoice</h1>

    <form id="invoiceForm">

        <div class="form-section">
            <h2>1. Select Shop</h2>
            <label for="shop_id">Shop:</label>
            <select name="shop_id" id="shop_id" required>
                <option value="">Select a Shop</option>
                <?php
                    // Database connection (ensure this is secure and ideally in a separate config file)
                    $servername = "localhost";
                    $username = "root";
                    $password = "";
                    $dbname = "supplies";

                    try {
                        $conn_shops = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
                        $conn_shops->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $stmt_shops = $conn_shops->prepare("SELECT id, shop_code, name FROM shops ORDER BY shop_code ASC");
                        $stmt_shops->execute();

                        $shop_results = $stmt_shops->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($shop_results as $shop_row) {
                            echo '<option value="' . htmlspecialchars($shop_row['id']) . '">' . htmlspecialchars($shop_row['shop_code']) . ' - ' . htmlspecialchars($shop_row['name']) . '</option>';
                        }
                    } catch(PDOException $e) {
                        echo '<option value="">Error loading shops</option>';
                    }
                    $conn_shops = null;
                ?>
            </select>
        </div>

        <div class="form-section">
            <h2>2. Select or Search Customer</h2>
            <label for="customer_search">Search Customer (by Code, Name, etc.):</label>
            <input type="text" id="customer_search" name="customer_search" placeholder="Start typing to search...">
            <div id="customer_search_results"></div>

            <label for="customer_id">Selected Customer ID:</label>
            <input type="text" id="customer_id" name="customer_id" readonly required>

            <label for="customer_code_display">Customer Code:</label>
            <input type="text" id="customer_code_display" name="customer_code_display" readonly>

            <p><strong>Customer Details:</strong></p>
            <div id="customer_details_display">
                <p>Address: <span id="customer_address_line1"></span></p>
                </div>

            <div>
                <input type="checkbox" id="customer_name_override_checkbox" name="customer_name_override_checkbox">
                <label for="customer_name_override_checkbox" style="display: inline;">Override Customer Name?</label>
                <input type="text" id="customer_name_override" name="customer_name_override" placeholder="Enter new customer name" style="display:none;">
            </div>
            <div>
                <input type="checkbox" id="customer_address_override_checkbox" name="customer_address_override_checkbox">
                <label for="customer_address_override_checkbox" style="display: inline;">Override Customer Address?</label>
                <textarea id="customer_address_override" name="customer_address_override" placeholder="Enter new customer address" style="display:none;"></textarea>
            </div>
        </div>

        <div class="form-section" id="items-section">
            <h2>3. Add Items</h2>
            <div id="items_container">
                </div>
            <button type="button" id="addItemBtn">Add Item</button>
            </div>

        <div class="form-section" id="optional-data-section">
            <h2>4. Optional Information & Financials</h2>
            <label for="notes_general">General Note:</label>
            <textarea id="notes_general" name="notes_general"></textarea>

            <label for="delivery_period">Delivery Period:</label>
            <input type="text" id="delivery_period" name="delivery_period" placeholder="e.g., 7-14 days">

            <label for="payment_terms">Payment Terms:</label>
            <input type="text" id="payment_terms" name="payment_terms" placeholder="e.g., 30 days net">

            <div>
                <input type="checkbox" id="apply_ppda_levy" name="apply_ppda_levy">
                <label for="apply_ppda_levy" style="display: inline;">Apply PPDA Levy (1%)?</label>
            </div>
            <hr>
            <h4>Totals:</h4>
            <p>Gross Total (Sum of Item Totals): <span id="gross_total_display">0.00</span></p>
            <p>PPDA Levy (1%): <span id="ppda_levy_amount_display">0.00</span></p>
            <p>Amount Before VAT: <span id="amount_before_vat_display">0.00</span></p> <div>
                <label for="vat_percentage_input_id">VAT Percentage (%):</label>
                <input type="number" id="vat_percentage_input_id" name="vat_percentage" value="16.5" step="0.1" style="width: 100px;">
            </div>
            <p>VAT Amount: <span id="vat_amount_display">0.00</span></p>
            <p><strong>Total Net Amount: <span id="total_net_amount_display">0.00</span></strong></p>
        </div>

        <div class="form-section" id="summary-section">
            <h2>5. Invoice Summary</h2>
            <div id="summary_area" style="display:none;">
                </div>
        </div>

        <div class="form-section">
            <label for="invoice_date">Invoice Date:</label>
            <input type="date" id="invoice_date" name="invoice_date" required>

            <label for="due_date">Due Date:</label>
            <input type="date" id="due_date" name="due_date">

            <label for="company_tpin">Company TPIN:</label>
            <input type="text" id="company_tpin" name="company_tpin" placeholder="Enter Company TPIN (if different from shop)">
            <button type="button" id="generateSummaryBtn">Review Invoice</button>
            <button type="submit" id="submitInvoiceBtn" style="display:none;">Generate Invoice</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('invoice_date').value = today;

    document.getElementById('customer_name_override_checkbox').addEventListener('change', function() {
        document.getElementById('customer_name_override').style.display = this.checked ? 'block' : 'none';
    });
    document.getElementById('customer_address_override_checkbox').addEventListener('change', function() {
        document.getElementById('customer_address_override').style.display = this.checked ? 'block' : 'none';
    });

    const customerSearchInput = document.getElementById('customer_search');
    const customerSearchResultsDiv = document.getElementById('customer_search_results');
    const customerIdInput = document.getElementById('customer_id');
    const customerCodeDisplay = document.getElementById('customer_code_display');
    const customerAddressLine1Display = document.getElementById('customer_address_line1');

    customerSearchInput.addEventListener('keyup', function() {
        const query = this.value;
        if (query.length < 2) {
            customerSearchResultsDiv.innerHTML = '';
            return;
        }
        fetch('search_customers.php?query=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                customerSearchResultsDiv.innerHTML = '';
                if (data.error) {
                    customerSearchResultsDiv.innerHTML = `<p>${data.error}</p>`;
                } else if (data.length > 0) {
                    const ul = document.createElement('ul');
                    data.forEach(customer => {
                        const li = document.createElement('li');
                        li.textContent = `${customer.customer_code} - ${customer.name} (${customer.address_line1 || 'No address'})`;
                        li.style.cursor = 'pointer';
                        li.addEventListener('click', function() {
                            customerIdInput.value = customer.id;
                            customerCodeDisplay.value = customer.customer_code;
                            customerAddressLine1Display.textContent = customer.address_line1 || 'N/A';
                            customerSearchInput.value = `${customer.customer_code} - ${customer.name}`;
                            customerSearchResultsDiv.innerHTML = '';
                        });
                        ul.appendChild(li);
                    });
                    customerSearchResultsDiv.appendChild(ul);
                } else {
                    customerSearchResultsDiv.innerHTML = '<p>No customers found.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching customers:', error);
                customerSearchResultsDiv.innerHTML = '<p>Error searching customers.</p>';
            });
    });

    const addItemBtn = document.getElementById('addItemBtn');
    const itemsContainer = document.getElementById('items_container');
    let itemCounter = 0;

    addItemBtn.addEventListener('click', function() {
        itemCounter++;
        const itemRow = document.createElement('div');
        itemRow.classList.add('item-row');
        itemRow.setAttribute('id', `item-row-${itemCounter}`);
        itemRow.innerHTML = `
            <input type="text" name="item_search[]" class="item-search" placeholder="Search SKU or Name (Optional)" style="flex-grow:2;">
            <div class="item-search-results" style="position:absolute; background-color:white; border:1px solid #ccc; z-index:100;"></div>
            <input type="hidden" name="product_id[]" class="product-id">
            <input type="text" name="item_name[]" class="item-name" placeholder="Item Name / Product Name" required>
            <input type="text" name="item_description[]" class="item-description" placeholder="Description (Optional)">
            <input type="number" name="item_quantity[]" class="item-quantity" value="1" min="0.01" step="0.01" placeholder="Qty" style="width: 70px;" required>
            <input type="text" name="item_uom[]" class="item-uom" placeholder="UoM">
            <input type="number" name="item_unit_price[]" class="item-unit-price" step="0.01" placeholder="Unit Price" required>
            <input type="text" name="item_total[]" class="item-total" readonly placeholder="Total">
            <span class="remove-item" data-id="${itemCounter}" style="cursor:pointer; color:red;">&times;</span>
            <input type="file" name="item_image_upload[]" class="item-image-upload" accept="image/*" style="display:none;">
            <img src="" class="item-image-preview" alt="Item Image" style="max-width: 50px; max-height: 50px; display:none; margin-left:10px;">
            <button type="button" class="upload-image-btn">Upload Img</button>
        `;
        itemsContainer.appendChild(itemRow);
        attachItemEventListeners(itemRow);
    });

    function attachItemEventListeners(itemRow) {
        const itemSearchInput = itemRow.querySelector('.item-search');
        const itemSearchResultsDiv = itemRow.querySelector('.item-search-results');
        const productIdInput = itemRow.querySelector('.product-id');
        const itemNameInput = itemRow.querySelector('.item-name');
        const itemDescriptionInput = itemRow.querySelector('.item-description');
        const quantityInput = itemRow.querySelector('.item-quantity');
        const unitPriceInput = itemRow.querySelector('.item-unit-price');
        const uomInput = itemRow.querySelector('.item-uom');
        const removeBtn = itemRow.querySelector('.remove-item');
        const imageUploadInput = itemRow.querySelector('.item-image-upload');
        const imagePreview = itemRow.querySelector('.item-image-preview');
        const uploadImageBtn = itemRow.querySelector('.upload-image-btn');

        itemSearchInput.addEventListener('keyup', function() {
            const query = this.value;
            if (query.length < 2) {
                itemSearchResultsDiv.innerHTML = '';
                productIdInput.value = ''; // Clear if search is too short
                return;
            }
            fetch('search_products.php?query=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    itemSearchResultsDiv.innerHTML = '';
                    if (data.error) {
                        itemSearchResultsDiv.innerHTML = `<p>${data.error}</p>`;
                    } else if (data.length > 0) {
                        const ul = document.createElement('ul');
                        data.forEach(product => {
                            const li = document.createElement('li');
                            li.textContent = `${product.sku || ''} - ${product.name} (Price: ${product.default_unit_price || 'N/A'})`;
                            li.style.cursor = 'pointer';
                            li.addEventListener('click', function() {
                                productIdInput.value = product.id;
                                itemNameInput.value = product.name;
                                itemDescriptionInput.value = product.description || '';
                                unitPriceInput.value = parseFloat(product.default_unit_price || 0).toFixed(2);
                                uomInput.value = product.default_unit_of_measurement || '';
                                if (product.default_image_path) {
                                    imagePreview.src = product.default_image_path;
                                    imagePreview.style.display = 'block';
                                } else {
                                    imagePreview.src = '';
                                    imagePreview.style.display = 'none';
                                }
                                itemSearchInput.value = `${product.sku || ''} - ${product.name}`;
                                itemSearchResultsDiv.innerHTML = '';
                                calculateItemTotal(itemRow);
                                calculateOverallTotals();
                            });
                            ul.appendChild(li);
                        });
                        itemSearchResultsDiv.appendChild(ul);
                    } else {
                        itemSearchResultsDiv.innerHTML = '<p>No products found. Enter manually.</p>';
                        productIdInput.value = ''; // Clear product ID for manual entry
                    }
                })
                .catch(error => {
                    console.error('Error fetching products:', error);
                    itemSearchResultsDiv.innerHTML = '<p>Error searching products.</p>';
                    productIdInput.value = '';
                });
        });

        [quantityInput, unitPriceInput].forEach(input => {
            input.addEventListener('input', () => {
                calculateItemTotal(itemRow);
                calculateOverallTotals();
            });
        });

        removeBtn.addEventListener('click', function() {
            itemRow.remove();
            calculateOverallTotals();
        });
        
        uploadImageBtn.addEventListener('click', function() {
            imageUploadInput.click();
        });

        imageUploadInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                imagePreview.src = '';
                imagePreview.style.display = 'none';
            }
        });
    }

    function calculateItemTotal(itemRow) {
        const quantity = parseFloat(itemRow.querySelector('.item-quantity').value) || 0;
        const unitPrice = parseFloat(itemRow.querySelector('.item-unit-price').value) || 0;
        itemRow.querySelector('.item-total').value = (quantity * unitPrice).toFixed(2);
    }

    window.calculateOverallTotals = function() { // Make it global for easy access if needed elsewhere
        let grossTotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            grossTotal += parseFloat(row.querySelector('.item-total').value) || 0;
        });
        document.getElementById('gross_total_display').textContent = grossTotal.toFixed(2);

        const applyPPDA = document.getElementById('apply_ppda_levy').checked;
        const ppdaPercentage = 0.01; // Assuming 1%
        let ppdaLevyAmount = applyPPDA ? grossTotal * ppdaPercentage : 0;
        document.getElementById('ppda_levy_amount_display').textContent = ppdaLevyAmount.toFixed(2);

        // Amount before VAT = Gross Total + PPDA Levy (as per original quotation logic)
        const amountBeforeVat = grossTotal ;
        document.getElementById('amount_before_vat_display').textContent = amountBeforeVat.toFixed(2);


        const vatPercentageInput = parseFloat(document.getElementById('vat_percentage_input_id').value || 16.5);
        const vatRate = vatPercentageInput / 100;
        // VAT is typically calculated on the amount *before* VAT, which in this case is (Gross + PPDA).
        // Or, if VAT is only on Gross: const vatAmount = grossTotal * vatRate;
        // Based on "Amount Before VAT" field usually being (Gross + other non-VAT levies), then VAT on that sum:
        const vatAmount = amountBeforeVat * vatRate;
        document.getElementById('vat_amount_display').textContent = vatAmount.toFixed(2);

        const totalNetAmount = amountBeforeVat + vatAmount+ ppdaLevyAmount;
        document.getElementById('total_net_amount_display').textContent = totalNetAmount.toFixed(2);
    }
    
    document.getElementById('apply_ppda_levy').addEventListener('change', calculateOverallTotals);
    document.getElementById('vat_percentage_input_id').addEventListener('input', calculateOverallTotals);


    const generateSummaryBtn = document.getElementById('generateSummaryBtn');
    const summaryArea = document.getElementById('summary_area');
    const submitInvoiceBtn = document.getElementById('submitInvoiceBtn');

    generateSummaryBtn.addEventListener('click', function() {
        if (!document.getElementById('shop_id').value) {
            alert('Please select a shop.'); return;
        }
        if (!document.getElementById('customer_id').value) {
            alert('Please select a customer.'); return;
        }
        if (document.querySelectorAll('.item-row').length === 0) {
            alert('Please add at least one item.'); return;
        }
        if (!document.getElementById('invoice_date').value) {
            alert('Please select an invoice date.'); return;
        }
        // Due date is optional in this setup, add validation if it becomes required
        
        calculateOverallTotals(); // Ensure totals are fresh before summary

        let summaryHTML = '<h3>Invoice Details:</h3>';
        
        const shopSelect = document.getElementById('shop_id');
        const selectedShopText = shopSelect.options[shopSelect.selectedIndex].text;
        summaryHTML += `<p><strong>Shop:</strong> ${selectedShopText}</p>`;
        
        const customerName = document.getElementById('customer_name_override_checkbox').checked ?
                             document.getElementById('customer_name_override').value :
                             document.getElementById('customer_search').value; // This might be "code - name", consider storing actual name
        const customerAddress = document.getElementById('customer_address_override_checkbox').checked ?
                                document.getElementById('customer_address_override').value :
                                document.getElementById('customer_address_line1').textContent;
        summaryHTML += `<p><strong>Customer:</strong> ${customerName}</p>`;
        summaryHTML += `<p><strong>Customer Address:</strong> ${customerAddress}</p>`;
        summaryHTML += `<p><strong>Customer Code:</strong> ${document.getElementById('customer_code_display').value}</p>`;
        
        summaryHTML += `<p><strong>Invoice Date:</strong> ${document.getElementById('invoice_date').value}</p>`;
        summaryHTML += `<p><strong>Due Date:</strong> ${document.getElementById('due_date').value || 'N/A'}</p>`;
        summaryHTML += `<p><strong>Company TPIN:</strong> ${document.getElementById('company_tpin').value || 'N/A'}</p>`;

        summaryHTML += '<h4>Items:</h4><table border="1" style="width:100%; border-collapse: collapse;"><thead><tr><th>#</th><th>Product/Service</th><th>Qty</th><th>UoM</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>';
        document.querySelectorAll('.item-row').forEach((row, index) => {
            const name = row.querySelector('.item-name').value || row.querySelector('.item-search').value;
            const qty = row.querySelector('.item-quantity').value;
            const uom = row.querySelector('.item-uom').value || 'N/A';
            const unitPrice = parseFloat(row.querySelector('.item-unit-price').value || 0).toFixed(2);
            const total = parseFloat(row.querySelector('.item-total').value || 0).toFixed(2);
            summaryHTML += `<tr><td>${index + 1}</td><td>${name}</td><td>${qty}</td><td>${uom}</td><td>${unitPrice}</td><td>${total}</td></tr>`;
        });
        summaryHTML += '</tbody></table>';

        summaryHTML += `<h4>Financials:</h4>`;
        summaryHTML += `<p><strong>Gross Total:</strong> ${document.getElementById('gross_total_display').textContent}</p>`;
        if (document.getElementById('apply_ppda_levy').checked) {
            summaryHTML += `<p><strong>PPDA Levy (1%):</strong> ${document.getElementById('ppda_levy_amount_display').textContent}</p>`;
        }
        summaryHTML += `<p><strong>Amount Before VAT:</strong> ${document.getElementById('amount_before_vat_display').textContent}</p>`;
        summaryHTML += `<p><strong>VAT (${document.getElementById('vat_percentage_input_id').value}%):</strong> ${document.getElementById('vat_amount_display').textContent}</p>`;
        summaryHTML += `<p><strong>Total Net Amount:</strong> ${document.getElementById('total_net_amount_display').textContent}</p>`;

        summaryHTML += '<h4>Optional Information:</h4>';
        summaryHTML += `<p><strong>General Note:</strong> ${document.getElementById('notes_general').value || 'N/A'}</p>`;
        summaryHTML += `<p><strong>Delivery Period:</strong> ${document.getElementById('delivery_period').value || 'N/A'}</p>`;
        summaryHTML += `<p><strong>Payment Terms:</strong> ${document.getElementById('payment_terms').value || 'N/A'}</p>`;
        
        summaryArea.innerHTML = summaryHTML;
        summaryArea.style.display = 'block';
        submitInvoiceBtn.style.display = 'inline-block';
        generateSummaryBtn.style.display = 'none';
    });

    document.getElementById('invoiceForm').addEventListener('submit', function(event) {
        event.preventDefault();
        calculateOverallTotals(); // Final calculation

        const formData = new FormData(this);
        formData.append('gross_total_amount', document.getElementById('gross_total_display').textContent);
        formData.append('ppda_levy_amount', document.getElementById('apply_ppda_levy').checked ? document.getElementById('ppda_levy_amount_display').textContent : '0.00');
        formData.append('amount_before_vat', document.getElementById('amount_before_vat_display').textContent); // This should be gross + ppda
        formData.append('vat_amount', document.getElementById('vat_amount_display').textContent);
        formData.append('total_net_amount', document.getElementById('total_net_amount_display').textContent);
        formData.append('vat_percentage_used', document.getElementById('vat_percentage_input_id').value);
        // Due date is already part of FormData via its name attribute.

        submitInvoiceBtn.disabled = true;
        submitInvoiceBtn.textContent = 'Processing...';

        fetch('process_invoice.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Invoice created successfully! Invoice Number: ' + (data.invoice_number || 'N/A'));
                window.location.href = 'admin_invoices.php';
            } else {
                alert('Error creating invoice: ' + (data.message || 'Unknown error'));
                submitInvoiceBtn.disabled = false;
                submitInvoiceBtn.textContent = 'Generate Invoice';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // If process_invoice.php doesn't return JSON or there's a network error, this will be caught.
            // Forcing a success message for now as per original code's catch block.
            alert('Submission attempt finished. Check server logs for details. Dev Note: A success message was shown due to original catch logic.');
            // A more robust error handling would be:
            // alert('An error occurred during submission. Please try again or contact support.');
            submitInvoiceBtn.disabled = false;
            submitInvoiceBtn.textContent = 'Generate Invoice';
        });
    });
});
</script>

</body>
</html>