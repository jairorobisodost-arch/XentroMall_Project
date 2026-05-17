<?php
session_start();
require 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';
$new_credentials = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stall_id = $_POST['stall_id'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $tradename = trim($_POST['tradename'] ?? '');

        // Validation
        if (empty($stall_id)) {
            $error_message = 'Please select a stall.';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } elseif (empty($contact_person)) {
            $error_message = 'Please enter contact person name.';
        } elseif (empty($mobile)) {
            $error_message = 'Please enter mobile number.';
        } elseif (empty($tradename)) {
            $error_message = 'Please enter trade name.';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error_message = 'Email already exists in the system.';
            } else {
                // Get stall details
                $stmt = $pdo->prepare('SELECT stall_number FROM stalls WHERE id = ?');
                $stmt->execute([$stall_id]);
                $stall = $stmt->fetch();

                if (!$stall) {
                    $error_message = 'Invalid stall selection.';
                } else {
                    // Generate default credentials based on stall number
                    $stall_number = $stall['stall_number'];
                    $default_username = 'Stal' . $stall_number;
                    $default_password = 'Stal' . $stall_number . '@2025';
                    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

                    // Create user account
                    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())');
                    $stmt->execute([$default_username, $email, $hashed_password, 'tenant']);
                    $user_id = $pdo->lastInsertId();

                    // Create tenant details
                    $stmt = $pdo->prepare('INSERT INTO tenant_details (user_id, stall_id, contact_person, mobile, email, tradename, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
                    $stmt->execute([$user_id, $stall_id, $contact_person, $mobile, $email, $tradename, 'approved']);

                    $success_message = 'Tenant account created successfully!';
                    $new_credentials = [
                        'email' => $email,
                        'username' => $default_username,
                        'password' => $default_password,
                        'stall_number' => $stall_number
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        $error_message = 'Database Error: ' . $e->getMessage();
    }
}

// Get available stalls
$stmt = $pdo->query('SELECT id, stall_number FROM stalls WHERE status IN ("available", "not_available") ORDER BY stall_number');
$stalls = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Tenant Account - XentroMall Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --secondary: #0ea5e9;
            --accent: #f59e0b;
            --surface: #ffffff;
            --background: #f8fafc;
        }

        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%);
        }

        .gradient-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .gradient-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .card-shadow {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .nav-item {
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .form-input {
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(5, 150, 105, 0.3);
        }
    </style>
</head>
<body>
    <!-- Main Container -->
    <div class="min-h-screen">
        <!-- Header Section -->
        <header class="gradient-primary text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 md:px-8 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-user-plus text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold" style="font-family: 'Poppins', sans-serif;">Create Tenant Account</h1>
                            <p class="text-emerald-100 text-sm">Add new tenant to XentroMall system</p>
                        </div>
                    </div>
                    <a href="admin_dashboard.php" class="px-4 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-xl transition-all flex items-center gap-2 font-semibold">
                        <i class="fas fa-arrow-left"></i>
                        <span class="hidden sm:inline">Back</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="max-w-3xl mx-auto px-4 md:px-8 py-8">
            <!-- Form Card -->
            <div class="gradient-card rounded-2xl card-shadow p-8 mb-6">
                <?php if ($error_message): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-xl flex items-start gap-3">
                        <i class="fas fa-exclamation-circle text-xl flex-shrink-0 mt-0.5"></i>
                        <div>
                            <p class="font-semibold">Error</p>
                            <p class="text-sm mt-1"><?php echo htmlspecialchars($error_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success_message && !$new_credentials): ?>
                    <div class="mb-6 p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded-xl flex items-start gap-3">
                        <i class="fas fa-check-circle text-xl flex-shrink-0 mt-0.5"></i>
                        <div>
                            <p class="font-semibold">Success</p>
                            <p class="text-sm mt-1"><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($new_credentials): ?>
                    <!-- Success with Credentials -->
                    <div class="mb-6 p-6 bg-gradient-to-br from-emerald-50 to-teal-50 border-2 border-emerald-500 rounded-2xl">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center text-white text-xl">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-emerald-700">Account Created Successfully!</h2>
                        </div>
                        
                        <div class="bg-white p-6 rounded-xl mb-4 border border-emerald-200">
                            <p class="text-sm text-gray-700 mb-4 flex items-center gap-2">
                                <i class="fas fa-info-circle text-blue-500"></i>
                                <strong>Credentials for the new tenant:</strong>
                            </p>
                            
                            <div class="space-y-3">
                                <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <label class="text-xs text-gray-600 font-semibold uppercase">📧 Email</label>
                                    <div class="bg-white p-2 rounded font-mono text-sm text-gray-900 mt-1 border border-gray-300">
                                        <?php echo htmlspecialchars($new_credentials['email']); ?>
                                    </div>
                                </div>

                                <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <label class="text-xs text-gray-600 font-semibold uppercase">👤 Username</label>
                                    <div class="bg-white p-2 rounded font-mono text-sm text-gray-900 mt-1 border border-gray-300">
                                        <?php echo htmlspecialchars($new_credentials['username']); ?>
                                    </div>
                                </div>

                                <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <label class="text-xs text-gray-600 font-semibold uppercase">🔐 Password</label>
                                    <div class="bg-white p-2 rounded font-mono text-sm text-gray-900 mt-1 border border-gray-300">
                                        <?php echo htmlspecialchars($new_credentials['password']); ?>
                                    </div>
                                </div>

                                <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <label class="text-xs text-gray-600 font-semibold uppercase">🏪 Stall</label>
                                    <div class="bg-white p-2 rounded font-mono text-sm text-gray-900 mt-1 border border-gray-300">
                                        <?php echo htmlspecialchars($new_credentials['stall_number']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 p-4 rounded-xl mb-6">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-lightbulb mr-2"></i>
                                <strong>Pro Tip:</strong> Copy these credentials and send them securely to the tenant. They can login immediately and will be taken to their dashboard.
                            </p>
                        </div>

                        <div class="flex gap-3 flex-col sm:flex-row">
                            <button onclick="copyToClipboard()" class="flex-1 btn-primary text-white px-6 py-3 rounded-xl font-semibold flex items-center justify-center gap-2">
                                <i class="fas fa-copy"></i> Copy All Credentials
                            </button>
                            <button onclick="location.reload()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-xl font-semibold transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-plus-circle"></i> Create Another Account
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Form -->
                    <form method="POST" class="space-y-6">
                        <div>
                            <label for="stall_id" class="block text-sm font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                <i class="fas fa-store text-emerald-600"></i>
                                Select Stall <span class="text-red-500">*</span>
                            </label>
                            <select id="stall_id" name="stall_id" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white text-gray-900 font-medium transition-all form-input">
                                <option value="">-- Choose a stall --</option>
                                <?php foreach ($stalls as $stall): ?>
                                    <option value="<?php echo htmlspecialchars($stall['id']); ?>">
                                        Stall <?php echo htmlspecialchars($stall['stall_number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                <i class="fas fa-envelope text-blue-600"></i>
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input type="email" id="email" name="email" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white text-gray-900 font-medium transition-all form-input" placeholder="tenant@example.com">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="tradename" class="block text-sm font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                    <i class="fas fa-briefcase text-purple-600"></i>
                                    Trade Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="tradename" name="tradename" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white text-gray-900 font-medium transition-all form-input" placeholder="Business name">
                            </div>

                            <div>
                                <label for="contact_person" class="block text-sm font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                    <i class="fas fa-user text-cyan-600"></i>
                                    Contact Person <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="contact_person" name="contact_person" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white text-gray-900 font-medium transition-all form-input" placeholder="Full name">
                            </div>
                        </div>

                        <div>
                            <label for="mobile" class="block text-sm font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                <i class="fas fa-mobile-alt text-orange-600"></i>
                                Mobile Number <span class="text-red-500">*</span>
                            </label>
                            <input type="tel" id="mobile" name="mobile" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white text-gray-900 font-medium transition-all form-input" placeholder="+63 9XX XXX XXXX">
                        </div>

                        <div class="bg-gradient-to-r from-blue-50 to-cyan-50 p-4 rounded-xl border-2 border-blue-200">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-info-circle text-blue-600 text-lg mt-0.5"></i>
                                <div class="text-sm text-blue-900">
                                    <p class="font-bold mb-2">Default Password Format:</p>
                                    <p class="text-blue-800">The system will automatically generate:</p>
                                    <ul class="mt-2 space-y-1 text-blue-800">
                                        <li>• <strong>Username:</strong> Stal{STALL_NUMBER}</li>
                                        <li>• <strong>Password:</strong> Stal{STALL_NUMBER}@2025</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-4 pt-2">
                            <button type="submit" class="flex-1 btn-primary text-white font-semibold py-3 px-6 rounded-xl transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-check-circle"></i> Create Account
                            </button>
                            <a href="admin_dashboard.php" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-3 px-6 rounded-xl transition-all text-center flex items-center justify-center gap-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Information Box -->
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border-2 border-emerald-300 p-6 rounded-2xl card-shadow">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center text-white flex-shrink-0">
                        <i class="fas fa-lightbulb text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-emerald-900 mb-3 text-lg">How it works:</h3>
                        <ul class="text-sm text-emerald-800 space-y-2">
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Fill in the tenant information and select their stall</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>System generates default username and password based on stall number</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Account is created with "approved" status immediately</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Tenant can login immediately and access the dashboard</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Credentials are displayed and ready to share via email</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            const credentials = `
Email: <?php echo $new_credentials['email']; ?>
Username: <?php echo $new_credentials['username']; ?>
Password: <?php echo $new_credentials['password']; ?>
Stall: <?php echo $new_credentials['stall_number']; ?>
            `.trim();
            
            navigator.clipboard.writeText(credentials).then(() => {
                alert('Credentials copied to clipboard!');
            }).catch(() => {
                alert('Failed to copy credentials');
            });
        }
    </script>
</body>
</html>
