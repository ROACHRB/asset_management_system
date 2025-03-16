<?php
include_once "../../includes/header.php";

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Check if we're viewing someone else's profile (admin only)
$viewing_user_id = $user_id;
$viewing_own_profile = true;
if(isset($_GET['id']) && !empty($_GET['id']) && $user_role == 'admin') {
    $viewing_user_id = $_GET['id'];
    $viewing_own_profile = ($viewing_user_id == $user_id);
}

// Log profile view activity (only when viewing own profile)
if($viewing_own_profile) {
    $activity_type = 'profile_view';
    $description = 'User viewed their profile';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $log_query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
                 VALUES (?, ?, ?, ?)";
    
    if ($log_stmt = mysqli_prepare($conn, $log_query)) {
        mysqli_stmt_bind_param($log_stmt, "isss", $user_id, $activity_type, $description, $ip_address);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
    }
}

// Get user details with role name and department name
$user_query = "SELECT u.*, r.role_name, r.description as role_description, d.department_name
               FROM users u
               LEFT JOIN roles r ON u.role = r.role_name
               LEFT JOIN departments d ON u.department = d.department_id
               WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $viewing_user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Get user's active assignments
$assignments_query = "SELECT aa.*, a.asset_name, a.asset_tag, aa.assignment_id
                     FROM asset_assignments aa
                     JOIN assets a ON aa.asset_id = a.asset_id
                     WHERE aa.assigned_to = ? AND aa.assignment_status = 'assigned'
                     ORDER BY aa.assignment_date DESC";
$stmt = mysqli_prepare($conn, $assignments_query);
mysqli_stmt_bind_param($stmt, "i", $viewing_user_id);
mysqli_stmt_execute($stmt);
$assignments_result = mysqli_stmt_get_result($stmt);

// Get user's recent activity with more informative descriptions
$activity_query = "SELECT ual.*, 
                    CASE 
                        WHEN ual.activity_type = 'login' THEN 'User logged in to the system'
                        WHEN ual.activity_type = 'logout' THEN 'User logged out of the system'
                        WHEN ual.activity_type = 'password_change' THEN 'User changed their password'
                        WHEN ual.activity_type = 'profile_view' THEN 'User viewed their profile'
                        WHEN ual.activity_type = 'user_created' THEN ual.description
                        WHEN ual.activity_type = 'user_deleted' THEN ual.description
                        WHEN ual.activity_type = 'user_suspended' THEN ual.description
                        WHEN ual.activity_type = 'user_activated' THEN ual.description
                        WHEN ual.activity_type = 'assignment_received' THEN CONCAT('New asset assigned to user')
                        WHEN ual.activity_type LIKE '%asset%' THEN COALESCE(ual.description, 'Asset-related activity')
                        ELSE COALESCE(ual.description, CONCAT(REPLACE(ual.activity_type, '_', ' '), ' activity'))
                    END AS formatted_description
                  FROM user_activity_logs ual
                  WHERE ual.user_id = ?
                  ORDER BY ual.created_at DESC
                  LIMIT 10";
