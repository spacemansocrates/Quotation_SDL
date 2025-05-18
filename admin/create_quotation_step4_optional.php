<?php
// create_quotation_step4_optional.php
require_once 'config.php';

// Ensure previous step was completed
if (empty($_SESSION['quotation_data']['items'])) {
    header('Location: create_quotation_step3_items.php');
    exit;
}
$_SESSION['quotation_data']['current_step'] = 4;
$optional_data = $_SESSION['quotation_data']['optional_fields'] ?? [];
$default_mra_note = DEFAULT_MRA_WHT_NOTE_TEMPLATE; // Or fetch from DB settings
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Quotation - Step 4: Optional Fields</title>
    <script>
        function toggleMraNote() {
            var checkbox = document.getElementById('include_mra_note_checkbox');
            var textarea = document.getElementById('mra_wht_note_textarea');
            textarea.style.display = checkbox.checked ? 'block' : 'none';
            if (checkbox.checked && textarea.value.trim() === '') {
                 textarea.value = "<?php echo htmlspecialchars(addslashes($default_mra_note)); // Be careful with JS escaping ?>";
            }
        }
    </script>
</head>
<body onload="toggleMraNote()">
    <h1>Step 4: Optional Fields</h1>
    <p><a href="create_quotation_step3_items.php?continue=1">« Back to Items</a></p>

    <form action="process_quotation.php" method="POST">
        <input type="hidden" name="action" value="set_optional_fields">

        <label for="notes_general">General Note:</label><br>
        <textarea name="optional[notes_general]" id="notes_general" rows="4" cols="50"><?php echo htmlspecialchars($optional_data['notes_general'] ?? ''); ?></textarea><br><br>

        <label for="delivery_period">Delivery Period:</label>
        <input type="text" name="optional[delivery_period]" id="delivery_period" value="<?php echo htmlspecialchars($optional_data['delivery_period'] ?? ''); ?>"><br><br>

        <label for="payment_terms">Payment Terms:</label>
        <input type="text" name="optional[payment_terms]" id="payment_terms" value="<?php echo htmlspecialchars($optional_data['payment_terms'] ?? ''); ?>"><br><br>
        <!-- Or use a dropdown:
        <select name="optional[payment_terms]">
            <option value="Net 30" <?php // echo ($optional_data['payment_terms'] ?? '') == 'Net 30' ? 'selected' : ''; ?>>Net 30 Days</option>
            <option value="COD" <?php // echo ($optional_data['payment_terms'] ?? '') == 'COD' ? 'selected' : ''; ?>>Cash on Delivery</option>
        </select><br><br>
        -->

        <label for="quotation_validity_days">Quotation Validity (days):</label>
        <input type="number" name="optional[quotation_validity_days]" id="quotation_validity_days" value="<?php echo htmlspecialchars($optional_data['quotation_validity_days'] ?? '30'); ?>"><br><br>

        <input type="checkbox" name="optional[include_mra_wht_note]" id="include_mra_note_checkbox" value="1"
               onchange="toggleMraNote()" <?php echo !empty($optional_data['mra_wht_note']) ? 'checked' : ''; ?>>
        <label for="include_mra_note_checkbox">Include MRA WHT Note</label><br>
        <textarea name="optional[mra_wht_note]" id="mra_wht_note_textarea" rows="4" cols="50" style="display:none;"><?php echo htmlspecialchars($optional_data['mra_wht_note'] ?? ''); ?></textarea><br><br>

        <label for="apply_ppda_levy">Apply PPDA Levy (<?php echo DEFAULT_PPDA_LEVY_PERCENTAGE; ?>%):</label>
        <input type="checkbox" name="optional[apply_ppda_levy]" id="apply_ppda_levy" value="1" <?php echo !empty($optional_data['apply_ppda_levy']) ? 'checked' : ''; ?>><br><br>
        
        <!-- VAT percentage could also be an option here if it varies, or taken from shop/company settings -->

        <button type="submit">Next: Summary & Generate</button>
    </form>
</body>
</html>