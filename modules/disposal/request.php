<?php
include_once "../../includes/header.php";

// Get available assets
$assets_query = "SELECT a.asset_id, a.asset_name, a.asset_tag, a.status, 
                 c.category_name, l.building, l.room
                 FROM assets a
                 LEFT JOIN categories c ON a.category_id = c.category_id
                 LEFT JOIN locations l ON a.location_id = l.location_id
                 WHERE a.status != 'disposed'
                 ORDER BY a.asset_name";
$assets_result = mysqli_query($conn, $assets_query);

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $error = "";
    
    // Validate inputs
    if(empty($_POST["asset_id"])) {
        $error = "Please select an asset.";
    }
    if(empty(trim($_POST["reason"]))) {
        $error = "Please provide a reason for disposal.";
    }
    
    if(empty($error)) {
        $sql = "INSERT INTO disposal_requests (asset_id, requested_by, reason, status, request_date) 
                VALUES (?, ?, ?, 'pending', NOW())";
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iis", 
                $_POST["asset_id"],
                $_SESSION["user_id"],
                $_POST["reason"]
            );
            
            if(mysqli_stmt_execute($stmt)) {
                header("location: index.php");
                exit();
            } else {
                $error = "Something went wrong. Please try again later.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-trash-alt mr-2"></i>New Disposal Request</h1>
        <p class="text-muted">Request disposal for an asset</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </div>
</div>

<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>Disposal Request Details
    </div>
    <div class="card-body">
        <form method="post" id="disposalForm">
            <div class="form-group">
                <label for="asset_id" class="required-field">Select Asset</label>
                <select class="form-control" id="asset_id" name="asset_id" required>
                    <option value="">-- Select Asset --</option>
                    <?php while($asset = mysqli_fetch_assoc($assets_result)): ?>
                        <option value="<?php echo $asset['asset_id']; ?>">
                            <?php 
                            echo htmlspecialchars($asset['asset_name']) . 
                                 ' (' . htmlspecialchars($asset['asset_tag']) . ')';
                            if(!empty($asset['category_name'])) {
                                echo ' - ' . htmlspecialchars($asset['category_name']);
                            }
                            ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div id="assetDetails" class="card bg-light mb-3 d-none">
                <div class="card-body" id="assetDetailsContent">
                    <!-- Asset details will be loaded here -->
                </div>
            </div>
            
            <div class="form-group">
                <label for="reason" class="required-field">Reason for Disposal</label>
                <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                <small class="form-text text-muted">
                    Please provide a detailed explanation for why this asset needs to be disposed of.
                </small>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane mr-1"></i>Submit Request
            </button>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Load asset details when an asset is selected
    $('#asset_id').change(function() {
        const assetId = $(this).val();
        if(assetId) {
            $.get('get_asset_details.php', {id: assetId}, function(data) {
                $('#assetDetailsContent').html(data);
                $('#assetDetails').removeClass('d-none');
            });
        } else {
            $('#assetDetails').addClass('d-none');
        }
    });
    
    // Form validation
    $("#disposalForm").validate({
        rules: {
            asset_id: "required",
            reason: {
                required: true,
                minlength: 10
            }
        },
        messages: {
            asset_id: "Please select an asset",
            reason: {
                required: "Please provide a reason for disposal",
                minlength: "Please provide a more detailed explanation"
            }
        },
        errorElement: "div",
        errorClass: "invalid-feedback",
        highlight: function(element) {
            $(element).addClass("is-invalid");
        },
        unhighlight: function(element) {
            $(element).removeClass("is-invalid");
        }
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>