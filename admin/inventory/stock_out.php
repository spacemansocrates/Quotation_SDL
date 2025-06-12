<?php
session_start();
require_once 'classes/InventoryManager.php';
// Database.php is not strictly needed here if all interaction goes through InventoryManager

// --- User Authentication Placeholder ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
}
// --- End User Authentication Placeholder ---

$inventory_manager_instance = new InventoryManager(); // For JS to call via AJAX

// --- AJAX Handler for Stock Out ---
// This block will be called by the JavaScript after a successful scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'ajax_scan_out') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid request.'];

    $barcode = trim(filter_input(INPUT_POST, 'barcode', FILTER_UNSAFE_RAW));
    // Quantity will be handled by a separate input field still, or defaulted to 1 if not present
    $quantity_out = filter_input(INPUT_POST, 'quantity_out', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
    $notes = filter_input(INPUT_POST, 'notes_ajax', FILTER_SANITIZE_SPECIAL_CHARS); // Different name to avoid conflict
    $reference_id = filter_input(INPUT_POST, 'reference_id_ajax', FILTER_VALIDATE_INT);
    $reference_number = filter_input(INPUT_POST, 'reference_number_ajax', FILTER_SANITIZE_SPECIAL_CHARS);

    // Your existing validation for barcode format from previous stock_out.php
    define('VALID_BARCODE_PREFIX_PHP', 'PROD_'); // Define constants if not already globally available
    define('MIN_BARCODE_LENGTH_PHP', 7);
    define('MAX_BARCODE_LENGTH_PHP', 100);

    $is_potentially_valid_barcode = true;
    if (defined('VALID_BARCODE_PREFIX_PHP') && VALID_BARCODE_PREFIX_PHP && strpos($barcode, VALID_BARCODE_PREFIX_PHP) !== 0) {
        $is_potentially_valid_barcode = false;
        $response['message'] = "Error: Scanned item does not have the expected format (prefix missing).";
    } elseif (strlen($barcode) < MIN_BARCODE_LENGTH_PHP || strlen($barcode) > MAX_BARCODE_LENGTH_PHP) {
        $is_potentially_valid_barcode = false;
        $response['message'] = "Error: Scanned item length is unusual.";
    }

    if ($is_potentially_valid_barcode && $barcode && $quantity_out > 0) {
        $clean_barcode = filter_var($barcode, FILTER_SANITIZE_SPECIAL_CHARS);
        $inventory = new InventoryManager(); // Re-instantiate or use global instance
        $result = $inventory->removeStock(
            $clean_barcode,
            $quantity_out,
            $_SESSION['user_id'],
            'sale_camera_scan', // Differentiate reference type if needed
            $reference_id,
            $reference_number,
            $notes
        );

        if ($result['success']) {
            $response = [
                'success' => true,
                'message' => $result['message'],
                'product_name' => htmlspecialchars($result['product']['name']),
                'sku' => htmlspecialchars($result['product']['sku']),
                'removed_quantity' => htmlspecialchars($result['removed_quantity']),
                'new_stock' => $result['new_stock']
            ];
        } else {
            $response['message'] = "Scan Error: " . htmlspecialchars($result['error']);
        }
    } elseif ($is_potentially_valid_barcode) {
        $response['message'] = "Error: Barcode is missing or quantity is invalid for AJAX request.";
    }
    // If $is_potentially_valid_barcode was false, message is already set in $response

    echo json_encode($response);
    exit; // Important: stop script execution after AJAX response
}
// --- End AJAX Handler ---


// For initial page load message display (if any, e.g., from non-AJAX actions if you keep any)
$message = '';
$message_type = '';
$last_scan_result = null;

// --- Configuration for Barcode Filtering (can be passed to JS) ---
define('VALID_BARCODE_PREFIX', 'PROD_');
define('MIN_BARCODE_LENGTH', 7);
define('MAX_BARCODE_LENGTH', 100);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Out - Camera Scanner</title>
    <!-- QuaggaJS CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    <style>
