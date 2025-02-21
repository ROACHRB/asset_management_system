<?php
// Ensure required variables are set to avoid undefined warnings
$pending_count = $pending_count ?? 0;
$total_count = $total_count ?? 0;
$items = $items ?? [];
$delivery_id = $delivery_id ?? 0;
?>

<div class="row">
    <div class="col-lg-12">
        <?php if ($pending_count > 0): ?>
            <div class="alert alert-warning mt-3 mb-0">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                There are still <?php echo htmlspecialchars($pending_count); ?> items that need to be processed.
                <a href="process_items.php?id=<?php echo htmlspecialchars($delivery_id); ?>" class="alert-link">Process items now</a>.
            </div>
        <?php elseif ($total_count > 0): ?>
            <div class="alert alert-success mt-3 mb-0">
                <i class="fas fa-check-circle mr-1"></i>
                All items have been processed successfully.
            </div>
        <?php endif; ?>
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
                            <?php if (!empty($items)): ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($item['item_name']); ?>
                                            <?php if (!empty($item['description'])): ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td class="text-right">
                                            <?php echo !empty($item['unit_cost']) ? '$' . number_format($item['unit_cost'], 2) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <?php
                                            switch ($item['status']) {
                                                case 'pending':
                                                    echo '<span class="badge badge-warning">Pending</span>';
                                                    break;
                                                case 'tagged':
                                                    echo '<span class="badge badge-info">Tagged</span>';
                                                    break;
                                                case 'stored':
                                                    echo '<span class="badge badge-success">Stored</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($item['status'] == 'pending'): ?>
                                                <a href="process_item.php?id=<?php echo htmlspecialchars($item['item_id']); ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-clipboard-check"></i>
                                                </a>
                                            <?php elseif ($item['is_asset']): ?>
                                                <a href="../inventory/view.php?delivery_item=<?php echo htmlspecialchars($item['item_id']); ?>" class="btn btn-sm btn-info">
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
