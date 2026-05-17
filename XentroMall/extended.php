<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle BIR approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $submissionId = $_POST['submission_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $adminUsername = $_SESSION['username'] ?? 'Admin';
    
    if ($submissionId && in_array($action, ['approve', 'reject'])) {
        try {
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            $birExpiryDate = $_POST['bir_expiry_date'] ?? null;
            
            $stmt = $pdo->prepare("UPDATE extended_bir SET status = ?, admin_remarks = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $remarks, $adminUsername, $submissionId]);
            
            if ($status === 'approved' && !empty($birExpiryDate)) {
                // Get user info to update tenant_details
                $stmtInfo = $pdo->prepare("SELECT user_id, email FROM extended_bir WHERE id = ?");
                $stmtInfo->execute([$submissionId]);
                $submission = $stmtInfo->fetch();
                
                if ($submission) {
                    $stmtUpdate = $pdo->prepare("UPDATE tenant_details SET bir_expiry_date = ? WHERE user_id = ? OR email = ?");
                    $stmtUpdate->execute([$birExpiryDate, $submission['user_id'], $submission['email']]);
                }
            }
            
            $_SESSION['success_message'] = "Extended BIR submission " . $status . " successfully.";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    }
    
    header("Location: extended.php");
    exit;
}

// Handle new BIR submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bir'])) {
    $userId = $_POST['user_id'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $businessName = $_POST['business_name'] ?? '';
    $tinNumber = $_POST['tin_number'] ?? '';
    $businessAddress = $_POST['business_address'] ?? '';
    $contactPerson = $_POST['contact_person'] ?? '';
    $contactNumber = $_POST['contact_number'] ?? '';
    $submissionType = $_POST['submission_type'] ?? 'application';
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/extended_bir/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = basename($_FILES['document']['name']);
        $targetFile = $uploadDir . uniqid() . '_' . $fileName;
        
        if (move_uploaded_file($_FILES['document']['tmp_name'], $targetFile)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO extended_bir (user_id, username, email, business_name, tin_number, business_address, contact_person, contact_number, document_path, submission_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $username, $email, $businessName, $tinNumber, $businessAddress, $contactPerson, $contactNumber, $targetFile, $submissionType]);
                
                $_SESSION['success_message'] = "Extended BIR submission added successfully.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = "Failed to upload document.";
        }
    } else {
        $_SESSION['error_message'] = "Please select a document to upload.";
    }
    
    header("Location: extended.php");
    exit;
}

