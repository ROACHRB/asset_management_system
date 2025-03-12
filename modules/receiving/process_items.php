<?php
// FILE PATH: asset_management_system/modules/receiving/process_items.php
// Include header
include_once "../../includes/header.php";
include_once "../../config/database.php";
include_once "../../includes/functions.php";

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Get delivery ID
$delivery_id = $_GET['id'] ?? 0;

// Initialize variables
$error = '';
$success = '';
$items = [];
$pending_count = 0;
$total_count = 0;

// Get delivery details
if (!empty($delivery_id)) {
    $query = "SELECT d.*, u.full_name as received_by_name 
              FROM deliveries d
              LEFT JOIN users u ON d.received_by = u.user_id
              WHERE d.delivery_id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $delivery_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $delivery = mysqli_fetch_assoc($result);
        
        // Get delivery items
        $items_query = "SELECT di.*, c.category_name,
                        CASE WHEN di.status = 'pending' THEN 0 ELSE 1 END as is_processed
                        FROM delivery_items di
                        LEFT JOIN categories c ON di.category_id = c.category_id
                        WHERE di.delivery_id = ?
                        ORDER BY di.status = 'pending' DESC, di.item_name";
        
        $stmt = mysqli_prepare($conn, $items_query);
        mysqli_stmt_bind_param($stmt, "i", $delivery_id);
        mysqli_stmt_execute($stmt);
        $items_result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($items_result) > 0) {
            while ($row = mysqli_fetch_assoc($items_result)) {
                $items[] = $row;
                $total_count++;
                if ($row['status'] == 'pending') {
                    $pending_count++;
                }
            }
        } else {
            $error = "No items found for this delivery.";
        }
    } else {
        $error = "Delivery not found.";
    }
} else {
    $error = "No delivery ID provided.";
}

// Get success message if any
$success_message = '';
if(isset($_GET['success'])) {
    if($_GET['success'] == 'stored') {
        $success_message = 'Item has been stored successfully.';
    } elseif($_GET['success'] == 'tagged') {
        $success_message = 'Item has been tagged as an asset successfully.';
    } elseif($_GET['success'] == 'completed') {
        $success_message = 'Item has been completed successfully.';
    }
}

// Get locations for reference
$locations_query = "SELECT location_id, building, room, department FROM locations ORDER BY building, room";
$locations_result = mysqli_query($conn, $locations_query);
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Process Delivery Items</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="index.php">Deliveries</a></li>
        <li class="breadcrumb-item"><a href="view_delivery.php?id=<?php echo $delivery_id; ?>">Delivery Details</a></li>
        <li class="breadcrumb-item active">Process Items</li>
    </ol>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-1"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($delivery)): ?>
        <!-- Delivery Information Card -->
        <!-- Delivery Information Card -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-truck-loading me-1"></i>
        Delivery Information
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p><strong><i class="fas fa-calendar-alt me-1"></i> Delivery Date:</strong> <?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong><i class="fas fa-building me-1"></i> Supplier:</strong> <?php echo htmlspecialchars($delivery['supplier']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong><i class="fas fa-hashtag me-1"></i> Reference Number:</strong> <?php echo htmlspecialchars($delivery['reference_number'] ?? 'N/A'); ?></p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <p>
                    <strong><i class="fas fa-user me-1"></i> Received By:</strong> <?php echo htmlspecialchars($delivery['received_by_name']); ?>
                    <?php if (!empty($delivery['notes'])): ?>
                        <span class="ms-3"><strong><i class="fas fa-sticky-note me-1"></i> Notes:</strong> <?php echo htmlspecialchars($delivery['notes']); ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>
        
        <!-- Items List Card -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-list me-1"></i>
                        Delivery Items (<?php echo $pending_count; ?> pending of <?php echo $total_count; ?> total)
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="itemsTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($items)): ?>
                                <?php foreach ($items as $item): ?>
                                    <tr class="<?php echo ($item['status'] == 'pending') ? 'table-warning' : ''; ?>">
                                        <td>
                                            <?php echo htmlspecialchars($item['item_name']); ?>
                                            <?php if (!empty($item['description'])): ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td class="text-end">
                                            <?php echo !empty($item['unit_cost']) ? '$' . number_format($item['unit_cost'], 2) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <?php
                                            switch ($item['status']) {
                                                case 'pending':
                                                    echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                    break;
                                                case 'tagged':
                                                    echo '<span class="badge bg-info">Tagged</span>';
                                                    break;
                                                case 'stored':
                                                    echo '<span class="badge bg-success">Completed</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($item['status'] == 'pending'): ?>
                                                <a href="complete_item.php?id=<?php echo $item['item_id']; ?>&delivery_id=<?php echo $delivery_id; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check-circle me-1"></i> Complete
                                                </a>
                                            <?php elseif ($item['status'] == 'tagged'): ?>
                                                <a href="../inventory/index.php?tag=<?php echo $item['item_name']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-barcode me-1"></i> View Asset
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    <i class="fas fa-check me-1"></i> Processed
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
                
                <div class="mt-3">
                    <a href="view_delivery.php?id=<?php echo $delivery_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Delivery Details
                    </a>
                    <?php if ($pending_count === 0 && $total_count > 0): ?>
                        <a href="index.php" class="btn btn-success ms-2">
                            <i class="fas fa-check-circle me-1"></i> All Items Processed
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Delivery not found. <a href="index.php" class="alert-link">Back to deliveries list</a>.
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#itemsTable').DataTable({
        order: [[4, 'asc']], // Sort by status (pending first)
        pageLength: 25,
        "columnDefs": [
            { "orderable": false, "targets": [5] } // Disable sorting on actions column
        ]
    });
});
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>