<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\assignments\get_asset_details.php
// Include database configuration
require_once "../../config/database.php";

// Check if asset ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">No asset ID provided</div>';
    exit;
}

// Get asset ID
$asset_id = intval($_GET['id']);

// Fetch asset details
$sql = "SELECT a.*, c.category_name, l.building, l.room
        FROM assets a
        LEFT JOIN categories c ON a.category_id = c.category_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        WHERE a.asset_id = ?";

if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $asset_id);
    
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) == 1) {
            $asset = mysqli_fetch_assoc($result);
            
            // Output asset details
            echo '<div class="row">';
            
            // Left column
            echo '<div class="col-md-6">';
            echo '<p><strong>Asset Name:</strong><br> ' . htmlspecialchars($asset['asset_name']) . '</p>';
            
            if(!empty($asset['asset_tag'])) {
                echo '<p><strong>Asset Tag:</strong><br> ' . htmlspecialchars($asset['asset_tag']) . '</p>';
            }
            
            if(!empty($asset['serial_number'])) {
                echo '<p><strong>Serial Number:</strong><br> ' . htmlspecialchars($asset['serial_number']) . '</p>';
            }
            
            echo '<p><strong>Category:</strong><br> ' . 
                 (!empty($asset['category_name']) ? htmlspecialchars($asset['category_name']) : 'Uncategorized') . '</p>';
            echo '</div>';
            
            // Right column
            echo '<div class="col-md-6">';
            echo '<p><strong>Location:</strong><br> ';
            if(!empty($asset['building'])) {
                echo htmlspecialchars($asset['building']);
                if(!empty($asset['room'])) {
                    echo ' - ' . htmlspecialchars($asset['room']);
                }
            } else {
                echo 'Not specified';
            }
            echo '</p>';
            
            echo '<p><strong>Condition:</strong><br> ' . ucfirst($asset['condition_status']) . '</p>';
            
            if(!empty($asset['purchase_date'])) {
                echo '<p><strong>Purchase Date:</strong><br> ' . date('M d, Y', strtotime($asset['purchase_date'])) . '</p>';
            }
            
            echo '</div>';
            echo '</div>';
            
        } else {
            echo '<div class="alert alert-danger">Asset not found</div>';
        }
    } else {
        echo '<div class="alert alert-danger">Error executing query</div>';
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo '<div class="alert alert-danger">Error preparing query</div>';
}

mysqli_close($conn);
?>