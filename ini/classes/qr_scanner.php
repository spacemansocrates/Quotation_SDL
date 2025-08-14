<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Scanner</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qr-scanner/1.4.2/qr-scanner.umd.min.js"></script>
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
            padding: 20px;
        }

        .scanner-container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .scanner-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .scanner-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .scanner-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .camera-container {
            position: relative;
            background: #000;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #scanner {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .scan-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 250px;
            height: 250px;
            border: 3px solid #00f2fe;
            border-radius: 20px;
            background: rgba(0, 242, 254, 0.1);
            animation: pulse 2s infinite;
        }

        .scan-overlay::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            animation: rotate 3s linear infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.6; transform: translate(-50%, -50%) scale(1); }
            50% { opacity: 1; transform: translate(-50%, -50%) scale(1.05); }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .controls {
            padding: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #333;
            box-shadow: 0 4px 15px rgba(255, 154, 158, 0.4);
        }

        .btn-secondary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 154, 158, 0.6);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .status {
            margin: 0 30px;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .status-scanning {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .status-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: white;
        }

        .status-error {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: white;
        }

        .results-container {
            margin: 0 30px 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            border: 2px solid #e9ecef;
        }

        .results-container h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3rem;
            text-align: center;
        }

        .result-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            border-left: 4px solid #4facfe;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease;
        }

        .result-qr {
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            color: #333;
            word-break: break-all;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .result-type {
            background: #4facfe;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            display: inline-block;
            margin-bottom: 5px;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .hidden {
            display: none;
        }

        .camera-placeholder {
            color: white;
            text-align: center;
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .scanner-header h1 {
                font-size: 2rem;
            }
            
            .camera-container {
                height: 300px;
            }
            
            .scan-overlay {
                width: 200px;
                height: 200px;
            }
            
            .controls {
                padding: 20px;
                gap: 10px;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="scanner-container">
        <div class="scanner-header">
            <h1>📱 QR Code Scanner</h1>
            <p>Point your camera at a QR code to scan it instantly</p>
        </div>

        <div class="camera-container">
            <video id="scanner" muted></video>
            <div class="scan-overlay"></div>
            <div id="placeholder" class="camera-placeholder">
                <p>📷 Camera not started</p>
                <p>Click "Start Scanner" to begin</p>
            </div>
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
        class QRCodeScanner {
            constructor() {
                this.isScanning = false;
                this.scannedCodes = new Set(); // Prevent duplicates
                this.qrScanner = null;
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
                this.videoElement = document.getElementById('scanner');
                this.placeholder = document.getElementById('placeholder');
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
                    
                    // Hide placeholder
                    this.placeholder.style.display = 'none';
                    
                    // Initialize QR Scanner
                    this.qrScanner = new QrScanner(
                        this.videoElement,
                        result => this.onQRCodeDetected(result),
                        {
                            returnDetailedScanResult: true,
                            highlightScanRegion: false,
                            highlightCodeOutline: true,
                            preferredCamera: 'environment' // Use back camera
                        }
                    );

                    await this.qrScanner.start();
                    this.isScanning = true;
                    
                    this.startBtn.disabled = true;
                    this.stopBtn.disabled = false;
                    
                    this.showStatus('📷 Scanner active - Point camera at QR code', 'scanning');

                } catch (error) {
                    console.error('Scanner initialization failed:', error);
                    this.showStatus('❌ Camera access failed. Please allow camera permissions.', 'error');
                    this.resetButtons();
                    this.placeholder.style.display = 'block';
                }
            }

            onQRCodeDetected(result) {
                const qrData = result.data;
                const qrType = this.detectQRType(qrData);
                
                // Prevent duplicate scans
                if (this.scannedCodes.has(qrData)) {
                    return;
                }
                
                this.scannedCodes.add(qrData);
                this.addResult(qrData, qrType);
                this.showStatus('✅ QR code detected successfully!', 'success');
                
                // Send to PHP backend
                this.sendToBackend(qrData, qrType);
                
                // Auto-hide success message after 2 seconds
                setTimeout(() => {
                    if (this.isScanning) {
                        this.showStatus('📷 Scanner active - Point camera at QR code', 'scanning');
                    }
                }, 2000);
            }

            detectQRType(data) {
                if (data.startsWith('http://') || data.startsWith('https://')) {
                    return 'URL';
                } else if (data.startsWith('mailto:')) {
                    return 'Email';
                } else if (data.startsWith('tel:')) {
                    return 'Phone';
                } else if (data.startsWith('sms:')) {
                    return 'SMS';
                } else if (data.includes('WIFI:')) {
                    return 'WiFi';
                } else if (data.includes('MECARD:') || data.includes('VCARD:')) {
                    return 'Contact';
                } else if (data.match(/^[0-9]+$/)) {
                    return 'Number';
                } else {
                    return 'Text';
                }
            }

            addResult(qrData, qrType) {
                const resultItem = document.createElement('div');
                resultItem.className = 'result-item';
                
                // Create clickable content for URLs
                let displayContent = qrData;
                if (qrType === 'URL') {
                    displayContent = `<a href="${qrData}" target="_blank" style="color: #4facfe; text-decoration: none;">${qrData}</a>`;
                } else if (qrType === 'Email') {
                    displayContent = `<a href="${qrData}" style="color: #4facfe; text-decoration: none;">${qrData}</a>`;
                } else if (qrType === 'Phone') {
                    displayContent = `<a href="${qrData}" style="color: #4facfe; text-decoration: none;">${qrData}</a>`;
                }
                
                resultItem.innerHTML = `
                    <div class="result-type">${qrType}</div>
                    <div class="result-qr">${displayContent}</div>
                    <small style="color: #999;">Scanned at ${new Date().toLocaleTimeString()}</small>
                `;
                
                this.resultsList.insertBefore(resultItem, this.resultsList.firstChild);
                this.results.classList.remove('hidden');
            }

            async sendToBackend(qrData, qrType) {
                try {
                    // Create form data
                    const formData = new FormData();
                    formData.append('qr_data', qrData);
                    formData.append('qr_type', qrType);
                    formData.append('timestamp', new Date().toISOString());

                    // In a real implementation, you would send this to your PHP script
                    // For demo purposes, we'll just log it
                    console.log('Sending to PHP backend:', {
                        qr_data: qrData,
                        qr_type: qrType,
                        timestamp: new Date().toISOString()
                    });

                    // Simulate PHP response
                    this.simulatePhpResponse(qrData, qrType);

                } catch (error) {
                    console.error('Failed to send to backend:', error);
                }
            }

            simulatePhpResponse(qrData, qrType) {
                // Simulate what a PHP backend might return
                const response = {
                    status: 'success',
                    message: 'QR code processed successfully',
                    data: {
                        qr_data: qrData,
                        qr_type: qrType,
                        processed_at: new Date().toISOString(),
                        additional_info: this.getAdditionalInfo(qrData, qrType)
                    }
                };
                
                console.log('PHP Response:', response);
            }

            getAdditionalInfo(qrData, qrType) {
                // Provide additional context based on QR type
                switch (qrType) {
                    case 'URL':
                        try {
                            const url = new URL(qrData);
                            return { domain: url.hostname, protocol: url.protocol };
                        } catch {
                            return { note: 'Invalid URL format' };
                        }
                    case 'Email':
                        return { type: 'Email contact', action: 'Click to compose email' };
                    case 'Phone':
                        return { type: 'Phone number', action: 'Click to call' };
                    case 'WiFi':
                        return { type: 'WiFi credentials', action: 'Network configuration data' };
                    default:
                        return { length: qrData.length, encoding: 'UTF-8' };
                }
            }

            stopScanning() {
                if (this.isScanning && this.qrScanner) {
                    this.qrScanner.stop();
                    this.qrScanner = null;
                    this.isScanning = false;
                    this.resetButtons();
                    this.placeholder.style.display = 'block';
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
            window.qrScanner = new QRCodeScanner();
        });

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && window.qrScanner && window.qrScanner.isScanning) {
                window.qrScanner.stopScanning();
            }
        });
    </script>

    <!-- PHP Backend Code (save as qr_handler.php) -->
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
$dbname = 'qr_scanner';
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
    $qr_data = $_POST['qr_data'] ?? '';
    $qr_type = $_POST['qr_type'] ?? '';
    $timestamp = $_POST['timestamp'] ?? date('Y-m-d H:i:s');
    
    if (empty($qr_data)) {
        http_response_code(400);
        echo json_encode(['error' => 'QR data is required']);
        exit;
    }
    
    try {
        // Insert scan record
        $stmt = $pdo->prepare("INSERT INTO scanned_qrcodes (qr_data, qr_type, scanned_at, ip_address, data_length) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$qr_data, $qr_type, $timestamp, $_SERVER['REMOTE_ADDR'], strlen($qr_data)]);
        
        // Analyze QR data for additional insights
        $analysis = analyzeQRData($qr_data, $qr_type);
        
        $response = [
            'status' => 'success',
            'message' => 'QR code processed successfully',
            'data' => [
                'qr_data' => $qr_data,
                'qr_type' => $qr_type,
                'scanned_at' => $timestamp,
                'data_length' => strlen($qr_data),
                'analysis' => $analysis
            ]
        ];
        
        echo json_encode($response);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process QR code']);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get recent scans with statistics
    try {
        $stmt = $pdo->prepare("SELECT * FROM scanned_qrcodes ORDER BY scanned_at DESC LIMIT 50");
        $stmt->execute();
        $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get statistics
        $statsStmt = $pdo->prepare("
            SELECT 
                qr_type, 
                COUNT(*) as count,
                AVG(data_length) as avg_length
            FROM scanned_qrcodes 
            WHERE scanned_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY qr_type
        ");
        $statsStmt->execute();
        $stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'recent_scans' => $scans,
                'daily_stats' => $stats
            ]
        ]);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve scans']);
    }
}

