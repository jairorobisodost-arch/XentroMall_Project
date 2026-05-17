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
    <title>Admin Settings - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --secondary: #0ea5e9;
            --accent: #f59e0b;
        }
        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .gradient-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }
        .gradient-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .nav-item {
            position: relative;
            overflow: hidden;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }
        .nav-item:hover, .nav-item.active {
            transform: translateX(10px);
            background: rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }
        .nav-item::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        .nav-item:hover::before, .nav-item.active::before {
            left: 100%;
        }
        .nav-item i {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .nav-item:hover i, .nav-item.active i {
            transform: scale(1.2) rotate(10deg);
            color: #fff;
        }
        
        aside {
            font-family: 'Poppins', sans-serif;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        aside:hover {
            box-shadow: 0 25px 50px -12px rgba(5, 150, 105, 0.25);
        }
        
        .scrollbar-custom::-webkit-scrollbar { width: 6px; }
        .scrollbar-custom::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 3px; }
        .scrollbar-custom::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 3px; }
        
        .animate-fade-in { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-input {
            transition: all 0.3s ease;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .form-input:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            outline: none;
            background: #ffffff;
        }
        .btn-action {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.3);
        }

        /* Sidebar Dropdown Styles */
        .nav-dropdown {
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            margin: 0 4px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .nav-dropdown.active {
            max-height: 500px;
            padding: 8px 0;
            margin-bottom: 8px;
            background: rgba(0, 0, 0, 0.1);
        }
        .dropdown-trigger .fa-chevron-right { opacity: 0.7; transition: transform 0.3s ease; }
        .dropdown-trigger.active .fa-chevron-right { transform: rotate(90deg); }
        .nav-item-sub { padding-left: 3.5rem !important; font-size: 0.9rem !important; opacity: 0.8; height: 40px; display: flex; align-items: center; }
    </style>
</head>
<body class="min-h-screen">
    <!-- Top Header -->
    <header class="gradient-primary shadow-2xl sticky top-0 z-50">
        <div class="max-w-[1400px] mx-auto px-4 lg:px-6 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo and Brand -->
                <div class="flex items-center gap-4">
                    <div class="bg-white/20 p-2 rounded-2xl backdrop-blur-sm">
                        <img src="img/logo.jpg" alt="XentroMall Logo" class="w-12 h-12 object-contain rounded-lg" />
                    </div>
                    <div>
                        <h1 class="font-bold text-2xl text-white select-none">XentroMall</h1>
                        <p class="text-white/80 text-sm">Admin Management System</p>
                    </div>
                </div>
                
                <!-- Admin Info and Menu -->
                <div class="flex items-center gap-4">
                    <div class="bg-white rounded-2xl px-5 py-3 flex items-center gap-3 shadow-lg border-2 border-emerald-100">
                        <div class="w-11 h-11 rounded-full bg-gradient-to-br from-emerald-500 to-blue-500 flex items-center justify-center shadow-md">
                            <i class="fas fa-user-shield text-white text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs text-gray-500 font-medium uppercase tracking-wide">Logged in as Admin</span>
                            <p class="font-bold text-gray-900 text-base leading-tight"><?php echo htmlspecialchars($admin['username'] ?? 'Admin'); ?></p>
                        </div>
                    </div>
                    
                    <div class="relative">
                        <button aria-label="Toggle menu" class="glass-effect text-white hover:bg-white/20 transition-colors p-3 rounded-xl" onclick="document.getElementById('adminSettingsMenu').classList.toggle('hidden')">
                            <i class="fas fa-ellipsis-v text-lg"></i>
                        </button>
                        <div id="adminSettingsMenu" class="hidden absolute right-0 top-14 bg-white rounded-2xl shadow-2xl w-56 z-50 overflow-hidden border border-gray-100">
                            <a href="admin_settings.php" class="block px-4 py-3 text-emerald-600 bg-emerald-50 border-b border-gray-50">
                                <i class="fas fa-cog mr-3"></i>Settings & Profile
                            </a>
                            <a href="logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 transition-colors">
                                <i class="fas fa-sign-out-alt mr-3"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex gap-6 max-w-[1400px] mx-auto px-4 lg:px-6 py-6">
        <!-- Sidebar -->
        <aside class="gradient-primary rounded-2xl w-72 p-4 flex flex-col gap-3 shadow-2xl text-white relative overflow-hidden h-[calc(100vh-120px)] sticky top-28">
            <div class="absolute -top-20 -right-20 w-40 h-40 bg-white/10 rounded-full"></div>
            <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-white/5 rounded-full"></div>
            
            <nav class="flex flex-col gap-2 relative z-10 overflow-y-auto scrollbar-custom">
                <a href="admin_dashboard.php" class="nav-item flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                    <i class="fas fa-chart-pie text-xl group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold">Dashboard</span>
                </a>

                <div class="nav-group mb-1">
                    <a class="nav-item dropdown-trigger flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group" onclick="toggleDropdown(this)">
                        <i class="fas fa-tasks text-xl group-hover:scale-110 transition-transform"></i>
                        <span class="font-semibold">Management</span>
                        <i class="fas fa-chevron-right ml-auto transition-transform"></i>
                    </a>
                    <div class="nav-dropdown">
                        <a href="admin_dashboard.php#tenants" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                            <i class="fas fa-users text-lg"></i>
                            <span class="font-semibold">Tenants</span>
                        </a>
                        <a href="admin_dashboard.php#applications" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                            <i class="fas fa-file-alt text-lg"></i>
                            <span class="font-semibold">Applications</span>
                        </a>
                        <a href="stall_page.php" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                            <i class="fas fa-store text-lg"></i>
                            <span class="font-semibold">Stall Management</span>
                        </a>
                        <a href="admin_work_permits.php" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                            <i class="fas fa-hard-hat text-lg"></i>
                            <span class="font-semibold">Work Permits</span>
                        </a>
                    </div>
                </div>

                <div class="nav-group mb-1">
                    <a class="nav-item dropdown-trigger flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group" onclick="toggleDropdown(this)">
                        <i class="fas fa-cogs text-xl group-hover:scale-110 transition-transform"></i>
                        <span class="font-semibold">Operations</span>
                        <i class="fas fa-chevron-right ml-auto transition-transform"></i>
                    </a>
                    <div class="nav-dropdown">
                        <a href="admin_dashboard.php#renewals" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                            <i class="fas fa-sync-alt text-lg"></i>
                            <span class="font-semibold">Renewal</span>
                        </a>
                        <a href="admin_dashboard.php#payments" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                            <i class="fas fa-credit-card text-lg"></i>
                            <span class="font-semibold">Payments</span>
                        </a>
                        <a href="admin_dashboard.php#billing" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                            <i class="fas fa-file-invoice-dollar text-lg"></i>
                            <span class="font-semibold">Billing</span>
                        </a>
                        <a href="admin_dashboard.php#soa" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                            <i class="fas fa-file-invoice text-lg"></i>
                            <span class="font-semibold">Statement of Account</span>
                        </a>
                        <a href="admin_dashboard.php#contracts" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                            <i class="fas fa-file-contract text-lg"></i>
                            <span class="font-semibold">Active Contracts</span>
                        </a>
                    </div>
                </div>

                <div class="nav-group mb-1">
                    <a class="nav-item dropdown-trigger flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group active" onclick="toggleDropdown(this)">
                        <i class="fas fa-shield-alt text-xl group-hover:scale-110 transition-transform"></i>
                        <span class="font-semibold">System</span>
                        <i class="fas fa-chevron-right ml-auto transition-transform"></i>
                    </a>
                    <div class="nav-dropdown active">
                        <a href="posting.php" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                            <i class="fas fa-bullhorn text-lg"></i>
                            <span class="font-semibold">Announcements</span>
                        </a>
                        <a href="admin_register.php" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group">
                            <i class="fas fa-user-plus text-lg"></i>
                            <span class="font-semibold">Register Admin</span>
                        </a>
                        <a href="admin_settings.php" class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg bg-white/20 active transition-all group">
                            <i class="fas fa-cog text-lg text-white"></i>
                            <span class="font-semibold text-white">Settings</span>
                        </a>
                    </div>
                </div>
                
                <div class="h-px bg-white/30 my-2 mx-4"></div>
                
                <a class="nav-item flex items-center gap-2 px-2 py-2 rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-50 hover:text-white transition-all group" href="logout.php">
                    <i class="fas fa-sign-out-alt text-xl group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold">Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col gap-6 overflow-auto scrollbar-custom pb-10">
            <!-- Header section -->
            <header class="animate-fade-in flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-3xl text-gray-900">Settings & Profile</h2>
                    <p class="text-gray-600 mt-1">Manage your account credentials and view system information.</p>
                </div>
            </header>

            <!-- Success/Error Messages -->
            <?php if ($success): ?>
                <div class="bg-gradient-to-r from-emerald-50 to-green-50 border-l-4 border-emerald-500 text-emerald-700 px-6 py-4 rounded-2xl shadow-md animate-fade-in flex items-center gap-3">
                    <i class="fas fa-check-circle text-2xl flex-shrink-0"></i>
                    <span class="font-medium text-lg"><?= $success ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-700 px-6 py-4 rounded-2xl shadow-md animate-fade-in flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-2xl flex-shrink-0"></i>
                    <span class="font-medium text-lg"><?= $error ?></span>
                </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 animate-fade-in" style="animation-delay: 0.1s">
                <div class="hover-lift gradient-card rounded-3xl p-6 shadow-md border border-gray-100 relative overflow-hidden group">
                    <div class="absolute -top-4 -right-4 w-24 h-24 bg-emerald-100 rounded-full opacity-50 group-hover:scale-110 transition-transform duration-500"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-14 h-14 bg-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-users text-white text-xl"></i>
                            </div>
                            <span class="text-3xl font-bold text-gray-900"><?= $tenantCount ?></span>
                        </div>
                        <h3 class="font-semibold text-gray-700">Total Tenants</h3>
                        <p class="text-sm text-gray-500 mt-1">Registered in system</p>
                    </div>
                </div>

                <div class="hover-lift gradient-card rounded-3xl p-6 shadow-md border border-gray-100 relative overflow-hidden group">
                    <div class="absolute -top-4 -right-4 w-24 h-24 bg-amber-100 rounded-full opacity-50 group-hover:scale-110 transition-transform duration-500"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-14 h-14 bg-amber-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-clock text-white text-xl"></i>
                            </div>
                            <span class="text-3xl font-bold text-gray-900"><?= $pendingApplications ?></span>
                        </div>
                        <h3 class="font-semibold text-gray-700">Pending Applications</h3>
                        <p class="text-sm text-gray-500 mt-1">Awaiting manual review</p>
                    </div>
                </div>

                <div class="hover-lift gradient-card rounded-3xl p-6 shadow-md border border-gray-100 relative overflow-hidden group">
                    <div class="absolute -top-4 -right-4 w-24 h-24 bg-blue-100 rounded-full opacity-50 group-hover:scale-110 transition-transform duration-500"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-14 h-14 bg-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-check text-white text-xl"></i>
                            </div>
                            <span class="text-3xl font-bold text-gray-900"><?= $approvedTenants ?></span>
                        </div>
                        <h3 class="font-semibold text-gray-700">Approved Tenants</h3>
                        <p class="text-sm text-gray-500 mt-1">Active within the mall</p>
                    </div>
                </div>
            </div>

            <!-- Forms Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-fade-in" style="animation-delay: 0.2s">
                
                <!-- Profile Information -->
                <div class="gradient-card border border-gray-100 rounded-3xl shadow-md overflow-hidden flex flex-col">
                    <div class="bg-gray-50/50 p-6 border-b border-gray-100">
                        <h3 class="font-bold text-gray-900 text-lg flex items-center gap-2">
                            <i class="fas fa-user-circle text-blue-500"></i> Profile Information
                        </h3>
                    </div>
                    <div class="p-6 flex-1 flex flex-col justify-center">
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                                <div class="w-full px-4 py-3 rounded-xl bg-gray-100 text-gray-700 border border-gray-200 cursor-not-allowed">
                                    <?= htmlspecialchars($admin['username'] ?? '') ?>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Username cannot be changed.</p>
                            </div>
                            
                            <form method="post" class="space-y-4">
                                <input type="hidden" name="update_email" value="1">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($admin['email'] ?? '') ?>" 
                                           class="form-input w-full px-4 py-3 rounded-xl text-gray-900 text-sm" required>
                                </div>
                                <button type="submit" class="w-full mt-2 btn-action text-white font-bold py-3 px-4 rounded-xl shadow-lg flex items-center justify-center gap-2">
                                    <i class="fas fa-save"></i> Update Email Address
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="gradient-card border border-gray-100 rounded-3xl shadow-md overflow-hidden flex flex-col">
                    <div class="bg-gray-50/50 p-6 border-b border-gray-100">
                        <h3 class="font-bold text-gray-900 text-lg flex items-center gap-2">
                            <i class="fas fa-shield-alt text-emerald-500"></i> Security Settings
                        </h3>
                    </div>
                    <div class="p-6 flex-1">
                        <form method="post" class="space-y-5">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                                <input type="password" name="current_password" required
                                       class="form-input w-full px-4 py-3 rounded-xl text-gray-900 text-sm" placeholder="Enter your current password">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                                <input type="password" name="new_password" required minlength="8"
                                       class="form-input w-full px-4 py-3 rounded-xl text-gray-900 text-sm" placeholder="Enter new password (min. 8 characters)">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" name="confirm_password" required minlength="8"
                                       class="form-input w-full px-4 py-3 rounded-xl text-gray-900 text-sm" placeholder="Confirm your new password">
                            </div>
                            
                            <button type="submit" class="w-full pt-2 btn-action text-white font-bold py-3 px-4 rounded-xl shadow-lg flex items-center justify-center gap-2">
                                <i class="fas fa-key"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="gradient-card border border-gray-100 rounded-2xl shadow-sm overflow-hidden p-6 mt-2 flex items-center justify-between animate-fade-in" style="animation-delay: 0.3s">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-900">System Information</h4>
                        <div class="flex items-center gap-6 mt-1 text-sm text-gray-500">
                            <span><strong class="text-gray-700">Version:</strong> v2.0.1</span>
                            <span><strong class="text-gray-700">Role:</strong> Administrator</span>
                            <span><strong class="text-gray-700">Last Login:</strong> <?= date('F j, Y, h:i A') ?></span>
                        </div>
                    </div>
                </div>
                <div class="bg-emerald-50 text-emerald-700 px-4 py-1.5 rounded-full text-sm font-bold border border-emerald-200 shadow-sm flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Online
                </div>
            </div>

        </main>
    </div>

    <!-- Dropdown Script matching Admin Dashboard -->
    <script>
        function toggleDropdown(trigger) {
            trigger.classList.toggle('active');
            let dropdown = trigger.nextElementSibling;
            dropdown.classList.toggle('active');
            
            // Close other dropdowns
            let allTriggers = document.querySelectorAll('.dropdown-trigger');
            allTriggers.forEach((otherTrigger) => {
                if(otherTrigger !== trigger && otherTrigger.classList.contains('active')) {
                    otherTrigger.classList.remove('active');
                    otherTrigger.nextElementSibling.classList.remove('active');
                }
            });
        }
        
        // Close menus when clicking outside
        document.addEventListener('click', function(event) {
            const adminMenu = document.getElementById('adminSettingsMenu');
            const adminToggle = document.querySelector('[onclick="document.getElementById(\'adminSettingsMenu\').classList.toggle(\'hidden\')"]');
            if (adminMenu && !adminMenu.contains(event.target) && !adminToggle.contains(event.target)) {
                adminMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