/* Reset and base styles */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    color: #1e293b;
    min-height: 100vh;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Navbar */
.navbar {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    padding: 1rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    backdrop-filter: blur(8px);
    position: sticky;
    top: 0;
    z-index: 50;
}

.navbar a {
    color: #f1f5f9;
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    margin-right: 0.5rem;
    font-weight: 500;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-block;
}

.navbar a:hover {
    background: rgba(255, 255, 255, 0.1);
    text-decoration: none;
    transform: translateY(-1px);
}

/* User info */
.user-info {
    padding: 0.75rem 1rem;
    text-align: right;
    font-size: 0.875rem;
    color: #64748b;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid #e2e8f0;
}

.user-info strong {
    color: #1e293b;
    font-weight: 600;
}

/* Main container - mobile first */
.main-container {
    padding: 1rem;
    gap: 1rem;
    display: flex;
    flex-direction: column;
    max-width: 100%;
}

/* Scanner column */
.scanner-column {
    background: #ffffff;
    border-radius: 1rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    padding: 1.5rem;
    border: 1px solid #e2e8f0;
    order: 1;
}

/* Form column */
.form-column {
    background: #ffffff;
    border-radius: 1rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    padding: 1.5rem;
    border: 1px solid #e2e8f0;
    order: 2;
}

/* Scanner header */
.scanner-header {
    text-align: center;
    margin-bottom: 1.5rem;
}

.scanner-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.scanner-header p {
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Camera container */
.camera-container {
    position: relative;
    width: 100%;
    max-width: 100%;
    margin: 0 auto 1.5rem;
    border: 2px solid #e2e8f0;
    border-radius: 1rem;
    overflow: hidden;
    aspect-ratio: 4 / 3;
    background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

#scanner video, #scanner canvas {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
    border-radius: 0.875rem;
}

.scan-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 80%;
    height: 30%;
    border: 3px dashed #3b82f6;
    border-radius: 0.5rem;
    transform: translate(-50%, -50%);
    pointer-events: none;
    animation: pulse 2s infinite;
    background: rgba(59, 130, 246, 0.05);
}

@keyframes pulse {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}

canvas.drawingBuffer {
    display: none;
}

/* Controls */
.controls {
    display: flex;
    justify-content: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.75rem;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    min-width: 120px;
    text-align: center;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3), 0 4px 6px -2px rgba(59, 130, 246, 0.1);
}

.btn-secondary {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    color: white;
}

.btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(100, 116, 139, 0.3), 0 4px 6px -2px rgba(100, 116, 139, 0.1);
}

.btn:disabled {
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    color: #94a3b8;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn:disabled::before {
    display: none;
}

/* Status messages */
.status {
    padding: 1rem;
    margin-top: 1rem;
    border-radius: 0.75rem;
    text-align: center;
    font-size: 0.9rem;
    font-weight: 500;
    backdrop-filter: blur(8px);
    transition: all 0.3s ease;
}

.status.hidden {
    display: none;
}

.status-scanning {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(29, 78, 216, 0.1) 100%);
    color: #1d4ed8;
    border: 2px solid rgba(59, 130, 246, 0.2);
}

.status-success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(21, 128, 61, 0.1) 100%);
    color: #15803d;
    border: 2px solid rgba(34, 197, 94, 0.2);
}

.status-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(185, 28, 28, 0.1) 100%);
    color: #b91c1c;
    border: 2px solid rgba(239, 68, 68, 0.2);
}

/* Form elements */
.form-column h2 {
    font-size: 1.5rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.form-column label {
    display: block;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.9rem;
    color: #374151;
}

.form-column input[type="text"],
.form-column input[type="number"],
.form-column textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 0.75rem;
    font-size: 0.9rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    background: #ffffff;
    color: #1f2937;
}

.form-column input[type="text"]:focus,
.form-column input[type="number"]:focus,
.form-column textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    transform: translateY(-1px);
}

