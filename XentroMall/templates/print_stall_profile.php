<div class="stall-profile">
    <div class="flex justify-between items-center mb-6 pb-4 border-b">
        <div>
            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($data['tradename'] ?? 'Stall Profile'); ?></h1>
            <p class="text-gray-600">Generated on <?php echo date('F j, Y h:i A'); ?></p>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-600">Stall ID: <?php echo htmlspecialchars($data['id'] ?? 'N/A'); ?></p>
            <p class="text-sm text-gray-600">Status: <span class="font-semibold <?php echo ($data['status'] ?? '') === 'active' ? 'text-green-600' : 'text-red-600'; ?>">
                <?php echo ucfirst(htmlspecialchars($data['status'] ?? 'N/A')); ?>
            </span></p>
        </div>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4 pb-2 border-b">Stall Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="font-semibold text-gray-700 mb-2">Business Details</h3>
                <div class="space-y-2">
                    <p><span class="font-medium">Trade Name:</span> <?php echo htmlspecialchars($data['tradename'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">Store Location:</span> <?php echo htmlspecialchars($data['store_location'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">Ownership Type:</span> <?php echo htmlspecialchars($data['ownership'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">Business Type:</span> <?php echo htmlspecialchars($data['business_type'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">Company Name:</span> <?php echo htmlspecialchars($data['company_name'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">Business Address:</span> <?php echo htmlspecialchars($data['business_address'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">TIN:</span> <?php echo htmlspecialchars($data['tin'] ?? 'N/A'); ?></p>
                </div>
            </div>
            
            <div>
                <h3 class="font-semibold text-gray-700 mb-2">Contact Information</h3>
                <div class="space-y-2">
                    <p><span class="font-medium">Tenant Representative:</span> <?php echo htmlspecialchars($data['tenant_representative'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">Contact Person:</span> <?php echo htmlspecialchars($data['contact_person'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">Position:</span> <?php echo htmlspecialchars($data['position'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">Contact Number:</span> <?php echo htmlspecialchars($data['contact_tel'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">Mobile:</span> <?php echo htmlspecialchars($data['mobile'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">Email:</span> <?php echo htmlspecialchars($data['user_email'] ?? 'N/A'); ?></p>
                    <p><span class="font-medium">Office Tel:</span> <?php echo htmlspecialchars($data['office_tel'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($data['lease_info'])): ?>
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4 pb-2 border-b">Lease Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="font-semibold text-gray-700 mb-2">Lease Terms</h3>
                <div class="space-y-2">
                    <p><span class="font-medium">Lease Start Date:</span> <?php echo !empty($data['lease_info']['start_date']) ? date('F j, Y', strtotime($data['lease_info']['start_date'])) : 'N/A'; ?></p>
                    <p><span class="font-medium">Lease End Date:</span> <?php echo !empty($data['lease_info']['end_date']) ? date('F j, Y', strtotime($data['lease_info']['end_date'])) : 'N/A'; ?></p>
                    <p><span class="font-medium">Monthly Rent:</span> <?php echo isset($data['lease_info']['monthly_rent']) ? '₱' . number_format($data['lease_info']['monthly_rent'], 2) : 'N/A'; ?></p>
                    <p><span class="font-medium">Security Deposit:</span> <?php echo isset($data['lease_info']['security_deposit']) ? '₱' . number_format($data['lease_info']['security_deposit'], 2) : 'N/A'; ?></p>
                </div>
            </div>
            <div>
                <h3 class="font-semibold text-gray-700 mb-2">Additional Terms</h3>
                <div class="space-y-2">
                    <p><span class="font-medium">Lease Type:</span> <?php echo !empty($data['lease_info']['lease_type']) ? ucfirst(htmlspecialchars($data['lease_info']['lease_type'])) : 'N/A'; ?></p>
                    <p><span class="font-medium">Payment Due Day:</span> <?php echo !empty($data['lease_info']['payment_due_day']) ? $data['lease_info']['payment_due_day'] . ' of each month' : 'N/A'; ?></p>
                    <p><span class="font-medium">Late Fee:</span> <?php echo isset($data['lease_info']['late_fee']) ? '₱' . number_format($data['lease_info']['late_fee'], 2) : 'N/A'; ?></p>
                    <p><span class="font-medium">Lease Status:</span> <span class="font-semibold <?php echo ($data['lease_info']['status'] ?? '') === 'active' ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo !empty($data['lease_info']['status']) ? ucfirst(htmlspecialchars($data['lease_info']['status'])) : 'N/A'; ?>
                    </span></p>
                </div>
            </div>
        </div>
        <?php if (!empty($data['lease_info']['terms'])): ?>
        <div class="mt-4">
            <h4 class="font-semibold text-gray-700 mb-1">Special Terms & Conditions</h4>
            <div class="bg-gray-50 p-3 rounded border">
                <?php echo nl2br(htmlspecialchars($data['lease_info']['terms'])); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4 pb-2 border-b">Inspection Records</h2>
        <?php if (!empty($data['inspections'])): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 border text-left">Date</th>
                            <th class="py-2 px-4 border text-left">Type</th>
                            <th class="py-2 px-4 border text-left">Inspector</th>
                            <th class="py-2 px-4 border text-left">Status</th>
                            <th class="py-2 px-4 border text-left">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['inspections'] as $inspection): ?>
                        <tr>
                            <td class="py-2 px-4 border"><?php echo !empty($inspection['inspection_date']) ? date('M j, Y', strtotime($inspection['inspection_date'])) : 'N/A'; ?></td>
                            <td class="py-2 px-4 border"><?php echo !empty($inspection['inspection_type']) ? ucfirst(htmlspecialchars($inspection['inspection_type'])) : 'N/A'; ?></td>
                            <td class="py-2 px-4 border"><?php echo !empty($inspection['inspector_name']) ? htmlspecialchars($inspection['inspector_name']) : 'N/A'; ?></td>
                            <td class="py-2 px-4 border">
                                <?php 
                                $statusClass = '';
                                $status = strtolower($inspection['status'] ?? '');
                                if ($status === 'passed') $statusClass = 'bg-green-100 text-green-800';
                                elseif ($status === 'failed') $statusClass = 'bg-red-100 text-red-800';
                                else $statusClass = 'bg-yellow-100 text-yellow-800';
                                ?>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $statusClass; ?>">
                                    <?php echo !empty($inspection['status']) ? ucfirst(htmlspecialchars($inspection['status'])) : 'Pending'; ?>
                                </span>
                            </td>
                            <td class="py-2 px-4 border"><?php echo !empty($inspection['notes']) ? htmlspecialchars(substr($inspection['notes'], 0, 50)) . (strlen($inspection['notes']) > 50 ? '...' : '') : 'N/A'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 italic">No inspection records found.</p>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <h2 class="text-xl font-semibold mb-4 pb-2 border-b">Recent Payments</h2>
            <?php if (!empty($data['recent_payments'])): ?>
                <div class="space-y-4">
                    <?php foreach ($data['recent_payments'] as $payment): ?>
                        <div class="border rounded p-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium"><?php echo '₱' . number_format($payment['amount'], 2); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo !empty($payment['payment_date']) ? date('M j, Y', strtotime($payment['payment_date'])) : 'N/A'; ?></p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                    <?php echo !empty($payment['payment_method']) ? ucfirst(htmlspecialchars($payment['payment_method'])) : 'N/A'; ?>
                                </span>
                            </div>
                            <p class="text-sm mt-1 text-gray-600"><?php echo !empty($payment['description']) ? htmlspecialchars($payment['description']) : 'No description'; ?></p>
                            <p class="text-xs mt-1 text-gray-500">Ref: <?php echo !empty($payment['reference_number']) ? htmlspecialchars($payment['reference_number']) : 'N/A'; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 italic">No recent payments found.</p>
            <?php endif; ?>
        </div>

        <div>
            <h2 class="text-xl font-semibold mb-4 pb-2 border-b">Recent Maintenance Requests</h2>
            <?php if (!empty($data['maintenance_requests'])): ?>
                <div class="space-y-4">
                    <?php foreach ($data['maintenance_requests'] as $request): ?>
                        <div class="border rounded p-3">
                            <div class="flex justify-between items-start">
                                <h3 class="font-medium"><?php echo !empty($request['title']) ? htmlspecialchars($request['title']) : 'No Title'; ?></h3>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php 
                                    $status = strtolower($request['status'] ?? '');
                                    if ($status === 'completed') echo 'bg-green-100 text-green-800';
                                    elseif ($status === 'in progress') echo 'bg-blue-100 text-blue-800';
                                    elseif ($status === 'pending') echo 'bg-yellow-100 text-yellow-800';
                                    else echo 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?php echo !empty($request['status']) ? ucfirst(htmlspecialchars($request['status'])) : 'Pending'; ?>
                                </span>
                            </div>
                            <p class="text-sm mt-1 text-gray-600">
                                <?php 
                                $description = $request['description'] ?? '';
                                echo strlen($description) > 60 ? htmlspecialchars(substr($description, 0, 60)) . '...' : htmlspecialchars($description);
                                ?>
                            </p>
                            <p class="text-xs mt-1 text-gray-500">
                                <?php echo !empty($request['created_at']) ? date('M j, Y', strtotime($request['created_at'])) : 'N/A'; ?>
                                <?php if (!empty($request['assigned_to'])): ?>
                                    • Assigned to: <?php echo htmlspecialchars($request['assigned_to']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 italic">No maintenance requests found.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-8 pt-4 border-t">
        <h2 class="text-xl font-semibold mb-4">Additional Notes</h2>
        <div class="border rounded p-4 bg-gray-50">
            <?php if (!empty($data['inspection_notes'])): ?>
                <?php echo nl2br(htmlspecialchars($data['inspection_notes'])); ?>
            <?php else: ?>
                <p class="text-gray-500 italic">No additional notes available.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="signature mt-8">
        <div class="border-t-2 border-gray-400 w-1/3 pt-2">
            <p class="text-center">Authorized Signature</p>
        </div>
    </div>
</div>
