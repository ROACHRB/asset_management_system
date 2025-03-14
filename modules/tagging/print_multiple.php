<?php
// File: modules/tagging/print_multiple.php

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

// Debug incoming parameters
error_log('Batch print request received with IDs: ' . (isset($_GET['ids']) ? $_GET['ids'] : 'none'));

// Process asset IDs
$asset_ids = [];
if (isset($_GET['ids']) && !empty($_GET['ids'])) {
    $ids_param = $_GET['ids'];
    $asset_ids = explode(',', $ids_param);
    
    // Clean and validate IDs
    foreach ($asset_ids as $key => $id) {
        $id = trim($id);
        if (!is_numeric($id)) {
            error_log('Removing non-numeric ID: ' . $id);
            unset($asset_ids[$key]);
        } else {
            $asset_ids[$key] = (int)$id;
        }
    }
    
    // Reindex array
    $asset_ids = array_values($asset_ids);
    error_log('Valid asset IDs: ' . implode(',', $asset_ids) . ' (Count: ' . count($asset_ids) . ')');
}

// Get assets information - DIRECT QUERY APPROACH
$assets = [];
if (!empty($asset_ids)) {
    // Simple approach with direct query
    $ids_string = implode(',', $asset_ids); // Safe because we validated all IDs are numeric integers
    
    $assets_query = "SELECT a.*, c.category_name
                    FROM assets a
                    LEFT JOIN categories c ON a.category_id = c.category_id
                    WHERE a.asset_id IN ($ids_string)
                    ORDER BY a.asset_tag";
    
    error_log('Executing query: ' . $assets_query);
    $result = mysqli_query($conn, $assets_query);
    
    if ($result) {
        while ($asset = mysqli_fetch_assoc($result)) {
            $assets[] = $asset;
            error_log('Found asset: ID=' . $asset['asset_id'] . ', Tag=' . ($asset['asset_tag'] ?? 'No Tag'));
            
            // Log the print action
            if (function_exists('log_asset_action')) {
                log_asset_action($conn, $asset['asset_id'], 'updated', $_SESSION['user_id'], 'Asset tag printed (batch)');
            } else if (function_exists('log_action')) {
                log_action($conn, $asset['asset_id'], 'updated', $_SESSION['user_id'], 'Asset tag printed (batch)');
            }
        }
    } else {
        error_log('Query failed: ' . mysqli_error($conn));
    }
}

error_log('Total assets found: ' . count($assets));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Multiple Asset Tags</title>
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
        
        .print-button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <!-- Print Instructions -->
    <div class="print-instructions no-print">
        <h3>Print Multiple Asset Tags</h3>
        <?php if (empty($assets)): ?>
            <p>No assets selected or no tags available for printing.</p>
        <?php else: ?>
            <p>Ready to print <?php echo count($assets); ?> asset tags.</p>
            <p><strong>Printing Tips:</strong></p>
            <ul>
                <li>Use a label printer if available</li>
                <li>Set your printer to "Actual size" (not "Fit to page")</li>
                <li>Disable headers and footers in your browser's print settings</li>
                <li>For best results, use adhesive label paper</li>
            </ul>
        <?php endif; ?>
        <button type="button" class="print-button" onclick="window.print();" <?php echo empty($assets) ? 'disabled' : ''; ?>>
            Print <?php echo count($assets); ?> Asset Tags
        </button>
        <a href="index.php" class="print-button" style="background-color: #6c757d;">Back to Asset Tagging</a>
    </div>
    
    <!-- Asset Tags -->
    <?php foreach ($assets as $index => $asset): ?>
        <?php 
        // Ensure we have valid data for tags, QR codes, and barcodes
        $asset_tag = !empty($asset['asset_tag']) ? $asset['asset_tag'] : 'ID:' . $asset['asset_id'];
        $qr_code_data = !empty($asset['qr_code']) ? $asset['qr_code'] : $asset_tag;
        $barcode_data = !empty($asset['barcode']) ? $asset['barcode'] : $asset_tag;
        ?>
        <div class="tag-container <?php echo ($index < count($assets) - 1) ? 'page-break' : ''; ?>">
            <div class="org-name">ASSET MANAGEMENT SYSTEM</div>
            
            <div class="asset-info">
                <strong>Name:</strong> <?php echo htmlspecialchars($asset['asset_name']); ?><br>
                <strong>Category:</strong> <?php echo htmlspecialchars($asset['category_name'] ?? 'Uncategorized'); ?>
            </div>
            
            <div class="code-container">
                <!-- QR Code using Google Chart API -->
                <img src="https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=<?php echo urlencode($qr_code_data); ?>&choe=UTF-8" 
                     alt="QR Code" class="qr-code">
                
                <div style="flex-grow: 1; text-align: center; padding-top: 5mm;">
                    <!-- Asset ID -->
                    <div style="font-size: 8pt; margin-bottom: 2mm;">ID: <?php echo $asset['asset_id']; ?></div>
                    
                    <!-- Asset Tag -->
                    <div class="asset-tag"><?php echo htmlspecialchars($asset_tag); ?></div>
                </div>
            </div>
            
            <!-- Barcode -->
            <img src="generate_barcode.php?text=<?php echo urlencode($barcode_data); ?>" alt="Barcode" class="barcode">
        </div>
    <?php endforeach; ?>
    
    <?php if (empty($assets)): ?>
        <div style="text-align: center; margin-top: 40px;">
            <h3>No assets selected or no tags available for printing.</h3>
            <p>Please go back and select assets with valid tags.</p>
        </div>
    <?php endif; ?>
    
    <script>
        // Log tag printing and add some debugging
        console.log("print_multiple.php loaded with <?php echo count($assets); ?> assets");
        <?php foreach ($assets as $asset): ?>
        console.log("Asset: ID=<?php echo $asset['asset_id']; ?>, Tag=<?php echo htmlspecialchars($asset['asset_tag'] ?? 'No Tag'); ?>");
        <?php endforeach; ?>
        
        window.addEventListener('afterprint', function() {
            console.log('Multiple asset tags printed');
        });
    </script>
</body>
</html>