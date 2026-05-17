<?php
session_start();
require 'config.php';

// Redirect logged-in users to their dashboards
// if (isset($_SESSION['role'])) {
//     if ($_SESSION['role'] === 'admin') {
//         header('Location: admin_dashboard.php');
//         exit;
//     } elseif ($_SESSION['role'] === 'tenant') {
//         header('Location: tenant_dashboard.php');
//         exit;
//     }
// }

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['login_submit']) || isset($_POST['username']))) {
    $input = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($input) || empty($password)) {
        $login_error = 'Please fill all required fields.';
    } else {
        // Check admins table
        $stmt = $pdo->prepare('SELECT id, username, password, "admin" as role FROM admins WHERE username = :input1 OR email = :input2');
        $stmt->execute(['input1' => $input, 'input2' => $input]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['role'] = 'admin';
            header('Location: admin_dashboard.php');
            exit;
        } else {
            // Check if terms agreement is required for tenant login
            if (!isset($_POST['termsAgreement']) || $_POST['termsAgreement'] !== 'on') {
                $login_error = 'You must agree to the Terms of Use and Privacy Policy to continue.';
            } else {
                // Check tenant login
                $stmt = $pdo->prepare('SELECT id, username, password, role, email_verified FROM users WHERE (username = :input1 OR email = :input2) AND role = :role');
                $stmt->execute(['input1' => $input, 'input2' => $input, 'role' => 'tenant']);
                $tenant = $stmt->fetch();

            if ($tenant && password_verify($password, $tenant['password'])) {
                
                // Check if email is verified first
                if (!$tenant['email_verified']) {
                    echo '<script>alert("Please verify your email address before logging in. Check your inbox for the verification link."); window.location.href = "resend_verification.php";</script>';
                    exit;
                }
                
                // Check the tenant's status
                $statusStmt = $pdo->prepare('SELECT status FROM tenant_details WHERE user_id = :user_id');
                $statusStmt->execute(['user_id' => $tenant['id']]);
                $tenantStatus = $statusStmt->fetch();
                
                $_SESSION['user_id'] = $tenant['id'];
                $_SESSION['username'] = $tenant['username'];
                $_SESSION['role'] = 'tenant';
                
                if (!$tenantStatus || $tenantStatus['status'] === null) {
                    // Status is NULL - application pending
                    echo '<script>alert("Thank you for submitting your application. It is now pending review by the manager. Please wait for further updates."); window.location.href = "login.php";</script>';
                    exit;
                } elseif ($tenantStatus['status'] === 'declined') {
                    // Status is declined
                    echo '<script>alert("Your application was not approved. You may resubmit your application.."); window.location.href = "resubmit.php?user_id='.$tenant['id'].'";</script>';
                    exit;
                } elseif ($tenantStatus['status'] === 'approved') {
                    // Status is approved - proceed to dashboard
                    header('Location: tenant_dashboard.php?page=space');
                    exit;
                } else {
                    // Handle any other unexpected status values
                    echo '<script>alert("There is an error with your application. Please try again later."); window.location.href = "login.php";</script>';
                    exit;
                }
            } else {
                echo '<script>alert("Invalid username/email or password."); window.location.href = "login.php";</script>';
                exit;
            }
                }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>XentroMall TMS — Sign in</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- AOS Animation Library -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <meta name="color-scheme" content="light dark" />
  <style>
    :root { color-scheme: light only; }
    html, body { height: 100%; }
    body {
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial;
      margin: 0;
      background: #020617;
      overflow-x: hidden;
    }
    
    /* Glassmorphism utility */
    .glass {
      background: rgba(255, 255, 255, 0.03);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .glass-dark {
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    /* Background hero with image + premium gradient overlay */
    .hero-bg { position: fixed; inset: 0; z-index: -10; overflow: hidden; }
    .hero-bg::before { 
      content: ""; 
      position: absolute; 
      inset: 0; 
      background: url('img/bg.jpg') center/cover no-repeat; 
      filter: brightness(0.4) saturate(1.2); 
      transform: scale(1.1);
      animation: slowZoom 20s infinite alternate ease-in-out;
    }
    
    @keyframes slowZoom {
      from { transform: scale(1); }
      to { transform: scale(1.1); }
    }
    
    .hero-bg::after { 
      content: ""; 
      position: absolute; 
      inset: 0; 
      background: 
        radial-gradient(circle at 10% 10%, rgba(59, 130, 246, 0.1), transparent 50%),
        radial-gradient(circle at 90% 90%, rgba(16, 185, 129, 0.08), transparent 50%),
        linear-gradient(to bottom, rgba(2, 6, 23, 0.4), rgba(2, 6, 23, 0.9));
    }

    /* Button Ripple Animation */
    .ripple-button {
      position: relative;
      overflow: hidden;
    }

    .ripple {
      position: absolute;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.7);
      transform: scale(0);
      animation: ripple-animation 0.8s ease-out;
      pointer-events: none;
      aspect-ratio: 1 / 1;
    }

    @keyframes ripple-animation {
      0% {
        transform: scale(0);
        opacity: 1;
      }
      30% {
        opacity: 0.9;
      }
      70% {
        opacity: 0.5;
      }
      100% {
        transform: scale(3.5);
        opacity: 0;
      }
    }

    /* Enhanced button hover effects */
    .login-button {
      position: relative;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .login-button:hover {
      transform: translateY(-1px);
      box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.3), 0 10px 10px -5px rgba(59, 130, 246, 0.2);
    }

    .login-button:active {
      transform: translateY(0);
    }

    /* Shimmer effect on button */
    .login-button::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .login-button:hover::before {
      left: 100%;
    }
  </style>
</head>
<body class="min-h-screen text-slate-800">
  <div class="hero-bg pointer-events-none" aria-hidden="true"></div>

  <main class="relative z-10 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-[440px]" data-aos="zoom-in" data-aos-duration="1000">
      <!-- Card -->
      <div class="relative rounded-[2.5rem] glass-dark shadow-[0_0_80px_rgba(0,0,0,0.5)] border border-white/10 overflow-hidden">
        <div class="relative p-10 sm:p-12">
          <!-- Brand -->
          <div class="flex flex-col items-center text-center mb-10" data-aos="fade-down" data-aos-delay="200">
            <div class="h-20 w-20 rounded-3xl glass p-2.5 mb-6 ring-2 ring-white/10 shadow-2xl">
              <img src="img/logo.jpg" alt="XentroMall" class="h-full w-full object-cover rounded-2xl" />
            </div>
            <h1 class="text-3xl font-black tracking-tight text-white mb-2">Sign <span class="text-emerald-400">In</span></h1>
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-white/30">Tenant Management System</p>
          </div>

          <!-- Error message -->
          <?php if (!empty($login_error)): ?>
            <div class="mb-8 rounded-2xl border border-red-500/20 bg-red-500/10 text-red-200 px-5 py-4 text-sm flex items-center gap-3" data-aos="shakeX">
              <i class="fas fa-exclamation-triangle"></i>
              <span><?php echo htmlspecialchars($login_error, ENT_QUOTES); ?></span>
            </div>
          <?php endif; ?>

          <form action="login.php" method="post" class="space-y-5" novalidate>
            <!-- Username / Email -->
            <div data-aos="fade-up" data-aos-delay="300">
              <label for="username" class="block text-xs font-black uppercase tracking-widest text-white/50 mb-2 ml-1">Username or Email</label>
              <div class="relative group">
                <div class="absolute inset-y-0 left-4 flex items-center text-white/30 group-focus-within:text-emerald-400 transition-colors">
                  <i class="fas fa-user-circle text-lg"></i>
                </div>
                <input
                  type="text"
                  id="username"
                  name="username"
                  value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES) : '';?>"
                  required
                  autocomplete="username"
                  placeholder="yourname or you@example.com"
                  class="w-full rounded-2xl glass px-12 py-4 text-white placeholder-white/20 outline-none focus:ring-2 focus:ring-emerald-500/30 transition-all duration-300"
                />
              </div>
            </div>

            <!-- Password -->
            <div data-aos="fade-up" data-aos-delay="400">
              <div class="flex items-center justify-between mb-2 ml-1">
                <label for="password" class="block text-xs font-black uppercase tracking-widest text-white/50">Password</label>
                <a href="forgot_password.php" class="text-xs font-bold text-emerald-400 hover:text-emerald-300 transition-colors">Forgot Password?</a>
              </div>
              <div class="relative group">
                <div class="absolute inset-y-0 left-4 flex items-center text-white/30 group-focus-within:text-emerald-400 transition-colors">
                  <i class="fas fa-lock text-lg"></i>
                </div>
                <input
                  type="password"
                  id="password"
                  name="password"
                  required
                  autocomplete="current-password"
                  placeholder="••••••••"
                  class="w-full rounded-2xl glass px-12 py-4 text-white placeholder-white/20 outline-none focus:ring-2 focus:ring-emerald-500/30 transition-all duration-300 pr-12"
                />
                <button type="button" id="togglePassword" aria-label="Show password" class="absolute inset-y-0 right-3 inline-flex items-center justify-center p-2 text-white/20 hover:text-white transition-colors">
                  <i id="eyeIcon" class="fas fa-eye text-lg"></i>
                </button>
              </div>
            </div>

            <!-- Submit -->
            <div data-aos="fade-up" data-aos-delay="500">
              <button
                type="submit"
                name="login_submit"
                id="loginButton"
                class="login-button ripple-button w-full inline-flex items-center justify-center gap-3 rounded-2xl bg-gradient-to-r from-emerald-500 to-blue-600 px-6 py-4 text-white font-black shadow-2xl shadow-emerald-500/20 hover:from-emerald-400 hover:to-blue-500 transition-all duration-300 active:scale-95 disabled:opacity-50 disabled:pointer-events-none"
              >
                <i class="fas fa-sign-in-alt"></i>
                <span>Sign In</span>
              </button>
            </div>

            <!-- Links -->
            <div class="text-sm text-white/40 text-center space-y-3 pt-4" data-aos="fade-up" data-aos-delay="600">
              <div>
                Don't have an account?
                <a href="user_stall_page.php" class="font-bold text-emerald-400 hover:text-emerald-300 transition-colors">Register here</a>
              </div>
              <div class="pb-6 border-b border-white/5">
                Need to verify your email?
                <a href="resend_verification.php" class="font-bold text-blue-400 hover:text-blue-300 transition-colors">Resend Link</a>
              </div>

              <!-- Terms Agreement Checkbox -->
              <div class="flex items-start gap-3 pt-4">
                <input
                  type="checkbox"
                  id="termsAgreement"
                  name="termsAgreement"
                  class="mt-1 h-5 w-5 rounded-lg border-white/10 bg-white/5 text-emerald-500 shadow-xl focus:ring-0 transition-all checked:bg-emerald-500 cursor-pointer"
                />
                <label for="termsAgreement" class="text-xs text-white/50 leading-relaxed text-left cursor-pointer">
                  I agree to the 
                  <button type="button" onclick="openTermsModal()" class="font-bold text-white hover:text-emerald-400 underline transition-colors">Terms of Use</button> 
                  and 
                  <button type="button" onclick="openPrivacyModal()" class="font-bold text-white hover:text-blue-400 underline transition-colors">Privacy Policy</button>
                  <span class="block mt-1 text-[10px] uppercase font-black tracking-widest text-white/20">(Required for Tenants)</span>
                </label>
              </div>
            </div>

      <!-- Small footer -->
      <p class="mt-8 text-center text-[10px] font-black uppercase tracking-[0.3em] text-white/20" data-aos="fade-up" data-aos-delay="700">
        © <?php echo date('Y'); ?> Xentro Mall Corporation • Integrity First
      </p>
    </div>
  </main>

  <!-- Terms of Use Modal -->
  <div id="termsModal" class="fixed inset-0 bg-[#020617]/90 backdrop-blur-xl flex items-center justify-center hidden z-[100] p-4 md:p-6">
    <div class="glass-dark rounded-[2.5rem] max-w-2xl w-full shadow-[0_0_100px_rgba(16,185,129,0.15)] border border-white/10 overflow-hidden relative" data-aos="zoom-in">
      <div class="bg-emerald-500/10 p-8 border-b border-white/5 relative">
        <h3 class="text-2xl font-black text-white">Terms of <span class="text-emerald-400">Use</span></h3>
        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mt-1">Tenant Management System</p>
        <button onclick="closeTermsModal()" class="absolute top-6 right-6 h-10 w-10 rounded-xl glass flex items-center justify-center text-white/50 hover:bg-white/10 hover:text-white transition-all">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <div class="p-8 overflow-y-auto max-height-[60vh] custom-scrollbar text-white/60 text-sm leading-relaxed space-y-6">
        <div>
          <h4 class="text-white font-bold mb-2">1. Acceptance of Terms</h4>
          <p>By accessing and using the XentroMall Tenant Management System (TMS), you acknowledge that you have read, understood, and agree to be bound by these Terms of Use.</p>
        </div>
        <div>
          <h4 class="text-white font-bold mb-2">2. Account Registration</h4>
          <p>You must provide accurate, complete, and current information when registering for an account. You are responsible for maintaining the confidentiality of your account credentials.</p>
        </div>
        <div>
          <h4 class="text-white font-bold mb-2">3. Tenant Responsibilities</h4>
          <p>Tenants must provide accurate business information and documentation, and comply with all applicable laws, regulations, and mall policies.</p>
        </div>
      </div>
      
      <div class="p-6 bg-white/5 border-t border-white/5 flex justify-end">
        <button onclick="closeTermsModal()" class="px-8 py-3 rounded-xl bg-emerald-500 text-white font-bold hover:bg-emerald-600 transition-colors shadow-lg">Close</button>
      </div>
    </div>
  </div>

  <!-- Privacy Policy Modal -->
  <div id="privacyModal" class="fixed inset-0 bg-[#020617]/90 backdrop-blur-xl flex items-center justify-center hidden z-[100] p-4 md:p-6">
    <div class="glass-dark rounded-[2.5rem] max-w-2xl w-full shadow-[0_0_100px_rgba(59,130,246,0.15)] border border-white/10 overflow-hidden relative" data-aos="zoom-in">
      <div class="bg-blue-500/10 p-8 border-b border-white/5 relative">
        <h3 class="text-2xl font-black text-white">Privacy <span class="text-blue-400">Policy</span></h3>
        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mt-1">Data Protection Standards</p>
        <button onclick="closePrivacyModal()" class="absolute top-6 right-6 h-10 w-10 rounded-xl glass flex items-center justify-center text-white/50 hover:bg-white/10 hover:text-white transition-all">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <div class="p-8 overflow-y-auto max-height-[60vh] custom-scrollbar text-white/60 text-sm leading-relaxed space-y-6">
        <div>
          <h4 class="text-white font-bold mb-2">1. Information Collection</h4>
          <p>We collect personal information (name, email, phone) and business documentation necessary for processing mall stall applications.</p>
        </div>
        <div>
          <h4 class="text-white font-bold mb-2">2. Data Security</h4>
          <p>We implement appropriate technical measures to protect your personal data against unauthorized access. Your data is encrypted and stored securely.</p>
        </div>
        <div>
          <h4 class="text-white font-bold mb-2">3. Data Usage</h4>
          <p>Your information is used solely for mall operations, communication, and system improvement. We do not sell your data to third parties.</p>
        </div>
      </div>
      
      <div class="p-6 bg-white/5 border-t border-white/5 flex justify-end">
        <button onclick="closePrivacyModal()" class="px-8 py-3 rounded-xl bg-blue-500 text-white font-bold hover:bg-blue-600 transition-colors shadow-lg">Close</button>
      </div>
    </div>
  </div>

  <!-- Loading Modal Overlay -->
  <div id="loadingOverlay" class="fixed inset-0 bg-[#020617]/90 backdrop-blur-xl flex items-center justify-center hidden z-[200]">
    <div class="glass-dark rounded-[2.5rem] p-12 max-w-sm w-full text-center border border-white/10 shadow-[0_0_100px_rgba(16,185,129,0.15)]" data-aos="zoom-in">
      <div class="h-24 w-24 rounded-full bg-emerald-500/20 flex items-center justify-center mx-auto mb-8 relative">
        <div class="absolute inset-0 rounded-full border-4 border-emerald-500/20 border-t-emerald-500 animate-spin"></div>
        <i class="fas fa-shield-alt text-4xl text-emerald-400"></i>
      </div>
      <h3 class="text-2xl font-black text-white mb-2">Authenticating</h3>
      <p class="text-white/40 text-sm mb-8">Securing your session...</p>
      
      <div class="w-full h-1.5 bg-white/5 rounded-full overflow-hidden mb-8">
        <div class="h-full bg-gradient-to-r from-emerald-500 to-blue-500 rounded-full animate-progress-bar"></div>
      </div>

      <div class="flex justify-center gap-2">
        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-bounce"></div>
        <div class="w-2 h-2 rounded-full bg-blue-500 animate-bounce [animation-delay:-0.2s]"></div>
        <div class="w-2 h-2 rounded-full bg-violet-500 animate-bounce [animation-delay:-0.4s]"></div>
      </div>
    </div>
  </div>

  <style>
    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }
    
    @keyframes slideUp {
      from {
        transform: translateY(30px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }
    
    @keyframes logoFloat {
      0%, 100% { 
        transform: translateY(0px);
      }
      50% { 
        transform: translateY(-10px);
      }
    }
    
    @keyframes progressBar {
      from { width: 0%; }
      to { width: 100%; }
    }
    
    .animate-progress-bar {
      animation: progressBar 3s ease-out forwards;
    }
    
    @keyframes bounce {
      0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
      40% { transform: translateY(-10px); opacity: 1; }
    }

    @keyframes shakeX {
      from, to { transform: translate3d(0, 0, 0); }
      10%, 30%, 50%, 70%, 90% { transform: translate3d(-10px, 0, 0); }
      20%, 40%, 60%, 80% { transform: translate3d(10px, 0, 0); }
    }
    
    [data-aos="shakeX"].aos-animate {
      animation: shakeX 0.8s ease-in-out;
    }

    /* Custom scrollbar for modals */
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

  <!-- AOS -->
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
    // Initialize AOS
    AOS.init({
      once: true,
      duration: 800,
      easing: 'ease-out-quad',
      offset: 0
    });

    // Login logic
    (function(){
      const form = document.querySelector('form');
      const loadingOverlay = document.getElementById('loadingOverlay');
      const loginButton = document.getElementById('loginButton');
      
      if (form && loadingOverlay && loginButton) {
        form.addEventListener('submit', function(e) {
          if (form.checkValidity()) {
            e.preventDefault();
            loadingOverlay.classList.remove('hidden');
            loadingOverlay.classList.add('flex');
            
            // Re-trigger AOS for loading card
            const loadingCard = loadingOverlay.querySelector('div[data-aos]');
            if (loadingCard) {
              loadingCard.classList.remove('aos-animate');
              setTimeout(() => loadingCard.classList.add('aos-animate'), 10);
            }

            // Important: Add hidden input to ensure 'login_submit' exists in $_POST
            if (!form.querySelector('input[name="login_submit"]')) {
              const hiddenInput = document.createElement('input');
              hiddenInput.type = 'hidden';
              hiddenInput.name = 'login_submit';
              hiddenInput.value = '1';
              form.appendChild(hiddenInput);
            }

            setTimeout(() => {
              form.submit();
            }, 3000);
          }
        });
      }
    })();

    // Password Toggle
    (function(){
      const pwdInput = document.getElementById('password');
      const toggleBtn = document.getElementById('togglePassword');
      const eyeIcon = document.getElementById('eyeIcon');
      if (!pwdInput || !toggleBtn) return;
      
      toggleBtn.addEventListener('click', () => {
        const type = pwdInput.type === 'password' ? 'text' : 'password';
        pwdInput.type = type;
        eyeIcon.className = type === 'password' ? 'fas fa-eye text-lg' : 'fas fa-eye-slash text-lg';
      });
    })();

    // Modal Handlers
    function toggleModal(id, show) {
      const modal = document.getElementById(id);
      const content = modal.querySelector('div[data-aos]');
      if (show) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        content.classList.remove('aos-animate');
        setTimeout(() => content.classList.add('aos-animate'), 10);
      } else {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
      }
    }

    window.openTermsModal = () => toggleModal('termsModal', true);
    window.closeTermsModal = () => toggleModal('termsModal', false);
    window.openPrivacyModal = () => toggleModal('privacyModal', true);
    window.closePrivacyModal = () => toggleModal('privacyModal', false);

    // Outside Click
    window.onclick = (e) => {
      if (e.target.id === 'termsModal') closeTermsModal();
      if (e.target.id === 'privacyModal') closePrivacyModal();
    }
  </script>
</body>
</html>
