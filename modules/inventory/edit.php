<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\inventory\edit.php
// Include header
include_once "../../includes/header.php";
include_once "../../includes/functions.php";

// Check if asset ID is provided
if(!isset($_GET['id']) || empty(trim($_GET['id']))) {
    // Redirect to the inventory page
    header("location: index.php");
    exit;
}

// Get asset ID from URL
$asset_id = trim($_GET['id']);

// Initialize variables
$asset_name = $description = $serial_number = $model = $manufacturer = "";
$purchase_date = $purchase_cost = $supplier = $warranty_expiry = "";
$category_id = $location_id = $condition_status = $status = "";
$error = $success = "";

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate asset name
    if(empty(trim($_POST["asset_name"]))) {
        $error = "Please enter an asset name.";
    } else {
        $asset_name = sanitize_input($conn, $_POST["asset_name"]);
    }
    
    // Get other form data
    $description = !empty($_POST["description"]) ? sanitize_input($conn, $_POST["description"]) : NULL;
    $serial_number = !empty($_POST["serial_number"]) ? sanitize_input($conn, $_POST["serial_number"]) : NULL;
    $model = !empty($_POST["model"]) ? sanitize_input($conn, $_POST["model"]) : NULL;
    $manufacturer = !empty($_POST["manufacturer"]) ? sanitize_input($conn, $_POST["manufacturer"]) : NULL;
    $purchase_date = !empty($_POST["purchase_date"]) ? sanitize_input($conn, $_POST["purchase_date"]) : NULL;
    $purchase_cost = !empty($_POST["purchase_cost"]) ? floatval($_POST["purchase_cost"]) : NULL;
    $supplier = !empty($_POST["supplier"]) ? sanitize_input($conn, $_POST["supplier"]) : NULL;
    $warranty_expiry = !empty($_POST["warranty_expiry"]) ? sanitize_input($conn, $_POST["warranty_expiry"]) : NULL;
    $category_id = !empty($_POST["category_id"]) ? intval($_POST["category_id"]) : NULL;
    $location_id = !empty($_POST["location_id"]) ? intval($_POST["location_id"]) : NULL;
    $condition_status = !empty($_POST["condition_status"]) ? sanitize_input($conn, $_POST["condition_status"]) : "new";
    $status = !empty($_POST["status"]) ? sanitize_input($conn, $_POST["status"]) : "available";
    
    // Check for errors before proceeding
    if(empty($error)) {
        // Prepare an update statement
        $sql = "UPDATE assets SET 
                asset_name=?, description=?, serial_number=?, model=?, manufacturer=?,
                purchase_date=?, purchase_cost=?, supplier=?, warranty_expiry=?, 
                status=?, condition_status=?, category_id=?, location_id=?, 
                updated_at=NOW()
                WHERE asset_id=?";
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssssssdssssiii", 
                $asset_name, $description, $serial_number, $model, $manufacturer,
                $purchase_date, $purchase_cost, $supplier, $warranty_expiry, 
                $status, $condition_status, $category_id, $location_id, $asset_id);
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)) {
                // Log the asset update
                log_action($conn, $asset_id, 'updated', $_SESSION['user_id'], 'Asset information updated');
                
                $success = "Asset successfully updated. <a href='view.php?id=" . $asset_id . "'>View Asset</a>";
            } else {
                $error = "Oops! Something went wrong. Please try again later.";
            }
            
            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch current asset data
