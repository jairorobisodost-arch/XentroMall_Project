<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';
$receipt = $_GET['receipt'] ?? '';
$zone = $_GET['zone'] ?? '';

function getStallProfile($pdo, $id) {
    // Get basic stall information
    $stmt = $pdo->prepare('SELECT td.*, u.username, u.email as user_email 
                          FROM tenant_details td 
                          LEFT JOIN users u ON td.user_id = u.id 
                          WHERE td.id = ?');
    $stmt->execute([$id]);
    $stall = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stall) {
        return null;
    }
    
    // Get lease information
    $stmt = $pdo->prepare('SELECT * FROM lease_agreements WHERE tenant_id = ? ORDER BY start_date DESC LIMIT 1');
    $stmt->execute([$stall['user_id']]);
    $lease = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get inspection records
    $stmt = $pdo->prepare('SELECT * FROM inspections WHERE tenant_id = ? ORDER BY inspection_date DESC');
    $stmt->execute([$stall['user_id']]);
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment history
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE user_id = ? ORDER BY payment_date DESC LIMIT 6');
    $stmt->execute([$stall['user_id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get maintenance requests
    $stmt = $pdo->prepare('SELECT * FROM maintenance_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
    $stmt->execute([$stall['user_id']]);
    $maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine all data
    return array_merge($stall, [
        'lease_info' => $lease ?: null,
        'inspections' => $inspections,
        'recent_payments' => $payments,
        'maintenance_requests' => $maintenance
    ]);
}

function getPaymentReceipt($pdo, $receiptNumber) {
    $stmt = $pdo->prepare('SELECT p.*, u.username FROM payments p JOIN users u ON p.user_id = u.id WHERE p.id = ?');
    $stmt->execute([$receiptNumber]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getMaintenanceLogs($pdo, $zone) {
    $stmt = $pdo->prepare("SELECT * FROM maintenance_requests WHERE zone = ? ORDER BY created_at DESC");
    $stmt->execute([$zone]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRenewalStatus($pdo) {
    $stmt = $pdo->query('SELECT r.*, u.username, t.tradename 
                        FROM renewal_requests r 
                        JOIN users u ON r.user_id = u.id 
                        JOIN tenant_details t ON u.id = t.user_id 
                        ORDER BY r.renewal_date DESC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCategoryReport($pdo) {
    $report = [
        'categories' => [],
        'total_stalls' => 0,
        'occupied' => 0,
        'vacant' => 0
    ];
    
    // Get total stalls
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM tenant_details');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $report['total_stalls'] = $result['total'];
    
    // Get occupied stalls
    $stmt = $pdo->query('SELECT COUNT(DISTINCT user_id) as occupied FROM payments WHERE status = "approved"');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $report['occupied'] = $result['occupied'];
    $report['vacant'] = $report['total_stalls'] - $report['occupied'];
    
    // Get categories
    $stmt = $pdo->query('SELECT business_type, COUNT(*) as count FROM tenant_details GROUP BY business_type');
    $report['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $report;
}

function getFinancialReport($pdo) {
    $report = [
        'total_revenue' => 0,
        'monthly_data' => [],
        'payment_methods' => []
    ];
    
    // Get total revenue
    $stmt = $pdo->query('SELECT SUM(amount) as total FROM payments WHERE status = "approved"');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $report['total_revenue'] = $result['total'] ?? 0;
    
    // Get monthly data
    $stmt = $pdo->query('SELECT 
        DATE_FORMAT(payment_date, "%Y-%m") as month,
        COUNT(*) as transactions,
        SUM(amount) as total
        FROM payments 
        WHERE status = "approved"
        GROUP BY DATE_FORMAT(payment_date, "%Y-%m")
        ORDER BY month DESC');
    $report['monthly_data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment methods
    $stmt = $pdo->query('SELECT 
        payment_method,
        COUNT(*) as transactions,
        SUM(amount) as total
        FROM payments 
        WHERE status = "approved"
        GROUP BY payment_method');
    $report['payment_methods'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $report;
}

function getOccupancyReport($pdo) {
    $report = [
        'total_stalls' => 0,
        'occupied' => 0,
        'vacant' => 0,
        'by_floor' => []
    ];
    
    // Get total stalls
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM tenant_details');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $report['total_stalls'] = $result['total'];
    
    // Get occupied stalls
    $stmt = $pdo->query('SELECT COUNT(DISTINCT user_id) as occupied FROM payments WHERE status = "approved"');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $report['occupied'] = $result['occupied'];
    $report['vacant'] = $report['total_stalls'] - $report['occupied'];
    
    // Get by floor (assuming store_location contains floor info)
    $stmt = $pdo->query('SELECT 
        store_location as floor,
        COUNT(*) as total,
        SUM(CASE WHEN id IN (SELECT DISTINCT user_id FROM payments WHERE status = "approved") THEN 1 ELSE 0 END) as occupied
        FROM tenant_details 
        GROUP BY store_location');
    $report['by_floor'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $report;
}

// Generate the appropriate report
$data = [];
$title = '';
$printView = '';

switch ($type) {
    case 'stall':
        $data = getStallProfile($pdo, $id);
        $title = 'Stall Profile: ' . htmlspecialchars($data['tradename'] ?? '');
        ob_start();
        include 'templates/print_stall_profile.php';
        $printView = ob_get_clean();
        break;
        
    case 'receipt':
        $data = getPaymentReceipt($pdo, $receipt);
        $title = 'Payment Receipt #' . htmlspecialchars($receipt);
        ob_start();
        include 'templates/print_receipt.php';
        $printView = ob_get_clean();
        break;
        
    case 'maintenance':
        $data = getMaintenanceLogs($pdo, $zone);
        $title = 'Maintenance Logs - ' . ucfirst(htmlspecialchars($zone)) . ' Floor';
        ob_start();
        include 'templates/print_maintenance_log.php';
        $printView = ob_get_clean();
        break;
        
    case 'renewal':
        $data = getRenewalStatus($pdo);
        $title = 'Renewal Status Report';
        ob_start();
        include 'templates/print_renewal_status.php';
        $printView = ob_get_clean();
        break;
        
    case 'category':
        $data = getCategoryReport($pdo);
        $title = 'Category Summary Report';
        ob_start();
        include 'templates/print_category_report.php';
        $printView = ob_get_clean();
        break;
        
    case 'financial':
        $data = getFinancialReport($pdo);
        $title = 'Financial Summary Report';
        ob_start();
        include 'templates/print_financial_report.php';
        $printView = ob_get_clean();
        break;
        
    case 'occupancy':
        $data = getOccupancyReport($pdo);
        $title = 'Occupancy Report';
        ob_start();
        include 'templates/print_occupancy_report.php';
        $printView = ob_get_clean();
        break;
        
    default:
        $title = 'Invalid Report';
        $printView = '<div class="alert alert-danger">Invalid report type specified.</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 2px solid #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .footer {
            border-top: 1px solid #333;
            margin-top: 20px;
            padding-top: 10px;
            text-align: center;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .no-print {
            display: none;
        }
    </style>
</head>
<body class="bg-white p-8">
    <div class="no-print mb-4">
        <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-print mr-2"></i>Print
        </button>
        <button onclick="window.close()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded ml-2">
            <i class="fas fa-times mr-2"></i>Close
        </button>
    </div>
    
    <div class="header">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($title); ?></h1>
                <p class="text-sm text-gray-600">Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-semibold">XentroMall</h2>
                <p class="text-sm">123 Mall Street, City</p>
                <p class="text-sm">Phone: (123) 456-7890</p>
            </div>
        </div>
    </div>
    
    <div class="content">
        <?php echo $printView; ?>
    </div>
    
    <div class="footer">
        <p>This is a computer-generated document. No signature is required.</p>
        <p>Page <span id="page-number"></span> of <span id="page-total"></span></p>
    </div>
    
    <script>
        // Update page numbers
        window.onload = function() {
            var totalPages = Math.ceil(document.body.scrollHeight / 1123); // A4 height in pixels at 96dpi
            document.getElementById('page-total').textContent = totalPages;
            
            // Update page numbers for each page when printing
            for (var i = 1; i <= totalPages; i++) {
                var page = document.createElement('div');
                page.style.position = 'absolute';
                page.style.bottom = '10px';
                page.style.right = '20px';
                page.style.fontSize = '10px';
                page.textContent = 'Page ' + i + ' of ' + totalPages;
                document.body.appendChild(page);
            }
        };
        
        // Auto-print and close after a delay if opened in a new window
        if (window.opener) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        }
    </script>
</body>
</html>
