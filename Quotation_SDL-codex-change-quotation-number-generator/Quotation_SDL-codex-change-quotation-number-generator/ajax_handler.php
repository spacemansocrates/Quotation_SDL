<?php
/**
 * Unified AJAX Handler for Quotation System
 * 
 * This file handles all AJAX requests for the quotation system, providing
 * endpoints for shop details, customer details, product search and details,
 * draft saving, and more.
 * 
 * @author Claude 3.7 Sonnet
 * @version 1.0
 */

// Initialize session
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    sendErrorResponse('Unauthorized access', 401);
    exit();
}

// Include required files
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Route the request based on action
switch ($action) {
    case 'get_shop':
        getShopDetails();
        break;
    case 'get_customer':
        getCustomerDetails();
        break;
    case 'search_products':
        searchProducts();
        break;
    case 'get_product':
        getProductDetails();
        break;
    case 'save_draft':
        saveQuotationDraft();
        break;
    case 'validate_tpin':
        validateTPIN();
        break;
    case 'preview_calculations':
        previewCalculations();
        break;
    case 'get_next_quotation_number':
        getNextQuotationNumber();
        break;
    case 'upload_temp_image':
        uploadTempImage();
        break;
    case 'get_company_settings':
        getCompanySettings();
        break;
    case 'create_customer':
        createCustomer();
        break;
    default:
        sendErrorResponse('Invalid action', 400);
        break;
}

/**
 * Get shop details by ID
 */
function getShopDetails() {
    global $conn;
    
    // Validate input
    $shopId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($shopId <= 0) {
        sendErrorResponse('Invalid shop ID', 400);
        return;
    }
    
    // Prepare and execute query
    $query = "SELECT id, name, shop_code, address_line1, phone, email, tpin_no 
              FROM shops 
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $shopId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $shop = $result->fetch_assoc();
        sendSuccessResponse($shop);
    } else {
        sendErrorResponse('Shop not found', 404);
    }
}

/**
 * Get customer details by ID
 */
function getCustomerDetails() {
    global $conn;
    
    // Validate input
    $customerId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($customerId <= 0) {
        sendErrorResponse('Invalid customer ID', 400);
        return;
    }
    
    // Prepare and execute query
    $query = "SELECT id, name, customer_code, address, phone, email, tpin, 
                     contact_person, payment_terms, notes 
              FROM customers 
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
        sendSuccessResponse($customer);
    } else {
        sendErrorResponse('Customer not found', 404);
    }
}

/**
 * Search products by name, SKU, or description
 */
function searchProducts() {
    global $conn;
    
    // Validate input
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    if (empty($search)) {
        sendErrorResponse('Search query required', 400);
        return;
    }
    
    // Add wildcard to search term
    $searchTerm = "%$search%";
    
    // Prepare and execute query
    $query = "SELECT p.id, p.name, p.sku, p.price, p.description, 
                     u.name as unit_name, c.name as category_name,
                     p.image_path
              FROM products p
              LEFT JOIN units_of_measurement u ON p.unit_id = u.id
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?
              ORDER BY p.name ASC
              LIMIT 20";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($product = $result->fetch_assoc()) {
        $products[] = $product;
    }
    
    sendSuccessResponse($products);
}

/**
 * Get detailed product information by ID
 */
function getProductDetails() {
    global $conn;
    
    // Validate input
    $productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($productId <= 0) {
        sendErrorResponse('Invalid product ID', 400);
        return;
    }
    
    // Prepare and execute query
    $query = "SELECT p.id, p.name, p.sku, p.price, p.description, 
                     p.unit_id, u.name as unit_name, 
                     p.category_id, c.name as category_name,
                     p.image_path, p.notes, p.tax_applicable
              FROM products p
              LEFT JOIN units_of_measurement u ON p.unit_id = u.id
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE p.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Add image URL if available
        if (!empty($product['image_path'])) {
            $product['image_url'] = 'uploads/products/' . $product['image_path'];
        } else {
            $product['image_url'] = 'images/no-image.png';
        }
        
        sendSuccessResponse($product);
    } else {
        sendErrorResponse('Product not found', 404);
    }
}

/**
 * Save quotation as draft
 */
