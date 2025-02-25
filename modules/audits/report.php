<?php
// File: modules/audits/report.php
include_once "../../includes/header.php";

// Check permission
if(!has_permission('generate_reports')) {
    $_SESSION['error'] = "Access denied. You don't have permission to generate reports.";
    header("Location: ../../index.php");
    exit;
}

// Check if audit ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid audit ID.";
    header("Location: index.php");
    exit;
}

$audit_id = $_GET['id'];

// Get audit information
$audit_query = "SELECT a.*, u.full_name as auditor_name 
                FROM physical_audits a
                JOIN users u ON a.auditor_id = u.user_id
                WHERE a.audit_id = ?";
$stmt = mysqli_prepare($conn, $audit_query);
mysqli_stmt_bind_param($stmt, "i", $audit_id);
mysqli_stmt_execute($stmt);
$audit_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($audit_result) == 0) {
    $_SESSION['error'] = "Audit not found.";
    header("Location: index.php");
    exit;
}

$audit = mysqli_fetch_assoc($audit_result);

// Get audit items
$items_query = "SELECT i.*, a.asset_name, a.asset_tag, a.serial_number, a.model, a.manufacturer, 
                a.purchase_date, a.purchase_cost, a.condition_status
                FROM audit_items i
                JOIN assets a ON i.asset_id = a.asset_id
                WHERE i.audit_id = ?
                ORDER BY i.status, a.asset_name";
$stmt = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt, "i", $audit_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);

// Count statistics
$total_items = mysqli_num_rows($items_result);
$found_count = 0;
$missing_count = 0;
$pending_count = 0;
$wrong_location_count = 0;
$total_cost = 0;
$missing_cost = 0;

$items = [];
while($item = mysqli_fetch_assoc($items_result)) {
    $items[] = $item;
    
    // Total asset cost
    $total_cost += $item['purchase_cost'];
    
    switch($item['status']) {
        case 'found': 
            $found_count++; 
            break;
        case 'missing': 
            $missing_count++; 
            $missing_cost += $item['purchase_cost'];
            break;
        case 'wrong_location': 
            $wrong_location_count++; 
            break;
        default: 
            $pending_count++;
    }
}

// Calculate percentages
$found_percent = ($total_items > 0) ? round(($found_count / $total_items) * 100, 1) : 0;
$missing_percent = ($total_items > 0) ? round(($missing_count / $total_items) * 100, 1) : 0;
$wrong_location_percent = ($total_items > 0) ? round(($wrong_location_count / $total_items) * 100, 1) : 0;

