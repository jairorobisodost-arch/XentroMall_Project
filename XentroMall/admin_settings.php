<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
	header('Location: login.php');
	exit;
}

$success = '';
$error = '';

// Fetch admin info
$stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = :id AND role = "admin"');
$stmt->execute(['id' => $_SESSION['user_id']]);
$admin = $stmt->fetch();

// If no admin found, try to get from admins table
if (!$admin) {
    $stmt = $pdo->prepare('SELECT username, email FROM admins WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $admin = $stmt->fetch();
}

// Set default values if still not found
if (!$admin) {
    $admin = [
        'username' => 'Admin',
        'email' => 'admin@xentromall.com'
    ];
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
	$current = $_POST['current_password'] ?? '';
	$new = $_POST['new_password'] ?? '';
	$confirm = $_POST['confirm_password'] ?? '';

	if (empty($current) || empty($new) || empty($confirm)) {
		$error = 'All password fields are required.';
	} elseif (strlen($new) < 8) {
		$error = 'New password must be at least 8 characters long.';
	} elseif ($new !== $confirm) {
		$error = 'New passwords do not match.';
	} else {
		// Try to get password from users table first
		$stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id AND role = "admin"');
		$stmt->execute(['id' => $_SESSION['user_id']]);
		$row = $stmt->fetch();
		
		// If not found, try admins table
		if (!$row) {
			$stmt = $pdo->prepare('SELECT password FROM admins WHERE id = :id');
			$stmt->execute(['id' => $_SESSION['user_id']]);
			$row = $stmt->fetch();
		}

		if ($row && password_verify($current, $row['password'])) {
			// Try to update in users table first
			$stmt = $pdo->prepare('UPDATE users SET password = :pw WHERE id = :id AND role = "admin"');
			$result = $stmt->execute([
				'pw' => password_hash($new, PASSWORD_DEFAULT),
				'id' => $_SESSION['user_id']
			]);
			
			// If no rows affected, try admins table
			if ($stmt->rowCount() === 0) {
				$stmt = $pdo->prepare('UPDATE admins SET password = :pw WHERE id = :id');
				$stmt->execute([
					'pw' => password_hash($new, PASSWORD_DEFAULT),
					'id' => $_SESSION['user_id']
				]);
			}
			
			$success = 'Password updated successfully!';
		} else {
			$error = 'Current password is incorrect.';
		}
	}
}

// Handle email update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
	$new_email = trim($_POST['email'] ?? '');
	
	if (empty($new_email)) {
		$error = 'Email address is required.';
	} elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
		$error = 'Please enter a valid email address.';
	} else {
		// Check if email is already taken by another admin (check both tables)
		$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id AND role = "admin"');
		$stmt->execute(['email' => $new_email, 'id' => $_SESSION['user_id']]);
		if ($stmt->fetch()) {
			$error = 'Email address is already in use.';
		} else {
			// Try to update in users table first
			$stmt = $pdo->prepare('UPDATE users SET email = :email WHERE id = :id AND role = "admin"');
			$result = $stmt->execute(['email' => $new_email, 'id' => $_SESSION['user_id']]);
			
			// If no rows affected, try admins table
			if ($stmt->rowCount() === 0) {
				$stmt = $pdo->prepare('UPDATE admins SET email = :email WHERE id = :id');
				$stmt->execute(['email' => $new_email, 'id' => $_SESSION['user_id']]);
			}
			
			// Refresh admin data
			$stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = :id AND role = "admin"');
			$stmt->execute(['id' => $_SESSION['user_id']]);
			$admin = $stmt->fetch();
			
			// If still not found, try admins table
			if (!$admin) {
				$stmt = $pdo->prepare('SELECT username, email FROM admins WHERE id = :id');
				$stmt->execute(['id' => $_SESSION['user_id']]);
				$admin = $stmt->fetch();
			}
			
			$success = 'Email updated successfully!';
		}
	}
}

