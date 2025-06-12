<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-Time Barcode Scanner</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .scanner-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .scanner-header {
            margin-bottom: 30px;
        }

        .scanner-header h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .scanner-header p {
            color: #666;
            font-size: 1.1em;
        }

        .camera-container {
            position: relative;
            margin: 20px 0;
            border-radius: 15px;
            overflow: hidden;
            background: #000;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        #scanner {
            width: 100%;
            height: 300px;
            border-radius: 15px;
        }

        #scanner canvas {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
        }

        #scanner video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
        }

        .scan-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 100px;
            border: 2px solid #00ff00;
            border-radius: 10px;
            pointer-events: none;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }

        .controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 20px 0;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #dee2e6;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .results-container {
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            border-left: 5px solid #667eea;
        }

        .result-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #28a745;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .result-code {
            font-family: 'Courier New', monospace;
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .result-format {
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status {
            padding: 10px 20px;
            border-radius: 20px;
            margin: 10px 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-scanning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 768px) {
            .scanner-container {
                padding: 20px;
                margin: 10px;
            }
            
            .scanner-header h1 {
                font-size: 2em;
            }
            
            #scanner {
                height: 250px;
            }
            
            .controls {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="scanner-container">
        <div class="scanner-header">
            <h1>📱 Barcode Scanner</h1>
            <p>Point your camera at a barcode to scan it instantly</p>
        </div>

        <div class="camera-container">
            <div id="scanner"></div>
            <div class="scan-overlay"></div>
        </div>

        <div class="controls">
            <button id="startBtn" class="btn btn-primary">Start Scanner</button>
            <button id="stopBtn" class="btn btn-secondary" disabled>Stop Scanner</button>
            <button id="clearBtn" class="btn btn-secondary">Clear Results</button>
        </div>

        <div id="status" class="status hidden"></div>

        <div id="results" class="results-container hidden">
            <h3>📊 Scan Results</h3>
            <div id="resultsList"></div>
        </div>
    </div>

    <script>
        class BarcodeScanner {
            constructor() {
                this.isScanning = false;
                this.scannedCodes = new Set(); // Prevent duplicates
                this.initializeElements();
                this.bindEvents();
            }

            initializeElements() {
                this.startBtn = document.getElementById('startBtn');
                this.stopBtn = document.getElementById('stopBtn');
                this.clearBtn = document.getElementById('clearBtn');
                this.status = document.getElementById('status');
                this.results = document.getElementById('results');
                this.resultsList = document.getElementById('resultsList');
            }

            bindEvents() {
                this.startBtn.addEventListener('click', () => this.startScanning());
                this.stopBtn.addEventListener('click', () => this.stopScanning());
                this.clearBtn.addEventListener('click', () => this.clearResults());
            }

            showStatus(message, type = 'scanning') {
                this.status.textContent = message;
                this.status.className = `status status-${type}`;
                this.status.classList.remove('hidden');
            }

            hideStatus() {
                this.status.classList.add('hidden');
            }

            async startScanning() {
                try {
                    this.showStatus('🔍 Initializing camera...', 'scanning');
                    
                    await Quagga.init({
                        inputStream: {
                            name: "Live",
                            type: "LiveStream",
                            target: document.querySelector('#scanner'),
                            constraints: {
                                width: 640,
                                height: 480,
                                facingMode: "environment" // Use back camera
                            }
                        },
                        locator: {
                            patchSize: "medium",
                            halfSample: true
                        },
                        numOfWorkers: 2,
                        frequency: 10,
                        decoder: {
                            readers: [
                                "code_128_reader",
                                "ean_reader",
                                "ean_8_reader",
                                "code_39_reader",
                                "code_39_vin_reader",
                                "codabar_reader",
                                "upc_reader",
                                "upc_e_reader",
                                "i2of5_reader"
                            ]
                        },
                        locate: true
                    });

                    Quagga.start();
                    this.isScanning = true;
                    
                    this.startBtn.disabled = true;
                    this.stopBtn.disabled = false;
                    
                    this.showStatus('📷 Scanner active - Point camera at barcode', 'scanning');

                    // Listen for successful scans
                    Quagga.onDetected(this.onBarcodeDetected.bind(this));

                } catch (error) {
                    console.error('Scanner initialization failed:', error);
                    this.showStatus('❌ Camera access failed. Please allow camera permissions.', 'error');
                    this.resetButtons();
                }
            }

            onBarcodeDetected(result) {
                const code = result.codeResult.code;
                const format = result.codeResult.format;
                
                // Prevent duplicate scans
                if (this.scannedCodes.has(code)) {
                    return;
                }
                
                this.scannedCodes.add(code);
                this.addResult(code, format);
                this.showStatus('✅ Barcode detected successfully!', 'success');
                
                // Send to PHP backend
                this.sendToBackend(code, format);
                
                // Auto-hide success message after 2 seconds
                setTimeout(() => {
                    if (this.isScanning) {
                        this.showStatus('📷 Scanner active - Point camera at barcode', 'scanning');
                    }
                }, 2000);
            }

            addResult(code, format) {
                const resultItem = document.createElement('div');
                resultItem.className = 'result-item';
                resultItem.innerHTML = `
                    <div class="result-code">${code}</div>
                    <div class="result-format">${format}</div>
                    <small style="color: #999;">Scanned at ${new Date().toLocaleTimeString()}</small>
                `;
                
                this.resultsList.insertBefore(resultItem, this.resultsList.firstChild);
                this.results.classList.remove('hidden');
            }

            async sendToBackend(code, format) {
                try {
                    // Create form data
                    const formData = new FormData();
                    formData.append('barcode', code);
                    formData.append('format', format);
                    formData.append('timestamp', new Date().toISOString());

                    // In a real implementation, you would send this to your PHP script
                    // For demo purposes, we'll just log it
                    console.log('Sending to PHP backend:', {
                        barcode: code,
                        format: format,
                        timestamp: new Date().toISOString()
                    });

                    // Simulate PHP response
                    this.simulatePhpResponse(code, format);

                } catch (error) {
                    console.error('Failed to send to backend:', error);
                }
            }

            simulatePhpResponse(code, format) {
                // Simulate what a PHP backend might return
                const response = {
                    status: 'success',
                    message: 'Barcode processed successfully',
                    data: {
                        barcode: code,
                        format: format,
                        processed_at: new Date().toISOString(),
                        // You could add product info lookup here
                        product_info: this.getProductInfo(code)
                    }
                };
                
                console.log('PHP Response:', response);
            }

            getProductInfo(code) {
                // Simulate product lookup
                const products = {
                    '123456789012': { name: 'Sample Product A', price: '$9.99' },
                    '987654321098': { name: 'Sample Product B', price: '$15.99' },
                };
                
                return products[code] || { name: 'Unknown Product', price: 'N/A' };
            }

            stopScanning() {
                if (this.isScanning) {
                    Quagga.stop();
                    this.isScanning = false;
                    this.resetButtons();
                    this.showStatus('⏹️ Scanner stopped', 'error');
                    
                    setTimeout(() => {
                        this.hideStatus();
                    }, 2000);
                }
            }

            resetButtons() {
                this.startBtn.disabled = false;
                this.stopBtn.disabled = true;
            }

            clearResults() {
                this.resultsList.innerHTML = '';
                this.results.classList.add('hidden');
                this.scannedCodes.clear();
                this.hideStatus();
            }
        }

        // Initialize scanner when page loads
        document.addEventListener('DOMContentLoaded', () => {
            new BarcodeScanner();
        });

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && window.scanner && window.scanner.isScanning) {
                window.scanner.stopScanning();
            }
        });
    </script>

    <!-- PHP Backend Code (save as barcode_handler.php) -->
    <script type="text/plain" id="php-code">
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = 'localhost';
$dbname = 'barcode_scanner';
$username = 'your_username';
$password = 'your_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = $_POST['barcode'] ?? '';
    $format = $_POST['format'] ?? '';
    $timestamp = $_POST['timestamp'] ?? date('Y-m-d H:i:s');
    
    if (empty($barcode)) {
        http_response_code(400);
        echo json_encode(['error' => 'Barcode is required']);
        exit;
    }
    
    try {
        // Insert scan record
        $stmt = $pdo->prepare("INSERT INTO scanned_barcodes (barcode, format, scanned_at, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$barcode, $format, $timestamp, $_SERVER['REMOTE_ADDR']]);
        
        // Get product information (if exists)
        $productStmt = $pdo->prepare("SELECT * FROM products WHERE barcode = ?");
        $productStmt->execute([$barcode]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        
        $response = [
            'status' => 'success',
            'message' => 'Barcode processed successfully',
            'data' => [
                'barcode' => $barcode,
                'format' => $format,
                'scanned_at' => $timestamp,
                'product' => $product ?: null
            ]
        ];
        
        echo json_encode($response);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process barcode']);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get recent scans
    try {
        $stmt = $pdo->prepare("SELECT * FROM scanned_barcodes ORDER BY scanned_at DESC LIMIT 50");
        $stmt->execute();
        $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'data' => $scans
        ]);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve scans']);
    }
}
?>
    </script>

</body>
</html>