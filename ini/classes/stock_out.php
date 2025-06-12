<?php
session_start();
require_once 'InventoryManager.php'; // Ensure you have this file and it defines InventoryManager and Database classes

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$inventory = new InventoryManager();
$message = '';
$scan_result = null;
$error_class = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'scan_out') {
    $qr_code = trim($_POST['qr_code']);
    $notes = trim($_POST['notes'] ?? '');
    $quotation_id = !empty($_POST['quotation_id']) ? (int)$_POST['quotation_id'] : null;
    $reference_number = trim($_POST['reference_number'] ?? '');
    
    if (empty($qr_code)) {
        $message = "Please enter or scan a barcode.";
        $error_class = 'error';
    } else {
        $result = $inventory->removeStock(
            $qr_code, 
            $_SESSION['user_id'], 
            'sale', 
            $quotation_id, 
            $notes,
            $reference_number
        );
        
        if ($result['success']) {
            $scan_result = $result;
            $message = $result['message'];
            $error_class = 'success';
            
            // Clear form data after successful scan
            // $_POST = array(); // Clearing POST like this might be too aggressive if you want to redisplay some info
            // For this example, we'll clear specific fields later or let the successful scan display handle it.
            // If you clear $_POST here, the values won't be available for the htmlspecialchars in the form inputs below
            // on the same page load if there was an error *before* this successful block.
            // A better approach for clearing is via JavaScript after success or on specific user action.

             if (!isset($_SESSION['scan_count'])) {
                $_SESSION['scan_count'] = 0;
            }
            $_SESSION['scan_count']++;

        } else {
            $message = $result['error'];
            $error_class = 'error';
        }
    }
}