// Get system statistics
$tenantCount = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "tenant"')->fetchColumn();
$pendingApplications = $pdo->query('SELECT COUNT(*) FROM tenant_details WHERE status = "pending"')->fetchColumn();
$approvedTenants = $pdo->query('SELECT COUNT(*) FROM tenant_details WHERE status = "approved"')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - XentroMall TMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <style>
        body { 
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji';
            margin: 0;
            background: #0b1220;
            min-height: 100vh;
        }
        /* Hero background with image + gradient overlay */
        .hero-bg {
            position: fixed;
            inset: 0;
            z-index: -10;
            overflow: hidden;
        }
        .hero-bg::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url('img/bg.jpg') center/cover no-repeat;
            filter: brightness(0.55) saturate(1.1);
            transform: scale(1.02);
        }
        .hero-bg::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(1200px 600px at 10% -10%, rgba(56,189,248,.25), transparent 60%),
                        radial-gradient(900px 500px at 90% 110%, rgba(34,197,94,.20), transparent 60%),
                        linear-gradient(180deg, rgba(2,6,23,.65), rgba(2,6,23,.65));
            mix-blend-mode: screen;
        }
        .glass-morphism {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .gradient-primary {
            background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
        }
        .gradient-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .gradient-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        .gradient-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .hover-scale {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .hover-scale:hover {
            transform: translateY(-2px);
        }
        .sidebar-item {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .sidebar-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 0;
            background: rgba(255, 255, 255, 0.1);
            transition: width 0.3s ease;
        }
        .sidebar-item:hover::before {
            width: 100%;
        }
        .form-input {
            transition: all 0.3s ease;
            border: 1px solid rgba(148, 163, 184, 0.3);
            background: rgba(255, 255, 255, 0.9);
        }
        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.3), 0 10px 10px -5px rgba(59, 130, 246, 0.2);
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        .btn-primary:hover::before {
            left: 100%;
        }
        .stat-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.5);
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(59, 130, 246, 0.7);
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">
    <div class="hero-bg pointer-events-none" aria-hidden="true"></div>
    
    <div class="relative z-10 min-h-screen flex h-screen">
        <!-- Sidebar -->
        <aside class="w-80 glass-morphism shadow-2xl flex flex-col">
            <!-- Logo Section -->
            <div class="p-6 border-b border-black/5">
                <div class="flex items-center gap-4">
                    <div class="h-12 w-12 rounded-xl overflow-hidden ring-1 ring-black/10 shadow-sm bg-white/60 flex items-center justify-center">
                        <img src="img/logo.jpg" alt="XentroMall" class="h-10 w-10 object-cover" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">XentroMall</h1>
                        <p class="text-sm text-slate-600">Admin Control Panel</p>
                    </div>
                </div>
            </div>
            
            <!-- Navigation -->
            <nav class="flex-1 p-4">
                <div class="space-y-2">
                    <a href="admin_dashboard.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-700 hover:bg-white/50">
                        <i class="fas fa-home w-5 text-center"></i>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    <a href="admin_dashboard.php#tenants" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-700 hover:bg-white/50">
                        <i class="fas fa-users w-5 text-center"></i>
                        <span class="font-medium">Tenants</span>
                    </a>
                    <a href="admin_settings.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg gradient-primary text-white">
                        <i class="fas fa-cog w-5 text-center"></i>
                        <span class="font-medium">Settings</span>
                    </a>
                </div>
                
                <div class="mt-8 pt-8 border-t border-black/5">
                    <div class="space-y-2">
                        <a href="logout.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50">
                            <i class="fas fa-sign-out-alt w-5 text-center"></i>
                            <span class="font-medium">Logout</span>
                        </a>
                    </div>
                </div>
            </nav>
            
            <!-- Admin Info -->
            <div class="p-4 border-t border-black/5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full gradient-primary flex items-center justify-center">
                        <i class="fas fa-user text-white"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars($admin['username']) ?></p>
                        <p class="text-xs text-slate-600">Administrator</p>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto custom-scrollbar">
            <div class="p-8">
                <!-- Header -->
                <div class="relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-black/5 p-6 mb-8">
                    <div class="absolute -inset-px rounded-2xl pointer-events-none" style="background: radial-gradient(800px 400px at 0% 0%, rgba(59,130,246,.08), transparent 50%), radial-gradient(700px 400px at 100% 100%, rgba(16,185,129,.08), transparent 50%);"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-3xl font-semibold tracking-tight text-slate-900 mb-2">Settings</h1>
                                <p class="text-slate-600">Manage your account and system preferences</p>
                            </div>
                            <a href="admin_dashboard.php" class="btn-primary text-white px-6 py-3 rounded-xl font-medium hover-scale inline-flex items-center gap-2">
                                <i class="fas fa-arrow-left"></i>
                                Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if ($success): ?>
                    <div class="relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-green-200 p-4 mb-6 flex items-center gap-3">
                        <div class="absolute -inset-px rounded-2xl pointer-events-none bg-green-50/50"></div>
                        <div class="relative">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            <span class="text-green-800 font-medium ml-3"><?= $success ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-red-200 p-4 mb-6 flex items-center gap-3">
                        <div class="absolute -inset-px rounded-2xl pointer-events-none bg-red-50/50"></div>
                        <div class="relative">
                            <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                            <span class="text-red-800 font-medium ml-3"><?= $error ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- System Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="stat-card relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-black/5 p-6">
                        <div class="absolute -inset-px rounded-2xl pointer-events-none" style="background: radial-gradient(600px 300px at 50% 50%, rgba(16,185,129,.05), transparent 70%);"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-full gradient-success flex items-center justify-center shadow-lg">
                                    <i class="fas fa-users text-white"></i>
                                </div>
                                <span class="text-2xl font-bold text-slate-900"><?= $tenantCount ?></span>
                            </div>
                            <h3 class="text-slate-700 font-medium">Total Tenants</h3>
                            <p class="text-sm text-slate-500 mt-1">Registered in system</p>
                        </div>
                    </div>
                    
                    <div class="stat-card relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-black/5 p-6">
                        <div class="absolute -inset-px rounded-2xl pointer-events-none" style="background: radial-gradient(600px 300px at 50% 50%, rgba(245,158,11,.05), transparent 70%);"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-full gradient-warning flex items-center justify-center shadow-lg">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                                <span class="text-2xl font-bold text-slate-900"><?= $pendingApplications ?></span>
                            </div>
                            <h3 class="text-slate-700 font-medium">Pending Applications</h3>
                            <p class="text-sm text-slate-500 mt-1">Awaiting review</p>
                        </div>
                    </div>
                    
                    <div class="stat-card relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-black/5 p-6">
                        <div class="absolute -inset-px rounded-2xl pointer-events-none" style="background: radial-gradient(600px 300px at 50% 50%, rgba(59,130,246,.05), transparent 70%);"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-full gradient-primary flex items-center justify-center shadow-lg">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <span class="text-2xl font-bold text-slate-900"><?= $approvedTenants ?></span>
                            </div>
                            <h3 class="text-slate-700 font-medium">Approved Tenants</h3>
                            <p class="text-sm text-slate-500 mt-1">Active in system</p>
                        </div>
                    </div>
                </div>

                <!-- Account Settings -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Profile Information -->
                    <div class="relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-black/5 p-6">
                        <div class="absolute -inset-px rounded-2xl pointer-events-none" style="background: radial-gradient(800px 400px at 0% 0%, rgba(59,130,246,.08), transparent 50%);"></div>
                        <div class="relative">
                            <h2 class="text-xl font-semibold tracking-tight text-slate-900 mb-6 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full gradient-primary flex items-center justify-center">
                                    <i class="fas fa-user-circle text-white text-sm"></i>
                                </div>
                                Profile Information
                            </h2>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Username</label>
                                    <div class="form-input w-full px-4 py-3 rounded-lg bg-slate-50 text-slate-700">
                                        <?= htmlspecialchars($admin['username']) ?>
                                    </div>
                                </div>
                                
                                <form method="post" class="space-y-4">
                                    <input type="hidden" name="update_email" value="1">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Email Address</label>
                                        <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" 
                                               class="form-input w-full px-4 py-3 rounded-lg" required>
                                    </div>
                                    <button type="submit" class="btn-primary w-full py-3 rounded-lg text-white font-medium">
                                        <i class="fas fa-envelope mr-2"></i>Update Email
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-black/5 p-6">
                        <div class="absolute -inset-px rounded-2xl pointer-events-none" style="background: radial-gradient(800px 400px at 100% 100%, rgba(239,68,68,.08), transparent 50%);"></div>
                        <div class="relative">
                            <h2 class="text-xl font-semibold tracking-tight text-slate-900 mb-6 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full gradient-danger flex items-center justify-center">
                                    <i class="fas fa-shield-alt text-white text-sm"></i>
                                </div>
                                Security Settings
                            </h2>
                            
                            <form method="post" class="space-y-4">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Current Password</label>
                                    <input type="password" name="current_password" required
                                           class="form-input w-full px-4 py-3 rounded-lg" placeholder="Enter current password">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">New Password</label>
                                    <input type="password" name="new_password" required minlength="8"
                                           class="form-input w-full px-4 py-3 rounded-lg" placeholder="Enter new password (min. 8 characters)">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Confirm New Password</label>
                                    <input type="password" name="confirm_password" required minlength="8"
                                           class="form-input w-full px-4 py-3 rounded-lg" placeholder="Confirm new password">
                                </div>
                                
                                <button type="submit" class="btn-primary w-full py-3 rounded-lg text-white font-medium">
                                    <i class="fas fa-key mr-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- System Information -->
                <div class="relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-black/5 p-6 mt-8">
                    <div class="absolute -inset-px rounded-2xl pointer-events-none" style="background: radial-gradient(800px 400px at 50% 0%, rgba(245,158,11,.08), transparent 50%);"></div>
                    <div class="relative">
                        <h2 class="text-xl font-semibold tracking-tight text-slate-900 mb-6 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full gradient-warning flex items-center justify-center">
                                <i class="fas fa-info-circle text-white text-sm"></i>
                            </div>
                            System Information
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <p class="text-sm text-slate-600 mb-1">System Version</p>
                                <p class="font-semibold text-slate-900">v2.0.1</p>
                            </div>
                            <div>
                                <p class="text-sm text-slate-600 mb-1">Last Login</p>
                                <p class="font-semibold text-slate-900"><?= date('M j, Y H:i') ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-slate-600 mb-1">Account Type</p>
                                <p class="font-semibold text-slate-900">Administrator</p>
                            </div>
                            <div>
                                <p class="text-sm text-slate-600 mb-1">Server Status</p>
                                <p class="font-semibold text-green-600">Online</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