$stmt = mysqli_prepare($conn, $activity_query);
mysqli_stmt_bind_param($stmt, "i", $viewing_user_id);
mysqli_stmt_execute($stmt);
$activity_result = mysqli_stmt_get_result($stmt);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <?php if($viewing_own_profile): ?>
            <h1><i class="fas fa-user-circle mr-2"></i>My Profile</h1>
            <p class="text-muted">View and manage your profile information</p>
        <?php else: ?>
            <h1><i class="fas fa-user-circle mr-2"></i>User Profile</h1>
            <p class="text-muted">Viewing profile for <?php echo htmlspecialchars($user['full_name']); ?></p>
        <?php endif; ?>
    </div>
    <div class="col-md-4 text-right">
        <?php if($viewing_own_profile): ?>
            <a href="edit_profile.php" class="btn btn-primary">
                <i class="fas fa-edit mr-2"></i>Edit Profile
            </a>
        <?php elseif($user_role == 'admin'): ?>
            <a href="edit.php?id=<?php echo $viewing_user_id; ?>" class="btn btn-primary mr-2">
                <i class="fas fa-edit mr-2"></i>Edit User
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to List
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- User Information -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <?php if(!empty($user['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                         class="rounded-circle mb-3" width="150" height="150" alt="Profile Image">
                <?php else: ?>
                    <i class="fas fa-user-circle fa-6x mb-3 text-secondary"></i>
                <?php endif; ?>
                
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p class="text-muted mb-4"><?php echo htmlspecialchars($user['department_name'] ?? 'No Department'); ?></p>
                
                <div class="mb-3">
                    <span class="badge badge-<?php 
                        echo ($user['role'] == 'admin' ? 'danger' : 
                            ($user['role'] == 'manager' ? 'warning' : 
                            ($user['role'] == 'staff' ? 'info' : 'secondary'))); 
                    ?> px-3 py-2">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </div>
                
                <div class="text-left">
                    <p class="mb-2">
                        <i class="fas fa-envelope mr-2"></i>
                        <?php echo htmlspecialchars($user['email']); ?>
                    </p>
                    <?php if(!empty($user['phone'])): ?>
                    <p class="mb-2">
                        <i class="fas fa-phone mr-2"></i>
                        <?php echo htmlspecialchars($user['phone']); ?>
                    </p>
                    <?php endif; ?>
                    <?php if(!empty($user['location'])): ?>
                    <p class="mb-2">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <?php echo htmlspecialchars($user['location']); ?>
                    </p>
                    <?php endif; ?>
                    <p class="mb-0">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                    </p>
                </div>
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
        
        <!-- User Stats -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-chart-bar mr-1"></i>Account Stats
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col">
                        <div class="h4 mb-1">
                            <?php
                            $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM asset_assignments WHERE assigned_to = ? AND assignment_status = 'assigned'");
                            mysqli_stmt_bind_param($stmt, "i", $viewing_user_id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            $count = mysqli_fetch_assoc($result)['count'];
                            echo $count;
                            ?>
                        </div>
                        <small class="text-muted">Active Assets</small>
                    </div>
                    <div class="col">
                        <div class="h4 mb-1">
                            <span class="badge badge-<?php 
                                echo ($user['status'] == 'active' ? 'success' : 
                                    ($user['status'] == 'suspended' ? 'danger' : 'warning')); 
                            ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                        <small class="text-muted">Status</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="col-md-8">
        <!-- Asset Assignments -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-laptop mr-1"></i><?php echo $viewing_own_profile ? 'My Assets' : 'Assigned Assets'; ?>
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
                                <?php if($_SESSION['role'] == 'admin'): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($assignment = mysqli_fetch_assoc($assignments_result)): ?>
                            <tr>
                                <td>
                                    <a href="asset_details.php?id=<?php echo $assignment['asset_id']; ?>">
                                        <?php echo htmlspecialchars($assignment['asset_name']); ?>
                                    </a>
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
                                <?php if($_SESSION['role'] == 'admin'): ?>
                                <td>
                                    <a href="../assets/return_asset.php?id=<?php echo $assignment['assignment_id']; ?>" 
                                       class="btn btn-sm btn-warning" 
                                       onclick="return confirm('Are you sure you want to mark this asset as returned?');">
                                        <i class="fas fa-undo mr-1"></i>Return
                                    </a>
                                    <a href="../assets/remove_assignment.php?id=<?php echo $assignment['assignment_id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this assignment? This action cannot be undone.');">
                                        <i class="fas fa-trash mr-1"></i>Remove
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No assets are currently assigned.</p>
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
                            <p class="mb-0"><?php echo htmlspecialchars($activity['formatted_description']); ?></p>
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
            <?php if(mysqli_num_rows($activity_result) > 0): ?>
            <div class="card-footer text-center">
                <a href="activity_history.php" class="btn btn-sm btn-outline-secondary">View Full Activity History</a>
            </div>
            <?php endif; ?>
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