function saveQuotationDraft() {
    global $conn;
    
    // Check if POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Invalid request method', 405);
        return;
    }
    
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        sendErrorResponse('Invalid CSRF token', 403);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Extract and validate required fields
        $shopId = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : 0;
        $customerId = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $quotationDate = isset($_POST['quotation_date']) ? $_POST['quotation_date'] : date('Y-m-d');
        
        // Validate essential data
        if ($shopId <= 0) {
            throw new Exception('Shop selection is required');
        }
        
        // Handle existing quotation (edit mode)
        $quotationId = isset($_POST['quotation_id']) ? intval($_POST['quotation_id']) : 0;
        
        if ($quotationId > 0) {
            // Update existing quotation
            $updateQuery = "UPDATE quotations SET 
                            shop_id = ?,
                            customer_id = ?,
                            quotation_date = ?,
                            customer_name_override = ?,
                            customer_address_override = ?,
                            customer_tpin = ?,
                            company_tpin = ?,
                            gross_total_amount = ?,
                            apply_ppda_levy = ?,
                            ppda_levy_percentage = ?,
                            ppda_levy_amount = ?,
                            amount_before_vat = ?,
                            vat_percentage = ?,
                            vat_amount = ?,
                            total_net_amount = ?,
                            notes_general = ?,
                            delivery_period = ?,
                            payment_terms = ?,
                            quotation_validity_days = ?,
                            mra_wht_note = ?,
                            updated_at = NOW(),
                            updated_by = ?
                            WHERE id = ?";
            
            $stmt = $conn->prepare($updateQuery);
            
            $customerNameOverride = $_POST['customer_name_override'] ?? '';
            $customerAddressOverride = $_POST['customer_address_override'] ?? '';
            $customerTpin = $_POST['customer_tpin'] ?? '';
            $companyTpin = $_POST['company_tpin'] ?? '';
            $grossTotal = $_POST['gross_total_amount'] ?? 0;
            $applyPpdaLevy = isset($_POST['apply_ppda_levy']) ? 1 : 0;
            $ppdaLevyPercentage = $_POST['ppda_levy_percentage'] ?? 1.00;
            $ppdaLevyAmount = $_POST['ppda_levy_amount'] ?? 0;
            $amountBeforeVat = $_POST['amount_before_vat'] ?? 0;
            $vatPercentage = $_POST['vat_percentage'] ?? 16.5;
            $vatAmount = $_POST['vat_amount'] ?? 0;
            $totalNetAmount = $_POST['total_net_amount'] ?? 0;
            $notesGeneral = $_POST['notes_general'] ?? '';
            $deliveryPeriod = $_POST['delivery_period'] ?? '';
            $paymentTerms = $_POST['payment_terms'] ?? '';
            $validityDays = $_POST['quotation_validity_days'] ?? 30;
            $mraWhtNote = isset($_POST['include_mra_wht']) ? ($_POST['mra_wht_note'] ?? '') : '';
            $updatedBy = $_SESSION['user_id'];
            
            $stmt->bind_param("iissssdddddddddsssisi", 
                $shopId, $customerId, $quotationDate, $customerNameOverride,
                $customerAddressOverride, $customerTpin, $companyTpin,
                $grossTotal, $applyPpdaLevy, $ppdaLevyPercentage, $ppdaLevyAmount,
                $amountBeforeVat, $vatPercentage, $vatAmount, $totalNetAmount,
                $notesGeneral, $deliveryPeriod, $paymentTerms, $validityDays,
                $mraWhtNote, $updatedBy, $quotationId
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update quotation: ' . $stmt->error);
            }
            
            // Delete existing items to replace with new ones
            $deleteItemsQuery = "DELETE FROM quotation_items WHERE quotation_id = ?";
            $stmt = $conn->prepare($deleteItemsQuery);
            $stmt->bind_param("i", $quotationId);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update quotation items: ' . $stmt->error);
            }
            
        } else {
            // Create new quotation
            // Generate quotation number
            $nextId = getNextQuotationId($conn);
            
            // Get customer code
            $customerCode = 'NEW';
            if ($customerId > 0) {
                $custQuery = "SELECT customer_code FROM customers WHERE id = ?";
                $stmt = $conn->prepare($custQuery);
                $stmt->bind_param("i", $customerId);
                $stmt->execute();
                $custResult = $stmt->get_result();
                if ($custResult && $custResult->num_rows > 0) {
                    $customerCode = $custResult->fetch_assoc()['customer_code'];
                }
            }
            
            // Get shop code
            $shopQuery = "SELECT shop_code FROM shops WHERE id = ?";
            $stmt = $conn->prepare($shopQuery);
            $stmt->bind_param("i", $shopId);
            $stmt->execute();
            $shopResult = $stmt->get_result();
            $shopCode = 'HQ';
            if ($shopResult && $shopResult->num_rows > 0) {
                $shopCode = $shopResult->fetch_assoc()['shop_code'];
            }
            
            // Generate formatted quotation number
            $paddedId = str_pad($nextId, 4, '0', STR_PAD_LEFT);
            $quotationNumber = "SDLT/$customerCode-$shopCode$paddedId";
            
            $insertQuery = "INSERT INTO quotations (
                            shop_id, customer_id, quotation_number, quotation_date,
                            customer_name_override, customer_address_override, customer_tpin,
                            company_tpin, gross_total_amount, apply_ppda_levy,
                            ppda_levy_percentage, ppda_levy_amount, amount_before_vat,
                            vat_percentage, vat_amount, total_net_amount,
                            notes_general, delivery_period, payment_terms,
                            quotation_validity_days, mra_wht_note, status,
                            created_at, created_by
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', NOW(), ?
                        )";
            
            $stmt = $conn->prepare($insertQuery);
            
            $customerNameOverride = $_POST['customer_name_override'] ?? '';
            $customerAddressOverride = $_POST['customer_address_override'] ?? '';
            $customerTpin = $_POST['customer_tpin'] ?? '';
            $companyTpin = $_POST['company_tpin'] ?? '';
            $grossTotal = $_POST['gross_total_amount'] ?? 0;
            $applyPpdaLevy = isset($_POST['apply_ppda_levy']) ? 1 : 0;
            $ppdaLevyPercentage = $_POST['ppda_levy_percentage'] ?? 1.00;
            $ppdaLevyAmount = $_POST['ppda_levy_amount'] ?? 0;
            $amountBeforeVat = $_POST['amount_before_vat'] ?? 0;
            $vatPercentage = $_POST['vat_percentage'] ?? 16.5;
            $vatAmount = $_POST['vat_amount'] ?? 0;
            $totalNetAmount = $_POST['total_net_amount'] ?? 0;
            $notesGeneral = $_POST['notes_general'] ?? '';
            $deliveryPeriod = $_POST['delivery_period'] ?? '';
            $paymentTerms = $_POST['payment_terms'] ?? '';
            $validityDays = $_POST['quotation_validity_days'] ?? 30;
            $mraWhtNote = isset($_POST['include_mra_wht']) ? ($_POST['mra_wht_note'] ?? '') : '';
            $createdBy = $_SESSION['user_id'];
            
            $stmt->bind_param("iisssssddddddddsssisi", 
                $shopId, $customerId, $quotationNumber, $quotationDate,
                $customerNameOverride, $customerAddressOverride, $customerTpin,
                $companyTpin, $grossTotal, $applyPpdaLevy, $ppdaLevyPercentage,
                $ppdaLevyAmount, $amountBeforeVat, $vatPercentage, $vatAmount,
                $totalNetAmount, $notesGeneral, $deliveryPeriod, $paymentTerms,
                $validityDays, $mraWhtNote, $createdBy
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create quotation: ' . $stmt->error);
            }
            
            $quotationId = $conn->insert_id;
            
            // Save new customer if requested
            if (isset($_POST['save_new_customer']) && $_POST['save_new_customer'] == 'on') {
                createNewCustomer($conn, $_POST);
            }
        }
        
        // Process quotation items
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            processQuotationItems($conn, $quotationId, $_POST['items']);
        }
        
        // Log activity
        logActivity($conn, $_SESSION['user_id'], 'save_draft', 'quotation', $quotationId, 
                    'Saved quotation #' . ($quotationId > 0 ? $quotationNumber : $quotationId) . ' as draft');
        
        // Commit transaction
        $conn->commit();
        
        // Return success with quotation ID
        sendSuccessResponse([
            'quotation_id' => $quotationId,
            'quotation_number' => $quotationNumber ?? '',
            'message' => 'Quotation saved successfully as draft'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        sendErrorResponse($e->getMessage(), 500);
    }
}

