<?php
// File: modules/tagging/print_tag.php

// Include database configuration
require_once "../../config/database.php";
require_once "../../includes/functions.php";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Access denied. Please log in.");
}

// Check permission
if (!has_permission('manage_assets')) {
    die("Access denied. You don't have permission to print asset tags.");
}

// Check if asset ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid asset ID.");
}

$asset_id = $_GET['id'];

// Get asset information
$asset_query = "SELECT a.*, c.category_name
                FROM assets a
                LEFT JOIN categories c ON a.category_id = c.category_id
                WHERE a.asset_id = ?";
$stmt = mysqli_prepare($conn, $asset_query);
mysqli_stmt_bind_param($stmt, "i", $asset_id);
mysqli_stmt_execute($stmt);
$asset_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($asset_result) == 0) {
    die("Asset not found.");
}

$asset = mysqli_fetch_assoc($asset_result);

// Check if asset has a tag
if (empty($asset['asset_tag'])) {
    die("This asset does not have a tag assigned. Please generate a tag first.");
}

// Log the print action
if (function_exists('log_asset_action')) {
    log_asset_action($conn, $asset_id, 'updated', $_SESSION['user_id'], 'Asset tag printed');
} else if (function_exists('log_action')) {
    log_action($conn, $asset_id, 'updated', $_SESSION['user_id'], 'Asset tag printed');
}

// Create QR code text - make sure it's not empty
$qr_text = !empty($asset['qr_code']) ? $asset['qr_code'] : "asset:{$asset_id}:{$asset['asset_tag']}";
$barcode_text = $asset['barcode'] ?? $asset['asset_tag'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Asset Tag - <?php echo htmlspecialchars($asset['asset_tag']); ?></title>
    <style>
        /* Reset & Page setup */
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 10pt;
        }
        
        /* Print specific styles */
        @page {
            size: 89mm 36mm;  /* Standard asset tag size (3.5" x 1.4") */
            margin: 0;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .page-break {
                page-break-after: always;
            }
        }
        
        /* Tag container */
        .tag-container {
            width: 89mm;
            height: 36mm;
            border: 1px dashed #ccc;
            box-sizing: border-box;
            padding: 3mm;
            position: relative;
            margin: 20px auto;
        }
        
        /* Organization logo & name */
        .org-name {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 1mm;
            text-align: center;
        }
        
        /* Asset info */
        .asset-info {
            margin-bottom: 2mm;
            font-size: 8pt;
        }
        
        /* Code container */
        .code-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2mm;
        }
        
        /* QR code */
        .qr-code {
            width: 20mm;
            height: 20mm;
        }
        
        /* Asset tag */
        .asset-tag {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin-top: 1mm;
        }
        
        /* Barcode */
        .barcode {
            width: 50mm;
            height: 10mm;
            margin: 0 auto;
            text-align: center;
        }
        
        /* Print instructions */
        .print-instructions {
            max-width: 500px;
            margin: 20px auto;
            padding: 15px;
            background-color: #f0f0f0;
            border-radius: 5px;
            font-size: 14px;
        }
        
        /* Print button */
        .print-button {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            text-align: center;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            font-size: 14px;
            text-decoration: none;
        }
        
        .print-button:hover {
            background-color: #45a049;
        }
    </style>
    <!-- Include Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <!-- Print Instructions -->
    <div class="print-instructions no-print">
        <h3>Print Asset Tag</h3>
        <p>This page is formatted to print on standard asset tag labels (3.5" x 1.4" or 89mm x 36mm).</p>
        <p><strong>Printing Tips:</strong></p>
        <ul>
            <li>Use a label printer if available</li>
            <li>Set your printer to "Actual size" (not "Fit to page")</li>
            <li>Disable headers and footers in your browser's print settings</li>
            <li>For best results, use adhesive label paper</li>
        </ul>
        <button type="button" class="print-button" onclick="window.print();">Print Asset Tag</button>
        <a href="index.php" class="print-button" style="background-color: #6c757d;">Back to Asset Tagging</a>
    </div>
    
    <!-- Asset Tag -->
    <div class="tag-container">
        <div class="org-name">ASSET MANAGEMENT SYSTEM</div>
        
        <div class="asset-info">
            <strong>Name:</strong> <?php echo htmlspecialchars($asset['asset_name']); ?><br>
            <strong>Category:</strong> <?php echo htmlspecialchars($asset['category_name'] ?? 'Uncategorized'); ?>
        </div>
        
        <div class="code-container">
            <!-- QR Code container -->
            <div id="qrcode" class="qr-code"></div>
            
            <div style="flex-grow: 1; text-align: center; padding-top: 5mm;">
                <!-- Asset ID -->
                <div style="font-size: 8pt; margin-bottom: 2mm;">ID: <?php echo $asset_id; ?></div>
                
                <!-- Asset Tag -->
                <div class="asset-tag"><?php echo htmlspecialchars($asset['asset_tag']); ?></div>
            </div>
        </div>
        
        <!-- Barcode - using JsBarcode -->
        <div class="barcode">
            <svg id="barcode"></svg>
        </div>
    </div>
    
    <script>
        // Generate QR code and barcode when the page loads
        window.onload = function() {
            // Generate QR code
            new QRCode(document.getElementById("qrcode"), {
                text: "<?php echo addslashes($qr_text); ?>",
                width: 75,
                height: 75,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H  // High error correction
            });
            
            // Generate barcode
            JsBarcode("#barcode", "<?php echo addslashes($barcode_text); ?>", {
                format: "CODE128",
                lineColor: "#000",
                width: 2,
                height: 40,
                displayValue: true,
                fontSize: 12,
                textMargin: 2
            });
        };

        // Log tag printing via AJAX (optional)
        window.addEventListener('afterprint', function() {
            // Can add AJAX call here to log the print action
            console.log('Asset tag printed');
        });
    </script>
</body>
</html>