// Generate PDF if requested
if(isset($_GET['format']) && $_GET['format'] == 'pdf') {
    // Include TCPDF library
    require_once('../../vendor/tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Asset Management System');
    $pdf->SetAuthor($_SESSION['full_name']);
    $pdf->SetTitle('Audit Report #' . $audit_id);
    $pdf->SetSubject('Physical Inventory Audit Report');
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, 'Physical Inventory Audit Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Audit #' . $audit_id . ' - ' . date('F d, Y', strtotime($audit['audit_date'])), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Audit Information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Audit Information', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(40, 7, 'Location:', 0, 0, 'L');
    $pdf->Cell(0, 7, htmlspecialchars($audit['location']), 0, 1, 'L');
    
    $pdf->Cell(40, 7, 'Audit Date:', 0, 0, 'L');
    $pdf->Cell(0, 7, date('F d, Y', strtotime($audit['audit_date'])), 0, 1, 'L');
    
    $pdf->Cell(40, 7, 'Conducted By:', 0, 0, 'L');
    $pdf->Cell(0, 7, htmlspecialchars($audit['auditor_name']), 0, 1, 'L');
    
    $pdf->Cell(40, 7, 'Status:', 0, 0, 'L');
    $pdf->Cell(0, 7, ucfirst($audit['status']), 0, 1, 'L');
    
    if($audit['status'] == 'completed' && $audit['completed_date']) {
        $pdf->Cell(40, 7, 'Completed On:', 0, 0, 'L');
        $pdf->Cell(0, 7, date('F d, Y', strtotime($audit['completed_date'])), 0, 1, 'L');
    }
    
    if(!empty($audit['notes'])) {
        $pdf->Cell(40, 7, 'Notes:', 0, 0, 'L');
        $pdf->Cell(0, 7, htmlspecialchars($audit['notes']), 0, 1, 'L');
    }
    
    $pdf->Ln(5);
    
    // Summary Results
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Summary Results', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    // Create a simple table for results
    $pdf->Cell(65, 7, 'Total Assets Audited:', 0, 0, 'L');
    $pdf->Cell(30, 7, $total_items, 0, 0, 'L');
    $pdf->Cell(30, 7, '100%', 0, 1, 'L');
    
    $pdf->Cell(65, 7, 'Assets Found:', 0, 0, 'L');
    $pdf->Cell(30, 7, $found_count, 0, 0, 'L');
    $pdf->Cell(30, 7, $found_percent . '%', 0, 1, 'L');
    
    $pdf->Cell(65, 7, 'Assets Missing:', 0, 0, 'L');
    $pdf->Cell(30, 7, $missing_count, 0, 0, 'L');
    $pdf->Cell(30, 7, $missing_percent . '%', 0, 1, 'L');
    
    if($wrong_location_count > 0) {
        $pdf->Cell(65, 7, 'Assets in Wrong Location:', 0, 0, 'L');
        $pdf->Cell(30, 7, $wrong_location_count, 0, 0, 'L');
        $pdf->Cell(30, 7, $wrong_location_percent . '%', 0, 1, 'L');
    }
    
    $pdf->Cell(65, 7, 'Total Asset Value:', 0, 0, 'L');
    $pdf->Cell(0, 7, format_currency($total_cost), 0, 1, 'L');
    
    $pdf->Cell(65, 7, 'Missing Asset Value:', 0, 0, 'L');
    $pdf->Cell(0, 7, format_currency($missing_cost), 0, 1, 'L');
    
    $pdf->Ln(5);
    
    // Missing Assets List
    if($missing_count > 0) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Missing Assets', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        // Table header
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(30, 7, 'Asset Tag', 1, 0, 'L', true);
        $pdf->Cell(60, 7, 'Asset Name', 1, 0, 'L', true);
        $pdf->Cell(35, 7, 'Model', 1, 0, 'L', true);
        $pdf->Cell(30, 7, 'Serial Number', 1, 0, 'L', true);
        $pdf->Cell(30, 7, 'Value', 1, 1, 'R', true);
        
        // Table data
        foreach($items as $item) {
            if($item['status'] == 'missing') {
                $pdf->Cell(30, 7, $item['asset_tag'], 1, 0, 'L');
                $pdf->Cell(60, 7, htmlspecialchars(substr($item['asset_name'], 0, 30)), 1, 0, 'L');
                $pdf->Cell(35, 7, htmlspecialchars(substr($item['model'], 0, 20)), 1, 0, 'L');
                $pdf->Cell(30, 7, htmlspecialchars(substr($item['serial_number'], 0, 15)), 1, 0, 'L');
                $pdf->Cell(30, 7, format_currency($item['purchase_cost']), 1, 1, 'R');
            }
        }
    }
    
    // Wrong Location Assets
    if($wrong_location_count > 0) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Assets in Wrong Location', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        // Table header
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(30, 7, 'Asset Tag', 1, 0, 'L', true);
        $pdf->Cell(70, 7, 'Asset Name', 1, 0, 'L', true);
        $pdf->Cell(40, 7, 'Model', 1, 0, 'L', true);
        $pdf->Cell(45, 7, 'Notes', 1, 1, 'L', true);
        
        // Table data
        foreach($items as $item) {
            if($item['status'] == 'wrong_location') {
                $pdf->Cell(30, 7, $item['asset_tag'], 1, 0, 'L');
                $pdf->Cell(70, 7, htmlspecialchars(substr($item['asset_name'], 0, 35)), 1, 0, 'L');
                $pdf->Cell(40, 7, htmlspecialchars(substr($item['model'], 0, 25)), 1, 0, 'L');
                $pdf->Cell(45, 7, htmlspecialchars(substr($item['notes'], 0, 25)), 1, 1, 'L');
            }
        }
    }
    
    // Completion signature section
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Verification and Approval', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Ln(10);
    $pdf->Cell(0, 7, 'This audit report has been verified and approved by:', 0, 1, 'L');
    
    $pdf->Ln(15);
    $pdf->Cell(90, 7, '___________________________', 0, 0, 'C');
    $pdf->Cell(90, 7, '___________________________', 0, 1, 'C');
    $pdf->Cell(90, 7, 'Signature of Auditor', 0, 0, 'C');
    $pdf->Cell(90, 7, 'Signature of Manager', 0, 1, 'C');
    
    $pdf->Ln(15);
    $pdf->Cell(90, 7, '___________________________', 0, 0, 'C');
    $pdf->Cell(90, 7, '___________________________', 0, 1, 'C');
    $pdf->Cell(90, 7, 'Date', 0, 0, 'C');
    $pdf->Cell(90, 7, 'Date', 0, 1, 'C');
    
    // Output PDF
    $pdf->Output('Audit_Report_' . $audit_id . '.pdf', 'D');
    exit;
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-file-alt mr-2"></i>Audit Report</h1>
        <p class="text-muted">
            Audit #<?php echo $audit_id; ?> - 
            <?php echo htmlspecialchars($audit['location']); ?> - 
            <?php echo date('M d, Y', strtotime($audit['audit_date'])); ?>
        </p>
    </div>
    <div class="col-md-4 text-right">
        <a href="view.php?id=<?php echo $audit_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Audit
        </a>
        <a href="report.php?id=<?php echo $audit_id; ?>&format=pdf" class="btn btn-danger ml-2" target="_blank">
            <i class="fas fa-file-pdf mr-2"></i>Download PDF
        </a>
        <button onclick="window.print();" class="btn btn-primary ml-2">
            <i class="fas fa-print mr-2"></i>Print
        </button>
    </div>
