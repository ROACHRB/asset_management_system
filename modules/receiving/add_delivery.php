<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\receiving\add_delivery.php
// Include header
include_once "../../includes/header.php";
include_once "../../includes/functions.php";

// Initialize variables
$delivery_date = date('Y-m-d');
$supplier = $reference_number = $notes = "";
$items = [];
$error = $success = "";

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate delivery date
    if(empty(trim($_POST["delivery_date"]))) {
        $error = "Please enter a delivery date.";
    } else {
        $delivery_date = sanitize_input($conn, $_POST["delivery_date"]);
    }
    
    // Validate supplier
    if(empty(trim($_POST["supplier"]))) {
        $error = "Please enter a supplier name.";
    } else {
        $supplier = sanitize_input($conn, $_POST["supplier"]);
    }
    
    // Get other form data
    $reference_number = !empty($_POST["reference_number"]) ? sanitize_input($conn, $_POST["reference_number"]) : NULL;
    $notes = !empty($_POST["notes"]) ? sanitize_input($conn, $_POST["notes"]) : NULL;
    
    // Validate items (at least one item is required)
    if(empty($_POST["item_name"]) || !is_array($_POST["item_name"]) || count($_POST["item_name"]) < 1) {
        $error = "Please add at least one item to the delivery.";
    } else {
        // Collect items data
        for($i = 0; $i < count($_POST["item_name"]); $i++) {
            if(!empty($_POST["item_name"][$i])) {
                $items[] = [
                    'name' => sanitize_input($conn, $_POST["item_name"][$i]),
                    'description' => !empty($_POST["item_description"][$i]) ? sanitize_input($conn, $_POST["item_description"][$i]) : NULL,
                    'quantity' => !empty($_POST["item_quantity"][$i]) ? intval($_POST["item_quantity"][$i]) : 1,
                    'unit_cost' => !empty($_POST["item_cost"][$i]) ? floatval($_POST["item_cost"][$i]) : NULL,
                    'category_id' => !empty($_POST["item_category"][$i]) ? intval($_POST["item_category"][$i]) : NULL
                ];
            }
        }
        
        if(count($items) < 1) {
            $error = "Please add at least one valid item to the delivery.";
        }
    }
    
    // Check for errors before proceeding
    if(empty($error)) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert delivery record
            $delivery_sql = "INSERT INTO deliveries (delivery_date, supplier, reference_number, received_by, notes, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())";
            
            $delivery_stmt = mysqli_prepare($conn, $delivery_sql);
            mysqli_stmt_bind_param($delivery_stmt, "sssss", 
                $delivery_date, $supplier, $reference_number, $_SESSION["user_id"], $notes);
            
            if(!mysqli_stmt_execute($delivery_stmt)) {
                throw new Exception("Error inserting delivery: " . mysqli_stmt_error($delivery_stmt));
            }
            
            // Get the inserted delivery ID
            $delivery_id = mysqli_insert_id($conn);
            
            // Insert delivery items
            $item_sql = "INSERT INTO delivery_items (delivery_id, item_name, description, quantity, unit_cost, category_id, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            
            $item_stmt = mysqli_prepare($conn, $item_sql);
            
            foreach($items as $item) {
                mysqli_stmt_bind_param($item_stmt, "issidi", 
                    $delivery_id, $item['name'], $item['description'], $item['quantity'], $item['unit_cost'], $item['category_id']);
                
                if(!mysqli_stmt_execute($item_stmt)) {
                    throw new Exception("Error inserting item: " . mysqli_stmt_error($item_stmt));
                }
            }
            
            // All queries successful, commit transaction
            mysqli_commit($conn);
            
            // Set success message
            $success = "Delivery successfully recorded. <a href='view_delivery.php?id=" . $delivery_id . "'>View Delivery</a> or <a href='process_items.php?id=" . $delivery_id . "'>Process Items</a>";
            
            // Reset form data
            $delivery_date = date('Y-m-d');
            $supplier = $reference_number = $notes = "";
            $items = [];
            
        } catch (Exception $e) {
            // An error occurred, rollback transaction
            mysqli_rollback($conn);
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-10">
        <h1><i class="fas fa-truck mr-2"></i>Record New Delivery</h1>
        <p class="text-muted">Enter details about received items</p>
    </div>
    <div class="col-md-2 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
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

<!-- Delivery Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>
        Delivery Information
    </div>
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="deliveryForm">
            
            <!-- Delivery Details -->
            <h5 class="border-bottom pb-2 mb-3">Delivery Details</h5>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="delivery_date" class="required-field">Delivery Date</label>
                    <input type="date" class="form-control" id="delivery_date" name="delivery_date" 
                           value="<?php echo $delivery_date; ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="supplier" class="required-field">Supplier</label>
                    <input type="text" class="form-control" id="supplier" name="supplier" 
                           value="<?php echo htmlspecialchars($supplier); ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="reference_number">Reference Number</label>
                    <input type="text" class="form-control" id="reference_number" name="reference_number"
                           value="<?php echo htmlspecialchars($reference_number); ?>" 
                           placeholder="PO/Invoice/Delivery Number">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2"
                              placeholder="Any additional information about this delivery"><?php echo htmlspecialchars($notes); ?></textarea>
                </div>
            </div>
            
            <!-- Delivery Items -->
            <h5 class="border-bottom pb-2 mb-3 mt-4">Delivery Items</h5>
            
            <div id="itemsContainer">
                <!-- Initial item row (template) -->
                <div class="item-row mb-3 pb-3 border-bottom">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="required-field">Item Name</label>
                            <input type="text" class="form-control item-name" name="item_name[]" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Category</label>
                            <select class="form-control item-category" name="item_category[]">
                                <?php echo get_category_options($conn); ?>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label>Quantity</label>
                            <input type="number" class="form-control item-quantity" name="item_quantity[]" 
                                   min="1" value="1">
                        </div>
                        <div class="form-group col-md-2">
                            <label>Unit Cost ($)</label>
                            <input type="number" class="form-control item-cost" name="item_cost[]" 
                                   min="0" step="0.01">
                        </div>
                        <div class="form-group col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-danger remove-item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <label>Description</label>
                            <textarea class="form-control item-description" name="item_description[]" rows="1"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mb-4">
                <button type="button" id="addItemBtn" class="btn btn-info">
                    <i class="fas fa-plus mr-1"></i>Add Another Item
                </button>
            </div>
            
            <button type="submit" class="btn btn-primary mt-3">
                <i class="fas fa-save mr-1"></i> Save Delivery
            </button>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle "Add Item" button
    $('#addItemBtn').click(function() {
        const newItem = $('.item-row:first').clone();
        newItem.find('input, textarea, select').val(''); // Clear values
        newItem.find('.item-quantity').val(1); // Reset quantity to 1
        newItem.appendTo('#itemsContainer');
        bindRemoveButton();
    });
    
    // Handle "Remove Item" button
    function bindRemoveButton() {
        $('.remove-item').off('click').click(function() {
            // Don't remove if it's the only item
            if($('.item-row').length > 1) {
                $(this).closest('.item-row').remove();
            } else {
                alert("At least one item is required.");
            }
        });
    }
    
    // Initial binding
    bindRemoveButton();
    
    // Form validation
    $("#deliveryForm").validate({
        rules: {
            delivery_date: "required",
            supplier: "required",
            "item_name[]": "required"
        },
        messages: {
            delivery_date: "Please enter delivery date",
            supplier: "Please enter supplier name",
            "item_name[]": "Please enter item name"
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
            error.insertAfter(element);
        }
    });
});
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>