/**
 * Validate TPIN format and check for existence
 */
function validateTPIN() {
    global $conn;
    
    $tpin = isset($_GET['tpin']) ? trim($_GET['tpin']) : '';
    
    if (empty($tpin)) {
        sendErrorResponse('TPIN is required', 400);
        return;
    }
    
    // Validate TPIN format (example: assuming 9-digit numeric)
    if (!preg_match('/^\d{9}$/', $tpin)) {
        sendErrorResponse('Invalid TPIN format. Expected 9-digit number.', 400);
        return;
    }
    
    // Check if TPIN exists in customers table
    $query = "SELECT id, name FROM customers WHERE tpin = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $tpin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
        sendSuccessResponse([
            'exists' => true,
            'customer_id' => $customer['id'],
            'customer_name' => $customer['name'],
            'message' => 'TPIN belongs to existing customer'
        ]);
    } else {
        sendSuccessResponse([
            'exists' => false,
            'message' => 'Valid TPIN format, no matching customer found'
        ]);
    }
}

/**
 * Preview calculations based on input data
 */
function previewCalculations() {
    // Check if POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Invalid request method', 405);
        return;
    }
    
    // Extract calculation parameters
    $grossTotal = isset($_POST['gross_total']) ? floatval($_POST['gross_total']) : 0;
    $applyPpdaLevy = isset($_POST['apply_ppda']) && $_POST['apply_ppda'] == 'true';
    $ppdaPercentage = isset($_POST['ppda_percentage']) ? floatval($_POST['ppda_percentage']) : 1.00;
    $vatPercentage = isset($_POST['vat_percentage']) ? floatval($_POST['vat_percentage']) : 16.50;
    
    // Perform calculations
    $ppdaAmount = 0;
    if ($applyPpdaLevy) {
        $ppdaAmount = $grossTotal * ($ppdaPercentage / 100);
    }
    
    $amountBeforeVat = $grossTotal + $ppdaAmount;
    $vatAmount = $amountBeforeVat * ($vatPercentage / 100);
    $totalNetAmount = $amountBeforeVat + $vatAmount;
    
    // Format numbers to 2 decimal places
    $ppdaAmount = number_format($ppdaAmount, 2, '.', '');
    $amountBeforeVat = number_format($amountBeforeVat, 2, '.', '');
    $vatAmount = number_format($vatAmount, 2, '.', '');
    $totalNetAmount = number_format($totalNetAmount, 2, '.', '');
    
    // Return calculations
    sendSuccessResponse([
        'ppda_amount' => $ppdaAmount,
        'amount_before_vat' => $amountBeforeVat,
        'vat_amount' => $vatAmount,
        'total_net_amount' => $totalNetAmount
    ]);
}

