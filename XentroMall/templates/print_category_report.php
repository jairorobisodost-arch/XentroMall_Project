<div class="category-report">
    <h2 class="text-xl font-semibold mb-2">Category Summary Report</h2>
    <p class="text-sm text-gray-600 mb-6">Generated on: <?php echo date('F j, Y'); ?></p>
    
    <div class="summary-cards grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-blue-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-blue-800">Total Stalls</h3>
            <p class="text-3xl font-bold"><?php echo $data['total_stalls']; ?></p>
        </div>
        <div class="bg-green-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-green-800">Occupied Stalls</h3>
            <p class="text-3xl font-bold"><?php echo $data['occupied']; ?></p>
            <p class="text-sm text-green-600">
                (<?php echo $data['total_stalls'] > 0 ? round(($data['occupied'] / $data['total_stalls']) * 100, 1) : 0; ?>% occupancy rate)
            </p>
        </div>
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-800">Vacant Stalls</h3>
            <p class="text-3xl font-bold"><?php echo $data['vacant']; ?></p>
            <p class="text-sm text-gray-600">
                (<?php echo $data['total_stalls'] > 0 ? round(($data['vacant'] / $data['total_stalls']) * 100, 1) : 0; ?>% vacancy rate)
            </p>
        </div>
    </div>
    
    <div class="category-breakdown mb-8">
        <h3 class="text-lg font-semibold mb-4 border-b pb-2">Breakdown by Business Category</h3>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border p-2 text-left">Business Category</th>
                        <th class="border p-2 text-right">Number of Stalls</th>
                        <th class="border p-2 text-right">Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalStalls = $data['total_stalls'];
                    foreach ($data['categories'] as $category): 
                        $percentage = $totalStalls > 0 ? ($category['count'] / $totalStalls) * 100 : 0;
                    ?>
                        <tr>
                            <td class="border p-2"><?php echo htmlspecialchars($category['business_type'] ?? 'Uncategorized'); ?></td>
                            <td class="border p-2 text-right"><?php echo $category['count']; ?></td>
                            <td class="border p-2 text-right">
                                <?php echo number_format($percentage, 1); ?>%
                                <div class="w-full bg-gray-200 rounded-full h-2.5 mt-1">
                                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-semibold">
                        <td class="border p-2">Total</td>
                        <td class="border p-2 text-right"><?php echo $totalStalls; ?></td>
                        <td class="border p-2 text-right">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <div class="occupancy-by-category mb-8">
        <h3 class="text-lg font-semibold mb-4 border-b pb-2">Occupancy by Category</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php 
            // In a real implementation, you would fetch occupancy data by category
            $sampleCategories = array_slice($data['categories'], 0, 4);
            foreach ($sampleCategories as $category): 
                $occupied = rand(1, $category['count']);
                $percentage = $category['count'] > 0 ? ($occupied / $category['count']) * 100 : 0;
            ?>
                <div class="border rounded-lg p-4">
                    <h4 class="font-medium mb-2"><?php echo htmlspecialchars($category['business_type'] ?? 'Uncategorized'); ?></h4>
                    <div class="flex justify-between text-sm mb-1">
                        <span>Occupied: <?php echo $occupied; ?></span>
                        <span>Vacant: <?php echo $category['count'] - $occupied; ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-green-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <div class="text-right text-sm text-gray-600 mt-1">
                        <?php echo number_format($percentage, 1); ?>% occupancy
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="notes">
        <h4 class="font-semibold mb-2">Notes:</h4>
        <ul class="list-disc list-inside text-sm text-gray-600">
            <li>Data is current as of <?php echo date('F j, Y'); ?></li>
            <li>Occupancy rate is calculated as (Occupied Stalls / Total Stalls) * 100</li>
            <li>Categories with no assigned stalls are not shown in the report</li>
        </ul>
    </div>
    
    <div class="signature mt-12">
        <div class="flex justify-between">
            <div class="border-t-2 border-gray-400 w-1/3 pt-2">
                <p class="text-center">Prepared By</p>
            </div>
            <div class="border-t-2 border-gray-400 w-1/3 pt-2">
                <p class="text-center">Approved By</p>
            </div>
        </div>
    </div>
</div>
