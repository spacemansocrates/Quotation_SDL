<?php
// Start session if you are using $_SESSION for user_id or other session variables
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Assuming your Database class is in 'classes/Database.php'
require_once 'classes/Database.php'; 

// Helper function to get current user ID (replace with your actual session logic)
// This is needed if your Database.php or other parts rely on it during initial page load.
// Otherwise, it's primarily for the AJAX handler and save script.
function getCurrentUserIdPageLoad() {
    return $_SESSION['user_id'] ?? 1; // Default for example
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Custom styles for autocomplete/dropdown suggestions if needed */
        .autocomplete-suggestions {
            border: 1px solid #e2e8f0;
            border-top: none;
            max-height: 150px;
            overflow-y: auto;
            position: absolute;
            background-color: white;
            z-index: 99;
            width: calc(100% - 2px); /* Match input width */
        }
        .autocomplete-suggestion {
            padding: 8px;
            cursor: pointer;
        }
        .autocomplete-suggestion:hover {
            background-color: #f1f5f9;
        }
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 8px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

<div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-5xl">
    <header class="mb-6">
        <h1 class="text-3xl font-bold text-gray-700">Create Invoice</h1>
    </header>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($_GET['error']) ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($_GET['success']) ?></span>
        </div>
    <?php endif; ?>

    <form id="invoiceForm" method="POST" action="actions/save_invoice.php" class="bg-white shadow-md rounded-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <div class="mb-4">
                    <label for="shop_id" class="block text-sm font-medium text-gray-700 mb-1">Shop <span class="text-red-500">*</span></label>
                    <select class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" id="shop_id" name="shop_id" required>
                        <option value="">Select Shop</option>
                        <?php
                        try {
                            $db = new Database();
                            $conn = $db->connect();
                            $stmt = $conn->query("SELECT id, name FROM shops ORDER BY name");
                            while ($shop = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$shop['id']}'>" . htmlspecialchars($shop['name']) . "</option>";
                            }
                        } catch (Exception $e) {
                            // Log error or display a friendly message
                            echo "<option value=''>Error loading shops</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-4 relative">
                    <label for="customer_search" class="block text-sm font-medium text-gray-700 mb-1">Customer <span class="text-red-500">*</span></label>
                    <input type="text" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" id="customer_search" placeholder="Search customer by name or code...">
                    <input type="hidden" id="customer_id" name="customer_id" required>
                    <div id="customer_suggestions_list" class="autocomplete-suggestions hidden"></div>
                    </div>

                <div class="mb-4">
                    <label for="quotation_id" class="block text-sm font-medium text-gray-700 mb-1">Load from Quotation (Optional)</label>
                    <div class="flex items-center space-x-2">
                        <select class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" id="quotation_id" name="quotation_id">
                            <option value="">Select Quotation</option>
                            </select>
                        <button type="button" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 whitespace-nowrap" id="loadQuotationBtn">
                            Load <span id="quotationLoadSpinner" class="spinner hidden"></span>
                        </button>
                    </div>
                     </div>
            </div>

            <div>
                <div class="mb-4">
                    <label for="invoice_date" class="block text-sm font-medium text-gray-700 mb-1">Invoice Date <span class="text-red-500">*</span></label>
                    <input type="date" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-4">
                    <label for="due_date" class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                    <input type="date" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" id="due_date" name="due_date">
                </div>

                <div class="mb-4">
                    <label for="payment_terms" class="block text-sm font-medium text-gray-700 mb-1">Payment Terms</label>
                    <input type="text" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" id="payment_terms" name="payment_terms" placeholder="e.g., Net 30 days">
                </div>
            </div>
        </div>

        <hr class="my-6 border-gray-300">

        <div>
            <h4 class="text-xl font-semibold text-gray-700 mb-3">Invoice Items</h4>
            <div id="itemsContainer" class="space-y-4">
                <div class="item-row p-3 border border-gray-200 rounded-md bg-gray-50" data-item-index="0">
                    <div class="grid grid-cols-12 gap-x-3 gap-y-2 items-end">
                        <div class="col-span-12 sm:col-span-4 relative">
                            <label class="block text-xs font-medium text-gray-600">Product/Service</label>
                            <input type="text" class="product-search w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm text-sm" placeholder="Search product..." data-item-index="0">
                            <input type="hidden" name="items[0][product_id]" class="product-id">
                            <div class="product-suggestions-list autocomplete-suggestions hidden"></div>
                        </div>
                        <div class="col-span-12 sm:col-span-4">
                             <label class="block text-xs font-medium text-gray-600">Description <span class="text-red-500">*</span></label>
                            <input type="text" class="w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm text-sm item-description" name="items[0][description]" placeholder="Description" required>
                        </div>
                        <div class="col-span-4 sm:col-span-1">
                            <label class="block text-xs font-medium text-gray-600">Qty <span class="text-red-500">*</span></label>
                            <input type="number" class="w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm text-sm quantity" name="items[0][quantity]" placeholder="Qty" step="0.01" required value="1">
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600">Unit</label>
                            <input type="text" class="w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm text-sm unit-of-measurement" name="items[0][unit_of_measurement]" placeholder="Unit (e.g. pcs, hrs)">
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600">Rate <span class="text-red-500">*</span></label>
                            <input type="number" class="w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm text-sm rate" name="items[0][rate_per_unit]" placeholder="Rate" step="0.01" required>
                        </div>
                        <div class="col-span-8 sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600">Total</label>
                            <input type="text" class="w-full p-2 mt-1 border-gray-300 rounded-md shadow-sm text-sm item-total bg-gray-100" readonly placeholder="Total">
                        </div>
                        <div class="col-span-4 sm:col-span-1 flex items-end justify-end">
                            <button type="button" class="remove-item px-2 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 text-xs h-9 mt-1">×</button>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="mt-4 px-4 py-2 border border-indigo-600 text-indigo-600 rounded-md hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="addItemBtn">Add Item</button>
        </div>

        <hr class="my-6 border-gray-300">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <div class="mb-4">
                    <label for="notes_general" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" id="notes_general" name="notes_general" rows="3"></textarea>
                </div>

                <div class="mb-4 p-3 border border-gray-200 rounded-md">
                    <div class="flex items-center mb-2">
                        <input class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" type="checkbox" id="apply_ppda_levy" name="apply_ppda_levy">
                        <label class="ml-2 block text-sm text-gray-900" for="apply_ppda_levy">
                            Apply PPDA Levy
                        </label>
                    </div>
                    <input type="number" class="w-full p-2 border border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" id="ppda_levy_percentage" name="ppda_levy_percentage" value="1.00" step="0.01" placeholder="PPDA %">
                </div>

                <div class="mb-4 p-3 border border-gray-200 rounded-md">
                    <label for="vat_percentage" class="block text-sm font-medium text-gray-700 mb-1">VAT Percentage</label>
                    <input type="number" class="w-full p-2 border border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" id="vat_percentage" name="vat_percentage" value="16.50" step="0.01" placeholder="VAT %">
                </div>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg shadow">
                <h5 class="text-lg font-semibold text-gray-700 mb-3">Invoice Totals</h5>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span>Gross Total:</span> <span id="grossTotalDisplay" class="font-medium">0.00</span></div>
                    <div class="flex justify-between"><span>PPDA Levy:</span> <span id="ppdaLevyDisplay" class="font-medium">0.00</span></div>
                    <div class="flex justify-between"><span>Amount Before VAT:</span> <span id="amountBeforeVatDisplay" class="font-medium">0.00</span></div>
                    <div class="flex justify-between"><span>VAT Amount:</span> <span id="vatAmountDisplay" class="font-medium">0.00</span></div>
                    <hr class="my-2 border-gray-300">
                    <div class="flex justify-between text-base font-bold"><span>Total Net Amount:</span> <span id="totalNetDisplay">0.00</span></div>
                </div>
                 <input type="hidden" name="gross_total_amount_calculated" id="gross_total_amount_calculated">
                <input type="hidden" name="ppda_levy_amount_calculated" id="ppda_levy_amount_calculated">
                <input type="hidden" name="amount_before_vat_calculated" id="amount_before_vat_calculated">
                <input type="hidden" name="vat_amount_calculated" id="vat_amount_calculated">
                <input type="hidden" name="total_net_amount_calculated" id="total_net_amount_calculated">
            </div>
        </div>

        <hr class="my-6 border-gray-300">

        <div class="mb-4">
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:w-auto" id="status" name="status">
                <option value="Draft">Draft</option>
                <option value="Sent">Sent</option>
                </select>
        </div>

        <div class="flex items-center justify-end space-x-3 mt-8">
            <a href="list_invoices.php" class="px-6 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 text-sm font-medium">
                Create Invoice <span id="formSubmitSpinner" class="spinner hidden"></span>
            </button>
        </div>
    </form>
</div>

<script src="js/invoice_form.js"></script>
<!-- 
    Your js/invoice_form.js should handle:
    1. Customer Search (#customer_search):
        - On input, debounce AJAX GET to 'ajax_handler.php?action=search_customers&term=...'
        - Display results in #customer_suggestions_list.
        - On selection, fill #customer_id (hidden) and #customer_search (display), then trigger quotation loading.
    2. Load Quotations for Customer:
        - When #customer_id is set, AJAX GET to 'ajax_handler.php?action=get_customer_quotations&customer_id=...'
        - Populate #quotation_id select.
    3. Load Quotation Button (#loadQuotationBtn):
        - On click, if #quotation_id is selected, AJAX GET to 'ajax_handler.php?action=get_quotation_details&quotation_id=...'
        - Populate form fields (payment_terms, notes_general) and invoice items (#itemsContainer).
    4. Invoice Items (#itemsContainer):
        - Add Item Button (#addItemBtn): Clone the item row template, update indices, and append.
        - Product Search (.product-search in each item row):
            - On input, debounce AJAX GET to 'ajax_handler.php?action=search_products&term=...'
            - Display results in .product-suggestions-list for that row.
            - On selection, fill .product-id (hidden), .item-description, .rate, .unit-of-measurement.
        - Remove Item Button (.remove-item): Remove the parent .item-row.
        - Auto-calculate item total (.item-total) when quantity or rate changes.
    5. Calculate Grand Totals:
        - Update #grossTotalDisplay, #ppdaLevyDisplay, etc., whenever items are added/removed or quantities/rates change.
        - Also update the hidden input fields for these totals.
    6. Form Submission:
        - Basic client-side validation (e.g., at least one item).
        - Show spinner on submit button.
-->
</body>
</html>