// Handle AJAX requests for real-time stock checking
if (isset($_GET['action']) && $_GET['action'] === 'check_stock' && isset($_GET['qr_code'])) {
    header('Content-Type: application/json');
    
    $qr_code = trim($_GET['qr_code']);
    
    try {
        // Assuming Database class is available via InventoryManager.php or autoloaded
        if (!class_exists('Database')) {
             // Handle missing Database class, perhaps log or throw error
             // For now, we assume it's loaded from InventoryManager.php
        }
        $db = new Database(); // Make sure Database class is defined and accessible
        $conn = $db->connect();
        
        $stmt = $conn->prepare("
            SELECT p.id, p.name, p.sku, p.description, 
                   COALESCE(i.quantity_in_stock, 0) as current_stock,
                   COALESCE(i.minimum_stock_level, 0) as min_level
            FROM products p 
            LEFT JOIN inventory_stock i ON p.id = i.product_id 
            WHERE p.qr_code = ?
        ");
        $stmt->execute([$qr_code]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            echo json_encode([
                'success' => true,
                'product' => $product,
                'can_scan' => $product['current_stock'] > 0
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Product not found for code: ' . htmlspecialchars($qr_code)
            ]);
        }
    } catch (Exception $e) {
        error_log("Database error in check_stock: " . $e->getMessage()); // Log error
        echo json_encode([
            'success' => false,
            'error' => 'Database error. Please check logs.' //. $e->getMessage() // Avoid exposing detailed error messages to client
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Out - Barcode Scanner</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qr-scanner/1.4.2/qr-scanner.umd.min.js"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background-color: #f4f7f6; color: #333; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding-top: 20px; }
        .container { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 700px; margin-bottom: 20px; }
        .header { text-align: center; margin-bottom: 25px; }
        .header h1 { color: #2c3e50; margin-bottom: 5px; font-size: 1.8em; }
        .header p { color: #7f8c8d; font-size: 0.95em; }

        .message { padding: 12px 18px; margin-bottom: 20px; border-radius: 5px; font-size: 0.9em; text-align: center; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .scan-result { background-color: #e9f5ff; padding: 20px; margin-bottom: 20px; border-left: 5px solid #3498db; border-radius: 5px; }
        .scan-result h3 { margin-top: 0; color: #2980b9; font-size: 1.2em; }
        .product-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 0.9em; }
        .detail-item strong { display: block; color: #555; margin-bottom: 3px; }
        .detail-item span { color: #333; }
        .stock-indicator.low { color: #e74c3c; font-weight: bold; }
        .stock-indicator.medium { color: #f39c12; font-weight: bold; }
        .stock-indicator.high { color: #2ecc71; font-weight: bold; }

        .form-section h3 { text-align: center; color: #34495e; margin-bottom: 20px; font-size: 1.3em;}
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; font-size: 0.9em; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group textarea {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 0.95em;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group textarea:focus { border-color: #3498db; outline: none; box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2); }
        .form-group textarea { resize: vertical; min-height: 60px; }
        .qr-input-group { display: flex; gap: 10px; align-items: center; }
        .qr-input-group input[type="text"] { flex-grow: 1; }
        .camera-button { padding: 10px 15px; background-color: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; white-space: nowrap; transition: background-color 0.3s; }
        .camera-button:hover { background-color: #2980b9; }
        .camera-button:disabled { background-color: #bdc3c7; cursor: not-allowed; }
        
        .btn { background-color: #27ae60; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; width: 100%; transition: background-color 0.3s; display: block; text-align: center; margin-bottom:10px;}
        .btn:hover { background-color: #229954; }
        .btn:disabled { background-color: #95a5a6; opacity: 0.7; cursor: not-allowed; }
        .btn-secondary { background-color: #7f8c8d; }
        .btn-secondary:hover { background-color: #6c7a7d; }

        .loading { display: none; text-align: center; padding: 15px; background-color: #f9f9f9; border-radius: 4px; margin-bottom: 15px; }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; margin: 0 auto 8px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .loading p { margin: 0; font-size: 0.9em; color: #555; }

        .product-preview { display: none; background-color: #f0f9ff; border: 1px solid #b3e0ff; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .product-preview.show { display: block; }
        .product-preview h4 { margin-top: 0; margin-bottom: 10px; color: #007bff; font-size: 1.1em; }
        #productInfo div { margin-bottom: 5px; font-size: 0.9em; }
        #productInfo strong { color: #333; }

        .scanner-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center; }
        .scanner-content { background-color: #fff; padding: 25px; border-radius: 8px; width: 90%; max-width: 500px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .scanner-video { width: 100%; max-height: 400px; border-radius: 4px; margin-bottom: 15px; background-color: #eee; }
        .scanner-status { margin-bottom: 15px; font-size: 0.9em; }
        .scanner-status .info { color: #3498db; }
        .scanner-status .success { color: #2ecc71; font-weight: bold; }
        .scanner-status .error { color: #e74c3c; font-weight: bold; }
        .close-scanner { background-color: #e74c3c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; transition: background-color 0.3s; }
        .close-scanner:hover { background-color: #c0392b; }

        .quick-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 25px; margin-bottom: 25px; }
        .stat-card { background-color: #ecf0f1; padding: 15px; border-radius: 5px; text-align: center; }
        .stat-card h4 { margin-top: 0; margin-bottom: 8px; color: #34495e; font-size: 0.95em; }
        .stat-value { font-size: 1.4em; font-weight: bold; color: #2c3e50; }

        .navigation { text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
        .navigation a { margin: 0 10px; color: #3498db; text-decoration: none; font-size: 0.95em; padding: 8px 12px; border-radius: 4px; transition: background-color 0.2s, color 0.2s; }
        .navigation a:hover { background-color: #ecf0f1; color: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Stock Out Scanner</h1>
            <p>Scan barcodes to remove items from inventory</p>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($error_class); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($scan_result && $scan_result['success']): ?>
                <div class="scan-result">
                    <h3>Item Successfully Scanned Out</h3>
                    <div class="product-details">
                        <div class="detail-item">
                            <strong>Product Name</strong>
                            <span><?php echo htmlspecialchars($scan_result['product']['name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>SKU</strong>
                            <span><?php echo htmlspecialchars($scan_result['product']['sku']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Remaining Stock</strong>
                            <span class="stock-indicator <?php 
                                $stock = $scan_result['new_stock'];
                                if ($stock <= 0) echo 'low'; // Or a specific 'out-of-stock' class
                                elseif ($stock <= ($scan_result['product']['minimum_stock_level'] ?? 3)) echo 'low'; // Use min_level if available
                                elseif ($stock > 10) echo 'high'; // Example threshold
                                else echo 'medium';
                            ?>">
                                <?php echo $stock; ?> units
                            </span>
                        </div>
                        <div class="detail-item">
                            <strong>Scanned By</strong>
                            <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Scan Time</strong>
                            <span><?php echo date('M j, Y - g:i A'); ?></span>
                        </div>
                        <?php if (!empty($scan_result['quotation_id'])): ?>
                        <div class="detail-item">
                            <strong>Quotation ID</strong>
                            <span>#<?php echo htmlspecialchars($scan_result['quotation_id']); ?></span>
                        </div>
                        <?php endif; ?>
                         <?php if (!empty($scan_result['reference_number'])): ?>
                        <div class="detail-item">
                            <strong>Reference</strong>
                            <span><?php echo htmlspecialchars($scan_result['reference_number']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="form-section">
                <h3>🔍 Scan Item Out</h3>
                
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Checking product information...</p>
                </div>
                
                <div class="product-preview" id="productPreview">
                    <h4>Product Information</h4>
                    <div id="productInfo"></div>
                </div>
                
                <form method="POST" id="scanForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="action" value="scan_out">
                    
                    <div class="form-group">
                        <label for="qr_code">Barcode *</label>
                        <div class="qr-input-group">
                            <input 
                                type="text" 
                                id="qr_code"
                                name="qr_code" 
                                placeholder="Scan barcode or enter manually"
                                required 
                                autofocus
                                autocomplete="off"
                                value="<?php echo htmlspecialchars($_POST['qr_code'] ?? ($scan_result && !$scan_result['success'] ? $_POST['qr_code'] : '')); ?>"
                            >
                            <button type="button" id="startScanBtn" class="camera-button">
                                📷 Scan
                            </button>
                        </div>
                        <small style="color: #666; font-size: 0.85em; display: block; margin-top: 5px;">
                            Click "Scan" to use camera, or type code and press Enter.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="quotation_id">Quotation ID (Optional)</label>
                        <input 
                            type="number" 
                            id="quotation_id"
                            name="quotation_id" 
                            placeholder="Enter quotation ID to link this transaction"
                             value="<?php echo htmlspecialchars($_POST['quotation_id'] ?? ($scan_result && !$scan_result['success'] ? $_POST['quotation_id'] : '')); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="reference_number">Reference Number (Optional)</label>
                        <input 
                            type="text" 
                            id="reference_number"
                            name="reference_number" 
                            placeholder="Invoice, receipt, or document number"
                            value="<?php echo htmlspecialchars($_POST['reference_number'] ?? ($scan_result && !$scan_result['success'] ? $_POST['reference_number'] : '')); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea 
                            id="notes"
                            name="notes" 
                            rows="3" 
                            placeholder="Additional notes about this transaction..."
                        ><?php echo htmlspecialchars($_POST['notes'] ?? ($scan_result && !$scan_result['success'] ? $_POST['notes'] : '')); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn" id="submitBtn">
                        📤 Scan Out Item
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="clearFormAndMessages()">
                        🔄 Clear Form
                    </button>
                </form>

                <div id="scannerModal" class="scanner-modal">
                    <div class="scanner-content">
                        <h3 style="text-align: center; margin-bottom: 15px;">📷 Barcode Scanner</h3>
                        <div class="scanner-status" id="scannerStatus">
                            <span class="info">Position barcode in front of camera</span>
                        </div>
                        <video id="scannerVideo" class="scanner-video" muted playsinline></video>
                        <div class="scanner-controls">
                            <button id="closeScannerBtn" class="close-scanner">❌ Close Scanner</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="quick-stats">
                <div class="stat-card">
                    <h4>Session Scans</h4>
                    <div class="stat-value" id="sessionScans">
                        <?php echo isset($_SESSION['scan_count']) ? $_SESSION['scan_count'] : 0; ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h4>Current User</h4>
                    <div class="stat-value" style="font-size: 1em;">
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h4>Last Scan Time</h4>
                    <div class="stat-value" style="font-size: 0.9em;">
                        <?php echo ($scan_result && $scan_result['success']) ? date('g:i A') : 'None'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="navigation">
            <a href="stock_in.php">📥 Stock In</a>
            <a href="stock_report.php">📊 Stock Report</a>
            <a href="transaction_history.php">📋 Transaction History</a>
            <a href="index.php">🏠 Dashboard</a>
             <a href="logout.php" style="color: #e74c3c;">🚪 Logout</a>
        </div>
    </div>

    <script>
        const qrCodeInput = document.getElementById('qr_code');
        const startScanBtn = document.getElementById('startScanBtn');
        const closeScannerBtn = document.getElementById('closeScannerBtn');
        const scannerModal = document.getElementById('scannerModal');
        const scannerVideo = document.getElementById('scannerVideo');
        const scannerStatus = document.getElementById('scannerStatus');
        const productPreviewEl = document.getElementById('productPreview');
        const productInfoEl = document.getElementById('productInfo');
        const loadingEl = document.getElementById('loading');
        const submitBtn = document.getElementById('submitBtn');
        const scanForm = document.getElementById('scanForm');

        // Add this event listener
startScanBtn.addEventListener('click', function(e) {
    e.preventDefault();
    startScanner();
});

// Also add this for the close button
closeScannerBtn.addEventListener('click', function(e) {
    e.preventDefault();
    closeScanner();
});

qrCodeInput.addEventListener('input', function() {
    clearTimeout(window.checkProductTimeout);
    window.checkProductTimeout = setTimeout(checkProductInfo, 500);
});

let stockOutQrScanner = null; // This will hold the QrScanner instance

        // Update session scan count on successful PHP processing
        <?php if ($scan_result && $scan_result['success']): ?>
        document.getElementById('sessionScans').textContent = '<?php echo $_SESSION['scan_count']; ?>';
        // Clear form fields on successful scan, but keep message and scan result visible
        qrCodeInput.value = '';
        document.getElementById('quotation_id').value = '';
        document.getElementById('reference_number').value = '';
        document.getElementById('notes').value = '';
        hideProductPreview(); // Hide preview as item is processed
        qrCodeInput.focus();
        <?php endif; ?>

      function startScanner() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera access is not supported by your browser or device. Please enter the code manually.');
                return;
            }

            // Ensure the video element is visible within the modal
            scannerVideo.style.display = 'block'; 
            scannerModal.style.display = 'flex';
            scannerStatus.innerHTML = '<span class="info">📷 Initializing camera...</span>';
            startScanBtn.disabled = true;
            startScanBtn.textContent = 'Scanner Active';

            // Initialize QrScanner if it hasn't been already or if destroyed
            if (!stockOutQrScanner) {
                stockOutQrScanner = new QrScanner(
                    scannerVideo, // Your video element
                    result => {
                        console.log('Decoded QR code:', result.data);
                        qrCodeInput.value = result.data;
                        scannerStatus.innerHTML = `<span class="success">✅ Barcode detected!</span>`;
                        
                        // Automatically close scanner and process after detection
                        setTimeout(() => {
                            closeScanner(); // This will also call stockOutQrScanner.stop()
                            checkProductInfo(); // Your existing function to check product
                            qrCodeInput.focus();
                        }, 700); // Short delay to show success message
                    },
                    {
                        highlightScanRegion: true,
                        highlightCodeOutline: true,
                        onDecodeError: error => {
                            // More specific error handling can be done here if needed
                            // For now, we let it continue trying.
                            // You might want to update scannerStatus for transient errors.
                            // Example: if (error !== 'No QR code found.') console.warn(error);
                        },
                    }
                );
            }

            stockOutQrScanner.start().then(() => {
                scannerStatus.innerHTML = '<span class="info">🔍 Position barcode in front of camera</span>';
            }).catch(err => {
                console.error("QrScanner start error:", err);
                let errorMsg = 'Unable to access camera. ';
                 if (typeof err === 'string') { // Some errors are simple strings
                    if (err.toLowerCase().includes('permission denied') || err.toLowerCase().includes('notallowederror')) {
                        errorMsg += 'Camera permission denied. Please allow access in browser settings.';
                    } else if (err.toLowerCase().includes('notfounderror')) {
                        errorMsg += 'No camera found on this device.';
                    } else if (err.toLowerCase().includes('camera not found') || err.toLowerCase().includes('no camera available')) {
                         errorMsg += 'No camera found on this device.';
                    } else if (err.toLowerCase().includes('already playing')) {
                        errorMsg += 'Camera may already be in use. Try closing other apps using the camera.';
                    }
                    else {
                        errorMsg += `Details: ${err}`;
                    }
                } else if (err.name) { // Standard DOMException
                    if (err.name === 'NotAllowedError') {
                        errorMsg += 'Camera permission denied. Please allow access in browser settings.';
                    } else if (err.name === 'NotFoundError') {
                        errorMsg += 'No camera found on this device.';
                    } else if (err.name === 'NotReadableError') {
                        errorMsg += 'The camera is already in use or could not be started.';
                    } else {
                        errorMsg += `Error: ${err.name} - ${err.message}`;
                    }
                } else {
                     errorMsg += 'An unknown error occurred. Please enter the code manually.';
                }

                scannerStatus.innerHTML = `<span class="error">❌ ${errorMsg}</span>`;
                alert(errorMsg);
                // Optionally call closeScanner() here if you want the modal to hide on critical start error
                // closeScanner(); 
            });
        }

       function closeScanner() {
            if (stockOutQrScanner) {
                stockOutQrScanner.stop();
                // To fully clean up the scanner instance if you won't reuse it immediately:
                // stockOutQrScanner.destroy();
                // stockOutQrScanner = null; 
                // If you destroy it, the `if (!stockOutQrScanner)` check in startScanner will reinitialize.
                // For now, just stopping is often sufficient if the user might scan again soon.
            }
            
            scannerVideo.style.display = 'none'; // Hide the video element itself
            scannerModal.style.display = 'none'; // Hide the modal
            startScanBtn.disabled = false;
            startScanBtn.textContent = '📷 Scan'; // Reset button text
            scannerStatus.innerHTML = '<span class="info">Position barcode in front of camera</span>'; // Reset status message
        }
        function checkProductInfo(code) {
            const qrCode = code || qrCodeInput.value.trim();
            
            if (!qrCode) {
                hideProductPreview();
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                return;
            }
            
            showLoading();
            hideProductPreviewInstantly(); // Hide previous preview immediately
            
            fetch(`?action=check_stock&qr_code=${encodeURIComponent(qrCode)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    hideLoading();
                    if (data.success && data.product) {
                        showProductPreview(data.product, data.can_scan);
                    } else {
                        showProductPreview(null, false, data.error || 'Product not found or error fetching details.');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Fetch Error:', error);
                    showProductPreview(null, false, `Network or server error: ${error.message}. Please try again.`);
                });
        }

        function showProductPreview(product, canScan, error = null) {
            productPreviewEl.classList.remove('show'); // remove to reset animation/transition if any
            if (error) {
                productInfoEl.innerHTML = `<div style="color: #721c24; background: #f8d7da; padding: 10px; border-radius: 5px;">Error: ${htmlspecialchars(error)}</div>`;
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
            } else if (product) {
                const stock = parseInt(product.current_stock, 10);
                const minLevel = parseInt(product.min_level, 10);
                let stockClass = 'medium'; // Default
                if (stock <= 0) stockClass = 'low critical'; // Critical might be a new style for 0
                else if (stock <= minLevel) stockClass = 'low';
                else if (stock > 10) stockClass = 'high'; // Example threshold

                productInfoEl.innerHTML = `
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                        <div><strong>Name:</strong><br>${htmlspecialchars(product.name)}</div>
                        <div><strong>SKU:</strong><br>${htmlspecialchars(product.sku)}</div>
                        <div><strong>Current Stock:</strong><br><span class="stock-indicator ${stockClass}">${stock} units</span></div>
                        <div><strong>Min. Stock Level:</strong><br>${minLevel} units</div>
                    </div>
                    ${!canScan ? `<div style="color: #721c24; margin-top: 10px; font-weight: bold; background: #f8d7da; padding: 8px; border-radius: 4px;">⚠️ ${stock <= 0 ? 'Out of stock' : 'Below minimum or out of stock'} - Cannot scan out</div>` : ''}
                    ${canScan && stock <= minLevel && stock > 0 ? `<div style="color: #856404; margin-top: 10px; font-weight: bold; background: #fff3cd; padding: 8px; border-radius: 4px;">🔔 Note: Stock is at or below minimum level.</div>` : ''}
                `;
                
                submitBtn.disabled = !canScan;
                submitBtn.style.opacity = canScan ? '1' : '0.5';
            }
            productPreviewEl.classList.add('show');
        }
        
        function hideProductPreviewInstantly() {
            productPreviewEl.classList.remove('show');
            productInfoEl.innerHTML = ''; // Clear content
        }

        function hideProductPreview() {
            // Slightly delay hiding to allow for click-through if needed, or simply hide
            setTimeout(() => {
                 if (qrCodeInput.value.trim() === '') { // Only hide if input is cleared
                    productPreviewEl.classList.remove('show');
                 }
            }, 100);
            // submitBtn.disabled = true; // Re-evaluate disabling submit based on other logic
            // submitBtn.style.opacity = '0.5';
        }

        function showLoading() {
            loadingEl.style.display = 'block';
        }
        
        function hideLoading() {
            loadingEl.style.display = 'none';
        }
        
        function clearFormAndMessages() {
            scanForm.reset();
            hideProductPreviewInstantly();
            qrCodeInput.focus();
            submitBtn.disabled = false; // Reset submit button state
            submitBtn.style.opacity = '1';
            
            // Remove general message and scan result displays
            const generalMessage = document.querySelector('.message');
            if (generalMessage) generalMessage.remove();
            const scanResultDisplay = document.querySelector('.scan-result');
            if (scanResultDisplay) scanResultDisplay.remove();
        }

        // Utility to prevent XSS in JS-rendered HTML parts
        function htmlspecialchars(str) {
            if (typeof str !== 'string') return '';
            return str.replace(/[&<>"']/g, function (match) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[match];
            });
        }

        window.addEventListener('load', function() {
            qrCodeInput.focus();
            // If QR code field has a value on load (e.g. from POST after error), trigger check
            if (qrCodeInput.value.trim() !== '') {
                checkProductInfo();
            } else {
                 submitBtn.disabled = true; // Disable if no QR code on load
                 submitBtn.style.opacity = '0.5';
            }
        });
        
        scanForm.addEventListener('submit', function(e) {
            if (submitBtn.disabled) {
                e.preventDefault();
                alert('Cannot scan out this item. Please check the product information or ensure the item is in stock.');
            }
        });

        <?php if ($message && $error_class === 'success'): // Auto-clear only success messages ?>
            setTimeout(() => {
                const successMessage = document.querySelector('.message.success');
                if (successMessage) {
                    successMessage.style.transition = 'opacity 0.5s ease-out';
                    successMessage.style.opacity = '0';
                    setTimeout(() => successMessage.remove(), 500);
                }
                // Also auto-clear the scan_result block if it's a success
                const scanResultBlock = document.querySelector('.scan-result');
                 if (scanResultBlock) {
                    scanResultBlock.style.transition = 'opacity 0.5s ease-out';
                    scanResultBlock.style.opacity = '0';
                    setTimeout(() => scanResultBlock.remove(), 500);
                }
            }, 7000); // Keep success messages a bit longer
        <?php endif; ?>

    </script>
</body>
</html>