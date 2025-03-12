<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\receiving\view_delivery.php
// Include header
include_once "../../includes/header.php";

// Check if delivery ID is provided
if(!isset($_GET['id']) || empty(trim($_GET['id']))) {
    // Redirect to the deliveries page
    header("location: index.php");
    exit;
}

// Get delivery ID from URL
$delivery_id = trim($_GET['id']);

// Fetch delivery details
$sql = "SELECT d.*, u.full_name as received_by_name
        FROM deliveries d
        LEFT JOIN users u ON d.received_by = u.user_id
        WHERE d.delivery_id = ?";

if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $delivery_id);
    
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) == 1) {
            $delivery = mysqli_fetch_assoc($result);
        } else {
            // Delivery not found
            header("location: index.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
        exit;
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo "Oops! Something went wrong. Please try again later.";
    exit;
}

// Fetch delivery items - Modified the query to fix the unknown column error
$items_sql = "SELECT di.*, c.category_name,
              (CASE WHEN a.asset_id IS NOT NULL THEN 1 ELSE 0 END) as is_asset
              FROM delivery_items di
              LEFT JOIN categories c ON di.category_id = c.category_id
              LEFT JOIN assets a ON di.item_id = a.category_id AND a.supplier = ?
              WHERE di.delivery_id = ?
              ORDER BY di.item_id";

$items_stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($items_stmt, "si", $delivery['supplier'], $delivery_id);
mysqli_stmt_execute($items_stmt);
$items_result = mysqli_stmt_get_result($items_stmt);

// Count items by status
$pending_count = $processed_count = $total_count = 0;
$total_value = 0;

$items = [];
while($item = mysqli_fetch_assoc($items_result)) {
    $items[] = $item;
    $total_count++;
    if($item['status'] == 'pending') {
        $pending_count++;
    } else {
        $processed_count++;
    }
    // Calculate value
    if(!empty($item['unit_cost'])) {
        $total_value += ($item['unit_cost'] * $item['quantity']);
    }
}

// Determine overall status
$delivery_status = 'pending';
if($total_count > 0) {
    if($pending_count == 0) {
        $delivery_status = 'completed';
    } else if($pending_count < $total_count) {
        $delivery_status = 'processing';
    }
}
?>

<div class="row mb-4">
    <div class="col-md-7">
        <h1>
            <i class="fas fa-truck mr-2"></i>Delivery Details
            <small class="text-muted">(#<?php echo $delivery_id; ?>)</small>
        </h1>
        <p class="text-muted">View delivery information and process items</p>
    </div>
    <div class="col-md-5 text-right">
        <div class="btn-group" role="group">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i>Back to List
            </a>
            <a href="process_items.php?id=<?php echo $delivery_id; ?>" class="btn btn-primary">
                <i class="fas fa-clipboard-check mr-1"></i>Process Items
            </a>
            <a href="edit_delivery.php?id=<?php echo $delivery_id; ?>" class="btn btn-info">
                <i class="fas fa-edit mr-1"></i>Edit Delivery
            </a>
            <?php if($delivery_status == 'pending'): ?>
            <a href="delete_delivery.php?id=<?php echo $delivery_id; ?>" class="btn btn-danger confirm-delete">
                <i class="fas fa-trash mr-1"></i>Delete
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Left Column - Delivery Information -->
    <div class="col-lg-5">
        <!-- Delivery Details Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>
                Delivery Information
                <span class="badge badge-<?php 
                    echo ($delivery_status == 'completed' ? 'success' : 
                         ($delivery_status == 'processing' ? 'info' : 'warning'));
                ?> float-right">
                    <?php echo ucfirst($delivery_status); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Delivery Date:</strong><br> <?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></p>
                        <p><strong>Supplier:</strong><br> <?php echo htmlspecialchars($delivery['supplier']); ?></p>
                        <p><strong>Reference Number:</strong><br> <?php echo !empty($delivery['reference_number']) ? htmlspecialchars($delivery['reference_number']) : 'N/A'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Received By:</strong><br> <?php echo htmlspecialchars($delivery['received_by_name']); ?></p>
                        <p><strong>Date Recorded:</strong><br> <?php echo date('M d, Y H:i', strtotime($delivery['created_at'])); ?></p>
                        <p>
                            <strong>Items Status:</strong><br>
                            <span class="text-success"><?php echo $processed_count; ?></span> processed, 
                            <span class="text-warning"><?php echo $pending_count; ?></span> pending
                        </p>
                    </div>
                </div>
                
                <?php if(!empty($delivery['notes'])): ?>
                <div class="mt-3">
                    <strong>Notes:</strong>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($delivery['notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Summary Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-chart-pie mr-1"></i>
                Delivery Summary
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4 mb-3">
                        <div class="h2 text-primary"><?php echo $total_count; ?></div>
                        <div class="text-muted">Total Items</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="h2 text-success">$<?php echo number_format($total_value, 2); ?></div>
                        <div class="text-muted">Total Value</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="h2 text-info"><?php echo $pending_count; ?></div>
                        <div class="text-muted">Pending Items</div>
                    </div>
                </div>
                
                <?php if($pending_count > 0): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    There are still <?php echo $pending_count; ?> items that need to be processed.
                    <a href="process_items.php?id=<?php echo $delivery_id; ?>" class="alert-link">Process items now</a>.
                </div>
                <?php elseif($total_count > 0): ?>
                <div class="alert alert-success mt-3 mb-0">
                    <i class="fas fa-check-circle mr-1"></i>
                    All items have been processed successfully.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Column - Items List -->
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-list mr-1"></i>
                Delivered Items
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($items) > 0): ?>
                                <?php foreach($items as $item): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($item['item_name']); ?>
                                            <?php if(!empty($item['description'])): ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-right">
                                            <?php echo !empty($item['unit_cost']) ? '$' . number_format($item['unit_cost'], 2) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <?php if($item['status'] == 'pending'): ?>
                                                <span class="badge badge-warning">Pending</span>
                                            <?php elseif($item['status'] == 'tagged'): ?>
                                                <span class="badge badge-info">Tagged</span>
                                            <?php elseif($item['status'] == 'stored'): ?>
                                                <span class="badge badge-success">Stored</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($item['status'] == 'pending'): ?>
                                                <a href="process_items.php?id=<?php echo $item['item_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-clipboard-check"></i>
                                                </a>
                                            <?php elseif($item['is_asset']): ?>
                                                <a href="../inventory/view.php?delivery_item=<?php echo $item['item_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-box"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No items found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once "../../includes/footer.php";
?>