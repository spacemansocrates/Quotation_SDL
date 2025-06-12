// assets/js/invoice.js

function initInvoiceForm(options = {}, formType = 'create') {
    const invoiceForm = document.getElementById('invoiceForm');
    const invoiceItemsTable = document.getElementById('invoiceItemsTable').getElementsByTagName('tbody')[0];
    const addItemBtn = document.getElementById('addItemBtn');
    const applyPpdaLevyCheckbox = document.getElementById('apply_ppda_levy');
    const shopSelect = document.getElementById('shop_id');
    const customerSelect = document.getElementById('customer_id');
    const companyTpinInput = document.getElementById('company_tpin');
    const quotationIdInput = document.getElementById('quotation_id');
    const loadQuotationBtn = document.getElementById('loadQuotationBtn');

    // Constants for percentages
    const PPDA_LEVY_PERCENTAGE = 1.00;
    const VAT_PERCENTAGE = 16.50;

    let allShops = options.shops || [];
    let allCustomers = options.customers || [];

    // Function to re-index item numbers
    function reindexItems() {
        const rows = invoiceItemsTable.querySelectorAll('.item-row');
        rows.forEach((row, index) => {
            row.querySelector('.item-number').textContent = index + 1;
        });
    }

    // Function to calculate individual item total
    function calculateItemTotal(row) {
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
        const total = (quantity * rate).toFixed(2);
        row.querySelector('.item-total').value = total;
        return parseFloat(total);
    }

    // Function to calculate all invoice totals
    function calculateInvoiceTotals() {
        let grossTotalAmount = 0;
        invoiceItemsTable.querySelectorAll('.item-row').forEach(row => {
            grossTotalAmount += calculateItemTotal(row);
        });

        let ppdaLevyAmount = 0;
        if (applyPpdaLevyCheckbox.checked) {
            ppdaLevyAmount = grossTotalAmount * (PPDA_LEVY_PERCENTAGE / 100);
        }

        let amountBeforeVat = grossTotalAmount + ppdaLevyAmount;
        let vatAmount = amountBeforeVat * (VAT_PERCENTAGE / 100);
        let totalNetAmount = amountBeforeVat + vatAmount;

        // Total paid comes from backend (for edit) or is 0 (for create)
        const totalPaidInput = document.getElementById('totalPaid');
        const totalPaid = parseFloat(totalPaidInput ? totalPaidInput.textContent.replace(/,/g, '') : (options.invoiceData ? options.invoiceData.total_paid : 0)) || 0;

        const balanceDue = totalNetAmount - totalPaid;

        document.getElementById('grossTotalAmount').textContent = grossTotalAmount.toFixed(2);
        document.querySelector('input[name="gross_total_amount"]').value = grossTotalAmount.toFixed(2);

        document.getElementById('ppdaLevyAmount').textContent = ppdaLevyAmount.toFixed(2);
        document.querySelector('input[name="ppda_levy_amount"]').value = ppdaLevyAmount.toFixed(2);

        document.getElementById('amountBeforeVat').textContent = amountBeforeVat.toFixed(2);
        document.querySelector('input[name="amount_before_vat"]').value = amountBeforeVat.toFixed(2);

        document.getElementById('vatAmount').textContent = vatAmount.toFixed(2);
        document.querySelector('input[name="vat_amount"]').value = vatAmount.toFixed(2);

        document.getElementById('totalNetAmount').textContent = totalNetAmount.toFixed(2);
        document.querySelector('input[name="total_net_amount"]').value = totalNetAmount.toFixed(2);

        // Update balance due display and hidden input
        document.getElementById('balanceDue').textContent = balanceDue.toFixed(2);
        document.querySelector('input[name="balance_due"]').value = balanceDue.toFixed(2);
    }

    // Add new item row
    function addNewItemRow(item = {}) {
        const newRow = invoiceItemsTable.insertRow();
        newRow.className = 'item-row';
        const currentIndex = invoiceItemsTable.rows.length;

        newRow.innerHTML = `
            <td><span class="item-number">${currentIndex}</span></td>
            <td>
                <input type="hidden" name="item_id[]" value="${item.id || ''}">
                <input type="hidden" name="item_product_id[]" class="item-product-id" value="${item.product_id || ''}">
                <textarea name="item_description[]" class="item-description" rows="2" required>${item.description || ''}</textarea>
            </td>
            <td>
                <input type="text" class="product-search-input" value="${item.product_sku || ''}" placeholder="Search SKU/Name">
                <div class="product-suggestions"></div>
            </td>
            <td><input type="number" name="item_quantity[]" class="item-quantity" step="0.01" min="0.01" value="${item.quantity || 1}" required></td>
            <td><input type="text" name="item_uom[]" class="item-uom" value="${item.unit_of_measurement || ''}"></td>
            <td><input type="number" name="item_rate[]" class="item-rate" step="0.01" min="0" value="${item.rate_per_unit || 0.00}" required></td>
            <td><input type="text" class="item-total" value="${(item.quantity * item.rate_per_unit).toFixed(2) || 0.00}" readonly></td>
            <td class="item-actions"><button type="button" class="remove-item-btn">X</button></td>
        `;

        attachItemRowListeners(newRow);
        reindexItems();
        calculateInvoiceTotals();
    }

    // Attach listeners to a single item row
    function attachItemRowListeners(row) {
        row.querySelector('.item-quantity').addEventListener('input', calculateInvoiceTotals);
        row.querySelector('.item-rate').addEventListener('input', calculateInvoiceTotals);
        row.querySelector('.remove-item-btn').addEventListener('click', function() {
            row.remove();
            reindexItems();
            calculateInvoiceTotals();
        });

        const productSearchInput = row.querySelector('.product-search-input');
        const productSuggestionsDiv = row.querySelector('.product-suggestions');
        const itemProductIdInput = row.querySelector('.item-product-id');
        const itemDescriptionInput = row.querySelector('.item-description');
        const itemUomInput = row.querySelector('.item-uom');
        const itemRateInput = row.querySelector('.item-rate');

        let searchTimeout;
        productSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value;
            if (query.length < 2) {
                productSuggestionsDiv.innerHTML = '';
                productSuggestionsDiv.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`../ajax/get_products.php?search=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(products => {
                        productSuggestionsDiv.innerHTML = '';
                        if (products.length > 0) {
                            products.forEach(product => {
                                const suggestionItem = document.createElement('div');
                                suggestionItem.className = 'suggestion-item';
                                suggestionItem.textContent = `${product.sku} - ${product.name} (${product.default_unit_price} / ${product.default_unit_of_measurement})`;
                                suggestionItem.dataset.productId = product.id;
                                suggestionItem.dataset.description = product.name;
                                suggestionItem.dataset.uom = product.default_unit_of_measurement;
                                suggestionItem.dataset.rate = product.default_unit_price;
                                suggestionItem.dataset.sku = product.sku;

                                suggestionItem.addEventListener('click', function() {
                                    itemProductIdInput.value = this.dataset.productId;
                                    itemDescriptionInput.value = this.dataset.description;
                                    itemUomInput.value = this.dataset.uom;
                                    itemRateInput.value = this.dataset.rate;
                                    productSearchInput.value = this.dataset.sku; // Set SKU in search box
                                    productSuggestionsDiv.innerHTML = '';
                                    productSuggestionsDiv.style.display = 'none';
                                    calculateInvoiceTotals(); // Recalculate totals after setting values
                                });
                                productSuggestionsDiv.appendChild(suggestionItem);
                            });
                            productSuggestionsDiv.style.display = 'block';
                        } else {
                            productSuggestionsDiv.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching products:', error);
                        productSuggestionsDiv.innerHTML = '<div>Error loading products.</div>';
                        productSuggestionsDiv.style.display = 'block';
                    });
            }, 300); // 300ms debounce
        });

        // Hide suggestions when input loses focus, but with a slight delay
        productSearchInput.addEventListener('blur', function() {
            setTimeout(() => {
                productSuggestionsDiv.style.display = 'none';
            }, 100);
        });
        // Show suggestions again when input gains focus if there's text
        productSearchInput.addEventListener('focus', function() {
            if (this.value.length >= 2 && productSuggestionsDiv.innerHTML !== '') {
                productSuggestionsDiv.style.display = 'block';
            }
        });
    }

    // Attach listeners to all initial rows (for edit mode)
    function attachAllItemRowListeners() {
        invoiceItemsTable.querySelectorAll('.item-row').forEach(row => {
            attachItemRowListeners(row);
        });
    }

    // Populate form fields in edit mode
    function populateFormForEdit(invoiceData, invoiceItems) {
        if (!invoiceData) return;

        document.getElementById('shop_id').value = invoiceData.shop_id || '';
        document.getElementById('customer_id').value = invoiceData.customer_id || '';
        document.getElementById('customer_name_override').value = invoiceData.customer_name_override || '';
        document.getElementById('customer_address_override').value = invoiceData.customer_address_override || '';
        document.getElementById('invoice_date').value = invoiceData.invoice_date || '';
        document.getElementById('due_date').value = invoiceData.due_date || '';
        document.getElementById('quotation_id').value = invoiceData.quotation_id || '';
        document.getElementById('company_tpin').value = invoiceData.company_tpin || ''; // This should be updated on shop change
        document.getElementById('notes_general').value = invoiceData.notes_general || '';
        document.getElementById('delivery_period').value = invoiceData.delivery_period || '';
        document.getElementById('payment_terms').value = invoiceData.payment_terms || '';
        applyPpdaLevyCheckbox.checked = invoiceData.apply_ppda_levy == 1;

        // Update fixed total values on the form from loaded data
        document.getElementById('grossTotalAmount').textContent = parseFloat(invoiceData.gross_total_amount || 0).toFixed(2);
        document.querySelector('input[name="gross_total_amount"]').value = parseFloat(invoiceData.gross_total_amount || 0).toFixed(2);
        document.getElementById('ppdaLevyAmount').textContent = parseFloat(invoiceData.ppda_levy_amount || 0).toFixed(2);
        document.querySelector('input[name="ppda_levy_amount"]').value = parseFloat(invoiceData.ppda_levy_amount || 0).toFixed(2);
        document.getElementById('amountBeforeVat').textContent = parseFloat(invoiceData.amount_before_vat || 0).toFixed(2);
        document.querySelector('input[name="amount_before_vat"]').value = parseFloat(invoiceData.amount_before_vat || 0).toFixed(2);
        document.getElementById('vatAmount').textContent = parseFloat(invoiceData.vat_amount || 0).toFixed(2);
        document.querySelector('input[name="vat_amount"]').value = parseFloat(invoiceData.vat_amount || 0).toFixed(2);
        document.getElementById('totalNetAmount').textContent = parseFloat(invoiceData.total_net_amount || 0).toFixed(2);
        document.querySelector('input[name="total_net_amount"]').value = parseFloat(invoiceData.total_net_amount || 0).toFixed(2);
        document.getElementById('totalPaid').textContent = parseFloat(invoiceData.total_paid || 0).toFixed(2);
        document.querySelector('input[name="total_paid"]').value = parseFloat(invoiceData.total_paid || 0).toFixed(2);
        document.getElementById('balanceDue').textContent = parseFloat(invoiceData.balance_due || 0).toFixed(2);
        document.querySelector('input[name="balance_due"]').value = parseFloat(invoiceData.balance_due || 0).toFixed(2);


        // Clear existing item rows before populating
        invoiceItemsTable.innerHTML = '';
        if (invoiceItems && invoiceItems.length > 0) {
            invoiceItems.forEach(item => addNewItemRow(item));
        } else {
            addNewItemRow(); // Add an empty row if no items
        }
        updateCompanyTpin(); // Ensure TPIN is correct if shop is pre-selected
        reindexItems();
    }


    // Event Listeners
    addItemBtn.addEventListener('click', () => addNewItemRow());
    applyPpdaLevyCheckbox.addEventListener('change', calculateInvoiceTotals);

    shopSelect.addEventListener('change', function() {
        updateCompanyTpin();
    });

    function updateCompanyTpin() {
        const selectedShopId = shopSelect.value;
        const selectedShopOption = shopSelect.options[shopSelect.selectedIndex];
        if (selectedShopOption && selectedShopOption.dataset.tpin) {
            companyTpinInput.value = selectedShopOption.dataset.tpin;
        } else {
            companyTpinInput.value = '';
        }
    }


    // Initial setup based on form type (create or edit)
    if (formType === 'edit' && options.invoiceData) {
        populateFormForEdit(options.invoiceData, options.invoiceItems);
    } else if (formType === 'create' && options.quotationId) {
        // If creating from a quotation
        quotationIdInput.value = options.quotationId;
        loadQuotationItems(options.quotationId);
    } else {
        addNewItemRow(); // For new invoice creation, start with one empty row
        calculateInvoiceTotals(); // Calculate initial totals
    }

    attachAllItemRowListeners(); // Attach listeners to all initial rows

    loadQuotationBtn.addEventListener('click', function() {
        const qId = quotationIdInput.value;
        if (qId) {
            loadQuotationItems(qId);
        } else {
            alert('Please enter a Quotation ID to load items.');
        }
    });

    function loadQuotationItems(quotationId) {
        fetch(`../ajax/load_quotation.php?quotation_id=${encodeURIComponent(quotationId)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const quotation = data.quotation;
                    const items = data.items;

                    // Populate main invoice fields from quotation
                    document.getElementById('shop_id').value = quotation.shop_id || '';
                    document.getElementById('customer_id').value = quotation.customer_id || '';
                    document.getElementById('customer_name_override').value = quotation.customer_name_override || '';
                    document.getElementById('customer_address_override').value = quotation.customer_address_override || '';
                    document.getElementById('delivery_period').value = quotation.delivery_period || '';
                    document.getElementById('payment_terms').value = quotation.payment_terms || '';
                    document.getElementById('notes_general').value = quotation.notes_general || '';
                    applyPpdaLevyCheckbox.checked = quotation.apply_ppda_levy == 1;

                    updateCompanyTpin(); // Update TPIN based on selected shop

                    // Clear existing invoice items
                    invoiceItemsTable.innerHTML = '';

                    // Add quotation items as invoice items
                    if (items.length > 0) {
                        items.forEach(item => {
                            addNewItemRow({
                                product_id: item.product_id,
                                description: item.description,
                                quantity: item.quantity,
                                unit_of_measurement: item.unit_of_measurement,
                                rate_per_unit: item.rate_per_unit
                            });
                        });
                    } else {
                        addNewItemRow(); // Add an empty row if no items in quotation
                    }

                    calculateInvoiceTotals(); // Recalculate based on loaded items
                    alert('Quotation items loaded successfully!');
                } else {
                    alert('Failed to load quotation: ' + (data.message || 'Unknown error.'));
                    console.error('Quotation load error:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching quotation:', error);
                alert('An error occurred while fetching quotation data.');
            });
    }
}