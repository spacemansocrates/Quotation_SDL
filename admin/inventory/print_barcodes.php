<?php
session_start();

// Redirect if no print data is found in session
if (!isset($_SESSION['print_data']) || empty($_SESSION['print_data'])) {
    // Perhaps set a message for stock_in.php to display
    // $_SESSION['message'] = ['type' => 'error', 'text' => 'No barcode data to print. Please generate barcodes first.'];
    header('Location: stock_in.php');
    exit;
}

$data = $_SESSION['print_data'];

// Clear the session data immediately after retrieving it to prevent re-printing the same batch
// by simply refreshing the page after printing.
unset($_SESSION['print_data']);

$product_name = isset($data['product']['name']) ? htmlspecialchars($data['product']['name']) : 'N/A';
$product_sku = isset($data['product']['sku']) ? htmlspecialchars($data['product']['sku']) : 'N/A';
$barcode_content = isset($data['barcode_content']) ? htmlspecialchars($data['barcode_content']) : 'N/A';
$barcode_image_base64 = isset($data['barcode_image_base64']) ? $data['barcode_image_base64'] : '';
$quantity_to_print = isset($data['quantity']) ? (int)$data['quantity'] : 0;
$batch_reference = isset($data['batch_reference']) ? htmlspecialchars($data['batch_reference']) : 'N/A';

// If essential data is missing, redirect or show error
if (empty($barcode_image_base64) || $quantity_to_print <= 0) {
    // Log this error for debugging
    error_log("Print_barcodes.php: Missing essential data. Product: $product_name, Qty: $quantity_to_print, Img empty: " . empty($barcode_image_base64));
    // $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Essential barcode data is missing. Cannot print.'];
    header('Location: stock_in.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Barcodes - <?php echo $product_name; ?></title>
    <style>
        /* General Body Styles (for screen view mostly) */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0; /* Light grey background for screen */
        }

        /* Controls for screen view (no-print) */
        .no-print-controls {
            position: fixed; /* Or 'sticky' if you prefer */
            top: 0;
            left: 0;
            width: 100%;
            background-color: #333;
            padding: 10px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
        }
        .no-print-controls button,
        .no-print-controls a {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .no-print-controls button:hover,
        .no-print-controls a:hover {
            background-color: #0056b3;
        }
        .no-print-controls .back-button {
            background-color: #6c757d;
        }
        .no-print-controls .back-button:hover {
            background-color: #545b62;
        }

        /* Page Title (visible on screen, hidden in print by default rule) */
        .page-title-screen {
            text-align: center;
            margin-top: 80px; /* Account for fixed controls */
            margin-bottom: 20px;
            font-size: 1.8em;
            color: #333;
        }

        /* Grid container for barcode items */
        .barcode-grid-container {
            display: grid;
            /* Adjust minmax for desired label size. e.g., 3 inches wide, 1.5 inches tall */
            /* These are rough estimates, printer DPI and settings matter. */
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); /* ~3 labels across an A4/Letter page */
            gap: 10px; /* Minimal gap, adjust based on label sheets */
            padding: 20px; /* Padding for screen view */
            margin-top: 60px; /* Space for fixed controls */
        }

        /* Individual barcode item style */
        .barcode-item {
            background-color: #fff; /* White background for each label */
            border: 1px dashed #ccc; /* Dashed border to visualize label, can be removed for actual label sheets */
            padding: 10px;
            text-align: center;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: center; /* Center content vertically */
            align-items: center;     /* Center content horizontally */
            overflow: hidden;        /* Prevent content spill */
            /* Define a fixed aspect ratio or height if needed for consistent label sizes */
            min-height: 120px; /* Example minimum height */
        }

        .barcode-item img {
            max-width: 95%;     /* Ensure barcode fits within padding */
            height: 50px;       /* Standard height for barcode image, adjust as needed */
            object-fit: contain;/* Scales down to fit, maintains aspect ratio */
            margin-bottom: 8px; /* Space between barcode and text */
        }

        .product-info {
            font-size: 12px; /* Adjust for readability on small labels */
            line-height: 1.3;
            color: #000; /* Black text for high contrast */
            width: 100%;
            word-wrap: break-word; /* Break long words */
        }
        .product-info .product-name {
            font-weight: bold;
            font-size: 1.1em; /* Slightly larger for product name */
            display: block;
            margin-bottom: 3px;
        }
        .product-info .detail {
            font-size: 0.9em;
            display: block;
        }


        /* Print Specific Styles */
        @media print {
            body {
                background-color: #fff; /* White background for printing */
                margin: 0.25in; /* Standard print margin, adjust as needed */
                padding: 0;
            }
            .no-print-controls, .page-title-screen {
                display: none !important; /* Hide controls and screen title when printing */
            }
            .barcode-grid-container {
                margin: 0;
                padding: 0;
                gap: 2mm; /* Smaller gap for printing on label sheets, adjust precisely */
                /* If printing on specific label sheets, you might need to adjust columns to match.
                   For example, if your sheet is 3 labels across:
                   grid-template-columns: 1fr 1fr 1fr;
                */
            }
            .barcode-item {
                border: 1px solid #000; /* Make border solid black for cutting guides, or 'none' if using pre-cut labels */
                padding: 5mm; /* Adjust padding for print */
                page-break-inside: avoid !important; /* Try to keep each item on one page */
                /* You might need to set fixed width/height here if printing on specific label sizes
                   e.g., width: 3in; height: 1.5in; (use cm or mm too)
                   Ensure box-sizing: border-box; is active or account for padding/border.
                */
                min-height: 0; /* Reset min-height for print if fixed sizes are used */
            }
            .barcode-item img {
                height: 1.0cm; /* Example fixed height in cm for print */
                /* For optimal scanning, ensure barcode lines are crisp.
                   Avoid scaling up the image too much if the source resolution from the generator is low.
                   The Picqer generator should produce good quality PNGs. */
            }
            .product-info {
                font-size: 8pt; /* Common print font size, adjust */
                line-height: 1.2;
            }
            .product-info .product-name {
                font-size: 10pt;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-controls">
        <button onclick="window.print();" title="Open Print Dialog">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 8px;">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            Print Barcodes
        </button>
        <a href="stock_in.php" class="back-button" title="Go back to Stock In page">
             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16" style="margin-right: 8px;">
                <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
            </svg>
            Back to Stock In
        </a>
    </div>

    <h1 class="page-title-screen">
        Print Preview: <?php echo $quantity_to_print; ?> Barcode(s) for <?php echo $product_name; ?>
    </h1>
    <p class="page-title-screen" style="font-size: 0.9em; margin-top: -15px;">Batch: <?php echo $batch_reference; ?></p>


    <div class="barcode-grid-container">
        <?php for ($i = 0; $i < $quantity_to_print; $i++): ?>
            <div class="barcode-item">
                <img src="data:image/png;base64,<?php echo $barcode_image_base64; ?>" alt="Barcode for <?php echo $product_name; ?>">
                <div class="product-info">
                    <span class="product-name"><?php echo $product_name; ?></span>
                    <?php if ($product_sku !== 'N/A' && !empty($product_sku)): ?>
                        <span class="detail">SKU: <?php echo $product_sku; ?></span>
                    <?php endif; ?>
                    <span class="detail">Code: <?php echo $barcode_content; ?></span>
                    <?php // You can add more info like price, expiry date if available in $data ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <script>
        // Optional: Automatically trigger print dialog on page load
        // window.onload = function() {
        //     window.print();
        // };
    </script>

</body>
</html>