function analyzeQRData($data, $type) {
    $analysis = ['type' => $type];
    
    switch($type) {
        case 'URL':
            $parsed = parse_url($data);
            $analysis['domain'] = $parsed['host'] ?? 'unknown';
            $analysis['secure'] = strpos($data, 'https://') === 0;
            break;
            
        case 'Email':
            $analysis['email'] = str_replace('mailto:', '', $data);
            break;
            
        case 'Phone':
            $analysis['number'] = str_replace('tel:', '', $data);
            break;
            
        case 'WiFi':
            // Parse WiFi QR format: WIFI:T:WPA;S:MyNetwork;P:MyPassword;H:false;
            if (preg_match('/WIFI:T:([^;]+);S:([^;]+);P:([^;]*);/', $data, $matches)) {
                $analysis['security'] = $matches[1];
                $analysis['ssid'] = $matches[2];
                $analysis['has_password'] = !empty($matches[3]);
            }
            break;
            
        default:
            $analysis['length'] = strlen($data);
            $analysis['encoding'] = mb_detect_encoding($data) ?: 'ASCII';
    }
    
    return $analysis;
}

/*
Database schema for MySQL:

CREATE TABLE scanned_qrcodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qr_data TEXT NOT NULL,
    qr_type VARCHAR(50) NOT NULL,
    data_length INT NOT NULL,
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    INDEX idx_scanned_at (scanned_at),
    INDEX idx_qr_type (qr_type)
);
*/
?>
    </script>

</body>
</html>