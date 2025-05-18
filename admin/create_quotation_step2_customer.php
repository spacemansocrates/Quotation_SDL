<?php
// create_quotation_step2_customer.php
session_start(); // Make sure session is started
require_once 'config.php'; // Ensure this file correctly establishes a PDO connection
$pdo = getDBConnection();

// Ensure previous step was completed
if (!isset($_SESSION['quotation_data']['shop_id'])) {
    header('Location: create_quotation_step1_shop.php');
    exit;
}
$_SESSION['quotation_data']['current_step'] = 2;

$search_term = isset($_GET['search_customer']) ? trim($_GET['search_customer']) : '';
$customers = [];
if (!empty($search_term)) {
    $stmt = $pdo->prepare("SELECT id, customer_code, name, email, phone FROM customers
                       WHERE name LIKE :name_term
                       OR customer_code LIKE :code_term
                       OR email LIKE :email_term");
    // Corrected execute call:
    $stmt->execute([
        'name_term' => '%' . $search_term . '%',
        'code_term' => '%' . $search_term . '%',
        'email_term' => '%' . $search_term . '%'
    ]);
    $customers = $stmt->fetchAll();
}

$new_customer_data = $_SESSION['quotation_data']['new_customer_data'] ?? [];
$selected_customer_id = $_SESSION['quotation_data']['customer_id'] ?? null;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Quotation - Step 2: Customer</title>
    <style>
        #new_customer_form { display: <?php echo (isset($_POST['add_new_customer_flag']) || (!empty($new_customer_data) && !$selected_customer_id)) ? 'block' : 'none'; ?>; }
        body { font-family: sans-serif; margin: 20px; }
        h1, h3 { color: #333; }
        form { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
        label { display: inline-block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="email"] { width: calc(100% - 22px); padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
        button, input[type="submit"] { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        button:hover, input[type="submit"]:hover { background-color: #0056b3; }
        hr { margin-top: 20px; margin-bottom: 20px; border: 0; border-top: 1px solid #eee; }
        .search-results div { margin-bottom: 8px; padding: 5px; border-bottom: 1px solid #eee; }
        .search-results div:last-child { border-bottom: none; }
        .search-results label { font-weight: normal; }
        #new_customer_form { padding: 15px; border: 1px dashed #ccc; margin-top: 10px; }
        #new_customer_form label { display: block; width: 150px; float: left; }
        #new_customer_form input[type="text"], #new_customer_form input[type="email"] { width: calc(100% - 170px); }
        #new_customer_form br { clear: both; }
        small { color: #666; }
        .error-message { color: red; font-style: italic; margin-bottom: 10px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>Step 2: Select or Add Customer</h1>
    <p><a href="create_quotation_step1_shop.php?continue=1">« Back to Shop Selection</a></p>

    <?php if (isset($_SESSION['error_message'])): ?>
        <p class="error-message"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
    <?php endif; ?>

    <form action="create_quotation_step2_customer.php" method="GET">
        <label for="search_customer">Search Existing Customer (by Name, Code, or Email):</label>
        <input type="text" name="search_customer" id="search_customer" value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit">Search</button>
    </form>
    <hr>

    <form action="process_quotation.php" method="POST">
        <input type="hidden" name="action" value="set_customer">

        <?php if (!empty($customers)): ?>
            <h3>Search Results:</h3>
            <div class="search-results">
            <?php foreach ($customers as $customer): ?>
                <div>
                    <input type="radio" name="customer_id" value="<?php echo $customer['id']; ?>" id="cust_<?php echo $customer['id']; ?>"
                           <?php echo ($selected_customer_id == $customer['id']) ? 'checked' : ''; ?>>
                    <label for="cust_<?php echo $customer['id']; ?>">
                        <?php echo htmlspecialchars($customer['name']); ?> (Code: <?php echo htmlspecialchars($customer['customer_code']); ?>) - <?php echo htmlspecialchars($customer['email']); ?> - Phone: <?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?>
                    </label>
                </div>
            <?php endforeach; ?>
            </div>
            <br>
        <?php elseif(!empty($search_term)): ?>
            <p>No customers found matching "<?php echo htmlspecialchars($search_term); ?>". You can add a new customer below.</p>
        <?php endif; ?>

        <button type="button" onclick="document.getElementById('new_customer_form').style.display='block'; document.querySelectorAll('input[name=customer_id]').forEach(r => r.checked=false); document.getElementById('add_new_customer_flag_input').value='1';">
            Add New Customer
        </button>
        <small>(Selecting an existing customer will override new customer details if any are entered below)</small>
        <br><br>

        <div id="new_customer_form">
            <h3>New Customer Details:</h3>
            <!-- This hidden input helps retain form visibility on server-side validation failures if you implement them -->
            <input type="hidden" name="add_new_customer_flag" id="add_new_customer_flag_input" value="<?php echo (isset($_POST['add_new_customer_flag']) || (!empty($new_customer_data) && !$selected_customer_id)) ? '1' : '0'; ?>">

            <label for="new_customer_code">Customer Code:</label>
            <input type="text" name="new_customer[customer_code]" id="new_customer_code" value="<?php echo htmlspecialchars($new_customer_data['customer_code'] ?? ''); ?>"><br>

            <label for="new_name">Name:</label>
            <input type="text" name="new_customer[name]" id="new_name" value="<?php echo htmlspecialchars($new_customer_data['name'] ?? ''); ?>"><br> <!-- Removed 'required' for now to simplify, add back with server-side validation -->

            <label for="new_address_line1">Address Line 1:</label>
            <input type="text" name="new_customer[address_line1]" id="new_address_line1" value="<?php echo htmlspecialchars($new_customer_data['address_line1'] ?? ''); ?>"><br>

            <label for="new_address_line2">Address Line 2:</label>
            <input type="text" name="new_customer[address_line2]" id="new_address_line2" value="<?php echo htmlspecialchars($new_customer_data['address_line2'] ?? ''); ?>"><br>

            <label for="new_city_location">City/Location:</label>
            <input type="text" name="new_customer[city_location]" id="new_city_location" value="<?php echo htmlspecialchars($new_customer_data['city_location'] ?? ''); ?>"><br>

            <label for="new_phone">Phone:</label>
            <input type="text" name="new_customer[phone]" id="new_phone" value="<?php echo htmlspecialchars($new_customer_data['phone'] ?? ''); ?>"><br>

            <label for="new_email">Email:</label>
            <input type="email" name="new_customer[email]" id="new_email" value="<?php echo htmlspecialchars($new_customer_data['email'] ?? ''); ?>"><br>

            <label for="new_tpin_no">TPIN No:</label>
            <input type="text" name="new_customer[tpin_no]" id="new_tpin_no" value="<?php echo htmlspecialchars($new_customer_data['tpin_no'] ?? ''); ?>"><br>
        </div>
        <br>
        <button type="submit">Next: Add Items »</button>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Clear new customer fields if an existing customer is selected
            document.querySelectorAll('input[name=customer_id]').forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        // Hide new customer form, but don't clear fields immediately.
                        // User might accidentally click and want to revert.
                        // Clearing could be done on submit if an existing customer is chosen.
                        document.getElementById('new_customer_form').style.display = 'none';
                        document.getElementById('add_new_customer_flag_input').value = '0'; // Indicate new customer form is not active
                    }
                });
            });

            // Logic to show new customer form if it was intended to be open
            // (e.g., after a POST request for adding new customer or if pre-filled from session)
            const newCustomerForm = document.getElementById('new_customer_form');
            const addNewCustomerFlag = document.getElementById('add_new_customer_flag_input').value;
            const hasPreselectedCustomer = document.querySelector('input[name="customer_id"]:checked');

            if (addNewCustomerFlag === '1' && !hasPreselectedCustomer) {
                newCustomerForm.style.display = 'block';
            } else if (hasPreselectedCustomer) {
                 newCustomerForm.style.display = 'none'; // Ensure it's hidden if a customer is selected
            }
        });
    </script>
</body>
</html>