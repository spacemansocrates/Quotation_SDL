

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Quotation</title>
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
    <a href="admin_quotations.php" class="btn btn-ghost">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
            <path d="M19 12H5"></path>
            <path d="M12 19l-7-7 7-7"></path>
        </svg>
        <span>Back to Quotations</span>
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
    <h1>Create New Quotation</h1>

    <form id="quotationForm">

        <div class="form-section">
       <div class="form-section">
            <h2>1. Select Shop(s)</h2>
            <label for="shops">Shop Code(s):</label>
            <select name="shops[]" id="shops" multiple required>
                <?php
                    // Database connection
                    $servername = "localhost";
                    $username = "root";
                    $password = "";
                    $dbname = "supplies";

                    try {
                        $conn_shops = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
                        $conn_shops->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $stmt_shops = $conn_shops->prepare("SELECT id, shop_code FROM shops ORDER BY shop_code ASC");
                        $stmt_shops->execute();

                        $shop_results = $stmt_shops->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($shop_results as $shop_row) {
                            echo '<option value="' . htmlspecialchars($shop_row['id']) . '">' . htmlspecialchars($shop_row['shop_code']) . '</option>';
                        }
                    } catch(PDOException $e) {
                        echo '<option value="">Error loading shops</option>';
                    }
                    $conn_shops = null;
                ?>
            </select>
            <small>Hold Ctrl (or Cmd on Mac) to select multiple shops.</small>
        </div>
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
            <hr>
            <h4>Totals:</h4>
            <p>Gross Total: <span id="gross_total_display">0.00</span></p>
            </div>


        <div class="form-section" id="optional-data-section">
            <h2>4. Optional Information</h2>
            <label for="notes_general">General Note:</label>
            <textarea id="notes_general" name="notes_general"></textarea>

            <label for="delivery_period">Delivery Period:</label>
            <input type="text" id="delivery_period" name="delivery_period" placeholder="e.g., 7-14 days">

            <label for="payment_terms">Payment Terms:</label>
            <input type="text" id="payment_terms" name="payment_terms" placeholder="e.g., 30 days net">

            <label for="quotation_validity_days">Quotation Validity (days):</label>
            <input type="number" id="quotation_validity_days" name="quotation_validity_days" value="30">

            <label for="mra_wht_note_content">MRA Withholding Tax Note Content:</label>
<textarea id="mra_wht_note_content" name="mra_wht_note_content" placeholder="Enter MRA withholding tax note...">MRA WITH HOLDING TAX EXEPTION CERTIFICATE NO.MRA/BMTO/WHTEC/008104 VALID UPTO 31-03-2026</textarea>

            <div>
                <input type="checkbox" id="apply_ppda_levy" name="apply_ppda_levy">
                <label for="apply_ppda_levy" style="display: inline;">Apply PPDA Levy (1%)?</label>
            </div>
        </div>
<hr>
<h4>Totals:</h4>
<p>Gross Total (Sum of Item Totals): <span id="gross_total_display">0.00</span></p>
<p>PPDA Levy (1%): <span id="ppda_levy_amount_display">0.00</span></p>
<p>Amount Before VAT: <span id="amount_before_vat_display">0.00</span></p>
<div>
    <label for="vat_percentage_input_id">VAT Percentage (%):</label>
    <input type="number" id="vat_percentage_input_id" name="vat_percentage" value="16.5" step="0.1" style="width: 100px;"> </div>
<p>VAT Amount: <span id="vat_amount_display">0.00</span></p>
<p><strong>Total Net Amount: <span id="total_net_amount_display">0.00</span></strong></p>
        <div class="form-section" id="summary-section">
            <h2>5. Quotation Summary</h2>
            <div id="summary_area">
                </div>
        </div>

        <div class="form-section">
            <label for="quotation_date">Quotation Date:</label>
            <input type="date" id="quotation_date" name="quotation_date" required>

            <label for="company_tpin">Company TPIN:</label>
            <input type="text" id="company_tpin" name="company_tpin" placeholder="Enter Company TPIN">


            <button type="button" id="generateSummaryBtn">Review Quotation</button>
            <button type="submit" id="submitQuotationBtn" style="display:none;">Generate Quotation</button>
        </div>
    </form>
