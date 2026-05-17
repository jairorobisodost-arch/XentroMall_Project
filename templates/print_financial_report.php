<div class="financial-report">
    <h2 class="text-xl font-semibold mb-2">Financial Summary Report</h2>
    <p class="text-sm text-gray-600 mb-6">Generated on: <?php echo date('F j, Y'); ?></p>
    
    <div class="summary-cards grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-blue-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-blue-800">Total Revenue</h3>
            <p class="text-2xl font-bold">₱<?php echo number_format($data['total_revenue'], 2); ?></p>
            <p class="text-sm text-blue-600">All time</p>
        </div>
        <div class="bg-green-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-green-800">This Month</h3>
            <p class="text-2xl font-bold">
                ₱<?php 
                $currentMonth = date('Y-m');
                $monthlyTotal = 0;
                foreach ($data['monthly_data'] as $month) {
                    if (str_starts_with($month['month'], $currentMonth)) {
                        $monthlyTotal = $month['total'];
                        break;
                    }
                }
                echo number_format($monthlyTotal, 2); 
                ?>
            </p>
            <p class="text-sm text-green-600">
                <?php 
                $transactions = 0;
                foreach ($data['monthly_data'] as $month) {
                    if (str_starts_with($month['month'], $currentMonth)) {
                        $transactions = $month['transactions'];
                        break;
                    }
                }
                echo $transactions . ' transactions';
                ?>
            </p>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-purple-800">Payment Methods</h3>
            <p class="text-sm">
                <?php 
                $methods = [];
                foreach ($data['payment_methods'] as $method) {
                    $methods[] = ucfirst($method['payment_method']) . ' (' . $method['transactions'] . ')';
                }
                echo implode(', ', $methods);
                ?>
            </p>
        </div>
    </div>
    
    <div class="revenue-trends mb-8">
        <h3 class="text-lg font-semibold mb-4 border-b pb-2">Monthly Revenue Trends</h3>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border p-2 text-left">Month</th>
                        <th class="border p-2 text-right">Transactions</th>
                        <th class="border p-2 text-right">Total Revenue</th>
                        <th class="border p-2 text-right w-3/4">Trend</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $previousAmount = null;
                    $maxAmount = max(array_column($data['monthly_data'], 'total'));
                    
                    foreach (array_reverse($data['monthly_data']) as $month): 
                        $monthName = date('M Y', strtotime($month['month'] . '-01'));
                        $percentage = $maxAmount > 0 ? ($month['total'] / $maxAmount) * 100 : 0;
                        
                        $trendClass = '';
                        if ($previousAmount !== null) {
                            if ($month['total'] > $previousAmount) {
                                $trendClass = 'text-green-600';
                                $trendIcon = '↑';
                            } elseif ($month['total'] < $previousAmount) {
                                $trendClass = 'text-red-600';
                                $trendIcon = '↓';
                            } else {
                                $trendClass = 'text-gray-600';
                                $trendIcon = '→';
                            }
                        }
                        $previousAmount = $month['total'];
                    ?>
                        <tr>
                            <td class="border p-2"><?php echo $monthName; ?></td>
                            <td class="border p-2 text-right"><?php echo $month['transactions']; ?></td>
                            <td class="border p-2 text-right">₱<?php echo number_format($month['total'], 2); ?></td>
                            <td class="border p-2">
                                <div class="flex items-center">
                                    <div class="w-full bg-gray-200 rounded-full h-4 mr-2">
                                        <div class="bg-blue-600 h-4 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <span class="text-xs <?php echo $trendClass; ?> w-4 text-center"><?php echo $trendIcon ?? ''; ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="payment-methods mb-8">
        <h3 class="text-lg font-semibold mb-4 border-b pb-2">Revenue by Payment Method</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border p-2 text-left">Payment Method</th>
                            <th class="border p-2 text-right">Amount</th>
                            <th class="border p-2 text-right">% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['payment_methods'] as $method): 
                            $percentage = $data['total_revenue'] > 0 ? ($method['total'] / $data['total_revenue']) * 100 : 0;
                        ?>
                            <tr>
                                <td class="border p-2"><?php echo ucfirst($method['payment_method']); ?></td>
                                <td class="border p-2 text-right">₱<?php echo number_format($method['total'], 2); ?></td>
                                <td class="border p-2 text-right"><?php echo number_format($percentage, 1); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-center">
                <div class="w-64 h-64">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="notes">
        <h4 class="font-semibold mb-2">Notes:</h4>
        <ul class="list-disc list-inside text-sm text-gray-600">
            <li>All amounts are in Philippine Peso (₱)</li>
            <li>Data is current as of <?php echo date('F j, Y'); ?></li>
            <li>Monthly trends show the relative performance compared to the highest earning month</li>
        </ul>
    </div>
    
    <div class="signature mt-12">
        <div class="flex justify-between">
            <div class="border-t-2 border-gray-400 w-1/3 pt-2">
                <p class="text-center">Accountant</p>
            </div>
            <div class="border-t-2 border-gray-400 w-1/3 pt-2">
                <p class="text-center">Approved By</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('paymentMethodChart').getContext('2d');
    const paymentMethods = <?php echo json_encode(array_column($data['payment_methods'], 'payment_method')); ?>;
    const paymentAmounts = <?php echo json_encode(array_column($data['payment_methods'], 'total')); ?>;
    
    // Generate colors for each payment method
    const backgroundColors = [
        'rgba(54, 162, 235, 0.7)',
        'rgba(75, 192, 192, 0.7)',
        'rgba(255, 206, 86, 0.7)',
        'rgba(153, 102, 255, 0.7)',
        'rgba(255, 159, 64, 0.7)'
    ];
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: paymentMethods.map(method => method.charAt(0).toUpperCase() + method.slice(1)),
            datasets: [{
                data: paymentAmounts,
                backgroundColor: backgroundColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ₱${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
});
</script>
