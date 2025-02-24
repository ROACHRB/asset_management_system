<?php
include_once "../../includes/header.php";

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: index.php");
    exit();
}

$user_id = $_GET['id'];

// Get user details with role name
$user_query = "SELECT u.*, r.role_name, r.description as role_description
               FROM users u
               LEFT JOIN roles r ON u.role = r.role_name
               WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Get user's active assignments
$assignments_query = "SELECT aa.*, a.asset_name, a.asset_tag
                     FROM asset_assignments aa
                     JOIN assets a ON aa.asset_id = a.asset_id
                     WHERE aa.assigned_to = ? AND aa.assignment_status = 'assigned'
                     ORDER BY aa.assignment_date DESC";
$stmt = mysqli_prepare($conn, $assignments_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$assignments_result = mysqli_stmt_get_result($stmt);

// Get user's recent activity
$activity_query = "SELECT * FROM user_activity_logs 
                  WHERE user_id = ?
                  ORDER BY created_at DESC
                  LIMIT 10";
$stmt = mysqli_prepare($conn, $activity_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$activity_result = mysqli_stmt_get_result($stmt);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-user mr-2"></i>User Profile</h1>
        <p class="text-muted">View user details and activity</p>
    </div>
    <div class="col-md-4 text-right">
        <?php if($_SESSION['role'] == 'admin'): ?>
        <a href="edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary mr-2">
            <i class="fas fa-edit mr-2"></i>Edit User
        </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </div>
</div>

<div class="row">
    <!-- User Information -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <?php if(!empty($user['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                         class="rounded-circle mb-3" width="150" height="150">
                <?php else: ?>
                    <i class="fas fa-user-circle fa-6x mb-3 text-secondary"></i>
                <?php endif; ?>
                
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p class="text-muted mb-4"><?php echo htmlspecialchars($user['department'] ?? 'No Department'); ?></p>
                
                <div class="mb-3">
                    <span class="badge badge-<?php 
                        echo ($user['role'] == 'admin' ? 'danger' : 
                            ($user['role'] == 'manager' ? 'warning' : 
                            ($user['role'] == 'staff' ? 'info' : 'secondary'))); 
                    ?> px-3 py-2">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </div>
                
                <p class="mb-0">
                    <i class="fas fa-envelope mr-2"></i>
                    <?php echo htmlspecialchars($user['email']); ?>
                </p>
            </div>
            <div class="card-footer">
                <small class="text-muted">
                    Last Login: 
                    <?php 
                    echo !empty($user['last_login']) ? 
                        date('M d, Y H:i', strtotime($user['last_login'])) : 
                        'Never';
                    ?>
                </small>
            </div>
        </div>
        
        <!-- User Status -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>Account Status
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col">
                        <div class="mb-1">
                            <span class="badge badge-<?php 
                                echo ($user['status'] == 'active' ? 'success' : 
                                    ($user['status'] == 'suspended' ? 'danger' : 'warning')); 
                            ?> px-3 py-2">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                        <small class="text-muted">Current Status</small>
                    </div>
                    <div class="col">
                        <div class="h4 mb-1">
                            <?php
                            $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM asset_assignments WHERE assigned_to = ? AND assignment_status = 'assigned'");
                            mysqli_stmt_bind_param($stmt, "i", $user_id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            $count = mysqli_fetch_assoc($result)['count'];
                            echo $count;
                            ?>
                        </div>
                        <small class="text-muted">Active Assignments</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Asset Assignments -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-clipboard-list mr-1"></i>Active Asset Assignments
            </div>
            <div class="card-body">
                <?php if(mysqli_num_rows($assignments_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Assigned Date</th>
                                <th>Expected Return</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($assignment = mysqli_fetch_assoc($assignments_result)): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($assignment['asset_name']); ?>
                                    <small class="text-muted d-block">
                                        <?php echo htmlspecialchars($assignment['asset_tag']); ?>
                                    </small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($assignment['assignment_date'])); ?></td>
                                <td>
                                    <?php 
                                    if(!empty($assignment['expected_return_date'])) {
                                        echo date('M d, Y', strtotime($assignment['expected_return_date']));
                                        if(strtotime($assignment['expected_return_date']) < time()) {
                                            echo ' <span class="badge badge-danger">Overdue</span>';
                                        }
                                    } else {
                                        echo 'Permanent';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-primary">Assigned</span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No active assignments found.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history mr-1"></i>Recent Activity
            </div>
            <div class="card-body">
                <?php if(mysqli_num_rows($activity_result) > 0): ?>
                <div class="timeline">
                    <?php while($activity = mysqli_fetch_assoc($activity_result)): ?>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-time">
                                <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
                            </div>
                            <h6><?php echo ucwords(str_replace('_', ' ', $activity['activity_type'])); ?></h6>
                            <p class="mb-0"><?php echo htmlspecialchars($activity['description']); ?></p>
                            <small class="text-muted">
                                IP: <?php echo htmlspecialchars($activity['ip_address']); ?>
                            </small>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No recent activity found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline-item {
    position: relative;
    padding-left: 40px;
    margin-bottom: 20px;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 2px;
    height: 100%;
    background-color: #e9ecef;
}

.timeline-content {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
    position: relative;
}

.timeline-content:before {
    content: '';
    position: absolute;
    left: -20px;
    top: 15px;
    width: 20px;
    height: 2px;
    background-color: #e9ecef;
}

.timeline-time {
    color: #6c757d;
    font-size: 0.875rem;
    margin-bottom: 5px;
}
</style>

<?php include_once "../../includes/footer.php"; ?>