/**
 * Get next quotation number for new quotation
 */
function getNextQuotationNumber() {
    global $conn;
    
    $shopId = isset($_GET['shop_id']) ? intval($_GET['shop_id']) : 0;
    $customerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
    
    if ($shopId <= 0) {
        sendErrorResponse('Shop ID is required', 400);
        return;
    }
    
    try {
        $nextId = getNextQuotationId($conn);
        
        // Get customer code
        $customerCode = 'NEW';
        if ($customerId > 0) {
            $custQuery = "SELECT customer_code FROM customers WHERE id = ?";
            $stmt = $conn->prepare($custQuery);
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            $custResult = $stmt->get_result();
            if ($custResult && $custResult->num_rows > 0) {
                $customerCode = $custResult->fetch_assoc()['customer_code'];
            }
        }
        
        // Get shop code
        $shopQuery = "SELECT shop_code FROM shops WHERE id = ?";
        $stmt = $conn->prepare($shopQuery);
        $stmt->bind_param("i", $shopId);
        $stmt->execute();
        $shopResult = $stmt->get_result();
        $shopCode = 'HQ';
        if ($shopResult && $shopResult->num_rows > 0) {
            $shopCode = $shopResult->fetch_assoc()['shop_code'];
        }
        
        // Generate formatted quotation number
        $paddedId = str_pad($nextId, 4, '0', STR_PAD_LEFT);
        $quotationNumber = "SDLT/$customerCode-$shopCode$paddedId";
        
        sendSuccessResponse([
            'next_id' => $nextId,
            'quotation_number' => $quotationNumber
        ]);
    } catch (Exception $e) {
        sendErrorResponse($e->getMessage(), 500);
    }
}

