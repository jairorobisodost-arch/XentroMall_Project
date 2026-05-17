<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized');
}

$refNo = $_GET['ref_no'] ?? 'N/A';
$tenantId = $_GET['tenant_id'] ?? 0;
$monthStr = $_GET['month'] ?? '';
$water = $_GET['water'] ?? '0.00';
$electric = $_GET['electric'] ?? '0.00';
$rent = $_GET['rent'] ?? '0.00';
$total = $_GET['total'] ?? '0.00';
$preparedBy = $_GET['prepared_by'] ?? 'Admin';

// Fetch tenant details
$stmt = $pdo->prepare("
    SELECT td.*, s.stall_number, s.description as stall_location
    FROM tenant_details td
    LEFT JOIN stalls s ON s.id = td.stall_id
    WHERE td.id = ?
");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    die('Tenant not found');
}

$billingMonth = !empty($monthStr) ? date('F Y', strtotime($monthStr . '-01')) : 'N/A';
$currentDate = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOA - <?php echo htmlspecialchars($tenant['tradename']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .print-shadow-none { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
        }
        body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; }
        .soa-card { max-width: 800px; margin: 40px auto; background: white; border-radius: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); overflow: hidden; }
        .accent-gradient { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="no-print mb-8 max-w-2xl mx-auto flex gap-4">
        <button onclick="window.print()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
            <i class="fas fa-print"></i> Print Statement
        </button>
        <button onclick="window.close()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-3 px-6 rounded-xl transition-all">
            Close
        </button>
    </div>

    <div class="soa-card print-shadow-none bg-white">
        <!-- Header -->
        <div class="accent-gradient h-4"></div>
        <div class="p-8 md:p-12">
            <div class="flex flex-col md:flex-row justify-between items-start gap-8 mb-12">
                <div>
                    <h1 class="text-4xl font-black text-blue-900 tracking-tight mb-2">XENTROMALL</h1>
                    <p class="text-gray-500 font-medium uppercase tracking-widest text-sm">Property Management Office</p>
                    <div class="mt-4 text-sm text-gray-600 space-y-1">
                        <p><i class="fas fa-location-dot mr-2 text-blue-500"></i> Mall Address, City, Philippines</p>
                        <p><i class="fas fa-phone mr-2 text-blue-500"></i> +63 (000) 000-0000</p>
                        <p><i class="fas fa-envelope mr-2 text-blue-500"></i> management@xentromall.com</p>
                    </div>
                </div>
                <div class="text-left md:text-right">
                    <h2 class="text-2xl font-black text-gray-900 mb-1 leading-none uppercase">Statement of Account</h2>
                    <p class="text-blue-600 font-bold tracking-widest text-sm mb-6"><?php echo htmlspecialchars($refNo); ?></p>
                    <div class="space-y-1">
                        <p class="text-xs text-gray-400 font-black uppercase tracking-widest">Date Issued</p>
                        <p class="font-bold text-gray-800"><?php echo $currentDate; ?></p>
                    </div>
                </div>
            </div>

            <!-- Tenant Info Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12 bg-blue-50/50 rounded-2xl p-6 border border-blue-100/50">
                <div>
                    <p class="text-xs text-blue-600 font-black uppercase tracking-widest mb-3">Bill To</p>
                    <h3 class="text-xl font-black text-gray-900 mb-1"><?php echo htmlspecialchars($tenant['tradename']); ?></h3>
                    <p class="font-bold text-gray-700 mb-2"><?php echo htmlspecialchars($tenant['company_name']); ?></p>
                    <p class="text-sm text-gray-600 leading-relaxed"><?php echo htmlspecialchars($tenant['business_address']); ?></p>
                </div>
                <div>
                    <p class="text-xs text-blue-600 font-black uppercase tracking-widest mb-3">Lease Details</p>
                    <div class="space-y-3">
                        <div class="flex justify-between border-b border-blue-100 pb-2">
                            <span class="text-sm text-gray-500 font-medium">Stall Number</span>
                            <span class="font-bold text-gray-900">#<?php echo htmlspecialchars($tenant['stall_number']); ?></span>
                        </div>
                        <div class="flex justify-between border-b border-blue-100 pb-2">
                            <span class="text-sm text-gray-500 font-medium">Billing Period</span>
                            <span class="font-bold text-blue-700 italic"><?php echo $billingMonth; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charges Table -->
            <div class="mb-12">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-100">
                            <th class="py-4 text-left text-xs font-black text-gray-400 uppercase tracking-widest">Description</th>
                            <th class="py-4 text-right text-xs font-black text-gray-400 uppercase tracking-widest">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr>
                            <td class="py-6">
                                <p class="font-bold text-gray-900">Monthly Lease Rental</p>
                                <p class="text-sm text-gray-500">Regular monthly stall rental fee</p>
                            </td>
                            <td class="py-6 text-right font-black text-gray-900">₱<?php echo $rent; ?></td>
                        </tr>
                        <tr>
                            <td class="py-6">
                                <p class="font-bold text-gray-900">Water Consumption Bill</p>
                                <p class="text-sm text-gray-500">Utility charges based on meter reading</p>
                            </td>
                            <td class="py-6 text-right font-black text-gray-900">₱<?php echo $water; ?></td>
                        </tr>
                        <tr>
                            <td class="py-6">
                                <p class="font-bold text-gray-900">Electricity Consumption Bill</p>
                                <p class="text-sm text-gray-500">Utility charges based on meter reading</p>
                            </td>
                            <td class="py-6 text-right font-black text-gray-900">₱<?php echo $electric; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals Section -->
            <div class="flex justify-end mb-12">
                <div class="w-full md:w-80 space-y-4">
                    <div class="flex justify-between items-center px-4 py-2">
                        <span class="text-gray-500 font-bold uppercase tracking-widest text-xs">Subtotal</span>
                        <span class="font-bold text-gray-900">₱<?php echo $total; ?></span>
                    </div>
                    <div class="flex justify-between items-center px-4 py-6 bg-blue-900 rounded-2xl shadow-xl shadow-blue-500/20">
                        <span class="text-blue-100 font-black uppercase tracking-widest text-sm">Total Due</span>
                        <span class="font-black text-white text-2xl tracking-tight"><?php echo $total; ?></span>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 pt-12 border-t border-gray-100">
                <div class="space-y-6">
                    <div>
                        <p class="text-xs text-gray-400 font-black uppercase tracking-widest mb-4">Payment Instructions</p>
                        <div class="bg-gray-50 rounded-xl p-4 text-xs text-gray-600 leading-relaxed border border-gray-100">
                            Please settle the total amount on or before the 15th of the month. You can pay via the Tenant Dashboard or at the Admin Office. For check payments, please make it payable to: <strong>Xentromall Property Management</strong>.
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-start md:items-end justify-end">
                    <div class="text-left md:text-right">
                        <p class="text-xs text-gray-400 font-black uppercase tracking-widest mb-12">Authorized Signature</p>
                        <div class="h-px w-48 bg-gray-900 mb-2"></div>
                        <p class="font-bold text-gray-900 uppercase tracking-tight"><?php echo htmlspecialchars($preparedBy); ?></p>
                        <p class="text-xs text-gray-500 font-medium">Administration Head</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="accent-gradient h-2 opacity-50"></div>
    </div>
    
    <p class="text-center text-xs text-gray-400 mb-8 uppercase tracking-[0.2em]">Generated via XentroMall Management System</p>
</body>
</html>
