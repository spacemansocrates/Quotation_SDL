// quotation.js - Handles quotation form functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize variables
    let itemCounter = 1;
    let quotationItems = [];
    const ppda_levy_rate = 0.01; // 1%
    
    // Initialize form elements
    const form = document.getElementById('quotation-form');
    const addItemBtn = document.getElementById('add-item-btn');
    const itemsTable = document.getElementById('items-tbody');
    const shopDropdown = document.getElementById('shop_id');
    const customerDropdown = document.getElementById('customer_id');
    const newCustomerToggle = document.getElementById('enter_new_customer_toggle');
    const applyPpdaLevy = document.getElementById('apply_ppda_levy');
    const vatPercentage = document.getElementById('vat_percentage');
    const includeMraWhtNote = document.getElementById('include_mra_wht_note');
    const mraWhtNoteDiv = document.getElementById('mra_wht_note_div');
    const newCustomerFields = document.getElementById('new-customer-fields');
    const existingCustomerDetails = document.getElementById('existing-customer-details');
    const saveDraftBtn = document.getElementById('save-draft-btn');
    const generateQuotationBtn = document.getElementById('generate-quotation-btn');
    
    // Event listeners
    addItemBtn.addEventListener('click', addNewItem);
    shopDropdown.addEventListener('change', updateShopDetails);
    customerDropdown.addEventListener('change', updateCustomerDetails);
    newCustomerToggle.addEventListener('change', toggleCustomerFields);
    applyPpdaLevy.addEventListener('change', recalculateTotals);
    vatPercentage.addEventListener('input', recalculateTotals);
    includeMraWhtNote.addEventListener('change', toggleMraNote);
    saveDraftBtn.addEventListener('click', saveDraft);
    generateQuotationBtn.addEventListener('click', generateQuotation);
    
    // Add first item row on load
    addNewItem();
    
    // Update quotation number when shop or customer changes
    shopDropdown.addEventListener('change', updateQuotationNumber);
    customerDropdown.addEventListener('change', updateQuotationNumber);
    
    /**
     * Adds a new item row to the quotation items table
     */
    function addNewItem() {
        const row = document.createElement('tr');
        row.dataset.itemId = itemCounter;
        
        row.innerHTML = `
            <td>${itemCounter}</td>
            <td>
                <input type="text" name="item_description[]" class="item-description" placeholder="Enter product description" required>
                <button type="button" class="select-product-btn">Select Product</button>
            </td>
            <td>
                <input type="file" name="item_image[]" class="item-image" accept="image/*">
                <img src="" alt="Product Image" class="product-image-preview" style="display:none; max-width:100px; max-height:100px;">
            </td>
            <td>
                <input type="number" name="item_quantity[]" class="item-quantity" value="1" min="1" step="0.01" required>
            </td>
            <td>
                <select name="item_unit[]" class="item-unit" required>
                    <option value="">--Select--</option>
                    ${unitsData.map(unit => `<option value="${unit.id}">${unit.name}</option>`).join('')}
                </select>
            </td>
            <td>
                <input type="number" name="item_rate[]" class="item-rate" value="0.00" min="0" step="0.01" required>
            </td>
            <td>
                <span class="item-total">0.00</span>
            </td>
            <td>
                <button type="button" class="remove-item-btn">Remove</button>
            </td>
        `;
        
        itemsTable.appendChild(row);
        
        // Add event listeners to new elements
        const quantityInput = row.querySelector('.item-quantity');
        const rateInput = row.querySelector('.item-rate');
        const removeBtn = row.querySelector('.remove-item-btn');
        const imageInput = row.querySelector('.item-image');
        const imagePreview = row.querySelector('.product-image-preview');
        const selectProductBtn = row.querySelector('.select-product-btn');
        
        quantityInput.addEventListener('input', updateItemTotal);
        rateInput.addEventListener('input', updateItemTotal);
        removeBtn.addEventListener('click', removeItem);
        
        // Handle image preview
        imageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.style.display = 'none';
            }
        });
        
        // Product selection modal would go here
        selectProductBtn.addEventListener('click', function() {
            alert('Product selection modal to be implemented');
            // Future implementation: Open modal to select product from database
        });
        
        itemCounter++;
    }
    
    /**
     * Updates the total for a specific item row
     * @param {Event} e - The input event
     */
    function updateItemTotal(e) {
        const row = e.target.closest('tr');
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
        const total = quantity * rate;
        
        row.querySelector('.item-total').textContent = total.toFixed(2);
        
        recalculateTotals();
    }
    
    /**
     * Removes an item row from the table
     * @param {Event} e - The click event
     */
    function removeItem(e) {
        const row = e.target.closest('tr');
        
        // Don't allow removing the last row
        if (itemsTable.rows.length <= 1) {
            alert('Cannot remove the last item. At least one item is required.');
            return;
        }
        
        row.remove();
        recalculateTotals();
    }
    
    /**
     * Recalculates all totals based on current form values
     */
    function recalculateTotals() {
        // Calculate gross total
        let grossTotal = 0;
        const itemTotals = document.querySelectorAll('.item-total');
        itemTotals.forEach(item => {
            grossTotal += parseFloat(item.textContent) || 0;
        });
        
        // Calculate PPDA levy if applicable
        const applyPpda = document.getElementById('apply_ppda_levy').checked;
        const ppdaAmount = applyPpda ? grossTotal * ppda_levy_rate : 0;
        
        // Calculate subtotal before VAT
        const subtotalBeforeVat = grossTotal + ppdaAmount;
        
        // Calculate VAT
        const vatRate = parseFloat(document.getElementById('vat_percentage').value) / 100 || 0;
        const vatAmount = subtotalBeforeVat * vatRate;
        
        // Calculate total net amount
        const totalNetAmount = subtotalBeforeVat + vatAmount;
        
        // Update display
        document.getElementById('gross_total_amount').textContent = grossTotal.toFixed(2);
        document.getElementById('ppda_levy_amount').textContent = ppdaAmount.toFixed(2);
        document.getElementById('subtotal_before_vat').textContent = subtotalBeforeVat.toFixed(2);
        document.getElementById('vat_amount').textContent = vatAmount.toFixed(2);
        document.getElementById('total_net_amount').textContent = totalNetAmount.toFixed(2);
    }
    
    /**
     * Updates shop details when shop selection changes
     */
    function updateShopDetails() {
        const shopId = shopDropdown.value;
        if (!shopId) {
            document.getElementById('shop-logo-preview').style.display = 'none';
            document.getElementById('shop-address-preview').textContent = '';
            document.getElementById('shop-tpin-preview').textContent = '';
            return;
        }
        
        // Get shop details from server
        fetch(`api/get_shop_details.php?id=${shopId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const shop = data.shop;
                    
                    // Update shop code hidden field for quotation number
                    document.getElementById('shop_code_hidden').value = shop.shop_code;
                    
                    // Update preview
                    if (shop.logo_path) {
                        const logoPreview = document.getElementById('shop-logo-preview');
                        logoPreview.src = shop.logo_path;
                        logoPreview.style.display = 'block';
                    }
                    
                    // Create address preview
                    let addressText = `${shop.name}\n`;
                    if (shop.address_line1) addressText += `${shop.address_line1}\n`;
                    if (shop.address_line2) addressText += `${shop.address_line2}\n`;
                    if (shop.city || shop.country) {
                        addressText += `${shop.city || ''}, ${shop.country || ''}\n`;
                    }
                    if (shop.phone) addressText += `Phone: ${shop.phone}\n`;
                    if (shop.email) addressText += `Email: ${shop.email}`;
                    
                    document.getElementById('shop-address-preview').textContent = addressText;
                    document.getElementById('shop-tpin-preview').textContent = shop.tpin_no || 'N/A';
                    
                    // Update quotation number
                    updateQuotationNumber();
                }
            })
            .catch(error => console.error('Error loading shop details:', error));
    }
    
    /**
     * Updates customer details when customer selection changes
     */
    function updateCustomerDetails() {
        const customerId = customerDropdown.value;
        if (!customerId) {
            document.getElementById('selected_customer_name').textContent = '';
            document.getElementById('selected_customer_address').textContent = '';
            document.getElementById('selected_customer_tpin').textContent = '';
            document.getElementById('selected_customer_code_display').textContent = '';
            existingCustomerDetails.classList.add('hidden');
            return;
        }
        
        // Get customer details from server
        fetch(`api/get_customer_details.php?id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const customer = data.customer;
                    
                    // Update customer code hidden field for quotation number
                    document.getElementById('customer_code_hidden').value = customer.customer_code;
                    
                    // Update preview
                    document.getElementById('selected_customer_name').textContent = customer.name;
                    
                    // Create address preview
                    let addressText = '';
                    if (customer.address_line1) addressText += `${customer.address_line1}\n`;
                    if (customer.address_line2) addressText += `${customer.address_line2}\n`;
                    if (customer.city_location) addressText += `${customer.city_location}`;
                    
                    document.getElementById('selected_customer_address').textContent = addressText;
                    document.getElementById('selected_customer_tpin').textContent = customer.tpin_no || 'N/A';
                    document.getElementById('selected_customer_code_display').textContent = customer.customer_code;
                    
                    existingCustomerDetails.classList.remove('hidden');
                    
                    // Update quotation number
                    updateQuotationNumber();
                }
            })
            .catch(error => console.error('Error loading customer details:', error));
    }
    
    /**
     * Toggles between existing customer and new customer entry
     */
    function toggleCustomerFields() {
        if (newCustomerToggle.checked) {
            customerDropdown.disabled = true;
            newCustomerFields.classList.remove('hidden');
            existingCustomerDetails.classList.add('hidden');
        } else {
            customerDropdown.disabled = false;
            newCustomerFields.classList.add('hidden');
            if (customerDropdown.value) {
                existingCustomerDetails.classList.remove('hidden');
            }
        }
        
        updateQuotationNumber();
    }
    
    /**
     * Shows/hides MRA WHT note section
     */
    function toggleMraNote() {
        if (includeMraWhtNote.checked) {
            mraWhtNoteDiv.classList.remove('hidden');
        } else {
            mraWhtNoteDiv.classList.add('hidden');
        }
    }
    
    /**
     * Updates the quotation number format based on shop and customer selection
     */
    function updateQuotationNumber() {
        let quotationNumber = 'SDL/';
        
        // Get customer code
        let customerCode = 'CUSTCODE';
        if (newCustomerToggle.checked) {
            const newCustomerCode = document.getElementById('new_customer_code').value;
            if (newCustomerCode) {
                customerCode = newCustomerCode;
            }
        } else {
            const customerCodeHidden = document.getElementById('customer_code_hidden').value;
            if (customerCodeHidden) {
                customerCode = customerCodeHidden;
            }
        }
        
        // Get shop code
        let shopCode = 'SHOPCODE';
        const shopCodeHidden = document.getElementById('shop_code_hidden').value;
        if (shopCodeHidden) {
            shopCode = shopCodeHidden;
        }
        
        // Format: SDL/SHOPCODE/CUSTCODE-###
        const uniqueNumber = Math.floor(1 + Math.random() * 999);
        const padded = String(uniqueNumber).padStart(3, '0');
        quotationNumber += `${shopCode}/${customerCode}-${padded}`;
        
        document.getElementById('quotation_number').value = quotationNumber;
    }
    
    /**
     * Saves the quotation as a draft
     */
    function saveDraft() {
        if (!validateForm()) {
            return;
        }
        
        submitQuotation('draft');
    }
    
    /**
     * Generates the final quotation
     */
    function generateQuotation() {
        if (!validateForm()) {
            return;
        }
        
        submitQuotation('final');
    }
    
    /**
     * Validates the form inputs
     * @returns {boolean} Whether the form is valid
     */
    function validateForm() {
        // Basic validation
        if (!shopDropdown.value) {
            alert('Please select a shop.');
            shopDropdown.focus();
            return false;
        }
        
        if (!newCustomerToggle.checked && !customerDropdown.value) {
            alert('Please select a customer or enter new customer details.');
            customerDropdown.focus();
            return false;
        }
        
        if (newCustomerToggle.checked) {
            const customerName = document.getElementById('customer_name_override').value;
            if (!customerName) {
                alert('Please enter customer name.');
                document.getElementById('customer_name_override').focus();
                return false;
            }
        }
        
        // Check for items
        const descriptions = document.querySelectorAll('.item-description');
        let hasItems = false;
        descriptions.forEach(desc => {
            if (desc.value.trim()) {
                hasItems = true;
            }
        });
        
        if (!hasItems) {
            alert('Please add at least one item with a description.');
            return false;
        }
        
        return true;
    }
    
    /**
     * Submits the quotation data to the server
     * @param {string} status - 'draft' or 'final'
     */
    function submitQuotation(status) {
        // Prepare form data
        const formData = new FormData(form);
        formData.append('status', status);
        
        // Add calculated fields
        formData.append('gross_total_amount', document.getElementById('gross_total_amount').textContent);
        formData.append('ppda_levy_amount', document.getElementById('ppda_levy_amount').textContent);
        formData.append('amount_before_vat', document.getElementById('subtotal_before_vat').textContent);
        formData.append('vat_amount', document.getElementById('vat_amount').textContent);
        formData.append('total_net_amount', document.getElementById('total_net_amount').textContent);
        
        // Submit form
        fetch('process_quotation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                if (data.quotation_id) {
                    window.location.href = `view_quotation.php?id=${data.quotation_id}`;
                }
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error submitting quotation:', error);
            alert('An error occurred while submitting the quotation. Please try again.');
        });
    }
});