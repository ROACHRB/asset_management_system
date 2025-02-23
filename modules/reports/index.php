<?php
include_once "../../includes/header.php";
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-chart-bar mr-2"></i>Advanced Reports</h1>
        <p class="text-muted">Generate and export detailed system reports</p>
    </div>
</div>

<div class="row">
    <!-- Inventory Report Card -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-boxes mr-2"></i>Inventory Reports</h5>
            </div>
            <div class="card-body">
                <p>Generate detailed inventory reports including asset status, location, and value.</p>
                <div class="list-group">
                    <a href="inventory_report.php" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Asset Inventory Report</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Complete inventory with status and location</small>
                    </a>
                    <a href="value_report.php" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Asset Value Report</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Asset valuation and depreciation</small>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignment Report Card -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-users mr-2"></i>Assignment Reports</h5>
            </div>
            <div class="card-body">
                <p>Track asset assignments, returns, and user history.</p>
                <div class="list-group">
                    <a href="assignment_report.php" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Current Assignments</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Active asset assignments by user</small>
                    </a>
                    <a href="history_report.php" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Assignment History</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Historical assignment data and trends</small>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Report Card -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-tools mr-2"></i>Maintenance Reports</h5>
            </div>
            <div class="card-body">
                <p>Track repairs, maintenance history, and asset condition.</p>
                <div class="list-group">
                    <a href="maintenance_report.php" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Maintenance History</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Asset repair and maintenance records</small>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Report Card -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-chart-line mr-2"></i>Analytics</h5>
            </div>
            <div class="card-body">
                <p>Generate analytical reports and trends.</p>
                <div class="list-group">
                    <a href="usage_report.php" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Asset Usage Analysis</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Usage patterns and statistics</small>
                    </a>
                    <a href="cost_report.php" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Cost Analysis</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Financial metrics and cost tracking</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once "../../includes/footer.php"; ?>