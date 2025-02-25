<?php
// File: modules/tagging/generate_tag.php
include_once "../../includes/header.php";

// Check permission
if(!has_permission('manage_assets')) {
    $_SESSION['error'] = "Access denied. You don't have permission to generate asset tags.";
    header("Location: ../../index.php");
    exit;
}

// Check if asset ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid asset ID.";
    header("Location: index.php");
    exit;
}

$asset_id = $_GET['id'];

// Get asset information
$asset_query = "SELECT a.*, c.category_name, l.building, l.room
                FROM assets a
                LEFT JOIN categories c ON a.category_id = c.category_id
                LEFT JOIN locations l ON a.location_id = l.location_id
                WHERE a.asset_id = ?";
$stmt = mysqli_prepare($conn, $asset_query);
mysqli_stmt_bind_param($stmt, "i", $asset_id);
mysqli_stmt_execute($stmt);
$asset_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($asset_result) == 0) {
    $_SESSION['error'] = "Asset not found.";
    header("Location: index.php");
    exit;
}

$asset = mysqli_fetch_assoc($asset_result);

// Check if asset already has a tag
$has_tag = !empty($asset['asset_tag']);

// Generate new tag if needed
if(isset($_POST['action']) && $_POST['action'] == 'generate') {
    // Generate a new asset tag
    $new_tag = generate_asset_tag($conn);
    
    // Update the asset with the new tag
    $update_query = "UPDATE assets SET asset_tag = ?, qr_code = ?, barcode = ? WHERE asset_id = ?";
    $qr_code = "asset:" . $asset_id . ":" . $new_tag; // Format: asset:id:tag
    $barcode = $new_tag; // Use the tag as barcode value
    
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "sssi", $new_tag, $qr_code, $barcode, $asset_id);
    $result = mysqli_stmt_execute($stmt);
    
    if($result) {
        // Log the action
        if(function_exists('log_asset_action')) {
            log_asset_action($conn, $asset_id, 'updated', $_SESSION['user_id'], 'Asset automatically tagged with: ' . $new_tag);
        } else if(function_exists('log_action')) {
            log_action($conn, $asset_id, 'updated', $_SESSION['user_id'], 'Asset automatically tagged with: ' . $new_tag);
        }
        
        $_SESSION['success'] = "Asset tag generated successfully.";
        header("Location: generate_tag.php?id=$asset_id");
        exit;
    } else {
        $_SESSION['error'] = "Error generating asset tag: " . mysqli_error($conn);
    }
}

// Create QR code text - make sure it's not empty
$qr_text = !empty($asset['qr_code']) ? $asset['qr_code'] : "asset:{$asset_id}:{$asset['asset_tag']}";
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-tag mr-2"></i>Generate Asset Tag</h1>
        <p class="text-muted">Generate a unique tag, barcode, and QR code for this asset</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Asset Tagging
        </a>
    </div>
</div>

<!-- Asset Information -->
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>Asset Information
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th width="30%">Asset ID:</th>
                        <td><?php echo $asset_id; ?></td>
                    </tr>
                    <tr>
                        <th>Name:</th>
                        <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?php echo htmlspecialchars($asset['category_name'] ?? 'Uncategorized'); ?></td>
                    </tr>
                    <tr>
                        <th>Model:</th>
                        <td>
                            <?php 
                            echo htmlspecialchars($asset['manufacturer'] . ' ' . $asset['model']); 
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Serial Number:</th>
                        <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                    </tr>
                    <tr>
                        <th>Location:</th>
                        <td>
                            <?php 
                            if($asset['location_id']) {
                                echo htmlspecialchars($asset['building']);
                                if(!empty($asset['room'])) {
                                    echo ' - ' . htmlspecialchars($asset['room']);
                                }
                            } else {
                                echo 'Not assigned';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td><?php echo get_status_badge($asset['status']); ?></td>
                    </tr>
                    <tr>
                        <th>Current Tag:</th>
                        <td>
                            <?php 
                            if($has_tag) {
                                echo htmlspecialchars($asset['asset_tag']);
                            } else {
                                echo '<span class="text-danger">No tag assigned</span>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                
                <?php if(!$has_tag): ?>
                    <form method="post" class="mt-3">
                        <input type="hidden" name="action" value="generate">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-tag mr-2"></i>Generate New Asset Tag
                        </button>
                    </form>
                <?php else: ?>
                    <form method="post" class="mt-3">
                        <input type="hidden" name="action" value="generate">
                        <button type="submit" class="btn btn-warning btn-block" 
                                onclick="return confirm('This will replace the existing tag. Continue?');">
                            <i class="fas fa-sync-alt mr-2"></i>Regenerate Asset Tag
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <?php if($has_tag): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-qrcode mr-1"></i>Tag Preview
                </div>
                <div class="card-body text-center">
                    <!-- QR Code -->
                    <div class="mb-4">
                        <h5>QR Code</h5>
                        <!-- Create direct QR code image with fallback -->
                        <?php
                        // URL-encode the QR text for safety
                        $qr_url = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . rawurlencode($qr_text) . "&choe=UTF-8";
                        ?>
                        <img src="<?php echo $qr_url; ?>" alt="QR Code" class="img-fluid" style="max-width: 200px;">
                    </div>
                    
                    <!-- Barcode -->
                    <div class="mb-4">
                        <h5>Barcode</h5>
                        <img src="generate_barcode.php?text=<?php echo urlencode($asset['barcode'] ?? $asset['asset_tag']); ?>" 
                             alt="Barcode" class="img-fluid" style="max-width: 200px;">
                    </div>
                    
                    <!-- Tag ID -->
                    <div class="mb-4">
                        <h5>Asset Tag</h5>
                        <div class="h2"><?php echo htmlspecialchars($asset['asset_tag']); ?></div>
                    </div>
                    
                    <hr>
                    
                    <div class="mt-3">
                        <a href="print_tag.php?id=<?php echo $asset_id; ?>" class="btn btn-success btn-lg" target="_blank">
                            <i class="fas fa-print mr-2"></i>Print Tag
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-qrcode mr-1"></i>Tag Preview
                </div>
                <div class="card-body text-center">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        This asset does not have a tag assigned. Please generate a new tag first.
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once "../../includes/footer.php"; ?>