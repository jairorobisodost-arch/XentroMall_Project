<div class="renewal-status">
    <h2 class="text-xl font-semibold mb-4">Lease Renewal Status Report</h2>
    <p class="text-sm text-gray-600 mb-6">Generated on: <?php echo date('F j, Y'); ?></p>
    
    <?php if (!empty($data)): ?>
        <div class="mb-6">
            <div class="grid grid-cols-4 gap-4 text-center mb-4">
                <div class="bg-blue-50 p-3 rounded">
                    <p class="text-2xl font-bold">
                        <?php echo count($data); ?>
                    </p>
                    <p class="text-sm">Total Renewals</p>
                </div>
                <div class="bg-green-50 p-3 rounded">
                    <p class="text-2xl font-bold">
                        <?php echo count(array_filter($data, fn($r) => strtotime($r['renewal_date']) > time())); ?>
                    </p>
                    <p class="text-sm">Upcoming</p>
                </div>
                <div class="bg-yellow-50 p-3 rounded">
                    <p class="text-2xl font-bold">
                        <?php echo count(array_filter($data, fn($r) => strtotime($r['renewal_date']) <= time() && strtotime($r['renewal_date']) > strtotime('-30 days'))); ?>
                    </p>
                    <p class="text-sm">Due Soon (30 days)</p>
                </div>
                <div class="bg-red-50 p-3 rounded">
                    <p class="text-2xl font-bold">
                        <?php echo count(array_filter($data, fn($r) => strtotime($r['renewal_date']) < strtotime('-30 days'))); ?>
                    </p>
                    <p class="text-sm">Overdue</p>
                </div>
            </div>
        </div>
        
        <table class="w-full">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border p-2">Tenant</th>
                    <th class="border p-2">Stall</th>
                    <th class="border p-2">Current Lease End</th>
                    <th class="border p-2">Renewal Date</th>
                    <th class="border p-2">Status</th>
                    <th class="border p-2">Contact</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Sort by renewal date (earliest first)
                usort($data, function($a, $b) {
                    return strtotime($a['renewal_date']) - strtotime($b['renewal_date']);
                });
                
                foreach ($data as $renewal): 
                    $renewalDate = strtotime($renewal['renewal_date']);
                    $now = time();
                    $thirtyDaysAgo = strtotime('-30 days');
                    $status = '';
                    $statusClass = '';
                    
                    if ($renewalDate > $now) {
                        $status = 'Upcoming';
                        $statusClass = 'bg-green-100 text-green-800';
                    } elseif ($renewalDate > $thirtyDaysAgo) {
                        $status = 'Due Soon';
                        $statusClass = 'bg-yellow-100 text-yellow-800';
                    } else {
                        $status = 'Overdue';
                        $statusClass = 'bg-red-100 text-red-800';
                    }
                    
                    $daysRemaining = floor(($renewalDate - $now) / (60 * 60 * 24));
                    $daysText = $daysRemaining > 0 ? "in $daysRemaining days" : abs($daysRemaining) . ' days ago';
                ?>
                    <tr>
                        <td class="border p-2"><?php echo htmlspecialchars($renewal['username'] ?? 'N/A'); ?></td>
                        <td class="border p-2"><?php echo htmlspecialchars($renewal['tradename'] ?? 'N/A'); ?></td>
                        <td class="border p-2">
                            <?php echo !empty($renewal['current_lease_end']) ? date('M j, Y', strtotime($renewal['current_lease_end'])) : 'N/A'; ?>
                        </td>
                        <td class="border p-2">
                            <?php echo date('M j, Y', $renewalDate); ?>
                            <div class="text-xs text-gray-500"><?php echo $daysText; ?></div>
                        </td>
                        <td class="border p-2">
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $statusClass; ?>">
                                <?php echo $status; ?>
                            </span>
                        </td>
                        <td class="border p-2">
                            <?php if (!empty($renewal['email'])): ?>
                                <div class="text-xs"><?php echo htmlspecialchars($renewal['email']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($renewal['mobile'])): ?>
                                <div class="text-xs"><?php echo htmlspecialchars($renewal['mobile']); ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="text-center py-8">
            <p class="text-gray-500">No renewal requests found.</p>
        </div>
    <?php endif; ?>
    
    <div class="notes mt-8">
        <h3 class="font-semibold mb-2">Notes:</h3>
        <ul class="list-disc list-inside text-sm text-gray-600">
            <li>Upcoming: Renewal date is in the future</li>
            <li>Due Soon: Renewal date is within the next 30 days</li>
            <li>Overdue: Renewal date has passed more than 30 days ago</li>
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