</div>

<!-- Report Content for Preview and Printing -->
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-12 text-center mb-4 d-none d-print-block">
                <h1>Physical Inventory Audit Report</h1>
                <p>Audit #<?php echo $audit_id; ?> - <?php echo date('F d, Y', strtotime($audit['audit_date'])); ?></p>
            </div>
            
            <!-- Audit Information -->
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle mr-1"></i>Audit Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Location:</th>
                                <td><?php echo htmlspecialchars($audit['location']); ?></td>
                            </tr>
                            <tr>
                                <th>Audit Date:</th>
                                <td><?php echo date('F d, Y', strtotime($audit['audit_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Conducted By:</th>
                                <td><?php echo htmlspecialchars($audit['auditor_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge badge-<?php echo ($audit['status'] == 'completed') ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($audit['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php if($audit['status'] == 'completed' && $audit['completed_date']): ?>
                            <tr>
                                <th>Completed On:</th>
                                <td><?php echo date('F d, Y', strtotime($audit['completed_date'])); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if(!empty($audit['notes'])): ?>
                            <tr>
                                <th>Notes:</th>
                                <td><?php echo nl2br(htmlspecialchars($audit['notes'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Summary Results -->
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie mr-1"></i>Summary Results</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <div class="h1"><?php echo $total_items; ?></div>
                                <div>Total Assets</div>
                            </div>
                            <div class="col-6">
                                <div class="h1 text-danger"><?php echo $missing_count; ?></div>
                                <div>Missing Assets</div>
                            </div>
                        </div>
                        
                        <div class="progress mb-3">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $found_percent; ?>%" 
                                 aria-valuenow="<?php echo $found_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo $found_percent; ?>% Found
                            </div>
                            <div class="progress-bar bg-danger" role="progressbar" 
                                 style="width: <?php echo $missing_percent; ?>%" 
                                 aria-valuenow="<?php echo $missing_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo $missing_percent; ?>% Missing
                            </div>
                        </div>
                        
                        <table class="table table-sm">
                            <tr>
                                <th>Total Asset Value:</th>
                                <td class="text-right"><?php echo format_currency($total_cost); ?></td>
                            </tr>
                            <tr>
                                <th>Missing Asset Value:</th>
                                <td class="text-right text-danger"><?php echo format_currency($missing_cost); ?></td>
                            </tr>
                            <tr>
                                <th>Value Discrepancy:</th>
                                <td class="text-right">
                                    <?php echo round(($missing_cost / $total_cost) * 100, 1); ?>% of total
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Missing Assets Section -->
        <?php if($missing_count > 0): ?>
        <div class="row mt-3">
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>Missing Assets</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Asset Tag</th>
                                        <th>Name</th>
                                        <th>Model</th>
                                        <th>Serial Number</th>
                                        <th>Purchase Date</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($items as $item): ?>
                                        <?php if($item['status'] == 'missing'): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['asset_tag']); ?></td>
                                                <td><?php echo htmlspecialchars($item['asset_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['manufacturer'] . ' ' . $item['model']); ?></td>
                                                <td><?php echo htmlspecialchars($item['serial_number']); ?></td>
                                                <td>
                                                    <?php 
                                                    echo ($item['purchase_date'] && $item['purchase_date'] != '0000-00-00') 
                                                        ? date('M d, Y', strtotime($item['purchase_date'])) 
                                                        : 'N/A'; 
                                                    ?>
                                                </td>
                                                <td class="text-right"><?php echo format_currency($item['purchase_cost']); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="5" class="text-right">Total Missing Value:</th>
                                        <th class="text-right"><?php echo format_currency($missing_cost); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Wrong Location Assets Section -->
        <?php if($wrong_location_count > 0): ?>
        <div class="row mt-3">
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-map-marker-alt mr-1"></i>Assets in Wrong Location</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Asset Tag</th>
                                        <th>Name</th>
                                        <th>Model</th>
                                        <th>Serial Number</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($items as $item): ?>
                                        <?php if($item['status'] == 'wrong_location'): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['asset_tag']); ?></td>
                                                <td><?php echo htmlspecialchars($item['asset_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['manufacturer'] . ' ' . $item['model']); ?></td>
                                                <td><?php echo htmlspecialchars($item['serial_number']); ?></td>
                                                <td><?php echo nl2br(htmlspecialchars($item['notes'])); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Certification Section (visible in print only) -->
        <div class="row mt-4 d-none d-print-block">
            <div class="col-12">
                <h5>Verification and Approval</h5>
                <p>This audit report has been verified and approved by:</p>
                
                <div class="row mt-5">
                    <div class="col-6 text-center">
                        <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;"></div>
                        <p>Signature of Auditor</p>
                    </div>
                    <div class="col-6 text-center">
                        <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;"></div>
                        <p>Signature of Manager</p>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-6 text-center">
                        <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;"></div>
                        <p>Date</p>
                    </div>
                    <div class="col-6 text-center">
                        <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;"></div>
                        <p>Date</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style media="print">
    @page {
        size: A4;
        margin: 1cm;
    }
    body {
        font-size: 12pt;
    }
    .navbar, .btn, footer, .no-print {
        display: none !important;
    }
    .card {
        border: none !important;
    }
    .card-header {
        background-color: #f1f1f1 !important;
        color: #000 !important;
    }
    .table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    .table th, .table td {
        border: 1px solid #ddd !important;
        padding: 8px !important;
    }
    .badge-success {
        color: #000 !important;
        background-color: #d4edda !important;
    }
    .badge-danger {
        color: #000 !important;
        background-color: #f8d7da !important;
    }
    .badge-warning {
        color: #000 !important;
        background-color: #fff3cd !important;
    }
    .text-danger {
        color: #000 !important;
        font-weight: bold !important;
    }
    .progress {
        border: 1px solid #ddd !important;
    }
    .progress-bar {
        color: #000 !important;
        text-shadow: none !important;
    }
    .bg-success {
        background-color: #d4edda !important;
        color: #000 !important;
    }
    .bg-danger {
        background-color: #f8d7da !important;
        color: #000 !important;
    }
</style>

<?php include_once "../../includes/footer.php"; ?>