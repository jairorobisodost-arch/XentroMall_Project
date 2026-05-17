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
$arrears = $_GET['arrears'] ?? '0.00';
$penalty = $_GET['penalty'] ?? '0.00';
$other = $_GET['other'] ?? '0.00';
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
        @page { size: 8.5in 14in; margin: 0.5in; } /* Long bond paper (Legal) */
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; margin: 0; padding: 0; font-size: 11px; } /* Scaled down base font */
            .print-shadow-none { box-shadow: none !important; border: 1px solid #e5e7eb !important; border-radius: 0 !important; }
            .soa-card { margin: 0 auto !important; max-width: 100% !important; border: none !important; padding: 0 !important; }
            .accent-gradient { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .bg-blue-900 { background-color: #1e3a8a !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; color: white !important; }
            .bg-blue-50 { background-color: #eff6ff !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .bg-gray-50 { background-color: #f9fafb !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; }
        .soa-card { max-width: 8.5in; margin: 10px auto; background: white; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .accent-gradient { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); }
    </style>
</head>
<body class="p-2 md:p-4">
    <div class="no-print mb-4 max-w-2xl mx-auto flex gap-4 text-sm">
        <button onclick="window.print()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow transition-all flex items-center justify-center gap-2">
            <i class="fas fa-print"></i> Print Statement (Long Paper)
        </button>
        <button onclick="window.close()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-6 rounded-lg transition-all">
            Close
        </button>
    </div>

    <div class="soa-card print-shadow-none bg-white">
        <!-- Header -->
        <div class="accent-gradient h-3"></div>
        <div class="p-6 md:p-8">
            <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-black text-blue-900 tracking-tight mb-1">XENTROMALL</h1>
                    <p class="text-gray-500 font-bold uppercase tracking-widest text-[10px]">Property Management Office</p>
                    <div class="mt-2 text-[11px] text-gray-600 space-y-0.5">
                        <p><i class="fas fa-location-dot mr-1.5 text-blue-500 w-3 text-center"></i> Mall Address, City, Philippines</p>
                        <p><i class="fas fa-phone mr-1.5 text-blue-500 w-3 text-center"></i> +63 (000) 000-0000</p>
                        <p><i class="fas fa-envelope mr-1.5 text-blue-500 w-3 text-center"></i> management@xentromall.com</p>
                    </div>
                </div>
                <div class="text-left md:text-right mt-2 md:mt-0">
                    <h2 class="text-xl font-black text-gray-900 mb-0.5 leading-none uppercase">Statement of Account</h2>
                    <p class="text-blue-600 font-bold tracking-widest text-xs mb-3"><?php echo htmlspecialchars($refNo); ?></p>
                    <div class="inline-block md:block">
                        <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest leading-none">Date Issued</p>
                        <p class="font-bold text-gray-800 text-sm"><?php echo $currentDate; ?></p>
                    </div>
                </div>
            </div>

            <!-- Tenant Info Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 bg-blue-50/50 rounded-lg p-4 border border-blue-100/50">
                <div>
                    <p class="text-[10px] text-blue-600 font-black uppercase tracking-widest mb-1.5">Bill To</p>
                    <h3 class="text-lg font-black text-gray-900 leading-tight mb-1"><?php echo htmlspecialchars($tenant['tradename']); ?></h3>
                    <p class="font-bold text-gray-700 text-xs mb-1"><?php echo htmlspecialchars($tenant['company_name']); ?></p>
                    <p class="text-[11px] text-gray-600 leading-snug"><?php echo htmlspecialchars($tenant['business_address']); ?></p>
                </div>
                <div>
                    <p class="text-[10px] text-blue-600 font-black uppercase tracking-widest mb-1.5">Lease Details</p>
                    <div class="space-y-2">
                        <div class="flex justify-between border-b border-blue-100 pb-1.5">
                            <span class="text-xs text-gray-500 font-semibold">Stall Number</span>
                            <span class="text-xs font-bold text-gray-900">#<?php echo htmlspecialchars($tenant['stall_number']); ?></span>
                        </div>
                        <div class="flex justify-between border-b border-blue-100 pb-1.5">
                            <span class="text-xs text-gray-500 font-semibold">Billing Period</span>
                            <span class="text-xs font-bold text-blue-700 italic"><?php echo $billingMonth; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charges Table & Totals -->
            <div class="mb-6 border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-2.5 px-4 text-left font-black text-gray-600 uppercase tracking-wider w-3/4 text-[10px]">Description</th>
                            <th class="py-2.5 px-4 text-right font-black text-gray-600 uppercase tracking-wider w-1/4 text-[10px]">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr>
                            <td class="py-3 px-4 align-top">
                                <p class="font-bold text-gray-900 text-sm mb-0.5">Monthly Lease Rental</p>
                                <p class="text-[10px] text-gray-500">Regular monthly stall rental fee</p>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-gray-900 text-base align-top">₱<?php echo $rent; ?></td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 align-top">
                                <p class="font-bold text-gray-900 text-sm mb-0.5">Water Consumption Bill</p>
                                <p class="text-[10px] text-gray-500">Utility charges based on meter reading</p>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-gray-900 text-base align-top">₱<?php echo $water; ?></td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 align-top">
                                <p class="font-bold text-gray-900 text-sm mb-0.5">Electricity Consumption Bill</p>
                                <p class="text-[10px] text-gray-500">Utility charges based on meter reading</p>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-gray-900 text-base align-top">₱<?php echo $electric; ?></td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 align-top border-t border-gray-100">
                                <p class="font-bold text-gray-900 text-sm mb-0.5">Rental Arrears</p>
                                <p class="text-[10px] text-gray-500">Unpaid balance from previous billing periods</p>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-gray-900 text-base align-top border-t border-gray-100">₱<?php echo $arrears; ?></td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 align-top border-t border-gray-100">
                                <p class="font-bold text-gray-900 text-sm mb-0.5">Penalty / Late Payment Fees</p>
                                <p class="text-[10px] text-gray-500">Charges for late rental payments</p>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-gray-900 text-base align-top border-t border-gray-100">₱<?php echo $penalty; ?></td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 align-top border-t border-gray-100">
                                <p class="font-bold text-gray-900 text-sm mb-0.5">Other Charges</p>
                                <p class="text-[10px] text-gray-500">Miscellaneous fees (Work permits, etc.)</p>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-gray-900 text-base align-top border-t border-gray-100">₱<?php echo $other; ?></td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td class="py-2.5 px-4 text-right text-gray-500 font-bold uppercase tracking-widest text-[10px]">Subtotal</td>
                            <td class="py-2.5 px-4 text-right font-bold text-gray-900 text-sm"><?php echo (strpos($total, '₱') === false) ? '₱' . $total : $total; ?></td>
                        </tr>
                        <tr class="bg-blue-900 text-white border-t border-blue-800">
                            <td class="py-3 px-4 text-right text-blue-100 font-black uppercase tracking-widest text-[11px] align-middle">Total Due</td>
                            <td class="py-3 px-4 text-right font-black text-lg tracking-tight leading-none bg-blue-900"><?php echo (strpos($total, '₱') === false) ? '₱' . $total : $total; ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Footer Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-5 border-t border-gray-150">
                <div>
                    <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest mb-1.5">Payment Instructions</p>
                    <div class="bg-gray-50 rounded p-2.5 text-[10px] text-gray-600 leading-relaxed border border-gray-150">
                            Please settle the total amount on or before the 15th of the month. You can pay via the Tenant Dashboard or at the Admin Office. For check payments, please make it payable to: <strong>Xentromall Property Management</strong>.
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-start md:items-end justify-end mt-2 md:mt-0">
                    <div class="text-left md:text-right">
                        <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest mb-6">Authorized Signature</p>
                        <div class="h-px w-40 bg-gray-900 mb-1.5"></div>
                        <p class="font-bold text-gray-900 text-xs uppercase tracking-tight"><?php echo htmlspecialchars($preparedBy); ?></p>
                        <p class="text-[9px] text-gray-500 font-semibold">Administration Head</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="accent-gradient h-1 opacity-50"></div>
    </div>
    
    <p class="no-print text-center text-[9px] text-gray-400 mb-4 uppercase tracking-[0.2em]">Generated via XentroMall Management System</p>
</body>
</html>
