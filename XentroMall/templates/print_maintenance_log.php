<div class="maintenance-log">
    <h2 class="text-xl font-semibold mb-4">Maintenance Logs - <?php echo ucfirst(htmlspecialchars($zone)); ?> Floor</h2>
    
    <?php if (!empty($data)): ?>
        <table class="w-full">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border p-2">Date</th>
                    <th class="border p-2">Issue</th>
                    <th class="border p-2">Reported By</th>
                    <th class="border p-2">Status</th>
                    <th class="border p-2">Completed Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $log): ?>
                    <tr>
                        <td class="border p-2"><?php echo !empty($log['reported_date']) ? date('M j, Y', strtotime($log['reported_date'])) : 'N/A'; ?></td>
                        <td class="border p-2"><?php echo htmlspecialchars($log['issue_description'] ?? 'N/A'); ?></td>
                        <td class="border p-2"><?php echo htmlspecialchars($log['reported_by'] ?? 'N/A'); ?></td>
                        <td class="border p-2">
                            <?php 
                            $status = $log['status'] ?? 'pending';
                            $statusClass = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'in_progress' => 'bg-blue-100 text-blue-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800'
                            ][$status] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $statusClass; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                            </span>
                        </td>
                        <td class="border p-2">
                            <?php 
                            echo !empty($log['completed_date']) 
                                ? date('M j, Y', strtotime($log['completed_date'])) 
                                : 'N/A'; 
                            ?>
                        </td>
                    </tr>
                    <?php if (!empty($log['resolution_notes'])): ?>
                        <tr>
                            <td colspan="5" class="border p-2 bg-gray-50">
                                <p class="font-semibold">Resolution Notes:</p>
                                <p><?php echo nl2br(htmlspecialchars($log['resolution_notes'])); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="text-center py-8">
            <p class="text-gray-500">No maintenance logs found for this zone.</p>
        </div>
    <?php endif; ?>
    
    <div class="summary mt-8">
        <h3 class="font-semibold mb-2">Summary</h3>
        <div class="grid grid-cols-4 gap-4 text-center">
            <div class="bg-blue-50 p-3 rounded">
                <p class="text-2xl font-bold">
                    <?php echo count(array_filter($data, fn($log) => ($log['status'] ?? '') === 'completed')); ?>
                </p>
                <p class="text-sm">Completed</p>
            </div>
            <div class="bg-yellow-50 p-3 rounded">
                <p class="text-2xl font-bold">
                    <?php echo count(array_filter($data, fn($log) => ($log['status'] ?? '') === 'pending')); ?>
                </p>
                <p class="text-sm">Pending</p>
            </div>
            <div class="bg-blue-100 p-3 rounded">
                <p class="text-2xl font-bold">
                    <?php echo count(array_filter($data, fn($log) => ($log['status'] ?? '') === 'in_progress')); ?>
                </p>
                <p class="text-sm">In Progress</p>
            </div>
            <div class="bg-red-50 p-3 rounded">
                <p class="text-2xl font-bold">
                    <?php echo count(array_filter($data, fn($log) => ($log['status'] ?? '') === 'cancelled')); ?>
                </p>
                <p class="text-sm">Cancelled</p>
            </div>
        </div>
    </div>
    
    <div class="signature mt-8">
        <div class="flex justify-between">
            <div class="border-t-2 border-gray-400 w-1/3 pt-2">
                <p class="text-center">Prepared By</p>
            </div>
            <div class="border-t-2 border-gray-400 w-1/3 pt-2">
                <p class="text-center">Noted By</p>
            </div>
        </div>
    </div>
</div>
