document.addEventListener('DOMContentLoaded', function () {
    // --- Configuration ---
    const AJAX_HANDLER_URL = 'ajax_handler.php'; // Adjust if your ajax_handler.php is elsewhere

    // --- Element Selectors ---
    const invoiceForm = document.getElementById('invoiceForm');
    const customerSearchInput = document.getElementById('customer_search');
    const customerIdInput = document.getElementById('customer_id');
    const customerSuggestionsList = document.getElementById('customer_suggestions_list');
    const quotationSelect = document.getElementById('quotation_id');
    const loadQuotationBtn = document.getElementById('loadQuotationBtn');
    const quotationLoadSpinner = document.getElementById('quotationLoadSpinner');
    const itemsContainer = document.getElementById('itemsContainer');
    const addItemBtn = document.getElementById('addItemBtn');

    // Totals display elements
    const grossTotalDisplay = document.getElementById('grossTotalDisplay');
    const ppdaLevyDisplay = document.getElementById('ppdaLevyDisplay');
    const amountBeforeVatDisplay = document.getElementById('amountBeforeVatDisplay');
    const vatAmountDisplay = document.getElementById('vatAmountDisplay');
    const totalNetDisplay = document.getElementById('totalNetDisplay');

    // Hidden input fields for totals
    const grossTotalHidden = document.getElementById('gross_total_amount_calculated');
    const ppdaLevyHidden = document.getElementById('ppda_levy_amount_calculated');
    const amountBeforeVatHidden = document.getElementById('amount_before_vat_calculated');
    const vatAmountHidden = document.getElementById('vat_amount_calculated');
    const totalNetHidden = document.getElementById('total_net_amount_calculated');

    const applyPpdaLevyCheckbox = document.getElementById('apply_ppda_levy');
    const ppdaLevyPercentageInput = document.getElementById('ppda_levy_percentage');
    const vatPercentageInput = document.getElementById('vat_percentage');
    const formSubmitSpinner = document.getElementById('formSubmitSpinner');

    let itemIndex = 0;
    if (itemsContainer && itemsContainer.querySelectorAll('.item-row').length > 0) {
        itemIndex = itemsContainer.querySelectorAll('.item-row').length;
    }


    // --- Debounce function ---
    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // --- Customer Search ---
    if (customerSearchInput) {
        customerSearchInput.addEventListener('input', debounce(async function(e) {
            const searchTerm = e.target.value.trim(); // e.target should be customerSearchInput
            
            if (searchTerm.length < 2) {
                if (customerSuggestionsList) {
                    customerSuggestionsList.innerHTML = '';
                    customerSuggestionsList.classList.add('hidden');
                }
                if (customerIdInput) { // Check if customerIdInput exists
                    customerIdInput.value = ''; 
                }
                clearQuotationOptions();
                return;
            }

            let response; // Define response here to access it in catch
            try {
                response = await fetch(`${AJAX_HANDLER_URL}?action=search_customers&term=${encodeURIComponent(searchTerm)}`);
                if (!response.ok) {
                    // If response is not ok, try to get text for more detailed error
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status}, body: ${errorText}`);
                }
                const customers = await response.json(); // This might throw SyntaxError

                if (customerSuggestionsList) {
                    customerSuggestionsList.innerHTML = '';
                    if (customers.length > 0) {
                        customers.forEach(customer => {
                            const div = document.createElement('div');
                            div.classList.add('autocomplete-suggestion');
                            div.textContent = `${customer.name} (${customer.customer_code || 'N/A'})`;
                            div.dataset.id = customer.id;
                            div.dataset.name = customer.name;
                            div.addEventListener('click', function() {
                                customerSearchInput.value = this.dataset.name;
                                if (customerIdInput) customerIdInput.value = this.dataset.id;
                                customerSuggestionsList.innerHTML = '';
                                customerSuggestionsList.classList.add('hidden');
                                fetchCustomerQuotations(this.dataset.id);
                            });
                            customerSuggestionsList.appendChild(div);
                        });
                        customerSuggestionsList.classList.remove('hidden');
                    } else {
                        customerSuggestionsList.innerHTML = '<div class="p-2 text-sm text-gray-500">No customers found.</div>';
                        customerSuggestionsList.classList.remove('hidden');
                        if (customerIdInput) customerIdInput.value = '';
                        clearQuotationOptions();
                    }
                }
            } catch (error) {
                console.error('Error searching customers:', error);
                if (customerSuggestionsList) {
                    customerSuggestionsList.innerHTML = '<div class="p-2 text-sm text-red-500">Error loading customers. Check console.</div>';
                    customerSuggestionsList.classList.remove('hidden');
                }
                // If the error is SyntaxError, it means response.json() failed.
                // The response might contain HTML error from PHP.
                if (error instanceof SyntaxError && response) {
                    console.error("Response was not valid JSON. Server response text:");
                    response.text().then(text => console.error(text)).catch(e => console.error("Could not read response text:", e));
                }
                if (customerIdInput) customerIdInput.value = '';
                clearQuotationOptions();
            }
        }, 300));

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (customerSuggestionsList && customerSearchInput && !customerSearchInput.contains(e.target) && !customerSuggestionsList.contains(e.target)) {
                customerSuggestionsList.classList.add('hidden');
            }
        });
    }
    
    function clearQuotationOptions() {
        if (quotationSelect) { // Check if quotationSelect exists
            quotationSelect.innerHTML = '<option value="">Select Quotation</option>';
        }
    }


    // --- Fetch Customer Quotations ---
    async function fetchCustomerQuotations(customerId) {
        if (!customerId) {
            clearQuotationOptions();
            return;
        }
        if (!quotationSelect) { // Guard against null quotationSelect
            console.error("Quotation select element not found.");
            return;
        }
        let response;
        try {
            response = await fetch(`${AJAX_HANDLER_URL}?action=get_customer_quotations&customer_id=${encodeURIComponent(customerId)}`);
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, body: ${errorText}`);
            }
            const quotations = await response.json();
            
            clearQuotationOptions(); 
            if (quotations.length > 0) {
                quotations.forEach(quot => {
                    const option = document.createElement('option');
                    option.value = quot.id;
                    option.textContent = `${quot.quotation_number} - ${new Date(quot.quotation_date).toLocaleDateString()}`;
                    quotationSelect.appendChild(option);
                });
            } else {
                 const option = document.createElement('option');
                 option.value = "";
                 option.textContent = "No quotations found for customer";
                 option.disabled = true;
                 quotationSelect.appendChild(option);
            }
        } catch (error) {
            console.error('Error fetching quotations:', error);
            if (error instanceof SyntaxError && response) {
                console.error("Response was not valid JSON. Server response text:");
                response.text().then(text => console.error(text)).catch(e => console.error("Could not read response text:", e));
            }
            clearQuotationOptions();
            const option = document.createElement('option');
            option.value = "";
            option.textContent = "Error loading quotations";
            option.disabled = true;
            if (quotationSelect) quotationSelect.appendChild(option);
        }
    }

    // --- Load Quotation Details ---
    if (loadQuotationBtn) {
        loadQuotationBtn.addEventListener('click', async function() {
            if (!quotationSelect) {
                alert('Quotation select element is missing.');
                return;
            }
            const quotationId = quotationSelect.value;
            if (!quotationId) {
                alert('Please select a quotation to load.');
                return;
            }

            if (quotationLoadSpinner) quotationLoadSpinner.classList.remove('hidden');
            loadQuotationBtn.disabled = true;
            let response;
            try {
                response = await fetch(`${AJAX_HANDLER_URL}?action=get_quotation_details&quotation_id=${encodeURIComponent(quotationId)}`);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status}, body: ${errorText}`);
                }
                const data = await response.json();

                if (data.details) {
                    const paymentTermsEl = document.getElementById('payment_terms');
                    const notesGeneralEl = document.getElementById('notes_general');
                    if (paymentTermsEl) paymentTermsEl.value = data.details.payment_terms || '';
                    if (notesGeneralEl) notesGeneralEl.value = data.details.notes_general || '';
                    if (applyPpdaLevyCheckbox) applyPpdaLevyCheckbox.checked = parseInt(data.details.apply_ppda_levy) === 1;
                    if (ppdaLevyPercentageInput) ppdaLevyPercentageInput.value = parseFloat(data.details.ppda_levy_percentage).toFixed(2);
                    if (vatPercentageInput) vatPercentageInput.value = parseFloat(data.details.vat_percentage).toFixed(2);
                }

                if (itemsContainer) {
                    itemsContainer.innerHTML = ''; 
                    itemIndex = 0; 
                    if (data.items && data.items.length > 0) {
                        data.items.forEach(item => {
                            addItemRow(item); 
                        });
                    } else {
                        addItemRow();
                    }
                }
                calculateAllTotals();

            } catch (error) {
                console.error('Error loading quotation details:', error);
                if (error instanceof SyntaxError && response) {
                    console.error("Response was not valid JSON. Server response text:");
                    response.text().then(text => console.error(text)).catch(e => console.error("Could not read response text:", e));
                }
                alert('Failed to load quotation details. Please try again. Check console for details.');
            } finally {
                if (quotationLoadSpinner) quotationLoadSpinner.classList.add('hidden');
                loadQuotationBtn.disabled = false;
            }
        });
    }

    // --- Invoice Items Management ---
    function createItemRowHTML(currentIndex, itemData = null) {
        const productId = itemData?.product_id || '';
        const description = itemData?.description || itemData?.product_name || '';
        const quantity = parseFloat(itemData?.quantity || 1).toFixed(2);
        const unit = itemData?.unit_of_measurement || itemData?.default_unit_of_measurement || '';
        const rate = parseFloat(itemData?.rate_per_unit || itemData?.default_unit_price || 0).toFixed(2);
        const itemTotal = (parseFloat(quantity) * parseFloat(rate)).toFixed(2);

        return `
            <div class="grid grid-cols-12 gap-x-3 gap-y-2 items-end">
                <div class="col-span-12 sm:col-span-4 relative">
                    <label class="block text-xs font-medium text-gray-600">Product/Service</label>
                    <input type="text" class="product-search w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm text-sm" placeholder="Search product..." data-item-index="${currentIndex}" value="${itemData?.product_name || itemData?.sku || ''}">
                    <input type="hidden" name="items[${currentIndex}][product_id]" class="product-id" value="${productId}">
                    <div class="product-suggestions-list autocomplete-suggestions hidden"></div>
                </div>
                <div class="col-span-12 sm:col-span-4">
                    <label class="block text-xs font-medium text-gray-600">Description <span class="text-red-500">*</span></label>
                    <input type="text" class="w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm text-sm item-description" name="items[${currentIndex}][description]" placeholder="Description" required value="${description}">
                </div>
                <div class="col-span-4 sm:col-span-1">
                    <label class="block text-xs font-medium text-gray-600">Qty <span class="text-red-500">*</span></label>
                    <input type="number" class="w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm text-sm quantity" name="items[${currentIndex}][quantity]" placeholder="Qty" step="0.01" required value="${quantity}">
                </div>
                <div class="col-span-4 sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600">Unit</label>
                    <input type="text" class="w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm text-sm unit-of-measurement" name="items[${currentIndex}][unit_of_measurement]" placeholder="Unit (e.g. pcs, hrs)" value="${unit}">
                </div>
                <div class="col-span-4 sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600">Rate <span class="text-red-500">*</span></label>
                    <input type="number" class="w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm text-sm rate" name="items[${currentIndex}][rate_per_unit]" placeholder="Rate" step="0.01" required value="${rate}">
                </div>
                <div class="col-span-8 sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600">Total</label>
                    <input type="text" class="w-full p-2 mt-1 border-gray-300 rounded-md shadow-sm text-sm item-total bg-gray-100" readonly placeholder="Total" value="${itemTotal}">
                </div>
                <div class="col-span-4 sm:col-span-1 flex items-end justify-end">
                    <button type="button" class="remove-item px-2 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 text-xs h-9 mt-1">×</button>
                </div>
            </div>
        `;
    }

    function addItemRow(itemData = null) {
        if (!itemsContainer) {
            console.error("Items container not found.");
            return;
        }
        const itemRowDiv = document.createElement('div');
        itemRowDiv.classList.add('item-row', 'p-3', 'border', 'border-gray-200', 'rounded-md', 'bg-gray-50');
        itemRowDiv.dataset.itemIndex = itemIndex;
        itemRowDiv.innerHTML = createItemRowHTML(itemIndex, itemData);
        itemsContainer.appendChild(itemRowDiv);
        attachItemEventListeners(itemRowDiv);
        itemIndex++;
        calculateAllTotals();
    }
    
    if (addItemBtn) {
        addItemBtn.addEventListener('click', () => addItemRow());
    }

    function attachItemEventListeners(itemRowElement) {
        const productSearchInput = itemRowElement.querySelector('.product-search');
        const productIdField = itemRowElement.querySelector('.product-id');
        const descriptionField = itemRowElement.querySelector('.item-description');
        const quantityField = itemRowElement.querySelector('.quantity');
        const unitField = itemRowElement.querySelector('.unit-of-measurement');
        const rateField = itemRowElement.querySelector('.rate');
        const removeItemBtn = itemRowElement.querySelector('.remove-item');
        const suggestionsList = itemRowElement.querySelector('.product-suggestions-list');

        if (productSearchInput && suggestionsList) {
            productSearchInput.addEventListener('input', debounce(async function(e) {
                const searchTerm = e.target.value.trim();
                if (searchTerm.length < 2) {
                    suggestionsList.innerHTML = '';
                    suggestionsList.classList.add('hidden');
                    return;
                }
                let response;
                try {
                    response = await fetch(`${AJAX_HANDLER_URL}?action=search_products&term=${encodeURIComponent(searchTerm)}`);
                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`HTTP error! status: ${response.status}, body: ${errorText}`);
                    }
                    const products = await response.json();

                    suggestionsList.innerHTML = '';
                    if (products.length > 0) {
                        products.forEach(product => {
                            const div = document.createElement('div');
                            div.classList.add('autocomplete-suggestion');
                            div.textContent = `${product.name} (${product.sku || 'N/A'})`;
                            div.dataset.id = product.id;
                            div.dataset.name = product.name;
                            div.dataset.description = product.description || product.name;
                            div.dataset.rate = product.default_unit_price || '0';
                            div.dataset.unit = product.default_unit_of_measurement || '';

                            div.addEventListener('click', function() {
                                productSearchInput.value = this.dataset.name; 
                                if(productIdField) productIdField.value = this.dataset.id;
                                if(descriptionField) descriptionField.value = this.dataset.description;
                                if(rateField) rateField.value = parseFloat(this.dataset.rate).toFixed(2);
                                if(unitField) unitField.value = this.dataset.unit;
                                
                                suggestionsList.innerHTML = '';
                                suggestionsList.classList.add('hidden');
                                calculateItemTotal(itemRowElement);
                                calculateAllTotals();
                            });
                            suggestionsList.appendChild(div);
                        });
                        suggestionsList.classList.remove('hidden');
                    } else {
                        suggestionsList.innerHTML = '<div class="p-2 text-sm text-gray-500">No products found.</div>';
                        suggestionsList.classList.remove('hidden');
                    }
                } catch (error) {
                    console.error('Error searching products:', error);
                    if (error instanceof SyntaxError && response) {
                        console.error("Response was not valid JSON. Server response text:");
                        response.text().then(text => console.error(text)).catch(e => console.error("Could not read response text:", e));
                    }
                    suggestionsList.innerHTML = '<div class="p-2 text-sm text-red-500">Error loading products. Check console.</div>';
                    suggestionsList.classList.remove('hidden');
                }
            }, 300));
            
            document.addEventListener('click', function(e) {
                if (productSearchInput && suggestionsList && !productSearchInput.contains(e.target) && !suggestionsList.contains(e.target)) {
                    suggestionsList.classList.add('hidden');
                }
            });
        }

        if (quantityField) quantityField.addEventListener('input', () => { calculateItemTotal(itemRowElement); calculateAllTotals(); });
        if (rateField) rateField.addEventListener('input', () => { calculateItemTotal(itemRowElement); calculateAllTotals(); });

        if (removeItemBtn) {
            removeItemBtn.addEventListener('click', function() {
                if (itemsContainer && itemsContainer.querySelectorAll('.item-row').length > 1) {
                    itemRowElement.remove();
                } else {
                    if(productSearchInput) productSearchInput.value = '';
                    if(productIdField) productIdField.value = '';
                    if(descriptionField) descriptionField.value = '';
                    if(quantityField) quantityField.value = '1.00';
                    if(unitField) unitField.value = '';
                    if(rateField) rateField.value = '';
                    const itemTotalField = itemRowElement.querySelector('.item-total');
                    if(itemTotalField) itemTotalField.value = '0.00';
                    alert("Cannot remove the last item. Fields have been cleared.");
                }
                calculateAllTotals();
            });
        }
    }
    
    if (itemsContainer) {
        itemsContainer.querySelectorAll('.item-row').forEach(row => {
            attachItemEventListeners(row);
        });
    }


    // --- Calculations ---
    function calculateItemTotal(itemRowElement) {
        const quantityField = itemRowElement.querySelector('.quantity');
        const rateField = itemRowElement.querySelector('.rate');
        const itemTotalField = itemRowElement.querySelector('.item-total');

        if (quantityField && rateField && itemTotalField) {
            const quantity = parseFloat(quantityField.value) || 0;
            const rate = parseFloat(rateField.value) || 0;
            itemTotalField.value = (quantity * rate).toFixed(2);
        }
    }

    function calculateAllTotals() {
        let gross = 0;
        if (itemsContainer) {
            itemsContainer.querySelectorAll('.item-row').forEach(row => {
                const itemTotalField = row.querySelector('.item-total');
                if (itemTotalField) {
                    const itemTotalValue = parseFloat(itemTotalField.value) || 0;
                    gross += itemTotalValue;
                }
            });
        }

        const ppdaApplies = applyPpdaLevyCheckbox ? applyPpdaLevyCheckbox.checked : false;
        const ppdaPercentage = ppdaLevyPercentageInput ? (parseFloat(ppdaLevyPercentageInput.value) || 0) : 0;
        const vatPercentage = vatPercentageInput ? (parseFloat(vatPercentageInput.value) || 0) : 0;

        let ppdaLevy = 0;
        if (ppdaApplies) {
            ppdaLevy = (gross * ppdaPercentage) / 100;
        }

        const amountBeforeVat = gross + ppdaLevy;
        const vatAmount = (amountBeforeVat * vatPercentage) / 100;
        const totalNet = amountBeforeVat + vatAmount;

        if(grossTotalDisplay) grossTotalDisplay.textContent = gross.toFixed(2);
        if(ppdaLevyDisplay) ppdaLevyDisplay.textContent = ppdaLevy.toFixed(2);
        if(amountBeforeVatDisplay) amountBeforeVatDisplay.textContent = amountBeforeVat.toFixed(2);
        if(vatAmountDisplay) vatAmountDisplay.textContent = vatAmount.toFixed(2);
        if(totalNetDisplay) totalNetDisplay.textContent = totalNet.toFixed(2);

        if(grossTotalHidden) grossTotalHidden.value = gross.toFixed(2);
        if(ppdaLevyHidden) ppdaLevyHidden.value = ppdaLevy.toFixed(2);
        if(amountBeforeVatHidden) amountBeforeVatHidden.value = amountBeforeVat.toFixed(2);
        if(vatAmountHidden) vatAmountHidden.value = vatAmount.toFixed(2);
        if(totalNetHidden) totalNetHidden.value = totalNet.toFixed(2);
    }

    if(applyPpdaLevyCheckbox) applyPpdaLevyCheckbox.addEventListener('change', calculateAllTotals);
    if(ppdaLevyPercentageInput) ppdaLevyPercentageInput.addEventListener('input', calculateAllTotals);
    if(vatPercentageInput) vatPercentageInput.addEventListener('input', calculateAllTotals);

    calculateAllTotals(); // Initial calculation


    // --- Form Submission ---
    if (invoiceForm) {
        invoiceForm.addEventListener('submit', function(e) {
            if (customerIdInput && !customerIdInput.value) {
                e.preventDefault();
                alert('Please select a customer.');
                if (customerSearchInput) customerSearchInput.focus();
                return;
            }

            let hasItems = false;
            if (itemsContainer) {
                const itemDescriptions = itemsContainer.querySelectorAll('.item-description');
                if (itemDescriptions.length === 0) {
                     e.preventDefault();
                     alert('Please add at least one invoice item.');
                     return;
                }
                itemDescriptions.forEach(descField => {
                    if (descField.value.trim() !== '') {
                        hasItems = true;
                    }
                });
                if (!hasItems && itemDescriptions.length > 0) {
                    e.preventDefault();
                    alert('Please provide a description for at least one invoice item.');
                    if(itemDescriptions[0]) itemDescriptions[0].focus();
                    return;
                }
            } else {
                 e.preventDefault();
                 alert('Items container not found. Cannot submit form.');
                 return;
            }
            
            calculateAllTotals(); 

            if (formSubmitSpinner) formSubmitSpinner.classList.remove('hidden');
            const submitButton = invoiceForm.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;
        });
    }
});