$fetch_sql = "SELECT * FROM assets WHERE asset_id = ?";
if($fetch_stmt = mysqli_prepare($conn, $fetch_sql)) {
    mysqli_stmt_bind_param($fetch_stmt, "i", $asset_id);
    
    if(mysqli_stmt_execute($fetch_stmt)) {
        $result = mysqli_stmt_get_result($fetch_stmt);
        
        if(mysqli_num_rows($result) == 1) {
            $asset = mysqli_fetch_assoc($result);
            
            // Set form values
            $asset_name = $asset['asset_name'];
            $description = $asset['description'];
            $serial_number = $asset['serial_number'];
            $model = $asset['model'];
            $manufacturer = $asset['manufacturer'];
            $purchase_date = $asset['purchase_date'];
            $purchase_cost = $asset['purchase_cost'];
            $supplier = $asset['supplier'];
            $warranty_expiry = $asset['warranty_expiry'];
            $category_id = $asset['category_id'];
            $location_id = $asset['location_id'];
            $condition_status = $asset['condition_status'];
            $status = $asset['status'];
        } else {
            // Asset not found
            header("location: index.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
        exit;
    }
    
    mysqli_stmt_close($fetch_stmt);
} else {
    echo "Oops! Something went wrong. Please try again later.";
    exit;
}
?>

<div class="row mb-4">
    <div class="col-md-10">
        <h1><i class="fas fa-edit mr-2"></i>Edit Asset</h1>
        <p class="text-muted">Update asset information</p>
    </div>
    <div class="col-md-2 text-right">
        <a href="view.php?id=<?php echo $asset_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Details
        </a>
    </div>
</div>

<!-- Error/Success Messages -->
<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if(!empty($success)): ?>
<div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Asset Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>
        Asset Information
    </div>
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $asset_id); ?>" method="post" id="assetForm">
            
            <!-- General Information -->
            <h5 class="border-bottom pb-2 mb-3">General Information</h5>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="asset_name" class="required-field">Asset Name</label>
                    <input type="text" class="form-control" id="asset_name" name="asset_name" value="<?php echo htmlspecialchars($asset_name); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="category_id">Category</label>
                    <select class="form-control" id="category_id" name="category_id">
                        <?php echo get_category_options($conn, $category_id); ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                </div>
            </div>
            
            <!-- Technical Details -->
            <h5 class="border-bottom pb-2 mb-3 mt-4">Technical Details</h5>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="serial_number">Serial Number</label>
                    <input type="text" class="form-control" id="serial_number" name="serial_number" value="<?php echo htmlspecialchars($serial_number); ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="model">Model</label>
                    <input type="text" class="form-control" id="model" name="model" value="<?php echo htmlspecialchars($model); ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="manufacturer">Manufacturer</label>
                    <input type="text" class="form-control" id="manufacturer" name="manufacturer" value="<?php echo htmlspecialchars($manufacturer); ?>">
                </div>
            </div>
            
            <!-- Purchase Information -->
            <h5 class="border-bottom pb-2 mb-3 mt-4">Purchase Information</h5>
            
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="purchase_date">Purchase Date</label>
                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?php echo $purchase_date; ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="purchase_cost">Purchase Cost</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₱</span>
                        </div>
                        <input type="number" class="form-control" id="purchase_cost" name="purchase_cost" step="0.01" min="0" value="<?php echo $purchase_cost; ?>">
                    </div>
                </div>
                <div class="form-group col-md-3">
                    <label for="supplier">Supplier</label>
                    <input type="text" class="form-control" id="supplier" name="supplier" value="<?php echo htmlspecialchars($supplier); ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="warranty_expiry">Warranty Expiry</label>
                    <input type="date" class="form-control" id="warranty_expiry" name="warranty_expiry" value="<?php echo $warranty_expiry; ?>">
                </div>
            </div>
            
            <!-- Status and Location -->
            <h5 class="border-bottom pb-2 mb-3 mt-4">Status and Location</h5>
            
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="available" <?php echo ($status == 'available') ? 'selected' : ''; ?>>Available</option>
                        <option value="assigned" <?php echo ($status == 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                        <option value="under_repair" <?php echo ($status == 'under_repair') ? 'selected' : ''; ?>>Under Repair</option>
                        <option value="disposed" <?php echo ($status == 'disposed') ? 'selected' : ''; ?>>Disposed</option>
                        <option value="lost" <?php echo ($status == 'lost') ? 'selected' : ''; ?>>Lost</option>
                        <option value="stolen" <?php echo ($status == 'stolen') ? 'selected' : ''; ?>>Stolen</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="condition_status">Condition</label>
                    <select class="form-control" id="condition_status" name="condition_status">
                        <option value="new" <?php echo ($condition_status == 'new') ? 'selected' : ''; ?>>New</option>
                        <option value="good" <?php echo ($condition_status == 'good') ? 'selected' : ''; ?>>Good</option>
                        <option value="fair" <?php echo ($condition_status == 'fair') ? 'selected' : ''; ?>>Fair</option>
                        <option value="poor" <?php echo ($condition_status == 'poor') ? 'selected' : ''; ?>>Poor</option>
                        <option value="unusable" <?php echo ($condition_status == 'unusable') ? 'selected' : ''; ?>>Unusable</option>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="location_id">Location</label>
                    <select class="form-control" id="location_id" name="location_id">
                        <?php echo get_location_options($conn, $location_id); ?>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary mt-3">
                <i class="fas fa-save mr-1"></i> Update Asset
            </button>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Form validation
    $("#assetForm").validate({
        rules: {
            asset_name: {
                required: true,
                minlength: 2
            },
            purchase_cost: {
                number: true,
                min: 0
            }
        },
        messages: {
            asset_name: {
                required: "Please enter an asset name",
                minlength: "Asset name must be at least 2 characters"
            },
            purchase_cost: {
                number: "Please enter a valid amount",
                min: "Amount cannot be negative"
            }
        },
        errorElement: "div",
        errorClass: "invalid-feedback",
        highlight: function(element) {
            $(element).addClass("is-invalid");
        },
        unhighlight: function(element) {
            $(element).removeClass("is-invalid");
        },
        errorPlacement: function(error, element) {
            if(element.parent('.input-group').length) {
                error.insertAfter(element.parent());
            } else {
                error.insertAfter(element);
            }
        }
    });
});
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>