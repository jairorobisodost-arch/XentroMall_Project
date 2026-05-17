<div class="receipt">
    <div class="text-center mb-6">
        <h2 class="text-xl font-bold">OFFICIAL RECEIPT</h2>
        <p class="text-sm">XentroMall</p>
        <p class="text-xs">123 Mall Street, City</p>
        <p class="text-xs">TIN: 123-456-789-000</p>
        <p class="text-xs">OR #: <?php echo htmlspecialchars($data['id'] ?? 'N/A'); ?></p>
        <p class="text-xs">Date: <?php echo !empty($data['payment_date']) ? date('F j, Y', strtotime($data['payment_date'])) : date('F j, Y'); ?></p>
    </div>
    
    <div class="border-t-2 border-b-2 border-gray-400 py-2 my-4">
        <div class="flex justify-between">
            <span>Received from:</span>
            <span class="font-semibold"><?php echo htmlspecialchars($data['username'] ?? 'N/A'); ?></span>
        </div>
        <div class="flex justify-between">
            <span>For payment of:</span>
            <span class="font-semibold"><?php echo htmlspecialchars($data['description'] ?? 'Monthly Rent'); ?></span>
        </div>
    </div>
    
    <div class="text-right mb-6">
        <p class="text-2xl font-bold">₱<?php echo isset($data['amount']) ? number_format($data['amount'], 2) : '0.00'; ?></p>
        <p class="text-sm text-gray-600"><?php echo $amountInWords ?? 'Zero pesos and 00/100'; ?></p>
    </div>
    
    <div class="payment-details mb-6">
        <h3 class="font-semibold mb-2">Payment Details:</h3>
        <table class="w-full">
            <tr>
                <td class="w-1/3">Payment Method:</td>
                <td class="font-semibold"><?php echo htmlspecialchars(ucfirst($data['payment_method'] ?? 'Cash')); ?></td>
            </tr>
            <tr>
                <td>Reference #:</td>
                <td class="font-semibold"><?php echo htmlspecialchars($data['reference_number'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Period Covered:</td>
                <td class="font-semibold">
                    <?php 
                    $startDate = !empty($data['period_start']) ? date('M j, Y', strtotime($data['period_start'])) : 'N/A';
                    $endDate = !empty($data['period_end']) ? date('M j, Y', strtotime($data['period_end'])) : 'N/A';
                    echo "$startDate to $endDate";
                    ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="terms mt-8 text-xs text-gray-600">
        <p class="mb-2">Thank you for your payment!</p>
        <p>This receipt is your official record of payment. Please retain for your records.</p>
    </div>
    
    <div class="signature mt-12">
        <div class="border-t-2 border-gray-400 w-1/3 mx-auto pt-2">
            <p class="text-center">Authorized Signature</p>
        </div>
    </div>
</div>
