<div class="occupancy-report">
    <h2 class="text-xl font-semibold mb-2">Occupancy Report</h2>
    <p class="text-sm text-gray-600 mb-6">Generated on: <?php echo date('F j, Y'); ?></p>
    
    <div class="summary-cards grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-blue-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-blue-800">Total Stalls</h3>
            <p class="text-3xl font-bold"><?php echo $data['total_stalls']; ?></p>
        </div>
        <div class="bg-green-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-green-800">Occupied</h3>
            <p class="text-3xl font-bold"><?php echo $data['occupied']; ?></p>
            <p class="text-sm text-green-600">
                <?php echo $data['total_stalls'] > 0 ? round(($data['occupied'] / $data['total_stalls']) * 100, 1) : 0; ?>% occupancy rate
            </p>
        </div>
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-800">Vacant</h3>
            <p class="text-3xl font-bold"><?php echo $data['vacant']; ?></p>
            <p class="text-sm text-gray-600">
                <?php echo $data['total_stalls'] > 0 ? round(($data['vacant'] / $data['total_stalls']) * 100, 1) : 0; ?>% vacancy rate
            </p>
        </div>
    </div>
    
    <div class="occupancy-by-floor mb-8">
        <h3 class="text-lg font-semibold mb-4 border-b pb-2">Occupancy by Floor/Zone</h3>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border p-2 text-left">Floor/Zone</th>
                        <th class="border p-2 text-right">Total Stalls</th>
                        <th class="border p-2 text-right">Occupied</th>
                        <th class="border p-2 text-right">Vacant</th>
                        <th class="border p-2 text-right w-1/3">Occupancy Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalStalls = 0;
                    $totalOccupied = 0;
                    
                    foreach ($data['by_floor'] as $floor): 
                        $vacant = $floor['total'] - $floor['occupied'];
                        $occupancyRate = $floor['total'] > 0 ? ($floor['occupied'] / $floor['total']) * 100 : 0;
                        
                        $totalStalls += $floor['total'];
                        $totalOccupied += $floor['occupied'];
                    ?>
                        <tr>
                            <td class="border p-2"><?php echo htmlspecialchars($floor['floor']); ?></td>
                            <td class="border p-2 text-right"><?php echo $floor['total']; ?></td>
                            <td class="border p-2 text-right">
                                <span class="text-green-700"><?php echo $floor['occupied']; ?></span>
                            </td>
                            <td class="border p-2 text-right">
                                <span class="text-gray-600"><?php echo $vacant; ?></span>
                            </td>
                            <td class="border p-2">
                                <div class="flex items-center">
                                    <div class="w-full bg-gray-200 rounded-full h-4 mr-2">
                                        <div class="bg-blue-600 h-4 rounded-full" style="width: <?php echo $occupancyRate; ?>%"></div>
                                    </div>
                                    <span class="text-xs"><?php echo number_format($occupancyRate, 1); ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-semibold">
                        <td class="border p-2">Total</td>
                        <td class="border p-2 text-right"><?php echo $totalStalls; ?></td>
                        <td class="border p-2 text-right"><?php echo $totalOccupied; ?></td>
                        <td class="border p-2 text-right"><?php echo $totalStalls - $totalOccupied; ?></td>
                        <td class="border p-2 text-right">
                            <?php 
                            $totalOccupancyRate = $totalStalls > 0 ? ($totalOccupied / $totalStalls) * 100 : 0;
                            echo number_format($totalOccupancyRate, 1); 
                            ?>%
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <div class="occupancy-trends mb-8">
        <h3 class="text-lg font-semibold mb-4 border-b pb-2">Occupancy Trends</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="font-medium mb-2">By Floor/Zone</h4>
                <div class="space-y-4">
                    <?php foreach ($data['by_floor'] as $floor): 
                        $occupancyRate = $floor['total'] > 0 ? ($floor['occupied'] / $floor['total']) * 100 : 0;
                    ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span><?php echo htmlspecialchars($floor['floor']); ?></span>
                                <span><?php echo number_format($occupancyRate, 1); ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-green-600 h-3 rounded-full" style="width: <?php echo $occupancyRate; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex items-center justify-center">
                <div class="w-64 h-64">
                    <canvas id="occupancyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="vacant-stalls mb-8">
        <h3 class="text-lg font-semibold mb-4 border-b pb-2">Vacant Stalls</h3>
        
        <?php 
        // In a real implementation, you would fetch actual vacant stalls from the database
        $sampleVacantStalls = [
            ['location' => 'GF-101', 'size' => '20 sqm', 'type' => 'Retail', 'last_occupied' => 'Jan 2023'],
            ['location' => 'GF-105', 'size' => '15 sqm', 'type' => 'Food', 'last_occupied' => 'Mar 2023'],
            ['location' => '1F-205', 'size' => '25 sqm', 'type' => 'Service', 'last_occupied' => 'Feb 2023'],
            ['location' => '2F-310', 'size' => '18 sqm', 'type' => 'Retail', 'last_occupied' => 'New'],
        ];
        ?>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border p-2 text-left">Stall #</th>
                        <th class="border p-2 text-left">Size</th>
                        <th class="border p-2 text-left">Type</th>
                        <th class="border p-2 text-left">Last Occupied</th>
                        <th class="border p-2 text-left">Vacant For</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sampleVacantStalls as $stall): 
                        $vacantMonths = $stall['last_occupied'] === 'New' ? 'New' : 
                            (date('n') - date('n', strtotime($stall['last_occupied']))) . ' months';
                    ?>
                        <tr>
                            <td class="border p-2"><?php echo $stall['location']; ?></td>
                            <td class="border p-2"><?php echo $stall['size']; ?></td>
                            <td class="border p-2"><?php echo $stall['type']; ?></td>
                            <td class="border p-2"><?php echo $stall['last_occupied']; ?></td>
                            <td class="border p-2">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php echo $stall['last_occupied'] === 'New' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $vacantMonths; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="notes">
        <h4 class="font-semibold mb-2">Notes:</h4>
        <ul class="list-disc list-inside text-sm text-gray-600">
            <li>Occupancy rate is calculated as (Occupied Stalls / Total Stalls) * 100</li>
            <li>Vacant stalls are those without an active lease or payment</li>
            <li>Report includes all types of stalls (retail, food, service, etc.)</li>
            <li>Data is current as of <?php echo date('F j, Y'); ?></li>
        </ul>
    </div>
    
    <div class="signature mt-12">
        <div class="flex justify-between">
            <div class="border-t-2 border-gray-400 w-1/3 pt-2">
                <p class="text-center">Property Manager</p>
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
    const ctx = document.getElementById('occupancyChart').getContext('2d');
    const floors = <?php echo json_encode(array_column($data['by_floor'], 'floor')); ?>;
    const occupied = <?php echo json_encode(array_column($data['by_floor'], 'occupied')); ?>;
    const total = <?php echo json_encode(array_column($data['by_floor'], 'total')); ?>;
    
    // Calculate vacancy for each floor
    const vacancy = total.map((t, i) => t - occupied[i]);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: floors,
            datasets: [
                {
                    label: 'Occupied',
                    data: occupied,
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Vacant',
                    data: vacancy,
                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    stacked: true,
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            const datasetIndex = context.datasetIndex;
                            const dataIndex = context.dataIndex;
                            const total = context.chart.data.datasets
                                .map(dataset => dataset.data[dataIndex])
                                .reduce((a, b) => a + b, 0);
                            const value = context.raw;
                            const percentage = Math.round((value / total) * 100);
                            return `${percentage}% of total`;
                        }
                    }
                }
            }
        }
    });
});
</script>