/**
 * Upload temporary image for quotation item
 */
function uploadTempImage() {
    // Check if POST request with file
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
        sendErrorResponse('Invalid request or no file uploaded', 400);
        return;
    }
    
    $file = $_FILES['image'];
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        sendErrorResponse('Invalid file type. Only JPG, PNG, and GIF are allowed.', 400);
        return;
    }
    
    if ($file['size'] > $maxFileSize) {
        sendErrorResponse('File too large. Maximum size is 5MB.', 400);
        return;
    }
    
    // Create temp directory if not exists
    $tempDir = 'uploads/temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    // Generate unique filename
    $fileName = uniqid('temp_') . '_' . basename($file['name']);
    $filePath = $tempDir . '/' . $fileName;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        sendSuccessResponse([
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_url' => $filePath,
            'message' => 'File uploaded successfully'
        ]);
    } else {
        sendErrorResponse('Failed to upload file', 500);
    }
}

/**
 * Get company settings for quotation
 */
function getCompanySettings() {
    global $conn;
    
    $settingsQuery = "SELECT setting_key, setting_value FROM company_settings 
                     WHERE setting_key IN ('default_vat_percentage', 'default_ppda_levy_percentage', 
                                          'default_mra_wht_note', 'company_name', 'company_tpin', 
                                          'quotation_validity_days', 'default_payment_terms')";
    $result = $conn->query($settingsQuery);
    
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    sendSuccessResponse($settings);
}

/**
 * Create new customer
 */
function createCustomer() {
    global $conn;
    
    // Check if POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Invalid request method', 405);
        return;
    }
    
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        sendErrorResponse('Invalid CSRF token', 403);
        return;
    }
    
    try {
        $customerData = createNewCustomer($conn, $_POST);
        sendSuccessResponse([
            'customer_id' => $customerData['id'],
            'customer_code' => $customerData['customer_code'],
            'message' => 'Customer created successfully'
        ]);
    } catch (Exception $e) {
        sendErrorResponse($e->getMessage(), 500);
    }
}

/**
 * Helper Functions
 */

/**
 * Send success response in JSON format
 */
function sendSuccessResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit();
}

/**
 * Send error response in JSON format
 */
function sendErrorResponse($message, $statusCode = 400) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

/**
 * Get next quotation ID
 */
function getNextQuotationId($conn) {
    $query = "SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM quotations";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['next_id'];
    }
    
    return 1; // Default to 1 if no quotations exist
}

/**
 * Process quotation items
 */