#scanned_barcode_display {
    font-weight: 700;
    color: #1d4ed8;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    padding: 1rem;
    border-radius: 0.75rem;
    text-align: center;
    margin-bottom: 1rem;
    min-height: 3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #e2e8f0;
    font-family: 'Courier New', monospace;
    letter-spacing: 0.5px;
}

/* Process button styling */
#manualProcessBtn {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    width: 100%;
    margin-top: 1.5rem;
    padding: 1rem;
    font-size: 1rem;
    font-weight: 700;
}

#manualProcessBtn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3), 0 4px 6px -2px rgba(16, 185, 129, 0.1);
}

/* Results section */
#results {
    margin-top: 1.5rem;
}

#results h3 {
    text-align: center;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 1rem;
}

.results-container.hidden {
    display: none;
}

.result-item {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    padding: 1rem;
    margin-bottom: 0.75rem;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

.result-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.result-code {
    font-weight: 700;
    font-size: 1rem;
    color: #1d4ed8;
    font-family: 'Courier New', monospace;
    margin-bottom: 0.5rem;
}

.result-format {
    color: #64748b;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.result-product-info {
    background: rgba(34, 197, 94, 0.1);
    padding: 0.75rem;
    border-radius: 0.5rem;
    color: #15803d;
    font-weight: 500;
    border-left: 4px solid #10b981;
    margin-top: 0.5rem;
}

.result-error {
    background: rgba(239, 68, 68, 0.1);
    padding: 0.75rem;
    border-radius: 0.5rem;
    color: #b91c1c;
    font-weight: 500;
    border-left: 4px solid #ef4444;
    margin-top: 0.5rem;
}

#clearProcessedBtn {
    margin-top: 1rem;
    width: 100%;
}

