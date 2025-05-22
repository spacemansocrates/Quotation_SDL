<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Quotation</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 1000px; margin: auto; background: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        fieldset { margin-bottom: 20px; border: 1px solid #ccc; padding: 15px; border-radius: 5px; }
        legend { font-weight: bold; padding: 0 10px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea {
            width: calc(100% - 22px); /* Account for padding and border */
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea { min-height: 80px; }
        select[multiple] { min-height: 100px; }
        button {
            background-color: #007bff; color: white; padding: 10px 15px;
            border: none; border-radius: 4px; cursor: pointer; font-size: 16px;
            margin-right: 10px;
        }
        button:hover { background-color: #0056b3; }
        .item-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .item-row input, .item-row select { margin-bottom: 0; }
        .item-row .item-field { flex-grow: 1; }
        .item-row .item-quantity, .item-row .item-price { width: 80px; flex-grow: 0; }
        .item-row .item-total { width: 100px; flex-grow: 0; text-align: right; font-weight: bold; }
        .item-row .item-action button { background-color: #dc3545; font-size: 12px; padding: 5px 10px; }
        .item-row .item-action button:hover { background-color: #c82333; }
        #customer-details, #product-search-results { margin-top: 10px; padding: 10px; background: #e9ecef; border-radius: 4px; }
        .summary-section { margin-top: 20px; padding:15px; border:1px dashed #007bff; border-radius: 5px;}
        .summary-section h3 { margin-top: 0; }
        .totals-summary { margin-top: 20px; text-align: right; }
        .totals-summary div { margin-bottom: 5px; font-size: 1.1em; }
        .totals-summary strong { display: inline-block; width: 150px; text-align: left; }
        .hidden { display: none; }
        .optional-data-section { margin-top: 20px; }
        .optional-data-section label { font-weight: normal; display: flex; align-items: center; }
        .optional-data-section input[type="checkbox"] { width: auto; margin-right: 10px; }

    </style>
</head>
<body>
    

<div class="container">
    <h1>Create New Quotation</h1>

    <form id="createQuotationForm" method="POST" action="your_php_script_to_process_quotation.php">

        <!-- Step 1: Select Shop(s) -->
        <fieldset>
            <legend>Shop Information</legend>
            <label for="shops">Select Shop(s):</label>
            <select id="shops" name="shops[]" multiple required>
                <!-- PHP will populate this from the 'shops' table -->
                <!-- Example: <option value="1">Main Street Branch</option> -->
                <!-- Example: <option value="2">Downtown Outlet</option> -->
            </select>
        </fieldset>

        <!-- Step 2: Choose/Search Customer -->
        <fieldset>
            <legend>Customer Information</legend>
            <div>
                <label for="customer_search">Search Customer (by Name, Code, etc.):</label>
                <input type="text" id="customer_search" placeholder="Start typing to search...">
                <!-- JS will use this to populate #customer_id or display results -->
            </div>
            <div>
                <label for="customer_id">Select Customer:</label>
                <select id="customer_id" name="customer_id" required>
                    <option value="">-- Select Customer --</option>
                    <!-- JS will populate this based on search, or PHP can load all if few -->
                    <!-- Example: <option value="101" data-code="CUST001" data-address="123 Main St">John Doe (CUST001)</option> -->
                </select>
            </div>
            <div id="customer-details" class="hidden">
                <h4>Selected Customer Details:</h4>
                <p><strong>Code:</strong> <span id="cust-detail-code"></span></p>
                <p><strong>Address:</strong> <span id="cust-detail-address"></span></p>
                <!-- Add more details as needed -->
            </div>
        </fieldset>

        <!-- Step 3: Add Items -->
        <fieldset>
            <legend>Quotation Items</legend>
            <div>
                <label for="product_search">Search Product (by SKU, Name):</label>
                <input type="text" id="product_search" placeholder="Start typing SKU or product name...">
                <div id="product-search-results">
                    <!-- JS will populate this with search results -->
                    <!-- Example: <div class="product-result" data-id="1" data-sku="SKU001" data-name="Widget A" data-price="10.00" data-uom="Unit">Widget A (SKU001) - $10.00</div> -->
                </div>
            </div>

            <h4>Added Items:</h4>
            <div id="quotation-items-container">
                <!-- Item rows will be added here by JavaScript -->
                <!-- Example structure of an item row (managed by JS):
                <div class="item-row" data-item-id="PRODUCT_ID_HERE">
                    <input type="hidden" name="items[PRODUCT_ID_HERE][product_id]" value="PRODUCT_ID_HERE">
                    <span class="item-field item-name">Product Name (SKU)</span>
                    <textarea name="items[PRODUCT_ID_HERE][description]" class="item-field item-description" placeholder="Item Description">Default Description</textarea>
                    <input type="number" name="items[PRODUCT_ID_HERE][quantity]" class="item-field item-quantity" value="1" min="1" step="1" required>
                    <input type="number" name="items[PRODUCT_ID_HERE][unit_price]" class="item-field item-price" value="0.00" step="0.01" required>
                    <input type="text" name="items[PRODUCT_ID_HERE][unit_of_measurement]" class="item-field item-uom" placeholder="e.g., pcs, kg, m">
                    <span class="item-total">$0.00</span>
                    <div class="item-action">
                        <button type="button" class="remove-item-btn">Remove</button>
                    </div>
                     <input type="text" name="items[PRODUCT_ID_HERE][image_path]" class="item-field item-image" placeholder="Optional image path">
                </div>
                -->
            </div>
            <button type="button" id="add-item-manual-btn" class="hidden">Add Item Manually (if search fails or custom item)</button>

            <div class="totals-summary">
                <div><strong>Subtotal:</strong> <span id="subtotal_amount">0.00</span></div>
                <div id="ppda_line" class="hidden"><strong>PPDA (1%):</strong> <span id="ppda_amount">0.00</span></div>
                <!-- VAT can be added here if needed -->
                <div><strong>GRAND TOTAL:</strong> <span id="grand_total_amount">0.00</span></div>
            </div>
        </fieldset>

        <!-- Step 4: Optional Data -->
        <fieldset class="optional-data-section">
            <legend>Optional Quotation Details</legend>
            <div>
                <label for="general_note">General Note:</label>
                <textarea id="general_note" name="general_note" placeholder="Any general notes for the quotation..."></textarea>
            </div>
            <div>
                <label for="delivery_period">Delivery Period:</label>
                <input type="text" id="delivery_period" name="delivery_period" placeholder="e.g., 3-5 working days">
            </div>
            <div>
                <label for="payment_terms">Payment Terms:</label>
                <textarea id="payment_terms" name="payment_terms" placeholder="e.g., 50% advance, 50% on delivery"></textarea>
            </div>
            <div>
                <label for="quotation_validity">Quotation Validity:</label>
                <input type="text" id="quotation_validity" name="quotation_validity" placeholder="e.g., 30 days">
            </div>
            <div>
                <label>
                    <input type="checkbox" id="apply_mra_note" name="apply_mra_note" value="1">
                    Include MRA Withholding Tax Note
                </label>
            </div>
            <div>
                <label>
                    <input type="checkbox" id="apply_ppda" name="apply_ppda" value="1">
                    Apply PPDA (1% before VAT)
                </label>
            </div>
        </fieldset>

        <!-- Step 5: Summary (populated by JS before final submission) -->
        <div id="quotation-summary-section" class="summary-section hidden">
            <h2>Quotation Summary</h2>
            <div id="summary-shop"><strong>Shop(s):</strong> <span></span></div>
            <div id="summary-customer"><strong>Customer:</strong> <span></span></div>
            <h3>Items:</h3>
            <div id="summary-items-list">
                <!-- JS will populate this with a formatted list/table of items -->
            </div>
            <div id="summary-totals" class="totals-summary">
                 <div><strong>Subtotal:</strong> <span id="summary_subtotal_amount">0.00</span></div>
                 <div id="summary_ppda_line" class="hidden"><strong>PPDA (1%):</strong> <span id="summary_ppda_amount">0.00</span></div>
                 <div><strong>GRAND TOTAL:</strong> <span id="summary_grand_total_amount">0.00</span></div>
            </div>
            <h3>Optional Details:</h3>
            <div id="summary-general-note"><strong>General Note:</strong> <span></span></div>
            <div id="summary-delivery-period"><strong>Delivery Period:</strong> <span></span></div>
            <div id="summary-payment-terms"><strong>Payment Terms:</strong> <span></span></div>
            <div id="summary-quotation-validity"><strong>Validity:</strong> <span></span></div>
            <div id="summary-mra-note"><strong>MRA Note:</strong> <span></span></div>
            <div id="summary-ppda-status"><strong>PPDA Applied:</strong> <span></span></div>
        </div>

        <!-- Step 6: Actions -->
        <div style="margin-top: 30px; text-align: right;">
            <button type="button" id="preview-quotation-btn">Preview Quotation</button>
            <button type="submit" id="generate-quotation-btn" class="hidden">Generate Quotation</button>
            <!-- Could also have a "Save as Draft" button -->
        </div>
    </form>
</div>

<script>
    // Basic JS logic placeholders (you'll expand this significantly)
    document.addEventListener('DOMContentLoaded', function() {
        // --- SHOP SELECTION ---
        // For a better UX with multi-select, consider libraries like Select2 or TomSelect.
        // For now, it's a standard multi-select.

        // --- CUSTOMER SELECTION ---
        const customerSearchInput = document.getElementById('customer_search');
        const customerSelect = document.getElementById('customer_id');
        const customerDetailsDiv = document.getElementById('customer-details');
        const custDetailCode = document.getElementById('cust-detail-code');
        const custDetailAddress = document.getElementById('cust-detail-address');

        // TODO: Implement AJAX search for customers
        // customerSearchInput.addEventListener('keyup', function() { /* AJAX call */ });

        customerSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                // Assuming data attributes are set on options by PHP/JS
                // custDetailCode.textContent = selectedOption.dataset.code || 'N/A';
                // custDetailAddress.textContent = selectedOption.dataset.address || 'N/A';
                // Dummy data for now
                if (selectedOption.value === "101") { // Example customer ID
                     custDetailCode.textContent = "CUST001";
                     custDetailAddress.textContent = "123 Main St, Anytown";
                } else {
                     custDetailCode.textContent = "N/A";
                     custDetailAddress.textContent = "N/A";
                }
                customerDetailsDiv.classList.remove('hidden');
            } else {
                customerDetailsDiv.classList.add('hidden');
            }
        });
        // --- END CUSTOMER ---


        // --- ITEM HANDLING ---
        const productSearchInput = document.getElementById('product_search');
        const productSearchResultsDiv = document.getElementById('product-search-results');
        const itemsContainer = document.getElementById('quotation-items-container');
        let itemIndex = 0; // For unique names for array submission

        // TODO: Implement AJAX search for products
        // productSearchInput.addEventListener('keyup', function() { /* AJAX call to search products */ });

        // TODO: Function to add product to items list when clicked from search results
        // This is a simplified placeholder for adding an item.
        // In reality, you'd click a search result, and that would trigger addItemToQuote.
        function addItemToQuote(product) { // product object from search result
            itemIndex++;
            const itemRow = document.createElement('div');
            itemRow.classList.add('item-row');
            itemRow.dataset.itemId = product.id; // e.g., product.id
            itemRow.innerHTML = `
                <input type="hidden" name="items[${itemIndex}][product_id]" value="${product.id}">
                <input type="hidden" name="items[${itemIndex}][sku]" value="${product.sku}">
                <input type="hidden" name="items[${itemIndex}][name]" value="${product.name}">
                <div class="item-field item-name" style="width:150px;">${product.name} (${product.sku})</div>
                <textarea name="items[${itemIndex}][description]" class="item-field item-description" placeholder="Item Description">${product.description || ''}</textarea>
                <input type="number" name="items[${itemIndex}][quantity]" class="item-field item-quantity" value="1" min="1" step="1" required>
                <input type="number" name="items[${itemIndex}][unit_price]" class="item-field item-price" value="${product.default_unit_price || '0.00'}" step="0.01" required>
                <input type="text" name="items[${itemIndex}][unit_of_measurement]" class="item-field item-uom" value="${product.default_unit_of_measurement || ''}" placeholder="e.g., pcs">
                <span class="item-total" style="width:80px; text-align:right;">${parseFloat(product.default_unit_price || 0).toFixed(2)}</span>
                <input type="text" name="items[${itemIndex}][image_path]" class="item-field item-image" value="${product.default_image_path || ''}" placeholder="Optional image path">
                <div class="item-action">
                    <button type="button" class="remove-item-btn">X</button>
                </div>
            `;
            itemsContainer.appendChild(itemRow);
            attachItemEventListeners(itemRow);
            updateTotals();
        }

        // Dummy product search result click handler
        // In a real scenario, productSearchResultsDiv would be populated by AJAX
        // and you'd add event listeners to those results.
        // For demo, let's simulate adding a product:
        // addItemToQuote({ id: 1, sku: 'TEST001', name: 'Test Product', description: 'A test product description.', default_unit_price: 25.50, default_unit_of_measurement: 'pc', default_image_path: '' });


        // Event delegation for remove buttons and quantity/price changes
        itemsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item-btn')) {
                e.target.closest('.item-row').remove();
                updateTotals();
            }
        });

        function attachItemEventListeners(itemRow) {
            const quantityInput = itemRow.querySelector('.item-quantity');
            const priceInput = itemRow.querySelector('.item-price');
            quantityInput.addEventListener('input', updateTotals);
            priceInput.addEventListener('input', updateTotals);
        }
        
        // This function should be called after any item is added/removed or qty/price changes
        function updateTotals() {
            let subtotal = 0;
            const items = itemsContainer.querySelectorAll('.item-row');
            items.forEach(row => {
                const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
                const price = parseFloat(row.querySelector('.item-price').value) || 0;
                const itemTotal = quantity * price;
                row.querySelector('.item-total').textContent = itemTotal.toFixed(2);
                subtotal += itemTotal;
            });

            document.getElementById('subtotal_amount').textContent = subtotal.toFixed(2);

            let grandTotal = subtotal;
            const ppdaCheckbox = document.getElementById('apply_ppda');
            const ppdaLine = document.getElementById('ppda_line');
            const ppdaAmountSpan = document.getElementById('ppda_amount');

            if (ppdaCheckbox.checked) {
                const ppdaAmount = subtotal * 0.01; // 1%
                ppdaAmountSpan.textContent = ppdaAmount.toFixed(2);
                grandTotal += ppdaAmount; // PPDA is usually an addition to the cost for the client
                                          // OR it's a tax withheld, meaning it REDUCES payment received.
                                          // Clarify "PPDA which is 1% before vat".
                                          // Assuming it's an *additional charge* for now.
                                          // If it's a withholding tax, it's an info line, doesn't change grand total payable by customer.
                                          // Let's assume it's an additional cost that the *customer* pays on top of subtotal.
                ppdaLine.classList.remove('hidden');
            } else {
                ppdaAmountSpan.textContent = "0.00";
                ppdaLine.classList.add('hidden');
            }
            // TODO: Add VAT calculation here if needed, after PPDA.
            document.getElementById('grand_total_amount').textContent = grandTotal.toFixed(2);
        }

        document.getElementById('apply_ppda').addEventListener('change', updateTotals);
        updateTotals(); // Initial calculation
        // --- END ITEM HANDLING ---


        // --- OPTIONAL DATA & SUMMARY ---
        const previewBtn = document.getElementById('preview-quotation-btn');
        const generateBtn = document.getElementById('generate-quotation-btn');
        const summarySection = document.getElementById('quotation-summary-section');

        previewBtn.addEventListener('click', function() {
            // Validate required fields before showing summary
            let isValid = true;
            // Example basic validation
            if (!document.getElementById('shops').value) {
                alert('Please select at least one shop.');
                isValid = false;
            }
            if (!document.getElementById('customer_id').value) {
                alert('Please select a customer.');
                isValid = false;
            }
            if (itemsContainer.children.length === 0) {
                alert('Please add at least one item to the quotation.');
                isValid = false;
            }
            
            if (!isValid) return;


            // Populate Summary - Shop(s)
            const selectedShops = Array.from(document.getElementById('shops').selectedOptions).map(opt => opt.textContent);
            document.querySelector('#summary-shop span').textContent = selectedShops.join(', ') || 'N/A';

            // Populate Summary - Customer
            const customerOption = document.getElementById('customer_id').selectedOptions[0];
            document.querySelector('#summary-customer span').textContent = customerOption ? customerOption.textContent : 'N/A';

            // Populate Summary - Items (simple list for now, could be a table)
            const summaryItemsList = document.getElementById('summary-items-list');
            summaryItemsList.innerHTML = ''; // Clear previous
            const items = itemsContainer.querySelectorAll('.item-row');
            if (items.length > 0) {
                const ul = document.createElement('ul');
                items.forEach(row => {
                    const name = row.querySelector('.item-name').textContent;
                    const qty = row.querySelector('.item-quantity').value;
                    const price = row.querySelector('.item-price').value;
                    const total = row.querySelector('.item-total').textContent;
                    const li = document.createElement('li');
                    li.textContent = `${name} - Qty: ${qty}, Price: ${price}, Total: ${total}`;
                    ul.appendChild(li);
                });
                summaryItemsList.appendChild(ul);
            } else {
                summaryItemsList.textContent = 'No items added.';
            }
            
            // Populate Summary - Totals
            document.getElementById('summary_subtotal_amount').textContent = document.getElementById('subtotal_amount').textContent;
            if (document.getElementById('apply_ppda').checked) {
                document.getElementById('summary_ppda_line').classList.remove('hidden');
                document.getElementById('summary_ppda_amount').textContent = document.getElementById('ppda_amount').textContent;

            } else {
                document.getElementById('summary_ppda_line').classList.add('hidden');
            }
            document.getElementById('summary_grand_total_amount').textContent = document.getElementById('grand_total_amount').textContent;


            // Populate Summary - Optional Data
            document.querySelector('#summary-general-note span').textContent = document.getElementById('general_note').value || 'N/A';
            document.querySelector('#summary-delivery-period span').textContent = document.getElementById('delivery_period').value || 'N/A';
            document.querySelector('#summary-payment-terms span').textContent = document.getElementById('payment_terms').value || 'N/A';
            document.querySelector('#summary-quotation-validity span').textContent = document.getElementById('quotation_validity').value || 'N/A';
            document.querySelector('#summary-mra-note span').textContent = document.getElementById('apply_mra_note').checked ? 'Yes' : 'No';
            document.querySelector('#summary-ppda-status span').textContent = document.getElementById('apply_ppda').checked ? 'Yes' : 'No';


            summarySection.classList.remove('hidden');
            generateBtn.classList.remove('hidden');
            previewBtn.classList.add('hidden'); // Hide preview, show generate
            window.scrollTo(0, summarySection.offsetTop - 20); // Scroll to summary
        });

        // If user changes anything after preview, hide summary and generate button
        document.getElementById('createQuotationForm').addEventListener('input', function(e){
            // Don't hide if the change is within the summary section itself (if it had inputs)
            if (!summarySection.contains(e.target)) {
                summarySection.classList.add('hidden');
                generateBtn.classList.add('hidden');
                previewBtn.classList.remove('hidden');
            }
        });

        // --- FORM SUBMISSION ---
        // document.getElementById('createQuotationForm').addEventListener('submit', function(event) {
        //     event.preventDefault(); // For AJAX submission or further validation
        //     // Final validation
        //     // Gather form data
        //     // Submit via AJAX or allow default form submission
        //     console.log('Form submitted');
        // });

        // --- INITIAL POPULATION (Example for Customer Select) ---
        // This would typically be done by PHP rendering the options directly,
        // or an initial AJAX call if the list is very long and needs searching from the start.
        // For demonstration, adding a dummy customer option to make the change event work.
        const dummyCustomerOption = document.createElement('option');
        dummyCustomerOption.value = "101";
        dummyCustomerOption.textContent = "John Doe (CUST001)";
        // Add data attributes if your JS relies on them for customer details display
        // dummyCustomerOption.dataset.code = "CUST001";
        // dummyCustomerOption.dataset.address = "123 Main St, Anytown";
        customerSelect.appendChild(dummyCustomerOption);
        
        // Dummy product search result for quick testing add item
        // In real app, this is populated by AJAX on product_search input
        productSearchResultsDiv.innerHTML = `
            <div class="product-result" style="cursor:pointer; padding:5px; border:1px solid #eee; margin-bottom:5px;"
                 data-id="1" data-sku="PROD001" data-name="Laptop XYZ"
                 data-description="High-performance laptop" data-price="1200.00" data-uom="Unit" data-image="">
                 Laptop XYZ (PROD001) - $1200.00 [Click to Add]
            </div>
            <div class="product-result" style="cursor:pointer; padding:5px; border:1px solid #eee;"
                 data-id="2" data-sku="SERV002" data-name="Consulting Hour"
                 data-description="One hour of expert consulting" data-price="150.00" data-uom="Hour" data-image="">
                 Consulting Hour (SERV002) - $150.00 [Click to Add]
            </div>
        `;
        productSearchResultsDiv.addEventListener('click', function(e){
            if(e.target.classList.contains('product-result')){
                const prodData = e.target.dataset;
                addItemToQuote({
                    id: prodData.id,
                    sku: prodData.sku,
                    name: prodData.name,
                    description: prodData.description,
                    default_unit_price: prodData.price,
                    default_unit_of_measurement: prodData.uom,
                    default_image_path: prodData.image
                });
                productSearchInput.value = ''; // Clear search
                // productSearchResultsDiv.innerHTML = ''; // Clear results
            }
        });


    });
</script>

</body>
</html>