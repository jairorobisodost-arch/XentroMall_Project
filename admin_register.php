<?php
session_start();
require __DIR__ . '/config.php';

$register_error = '';
$register_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $register_error = 'Please fill all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $register_error = 'Passwords do not match.';
    } else {
        // Check if username or email already exists in admins table
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :username OR email = :email');
        $stmt->execute(['username' => $username, 'email' => $email]);
        if ($stmt->fetch()) {
            $register_error = 'Username or email already exists.';
        } else {
            // Insert new admin into admins table
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admins (username, email, password, created_at) VALUES (:username, :email, :password, NOW())');
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password' => $hashed_password
            ]);
            $register_success = 'Admin registered successfully. You can now <a href="login.php">login</a>.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Registration - XentroMall</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%);
    }
    
    .gradient-primary {
      background: linear-gradient(135deg, #059669 0%, #0ea5e9 100%);
    }
    
    .form-input {
      transition: all 0.3s ease;
    }
    
    .form-input:focus {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.2);
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
  <main class="w-full max-w-6xl">
    <!-- Main Card -->
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
      <div class="grid md:grid-cols-2 gap-0">
        
        <!-- Left Side - Branding -->
        <div class="gradient-primary p-12 flex flex-col justify-center items-center text-white relative overflow-hidden">
          <!-- Background decoration -->
          <div class="absolute -top-20 -right-20 w-64 h-64 bg-white/10 rounded-full"></div>
          <div class="absolute -bottom-20 -left-20 w-48 h-48 bg-white/5 rounded-full"></div>
          
          <div class="relative z-10 text-center">
            <!-- Logo -->
            <div class="mb-8 flex justify-center">
              <div class="bg-white/20 p-4 rounded-3xl backdrop-blur-sm">
                <img src="img/logo.jpg" alt="XentroMall Logo" class="w-32 h-32 object-contain rounded-2xl" />
              </div>
            </div>
            
            <h1 class="text-4xl font-bold mb-4" style="font-family: 'Poppins', sans-serif;">XentroMall</h1>
            <p class="text-xl text-white/90 mb-6">Admin Management System</p>
            
            <div class="space-y-4 mt-12">
              <div class="flex items-center gap-3 text-white/90">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                  <i class="fas fa-shield-alt"></i>
                </div>
                <span>Secure Admin Access</span>
              </div>
              <div class="flex items-center gap-3 text-white/90">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                  <i class="fas fa-users-cog"></i>
                </div>
                <span>Manage Your Mall</span>
              </div>
              <div class="flex items-center gap-3 text-white/90">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                  <i class="fas fa-chart-line"></i>
                </div>
                <span>Track Performance</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Side - Form -->
        <div class="p-12">
          <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2" style="font-family: 'Poppins', sans-serif;">Create Admin Account</h2>
            <p class="text-gray-600">Register a new administrator for the system</p>
          </div>

          <!-- Messages -->
          <?php if (!empty($register_error)): ?>
            <div class="mb-6 rounded-2xl border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-pink-50 text-red-700 px-5 py-4 flex items-center gap-3 shadow-md">
              <i class="fas fa-exclamation-circle text-2xl"></i>
              <span class="font-medium"><?php echo htmlspecialchars($register_error); ?></span>
            </div>
          <?php endif; ?>

          <?php if (!empty($register_success)): ?>
            <div class="mb-6 rounded-2xl border-l-4 border-emerald-500 bg-gradient-to-r from-emerald-50 to-green-50 text-emerald-700 px-5 py-4 flex items-center gap-3 shadow-md">
              <i class="fas fa-check-circle text-2xl"></i>
              <span class="font-medium"><?php echo $register_success; ?></span>
            </div>
          <?php endif; ?>

          <!-- Form -->
          <form method="post" action="admin_register.php" class="space-y-5">
            <div>
              <label for="username" class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                <i class="fas fa-user text-emerald-600"></i>
                Username
              </label>
              <input
                type="text"
                id="username"
                name="username"
                required
                placeholder="Enter admin username"
                class="form-input w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3.5 text-gray-900 placeholder-gray-400 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all font-medium"
              />
            </div>

            <div>
              <label for="email" class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                <i class="fas fa-envelope text-blue-600"></i>
                Email Address
              </label>
              <input
                type="email"
                id="email"
                name="email"
                required
                placeholder="admin@xentromall.com"
                class="form-input w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3.5 text-gray-900 placeholder-gray-400 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all font-medium"
              />
            </div>

            <div>
              <label for="password" class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                <i class="fas fa-lock text-amber-600"></i>
                Password
              </label>
              <input
                type="password"
                id="password"
                name="password"
                required
                placeholder="Create a strong password"
                class="form-input w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3.5 text-gray-900 placeholder-gray-400 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all font-medium"
              />
            </div>

            <div>
              <label for="confirm_password" class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                <i class="fas fa-check-circle text-purple-600"></i>
                Confirm Password
              </label>
              <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                required
                placeholder="Re-enter your password"
                class="form-input w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3.5 text-gray-900 placeholder-gray-400 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all font-medium"
              />
            </div>

            <button
              type="submit"
              name="register_submit"
              class="w-full inline-flex items-center justify-center gap-3 rounded-xl gradient-primary px-6 py-4 text-white font-bold text-lg shadow-xl hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-emerald-600/30 active:scale-[.98] transition-all mt-6"
            >
              <i class="fas fa-user-plus text-xl"></i>
              <span>Create Admin Account</span>
            </button>
          </form>

          <!-- Back Link -->
          <div class="mt-8 pt-6 border-t border-gray-200 text-center">
            <a href="admin_dashboard.php" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
              <i class="fas fa-arrow-left"></i>
              <span>Back to Dashboard</span>
            </a>
          </div>
        </div>
        
      </div>
    </div>

    <!-- Footer -->
    <p class="mt-8 text-center text-sm text-gray-500">
      © <?php echo date('Y'); ?> XentroMall Management System • All rights reserved
    </p>
  </main>
</body>
</html>