/* Tablet and desktop styles */
@media (min-width: 768px) {
    .main-container {
        flex-direction: row;
        padding: 2rem;
        gap: 2rem;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .scanner-column {
        flex: 2;
        order: 0;
    }
    
    .form-column {
        flex: 1;
        order: 0;
    }
    
    .camera-container {
        max-width: 600px;
    }
    
    .controls {
        gap: 1rem;
    }
    
    .btn {
        min-width: 140px;
    }
}

@media (min-width: 1024px) {
    .scanner-header h1 {
        font-size: 2rem;
    }
    
    .form-column h2 {
        font-size: 1.75rem;
    }
    
    .camera-container {
        max-width: 700px;
    }
}

/* Mobile navbar improvements */
@media (max-width: 767px) {
    .navbar {
        padding: 0.75rem;
    }
    
    .navbar a {
        padding: 0.5rem 0.75rem;
        margin-right: 0.25rem;
        font-size: 0.875rem;
    }
    
    .user-info {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .scanner-header h1 {
        font-size: 1.5rem;
    }
    
    .form-column h2 {
        font-size: 1.25rem;
    }
}
    </style>
</head>
<body>
    <div class="navbar">
        <a href="index.php">Dashboard</a>
        <a href="stock_in.php">Stock In</a>
        <a href="stock_out.php">Stock Out</a>
        <a href="stock_report.php">Stock Report</a>
    </div>
    <div class="user-info">
        Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
    </div>

    <div class="main-container">
        <div class="scanner-column">
            <div class="scanner-header">
                <h1><span style="font-size: 1.5em;">📷</span> Camera Barcode Scanner</h1>
                <p>Point your camera at a barcode to scan it.</p>
            </div>
            <div class="camera-container">
                <div id="scanner"></div>
                <div class="scan-overlay"></div>
            </div>
            <div class="controls">
                <button id="startBtn" class="btn btn-primary">Start Scanner</button>
                <button id="stopBtn" class="btn btn-secondary" disabled>Stop Scanner</button>
            </div>
            <div id="status" class="status hidden"></div>
            <div id="results" class="results-container hidden">
                <h3>Processed Scans</h3>
                <div id="resultsList"></div>
                 <button id="clearProcessedBtn" class="btn btn-secondary" style="margin-top:10px; width: auto;">Clear Processed List</button>
            </div>
        </div>

        <div class="form-column">
            <h2>Stock Out Details</h2>
            <label for="scanned_barcode_display">Scanned Barcode:</label>
            <div id="scanned_barcode_display">(No barcode scanned yet)</div>

            <label for="quantity_out">Quantity to Scan Out:</label>
            <input type="number" id="quantity_out" name="quantity_out" min="1" value="1" required>

            <label for="reference_id_out">Reference ID (Optional):</label>
            <input type="number" name="reference_id_ajax" id="reference_id_out" placeholder="e.g., Order ID">

            <label for="reference_number_out">Reference Number (Optional):</label>
            <input type="text" name="reference_number_ajax" id="reference_number_out" placeholder="e.g., Invoice/Ref">

            <label for="notes_out">Notes (Optional):</label>
            <textarea name="notes_ajax" id="notes_out" placeholder="Additional notes for this stock out"></textarea>

            <button id="manualProcessBtn" class="btn btn-primary" style="background-color: #28a745; width:100%; margin-top:15px;" disabled>Process Scanned Barcode</button>
            <p style="font-size:0.8em; text-align:center; margin-top:5px;">(Scanned barcodes will be processed with these details)</p>
        </div>
    </div>

    <script>
        // Pass PHP defined constants to JavaScript
        const JS_VALID_BARCODE_PREFIX = "<?php echo defined('VALID_BARCODE_PREFIX') ? VALID_BARCODE_PREFIX : ''; ?>";
        const JS_MIN_BARCODE_LENGTH = <?php echo defined('MIN_BARCODE_LENGTH') ? MIN_BARCODE_LENGTH : 7; ?>;
        const JS_MAX_BARCODE_LENGTH = <?php echo defined('MAX_BARCODE_LENGTH') ? MAX_BARCODE_LENGTH : 100; ?>;

        class BarcodeScannerApp {
            constructor() {
                this.isScanning = false;
                this.lastScannedCode = null; // Store the most recently scanned valid code
                this.initializeElements();
                this.bindEvents();
            }

            initializeElements() {
                this.startBtn = document.getElementById('startBtn');
                this.stopBtn = document.getElementById('stopBtn');
                this.statusDiv = document.getElementById('status'); // Renamed from 'status' to avoid conflict
                this.resultsContainer = document.getElementById('results'); // Renamed from 'results'
                this.resultsList = document.getElementById('resultsList');
                this.scannedBarcodeDisplay = document.getElementById('scanned_barcode_display');
                this.quantityInput = document.getElementById('quantity_out');
                this.manualProcessBtn = document.getElementById('manualProcessBtn');
                this.clearProcessedBtn = document.getElementById('clearProcessedBtn');
                this.refIdInput = document.getElementById('reference_id_out');
                this.refNoInput = document.getElementById('reference_number_out');
                this.notesInput = document.getElementById('notes_out');
            }

            bindEvents() {
                this.startBtn.addEventListener('click', () => this.startScanning());
                this.stopBtn.addEventListener('click', () => this.stopScanning());
                this.manualProcessBtn.addEventListener('click', () => this.processLastScannedCode());
                this.clearProcessedBtn.addEventListener('click', () => this.clearProcessedResults());
            }

            showStatus(message, type = 'scanning') {
                this.statusDiv.textContent = message;
                this.statusDiv.className = `status status-${type}`; // Ensure class is set correctly
                this.statusDiv.classList.remove('hidden');
            }

            hideStatus() {
                this.statusDiv.classList.add('hidden');
            }

            async startScanning() {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    this.showStatus('❌ Camera access not supported by your browser.', 'error');
                    return;
                }
                try {
                    this.showStatus('🔍 Initializing camera...', 'scanning');
                    this.lastScannedCode = null; // Reset last scanned code
                    this.scannedBarcodeDisplay.textContent = "(Scanning...)";
                    this.manualProcessBtn.disabled = true;

                    await Quagga.init({
                        inputStream: {
                            name: "Live", type: "LiveStream", target: document.querySelector('#scanner'),
                            constraints: { width: {min: 640}, height: {min: 480}, facingMode: "environment", aspectRatio: {ideal: 1.7777777778} }
                        },
                        locator: { patchSize: "medium", halfSample: true },
                        numOfWorkers: navigator.hardwareConcurrency || 2, // Use available cores
                        frequency: 10, // Scan attempt frequency
                        decoder: { readers: ["code_128_reader", "ean_reader", "upc_reader", "code_39_reader"] }, // Common readers
                        locate: true
                    }, (err) => {
                        if (err) {
                            console.error('Quagga initialization failed:', err);
                            this.showStatus('❌ Camera initialization failed. ' + err.message, 'error');
                            this.resetButtons();
                            return;
                        }
                        Quagga.start();
                        this.isScanning = true;
                        this.startBtn.disabled = true;
                        this.stopBtn.disabled = false;
                        this.showStatus('📷 Scanner active - Point camera at barcode', 'scanning');
                        Quagga.onDetected(this.onBarcodeDetected.bind(this));
                        Quagga.onProcessed(this.onProcessed.bind(this)); // For drawing detection box
                    });
                } catch (error) {
                    console.error('Scanner start failed:', error);
                    this.showStatus('❌ Camera access failed. Please allow camera permissions.', 'error');
                    this.resetButtons();
                }
            }

            onProcessed(result) {
                const drawingCtx = Quagga.canvas.ctx.overlay;
                const drawingCanvas = Quagga.canvas.dom.overlay;

                drawingCtx.clearRect(0, 0, parseInt(drawingCanvas.getAttribute("width")), parseInt(drawingCanvas.getAttribute("height")));

                if (result) {
                    if (result.boxes) {
                        result.boxes.filter(box => box !== result.box).forEach(box => {
                            Quagga.ImageDebug.drawPath(box, {x: 0, y: 1}, drawingCtx, {color: "green", lineWidth: 2});
                        });
                    }
                    if (result.box) {
                        Quagga.ImageDebug.drawPath(result.box, {x: 0, y: 1}, drawingCtx, {color: "blue", lineWidth: 2});
                    }
                    if (result.codeResult && result.codeResult.code) {
                        // Quagga.ImageDebug.drawPath(result.line, {x: 'x', y: 'y'}, drawingCtx, {color: 'red', lineWidth: 3});
                    }
                }
            }

            onBarcodeDetected(result) {
                const code = result.codeResult.code;
                
                // Basic client-side validation
                if (JS_VALID_BARCODE_PREFIX && !code.startsWith(JS_VALID_BARCODE_PREFIX)) {
                    this.showStatus(`⚠️ Invalid Prefix: ${code}. Expected ${JS_VALID_BARCODE_PREFIX}...`, 'error');
                    setTimeout(() => { if(this.isScanning) this.showStatus('📷 Scanner active...', 'scanning');}, 2000);
                    return;
                }
                if (code.length < JS_MIN_BARCODE_LENGTH || code.length > JS_MAX_BARCODE_LENGTH) {
                     this.showStatus(`⚠️ Invalid Length: ${code}. Expected ${JS_MIN_BARCODE_LENGTH}-${JS_MAX_BARCODE_LENGTH} chars.`, 'error');
                     setTimeout(() => { if(this.isScanning) this.showStatus('📷 Scanner active...', 'scanning');}, 2000);
                    return;
                }

                this.showStatus(`✅ Barcode: ${code}`, 'success');
                this.lastScannedCode = code;
                this.scannedBarcodeDisplay.textContent = code;
                this.manualProcessBtn.disabled = false;
                
                // Optional: auto-stop scanner after a successful scan
                // this.stopScanning(); 
                // Or just provide feedback and wait for manual "Process" button click

                // To auto-process: (uncomment if desired)
                // this.processLastScannedCode();
            }

            async processLastScannedCode() {
                if (!this.lastScannedCode) {
                    alert("No barcode has been scanned yet, or the last scan was invalid.");
                    return;
                }

                this.manualProcessBtn.disabled = true; // Prevent multiple clicks
                this.showStatus(`⏳ Processing ${this.lastScannedCode}...`, 'scanning');

                const formData = new FormData();
                formData.append('action', 'ajax_scan_out');
                formData.append('barcode', this.lastScannedCode);
                formData.append('quantity_out', this.quantityInput.value);
                formData.append('notes_ajax', this.notesInput.value);
                formData.append('reference_id_ajax', this.refIdInput.value);
                formData.append('reference_number_ajax', this.refNoInput.value);

                try {
                    const response = await fetch('stock_out.php', { // Post to the same page
                        method: 'POST',
                        body: formData
                    });
                    const resultData = await response.json();

                    if (resultData.success) {
                        this.addProcessedResult(this.lastScannedCode, 'Processed', resultData);
                        this.showStatus(`✔️ ${resultData.message}`, 'success');
                        this.lastScannedCode = null; // Clear after successful processing
                        this.scannedBarcodeDisplay.textContent = "(Ready for next scan)";
                    } else {
                        this.addProcessedResult(this.lastScannedCode, 'Error', resultData);
                        this.showStatus(`❌ Error: ${resultData.message}`, 'error');
                        this.manualProcessBtn.disabled = false; // Re-enable on error
                    }
                } catch (error) {
                    console.error('Error processing barcode:', error);
                    this.addProcessedResult(this.lastScannedCode, 'Error', { message: 'Network or server error during processing.' });
                    this.showStatus('❌ Network error. Could not process.', 'error');
                    this.manualProcessBtn.disabled = false; // Re-enable on error
                }
                setTimeout(() => {
                    if (this.isScanning) this.showStatus('📷 Scanner active - Point camera at barcode', 'scanning');
                    else if (!this.lastScannedCode) this.hideStatus(); // Hide if no code pending
                }, 3000);
            }

            addProcessedResult(code, statusText, serverResponse) {
                const resultItem = document.createElement('div');
                resultItem.className = 'result-item';
                let html = `
                    <div class="result-code">${code}</div>
                    <div class="result-format">Status: ${statusText} at ${new Date().toLocaleTimeString()}</div>`;

                if (serverResponse.success) {
                    html += `<div class="result-product-info">
                                Product: ${serverResponse.product_name || 'N/A'} (SKU: ${serverResponse.sku || 'N/A'})<br>
                                Removed: ${serverResponse.removed_quantity || 'N/A'}, New Stock: ${serverResponse.new_stock !== undefined ? serverResponse.new_stock : 'N/A'}
                             </div>`;
                } else {
                    html += `<div class="result-error">Message: ${serverResponse.message || 'Unknown error'}</div>`;
                }
                resultItem.innerHTML = html;
                this.resultsList.insertBefore(resultItem, this.resultsList.firstChild);
                this.resultsContainer.classList.remove('hidden');
            }
            
            clearProcessedResults() {
                this.resultsList.innerHTML = '';
                this.resultsContainer.classList.add('hidden');
            }

            stopScanning() {
                if (this.isScanning) {
                    Quagga.stop();
                    this.isScanning = false;
                    this.resetButtons();
                    this.showStatus('⏹️ Scanner stopped.', 'error');
                    setTimeout(() => { this.hideStatus(); }, 2000);
                }
            }

            resetButtons() {
                this.startBtn.disabled = false;
                this.stopBtn.disabled = true;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            window.barcodeScannerApp = new BarcodeScannerApp(); // Make it globally accessible if needed for debugging
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden && window.barcodeScannerApp && window.barcodeScannerApp.isScanning) {
                window.barcodeScannerApp.stopScanning();
            }
        });
    </script>
</body>
</html>