function processQuotationItems($conn, $quotationId, $items) {
    if (empty($items) || !is_array($items)) {
        return;
    }
    
    $uploadDir = 'uploads/quotation_items/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $insertQuery = "INSERT INTO quotation_items (
                    quotation_id, item_number, product_id, description,
                    quantity, unit_of_measurement, rate_per_unit, total_amount,
                    image_path, custom_unit
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    
    foreach ($items as $index => $item) {
        // Skip incomplete items
        if (empty($item['description']) || empty($item['quantity']) || empty($item['rate_per_unit'])) {
            continue;
        }
        
        $itemNumber = $index + 1;
        $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;
        $description = $item['description'];
        $quantity = floatval($item['quantity']);
        $unit = $item['unit_of_measurement'];
        $customUnit = isset($item['custom_unit']) ? $item['custom_unit'] : '';
        $rate = floatval($item['rate_per_unit']);
        $total = floatval($item['total_amount']);
        
        // Handle image if uploaded
        $imagePath = '';
        if (isset($_FILES['items']['name'][$index]['image']) && !empty($_FILES['items']['name'][$index]['image'])) {
            $file = [
                'name' => $_FILES['items']['name'][$index]['image'],
                'type' => $_FILES['items']['type'][$index]['image'],
                'tmp_name' => $_FILES['items']['tmp_name'][$index]['image'],
                'error' => $_FILES['items']['error'][$index]['image'],
                'size' => $_FILES['items']['size'][$index]['image']
            ];
            
            // Process valid image
            if ($file['error'] === UPLOAD_ERR_OK) {
                $fileName = 'item_' . $quotationId . '_' . $itemNumber . '_' . time() . '_' . basename($file['name']);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $imagePath = $fileName;
                }
            }
        } else if (isset($item['image_temp']) && !empty($item['image_temp'])) {
            // Handle previously uploaded temp image
            $tempPath = 'uploads/temp/' . $item['image_temp'];
            if (file_exists($tempPath)) {
                $fileName = 'item_' . $quotationId . '_' . $itemNumber . '_' . time() . '_' . basename($item['image_temp']);
                $filePath = $uploadDir . $fileName;
                
                if (rename($tempPath, $filePath)) {
                    $imagePath = $fileName;
                }
            }
        }
        
        // Prepare and execute statement
        $stmt->bind_param("iiisdsdss", 
            $quotationId, $itemNumber, $productId, $description, 
            $quantity, $unit, $rate, $total, $imagePath, $customUnit
        );
        
        $stmt->execute();
    }
    
    $stmt->close();
}

/**
 * Create a new customer from form data
 */
function createNewCustomer($conn, $formData) {
    // Validate required fields
    $customerName = $formData['customer_name_override'] ?? '';
    if (empty($customerName)) {
        throw new Exception('Customer name is required');
    }
    
    // Generate unique customer code
    $customerCode = generateCustomerCode($conn, $customerName);
    
    // Insert new customer
    $insertQuery = "INSERT INTO customers (
                    name, customer_code, address, tpin,
                    created_at, created_by, notes
                ) VALUES (?, ?, ?, ?, NOW(), ?, 'Added via quotation form')";
    
    $stmt = $conn->prepare($insertQuery);
    
    $customerAddress = $formData['customer_address_override'] ?? '';
    $customerTpin = $formData['customer_tpin'] ?? '';
    $createdBy = $_SESSION['user_id'];
    
    $stmt->bind_param("ssssi", 
        $customerName, $customerCode, $customerAddress, 
        $customerTpin, $createdBy
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create customer: ' . $stmt->error);
    }
    
    $customerId = $conn->insert_id;
    
    // Log activity
    logActivity($conn, $_SESSION['user_id'], 'create', 'customer', $customerId, 
                'Created new customer via quotation form');
    
    return [
        'id' => $customerId,
        'customer_code' => $customerCode
    ];
}

/**
 * Generate a unique customer code based on name
 */
function generateCustomerCode($conn, $customerName) {
    // Extract first 4 characters of name, uppercase
    $nameWords = explode(' ', $customerName);
    $firstWord = $nameWords[0];
    $code = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $firstWord)), 0, 4);
    
    // Pad with 'X' if less than 4 characters
    $code = str_pad($code, 4, 'X');
    
    // Check if code exists, add numbers if it does
    $query = "SELECT COUNT(*) as count FROM customers WHERE customer_code LIKE ?";
    $stmt = $conn->prepare($query);
    $likePattern = $code . '%';
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count > 0) {
        $code .= ($count + 1);
    }
    
    return $code;
}

/**
 * Log system activity
 */
function logActivity($conn, $userId, $actionType, $targetEntity, $targetEntityId, $description) {
    $insertQuery = "INSERT INTO activity_log (
                    user_id, action_type, target_entity, target_entity_id,
                    description, ip_address, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($insertQuery);
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt->bind_param("ississ", 
        $userId, $actionType, $targetEntity, 
        $targetEntityId, $description, $ipAddress
    );
    
    $stmt->execute();
}

/**
 * Validate CSRF token
 */
function validateCsrfToken($token) {
    // The validateCsrfToken function should be implemented in functions.php
    // This is just a placeholder check in case it's not
    if (function_exists('validateCsrfToken')) {
        return validateCsrfToken($token);
    }
    
    // Simple session-based validation if validateCsrfToken is not available
    return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token;
}