// Fetch Extended BIR submissions
try {
    $stmt = $pdo->prepare("SELECT * FROM extended_bir ORDER BY submitted_at DESC");
    $stmt->execute();
    $birSubmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $birSubmissions = [];
    $error_message = "Error fetching BIR submissions: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Extended BIR Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .scrollbar-thin::-webkit-scrollbar { width: 6px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background-color: #10b981; border-radius: 8px; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-gradient-to-r from-emerald-600 to-emerald-500 text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center gap-4">
                        <a href="admin_dashboard.php" class="flex items-center gap-2 text-white hover:text-emerald-100 transition-colors">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Dashboard</span>
                        </a>
                        <div class="h-6 w-px bg-emerald-400"></div>
                        <h1 class="text-xl font-semibold">Extended BIR Management</h1>
                    </div>
                    <div class="flex items-center gap-4">
                        <button onclick="openAddModal()" class="bg-emerald-700 hover:bg-emerald-800 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add New BIR
                        </button>
                        <span class="text-emerald-100">Admin Panel</span>
                        <div class="w-8 h-8 bg-emerald-700 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-sm"></i>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="mb-6 rounded-lg bg-emerald-50 text-emerald-700 px-4 py-3 border border-emerald-200">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="mb-6 rounded-lg bg-red-50 text-red-700 px-4 py-3 border border-red-200">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Total Submissions</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo count($birSubmissions); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Pending</p>
                            <p class="text-2xl font-bold text-yellow-600">
                                <?php echo count(array_filter($birSubmissions, fn($s) => $s['status'] === 'pending')); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Approved</p>
                            <p class="text-2xl font-bold text-emerald-600">
                                <?php echo count(array_filter($birSubmissions, fn($s) => $s['status'] === 'approved')); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Rejected</p>
                            <p class="text-2xl font-bold text-red-600">
                                <?php echo count(array_filter($birSubmissions, fn($s) => $s['status'] === 'rejected')); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BIR Submissions Table -->
            <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Extended BIR Submissions</h2>
                    <p class="text-sm text-gray-500 mt-1">Manage all Extended BIR registrations</p>
                </div>

                <div class="overflow-x-auto">
                    <?php if (empty($birSubmissions)): ?>
                        <div class="p-8 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-file-alt text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No BIR Submissions</h3>
                            <p class="text-gray-500">There are no Extended BIR submissions to display at this time.</p>
                            <button onclick="openAddModal()" class="mt-4 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                <i class="fas fa-plus mr-2"></i>Add First BIR Submission
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant Info</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($birSubmissions as $submission): ?>
                                    <tr class="hover:bg-gray-50">
                                        <!-- ID -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            #<?php echo $submission['id']; ?>
                                        </td>

                                        <!-- Tenant Info -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-user text-emerald-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($submission['username']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($submission['email']); ?>
                                                    </div>
                                                    <?php if ($submission['contact_person']): ?>
                                                        <div class="text-xs text-gray-400">
                                                            <?php echo htmlspecialchars($submission['contact_person']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Business Details -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm">
                                                <?php if ($submission['business_name']): ?>
                                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($submission['business_name']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($submission['tin_number']): ?>
                                                    <div class="text-gray-500">TIN: <?php echo htmlspecialchars($submission['tin_number']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($submission['contact_number']): ?>
                                                    <div class="text-xs text-gray-400"><?php echo htmlspecialchars($submission['contact_number']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Type -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($submission['submission_type'] === 'renewal'): ?>
                                                <span class="inline-flex items-center gap-2 px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">
                                                    <i class="fas fa-sync-alt"></i>
                                                    <span>Renewal</span>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-2 px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">
                                                    <i class="fas fa-file-alt"></i>
                                                    <span>Application</span>
                                                </span>
                                            <?php endif; ?>
                                            <div class="text-xs text-gray-400 mt-1">
                                                <?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?>
                                            </div>
                                        </td>

                                        <!-- Document -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="<?php echo htmlspecialchars($submission['document_path']); ?>" 
                                               target="_blank" 
                                               class="inline-flex items-center gap-2 px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium hover:bg-indigo-200 transition-colors">
                                                <i class="fas fa-file-pdf"></i>
                                                <span>View Document</span>
                                            </a>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                                $status = $submission['status'];
                                                $statusClasses = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'approved' => 'bg-emerald-100 text-emerald-800',
                                                    'rejected' => 'bg-red-100 text-red-800'
                                                ];
                                                $statusIcons = [
                                                    'pending' => 'fas fa-clock',
                                                    'approved' => 'fas fa-check-circle',
                                                    'rejected' => 'fas fa-times-circle'
                                                ];
                                            ?>
                                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium <?php echo $statusClasses[$status]; ?>">
                                                <i class="<?php echo $statusIcons[$status]; ?>"></i>
                                                <span><?php echo ucfirst($status); ?></span>
                                            </span>
                                            <?php if ($submission['processed_at']): ?>
                                                <div class="text-xs text-gray-400 mt-1">
                                                    <?php echo date('M j, Y', strtotime($submission['processed_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($submission['processed_by']): ?>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    by <?php echo htmlspecialchars($submission['processed_by']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($submission['admin_remarks']): ?>
                                                <div class="text-xs text-gray-600 mt-1 italic">
                                                    "<?php echo htmlspecialchars($submission['admin_remarks']); ?>"
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php if ($submission['status'] === 'pending'): ?>
                                                <div class="flex items-center gap-2">
                                                    <button onclick="openApprovalModal(<?php echo htmlspecialchars(json_encode($submission)); ?>)" 
                                                            class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition-colors">
                                                        <i class="fas fa-check text-xs"></i>
                                                        <span>Approve</span>
                                                    </button>
                                                    <button onclick="openRejectionModal(<?php echo htmlspecialchars(json_encode($submission)); ?>)" 
                                                            class="inline-flex items-center gap-1 px-3 py-1 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                                                        <i class="fas fa-times text-xs"></i>
                                                        <span>Reject</span>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">
                                                    <?php echo $submission['processed_at'] ? 'Processed: ' . date('M j, Y', strtotime($submission['processed_at'])) : 'Processed'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add New BIR Modal -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Add New Extended BIR Submission</h3>
                <p class="text-sm text-gray-500 mt-1">Add a new Extended BIR registration</p>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="add_bir" value="1">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">User ID</label>
                        <input type="number" name="user_id" id="user_id" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" name="username" id="username" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" id="email" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="business_name" class="block text-sm font-medium text-gray-700 mb-1">Business Name</label>
                        <input type="text" name="business_name" id="business_name" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="tin_number" class="block text-sm font-medium text-gray-700 mb-1">TIN Number</label>
                        <input type="text" name="tin_number" id="tin_number" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                </div>
                
                <div>
                    <label for="business_address" class="block text-sm font-medium text-gray-700 mb-1">Business Address</label>
                    <textarea name="business_address" id="business_address" rows="2" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                        <input type="text" name="contact_person" id="contact_person" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                        <input type="text" name="contact_number" id="contact_number" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                </div>
                
                <div>
                    <label for="submission_type" class="block text-sm font-medium text-gray-700 mb-1">Submission Type</label>
                    <select name="submission_type" id="submission_type" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        <option value="application">Application</option>
                        <option value="renewal">Renewal</option>
                    </select>
                </div>
                
                <div>
                    <label for="document" class="block text-sm font-medium text-gray-700 mb-1">BIR Document <span class="text-red-500">*</span></label>
                    <input type="file" name="document" id="document" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Accepted formats: PDF, JPG, PNG, DOC, DOCX</p>
                </div>
                
                <div class="flex items-center justify-end gap-3 pt-4">
                    <button type="button" onclick="closeAddModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition-colors">
                        Add BIR Submission
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Approve Extended BIR</h3>
                <p class="text-sm text-gray-500 mt-1">Approve this Extended BIR registration</p>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="submission_id" id="approvalSubmissionId">
                <input type="hidden" name="action" value="approve">
                
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-emerald-900 mb-2">Submission Information</h4>
                    <div id="approvalSubmissionInfo" class="text-sm text-emerald-800"></div>
                </div>
                
                <div>
                    <label for="approval_remarks" class="block text-sm font-medium text-gray-700 mb-1">Approval Remarks (Optional)</label>
                    <textarea name="remarks" id="approval_remarks" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                              placeholder="Add any remarks for this approval..."></textarea>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <label for="bir_expiry_date" class="block text-sm font-bold text-blue-900 mb-1">
                        <i class="fas fa-calendar-alt mr-1"></i> BIR Registration Expiry Date
                    </label>
                    <input type="date" name="bir_expiry_date" id="bir_expiry_date" required
                           class="w-full px-3 py-2 border border-blue-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                    <p class="text-xs text-blue-700 mt-1">Set the expiration date for this tenant's BIR registration according to the document.</p>
                </div>
                
                <div class="flex items-center justify-end gap-3 pt-4">
                    <button type="button" onclick="closeApprovalModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition-colors">
                        Approve BIR
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Reject Extended BIR</h3>
                <p class="text-sm text-gray-500 mt-1">Reject this Extended BIR registration</p>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="submission_id" id="rejectionSubmissionId">
                <input type="hidden" name="action" value="reject">
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-red-900 mb-2">Submission Information</h4>
                    <div id="rejectionSubmissionInfo" class="text-sm text-red-800"></div>
                </div>
                
                <div>
                    <label for="rejection_remarks" class="block text-sm font-medium text-gray-700 mb-1">Rejection Reason <span class="text-red-500">*</span></label>
                    <textarea name="remarks" id="rejection_remarks" rows="3" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-red-500 focus:border-transparent"
                              placeholder="Please provide a reason for rejection..."></textarea>
                </div>
                
                <div class="flex items-center justify-end gap-3 pt-4">
                    <button type="button" onclick="closeRejectionModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        Reject BIR
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        function openApprovalModal(submission) {
            document.getElementById('approvalSubmissionId').value = submission.id;
            
            const submissionInfo = `
                <div><strong>ID:</strong> #${submission.id}</div>
                <div><strong>Tenant:</strong> ${submission.username}</div>
                <div><strong>Email:</strong> ${submission.email}</div>
                <div><strong>Business:</strong> ${submission.business_name || 'N/A'}</div>
                <div><strong>Type:</strong> ${submission.submission_type}</div>
            `;
            document.getElementById('approvalSubmissionInfo').innerHTML = submissionInfo;
            
            document.getElementById('approvalModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        function openRejectionModal(submission) {
            document.getElementById('rejectionSubmissionId').value = submission.id;
            
            const submissionInfo = `
                <div><strong>ID:</strong> #${submission.id}</div>
                <div><strong>Tenant:</strong> ${submission.username}</div>
                <div><strong>Email:</strong> ${submission.email}</div>
                <div><strong>Business:</strong> ${submission.business_name || 'N/A'}</div>
                <div><strong>Type:</strong> ${submission.submission_type}</div>
            `;
            document.getElementById('rejectionSubmissionInfo').innerHTML = submissionInfo;
            
            document.getElementById('rejectionModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeRejectionModal() {
            document.getElementById('rejectionModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        // Close modals when clicking outside
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddModal();
        });
        
        document.getElementById('approvalModal').addEventListener('click', function(e) {
            if (e.target === this) closeApprovalModal();
        });
        
        document.getElementById('rejectionModal').addEventListener('click', function(e) {
            if (e.target === this) closeRejectionModal();
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeApprovalModal();
                closeRejectionModal();
            }
        });
    </script>
</body>
</html>