</div>

<script>
    // JavaScript will go here
    document.addEventListener('DOMContentLoaded', function() {
        // Set default quotation date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('quotation_date').value = today;

        // Override checkboxes logic
        document.getElementById('customer_name_override_checkbox').addEventListener('change', function() {
            document.getElementById('customer_name_override').style.display = this.checked ? 'block' : 'none';
        });
        document.getElementById('customer_address_override_checkbox').addEventListener('change', function() {
            document.getElementById('customer_address_override').style.display = this.checked ? 'block' : 'none';
        });

        // More JS in subsequent phases
        // (Inside the DOMContentLoaded event listener)

const customerSearchInput = document.getElementById('customer_search');
const customerSearchResultsDiv = document.getElementById('customer_search_results');
const customerIdInput = document.getElementById('customer_id');
const customerCodeDisplay = document.getElementById('customer_code_display');
const customerAddressLine1Display = document.getElementById('customer_address_line1');
// Add other customer detail display elements here

customerSearchInput.addEventListener('keyup', function() {
    const query = this.value;
    if (query.length < 2) { // Start searching after 2 characters
        customerSearchResultsDiv.innerHTML = '';
        return;
    }

    // AJAX call to PHP script for searching customers
    fetch('search_customers.php?query=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            customerSearchResultsDiv.innerHTML = ''; // Clear previous results
            if (data.error) {
                customerSearchResultsDiv.innerHTML = `<p>${data.error}</p>`;
            } else if (data.length > 0) {
                const ul = document.createElement('ul');
                data.forEach(customer => {
                    const li = document.createElement('li');
                    li.textContent = `${customer.customer_code} - ${customer.name} ${customer.address_line1}`; // Adjust as per your fields
                    li.style.cursor = 'pointer';
                    li.addEventListener('click', function() {
                        customerIdInput.value = customer.id;
                        customerCodeDisplay.value = customer.customer_code;
                        customerAddressLine1Display.textContent = customer.address_line1 || 'N/A';
                        // Populate other customer detail fields
                        // customer_details_display.querySelector(...) = customer.other_field;
                        customerSearchInput.value = `${customer.customer_code} - ${customer.name} ${customer.address_line1}`;
                        customerSearchResultsDiv.innerHTML = ''; // Clear results after selection
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
    });
    // (Inside the DOMContentLoaded event listener or your scripts.js)

const addItemBtn = document.getElementById('addItemBtn');
const itemsContainer = document.getElementById('items_container');
const grossTotalDisplay = document.getElementById('gross_total_display');
let itemCounter = 0;

addItemBtn.addEventListener('click', function() {
    itemCounter++;
    const itemRow = document.createElement('div');
    itemRow.classList.add('item-row');
    itemRow.setAttribute('id', `item-row-${itemCounter}`);
    itemRow.innerHTML = `
        <input type="text" name="item_search[]" class="item-search" placeholder="Search SKU or Name (Optional)" style="flex-grow:2;">
        <div class="item-search-results"></div>
        <input type="hidden" name="product_id[]" class="product-id">
        <input type="text" name="item_name[]" class="item-name" placeholder="Item Name / Product Name">
        <input type="text" name="item_description[]" class="item-description" placeholder="Description (Optional)">
        <input type="number" name="item_quantity[]" class="item-quantity" value="1" min="1" placeholder="Qty" style="width: 70px;">
        <input type="text" name="item_uom[]" class="item-uom" placeholder="UoM">
        <input type="number" name="item_unit_price[]" class="item-unit-price" step="0.01" placeholder="Unit Price">
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
    const totalInput = itemRow.querySelector('.item-total');
    const removeBtn = itemRow.querySelector('.remove-item');
    const imageUploadInput = itemRow.querySelector('.item-image-upload');
    const imagePreview = itemRow.querySelector('.item-image-preview');
    const uploadImageBtn = itemRow.querySelector('.upload-image-btn');

    itemSearchInput.addEventListener('keyup', function() {
        const query = this.value;
        if (query.length < 2) {
            itemSearchResultsDiv.innerHTML = '';
            // If user clears search, treat it as a potential manual entry.
            // Clear the product ID to ensure it's not linked to a product.
            productIdInput.value = '';
            // Keep existing manual text in name/description/price
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
                        li.textContent = `${product.sku} - ${product.name} (Price: ${product.default_unit_price})`;
                        li.style.cursor = 'pointer';
                        li.addEventListener('click', function() {
                            productIdInput.value = product.id;
                            itemNameInput.value = product.name; // Populate from product
                            itemDescriptionInput.value = product.description || ''; // Populate from product
                            unitPriceInput.value = parseFloat(product.default_unit_price).toFixed(2); // Populate from product
                            uomInput.value = product.default_unit_of_measurement || ''; // Populate from product
                            if (product.default_image_path) {
                                imagePreview.src = product.default_image_path;
                                imagePreview.style.display = 'block';
                            } else {
                                imagePreview.src = '';
                                imagePreview.style.display = 'none';
                            }
                            itemSearchInput.value = `${product.sku} - ${product.name}`; // Set search box to selected product
                            itemSearchResultsDiv.innerHTML = ''; // Clear search results
                            calculateItemTotal(itemRow);
                            calculateOverallTotals();
                        });
                        ul.appendChild(li);
                    });
                    itemSearchResultsDiv.appendChild(ul);
                } else {
                    itemSearchResultsDiv.innerHTML = '<p>No products found. You can enter details manually.</p>';
                    // If no products found, ensure product_id is cleared for manual entry
                    productIdInput.value = '';
                }
            })
            .catch(error => {
                console.error('Error fetching products:', error);
                itemSearchResultsDiv.innerHTML = '<p>Error searching products.</p>';
                productIdInput.value = ''; // Clear on error too
            });
    });

    // Add input listeners for manual changes to name, description, quantity, price, and UoM
    // This ensures recalculations happen even if no product is selected.
    itemNameInput.addEventListener('input', function() {
        // No total recalculation needed for name/description changes
    });
    itemDescriptionInput.addEventListener('input', function() {
        // No total recalculation needed for name/description changes
    });
    quantityInput.addEventListener('input', function() {
        calculateItemTotal(itemRow);
        calculateOverallTotals();
    });
    unitPriceInput.addEventListener('input', function() {
        calculateItemTotal(itemRow);
        calculateOverallTotals();
    });
    uomInput.addEventListener('input', function() {
        // No total recalculation needed for UoM changes
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
    const totalInput = itemRow.querySelector('.item-total');
    totalInput.value = (quantity * unitPrice).toFixed(2);
}

function calculateOverallTotals() {
    let grossTotal = 0;

    // Sum item totals
    document.querySelectorAll('.item-row').forEach(row => {
        grossTotal += parseFloat(row.querySelector('.item-total').value) || 0;
    });

    // Display gross total
    document.getElementById('gross_total_display').textContent = grossTotal.toFixed(2);

    // Check if PPDA levy should be applied
    const applyPPDA = document.getElementById('apply_ppda_levy').checked;

    // Get VAT percentage
    const vatPercentage = parseFloat(document.getElementById('vat_percentage_input_id')?.value || 16.5) / 100;

    // Calculate PPDA levy
    let ppdaLevyAmount = applyPPDA ? grossTotal * 0.01 : 0;
    document.getElementById('ppda_levy_amount_display').textContent = ppdaLevyAmount.toFixed(2);

    // Calculate VAT (only on gross total, NOT on PPDA)
    const vatAmount = grossTotal * vatPercentage;
    document.getElementById('vat_amount_display').textContent = vatAmount.toFixed(2);

    // Total Net Amount = Gross + PPDA + VAT
    const totalNetAmount = grossTotal + ppdaLevyAmount + vatAmount;
    document.getElementById('total_net_amount_display').textContent = totalNetAmount.toFixed(2);
}
// Initial call if there are pre-loaded items (e.g. when editing)
// calculateOverallTotals();

// Add these spans in your HTML, in the items section or summary section:
/*
HTML additions for totals in the "Items" section or create a new "Totals" subsection:
<p>PPDA Levy (1%): <span id="ppda_levy_amount_display">0.00</span></p>
<p>Amount Before VAT: <span id="amount_before_vat_display">0.00</span></p>
<label for="vat_percentage_input">VAT Percentage (%):</label>
<input type="number" id="vat_percentage_input_id" value="16.5" step="0.1"> <p>VAT Amount: <span id="vat_amount_display">0.00</span></p>
<p><strong>Total Net Amount: <span id="total_net_amount_display">0.00</span></strong></p>
*/

// Also ensure to call calculateOverallTotals() when PPDA checkbox or VAT percentage changes
if (document.getElementById('apply_ppda_levy')) {
    document.getElementById('apply_ppda_levy').addEventListener('change', calculateOverallTotals);
}
// Assuming you add a VAT percentage input with id 'vat_percentage_input_id'
if (document.getElementById('vat_percentage_input_id')) {
    document.getElementById('vat_percentage_input_id').addEventListener('input', calculateOverallTotals);
}
// (Inside the DOMContentLoaded event listener or your scripts.js)

const generateSummaryBtn = document.getElementById('generateSummaryBtn');
const summaryArea = document.getElementById('summary_area');
const submitQuotationBtn = document.getElementById('submitQuotationBtn');

generateSummaryBtn.addEventListener('click', function() {
    // Basic validation (can be more extensive)
    if (!document.getElementById('shops').value) {
        alert('Please select at least one shop.');
        return;
    }
    if (!document.getElementById('customer_id').value) {
        alert('Please select a customer.');
        return;
    }
    if (document.querySelectorAll('.item-row').length === 0) {
        alert('Please add at least one item to the quotation.');
        return;
    }
    if (!document.getElementById('quotation_date').value) {
        alert('Please select a quotation date.');
        return;
    }


    let summaryHTML = '<h3>Quotation Details:</h3>';

    // Shop(s)
    const selectedShops = Array.from(document.getElementById('shops').selectedOptions).map(opt => opt.text);
    summaryHTML += `<p><strong>Shop(s):</strong> ${selectedShops.join(', ')}</p>`;

    // Customer
    const customerName = document.getElementById('customer_name_override_checkbox').checked ?
                         document.getElementById('customer_name_override').value :
                         document.getElementById('customer_search').value; // Or fetch full name if stored separately
    const customerAddress = document.getElementById('customer_address_override_checkbox').checked ?
                            document.getElementById('customer_address_override').value :
                            document.getElementById('customer_address_line1').textContent;
    summaryHTML += `<p><strong>Customer:</strong> ${customerName}</p>`;
    summaryHTML += `<p><strong>Customer Address:</strong> ${customerAddress}</p>`;
    summaryHTML += `<p><strong>Customer Code:</strong> ${document.getElementById('customer_code_display').value}</p>`;


    summaryHTML += `<p><strong>Quotation Date:</strong> ${document.getElementById('quotation_date').value}</p>`;
    summaryHTML += `<p><strong>Company TPIN:</strong> ${document.getElementById('company_tpin').value || 'N/A'}</p>`;


    // Items
    summaryHTML += '<h4>Items:</h4><table border="1" style="width:100%; border-collapse: collapse;"><thead><tr><th>Product</th><th>Qty</th><th>UoM</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>';
    document.querySelectorAll('.item-row').forEach(row => {
        const name = row.querySelector('.item-name').value || row.querySelector('.item-search').value;
        const qty = row.querySelector('.item-quantity').value;
        const uom = row.querySelector('.item-uom').value || 'N/A';
        const unitPrice = parseFloat(row.querySelector('.item-unit-price').value).toFixed(2);
        const total = parseFloat(row.querySelector('.item-total').value).toFixed(2);
        summaryHTML += `<tr><td>${name}</td><td>${qty}</td><td>${uom}</td><td>${unitPrice}</td><td>${total}</td></tr>`;
    });
    summaryHTML += '</tbody></table>';

    // Totals
    summaryHTML += `<h4>Financials:</h4>`;
    summaryHTML += `<p><strong>Gross Total:</strong> ${document.getElementById('gross_total_display').textContent}</p>`;
    if (document.getElementById('apply_ppda_levy').checked) {
        summaryHTML += `<p><strong>PPDA Levy (1%):</strong> ${document.getElementById('ppda_levy_amount_display').textContent}</p>`;
    }
    summaryHTML += `<p><strong>Amount Before VAT:</strong> ${document.getElementById('amount_before_vat_display').textContent}</p>`;
    summaryHTML += `<p><strong>VAT (${document.getElementById('vat_percentage_input_id').value}%):</strong> ${document.getElementById('vat_amount_display').textContent}</p>`;
    summaryHTML += `<p><strong>Total Net Amount:</strong> ${document.getElementById('total_net_amount_display').textContent}</p>`;


    // Optional Data
    summaryHTML += '<h4>Optional Information:</h4>';
    summaryHTML += `<p><strong>General Note:</strong> ${document.getElementById('notes_general').value || 'N/A'}</p>`;
    summaryHTML += `<p><strong>Delivery Period:</strong> ${document.getElementById('delivery_period').value || 'N/A'}</p>`;
    summaryHTML += `<p><strong>Payment Terms:</strong> ${document.getElementById('payment_terms').value || 'N/A'}</p>`;
    summaryHTML += `<p><strong>Quotation Validity:</strong> ${document.getElementById('quotation_validity_days').value} days</p>`;
    summaryHTML += `<p><strong>MRA WHT Note:</strong> ${document.getElementById('mra_wht_note_content').value || 'N/A'}</p>`;


    summaryArea.innerHTML = summaryHTML;
    summaryArea.style.display = 'block';
    submitQuotationBtn.style.display = 'inline-block'; // Show the final submit button
    generateSummaryBtn.style.display = 'none'; // Hide review button
});

// Handle form submission
document.getElementById('quotationForm').addEventListener('submit', function(event) {
    event.preventDefault(); // Prevent default HTML form submission

    // Ensure summary has been generated and calculations are up-to-date
    calculateOverallTotals(); // Recalculate just in case

    const formData = new FormData(this);

    // Append calculated totals that are not direct form inputs but are needed server-side
    formData.append('gross_total_amount', document.getElementById('gross_total_display').textContent);
    formData.append('ppda_levy_amount', document.getElementById('apply_ppda_levy').checked ? document.getElementById('ppda_levy_amount_display').textContent : '0.00');
    formData.append('amount_before_vat', document.getElementById('amount_before_vat_display').textContent);
    formData.append('vat_amount', document.getElementById('vat_amount_display').textContent);
    formData.append('total_net_amount', document.getElementById('total_net_amount_display').textContent);
    formData.append('vat_percentage_used', document.getElementById('vat_percentage_input_id').value); // Send the VAT % used

    // You might want to disable the button to prevent multiple submissions
    submitQuotationBtn.disabled = true;
    submitQuotationBtn.textContent = 'Processing...';

    fetch('process_quotation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Quotation created successfully! Quotation Number: ' + data.quotation_number);
            // Optionally redirect or clear the form
            window.location.href = 'admin_quotations.php'; // Or wherever you manage quotations
        } else {
            alert('Error creating quotation: ' + data.message);
            submitQuotationBtn.disabled = false;
            submitQuotationBtn.textContent = 'Generate Quotation';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Success.');
        submitQuotationBtn.disabled = false;
        submitQuotationBtn.textContent = 'Generate Quotation';
    });
});
</script>

</body>
</html>