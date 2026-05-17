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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
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
  <meta name="color-scheme" content="light dark" />
  <style>
    :root { color-scheme: light only; }
    html, body { height: 100%; }
    body {
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji';
      margin: 0;
      background: #0b1220;
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

  <main class="relative z-10 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md">
      <!-- Card -->
      <div class="relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-black/5">
        <div class="absolute -inset-px rounded-2xl pointer-events-none" style="background: radial-gradient(800px 400px at 0% 0%, rgba(59,130,246,.08), transparent 50%), radial-gradient(700px 400px at 100% 100%, rgba(16,185,129,.08), transparent 50%);"></div>

        <div class="relative p-8 sm:p-10">
          <!-- Brand -->
          <div class="flex items-center gap-4 mb-6">
            <div class="h-12 w-12 rounded-xl overflow-hidden ring-1 ring-black/10 shadow-sm bg-white/60 flex items-center justify-center">
              <img src="img/logo.jpg" alt="XentroMall" class="h-10 w-10 object-cover" />
            </div>
            <div>
              <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Sign in</h1>
              <p class="text-sm text-slate-600">Tenant Management System</p>
            </div>
          </div>

          <!-- Error message -->
          <?php if (!empty($login_error)): ?>
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
              <?php echo htmlspecialchars($login_error, ENT_QUOTES); ?>
            </div>
          <?php endif; ?>

          <form action="login.php" method="post" class="space-y-5" novalidate>
            <!-- Username / Email -->
            <div>
              <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Username or Email</label>
              <div class="relative">
                <input
                  type="text"
                  id="username"
                  name="username"
                  value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES) : '';?>"
                  required
                  autocomplete="username"
                  placeholder="yourname or you@example.com"
                  class="peer w-full rounded-lg border border-slate-300 bg-white/90 px-4 py-3 text-slate-900 placeholder-slate-400 shadow-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 transition"
                />
                <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M2 5a2 2 0 012-2h6a2 2 0 012 2v1h2a2 2 0 012 2v7a2 2 0 01-2 2H8a2 2 0 01-2-2v-1H4a2 2 0 01-2-2V5zm8 1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v7a1 1 0 001 1h2V9a2 2 0 012-2h4z"/></svg>
                </div>
              </div>
            </div>

            <!-- Password -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                <a href="forgot_password.php" class="text-sm font-medium text-blue-600 hover:text-blue-700">Forgot?</a>
              </div>
              <div class="relative">
                <input
                  type="password"
                  id="password"
                  name="password"
                  required
                  autocomplete="current-password"
                  placeholder="••••••••"
                  class="peer w-full rounded-lg border border-slate-300 bg-white/90 px-4 py-3 pr-11 text-slate-900 placeholder-slate-400 shadow-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 transition"
                />
                <button type="button" id="togglePassword" aria-label="Show password" class="absolute inset-y-0 right-2.5 inline-flex items-center justify-center rounded-md p-2 text-slate-500 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                  <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M12 5c-7 0-10 7-10 7s3 7 10 7 10-7 10-7-3-7-10-7zm0 12a5 5 0 110-10 5 5 0 010 10z"/><circle cx="12" cy="12" r="3" fill="white"/></svg>
                </button>
              </div>
            </div>

            <!-- Submit -->
            <button
              type="submit"
              name="login_submit"
              id="loginButton"
              class="login-button ripple-button w-full inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-blue-600 to-emerald-500 px-4 py-3 text-white font-semibold shadow-lg shadow-emerald-500/20 hover:from-blue-700 hover:to-emerald-600 focus:outline-none focus:ring-4 focus:ring-blue-600/30 active:scale-[.99] transition"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M10 3a1 1 0 00-1 1v6H6.5a.5.5 0 00-.354.854l5 5a.5.5 0 00.708 0l5-5A.5.5 0 0016.5 10H13V4a1 1 0 00-1-1h-2z"/><path d="M4 19h16v2H4z"/></svg>
              Sign in
            </button>

            <!-- Links -->
            <div class="text-sm text-slate-600 text-center space-y-2">
              <div>
                Don't have an account?
                <a href="user_stall_page.php" class="font-medium text-blue-600 hover:text-blue-700">Register</a>
              </div>
              <div>
                Need to verify your email?
                <a href="resend_verification.php" class="font-medium text-blue-600 hover:text-blue-700">Resend Verification</a>
              </div>
            </div>

            <!-- Terms Agreement Checkbox -->
            <div class="flex items-start gap-3">
              <input
                type="checkbox"
                id="termsAgreement"
                name="termsAgreement"
                class="mt-1 h-4 w-4 rounded border-slate-300 bg-white/90 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
              <label for="termsAgreement" class="text-xs text-slate-600 leading-relaxed">
                I have read and agree to the 
                <a href="#" onclick="openTermsModal()" class="font-medium text-blue-600 hover:text-blue-700 underline">Terms of Use</a> 
                and 
                <a href="#" onclick="openPrivacyModal()" class="font-medium text-blue-600 hover:text-blue-700 underline">Privacy Policy</a>
                <span class="text-slate-500 font-normal">(Required for tenant accounts)</span>
              </label>
            </div>

            <!-- Terms and Conditions Notice -->
            <div class="text-xs text-slate-500 text-center leading-relaxed">
              By signing in, you acknowledge that you have read and agreed to our terms and policies
            </div>
          </form>
        </div>
      </div>

      <!-- Small footer -->
      <p class="mt-6 text-center text-xs text-slate-400">
        © <?php echo date('Y'); ?> XentroMall • All rights reserved
      </p>
    </div>
  </main>

  <!-- Terms of Use Modal -->
  <div id="termsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(8px); animation: fadeIn 0.3s ease-out;">
    <div style="background: white; border-radius: 24px; padding: 0; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); max-width: 600px; max-height: 80vh; width: 90%; animation: slideUp 0.4s ease-out; overflow: hidden;">
      <!-- Header -->
      <div style="background: linear-gradient(135deg, #10b981, #3b82f6); padding: 24px 32px; position: relative;">
        <h3 style="color: white; margin: 0; font-size: 24px; font-weight: 700;">Terms of Use</h3>
        <p style="color: rgba(255, 255, 255, 0.9); margin: 4px 0 0; font-size: 14px;">XentroMall Tenant Management System</p>
        <button onclick="closeTermsModal()" style="position: absolute; top: 20px; right: 20px; background: rgba(255, 255, 255, 0.2); border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s;">
          <svg xmlns="http://www.w3.org/2000/svg" fill="white" style="width: 16px; height: 16px;" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
          </svg>
        </button>
      </div>
      
      <!-- Content -->
      <div style="padding: 32px; overflow-y: auto; max-height: calc(80vh - 120px);" class="custom-scrollbar">
        <div style="color: #475569; font-size: 14px; line-height: 1.6; space-y: 16px;">
          
          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">1. Acceptance of Terms</h4>
            <p>By accessing and using the XentroMall Tenant Management System (TMS), you acknowledge that you have read, understood, and agree to be bound by these Terms of Use. If you do not agree to these terms, please do not use our services.</p>
          </div>

          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">2. Account Registration and Security</h4>
            <p style="margin-bottom: 4px;">• You must provide accurate, complete, and current information when registering for an account.</p>
            <p style="margin-bottom: 4px;">• You are responsible for maintaining the confidentiality of your account credentials.</p>
            <p style="margin-bottom: 4px;">• You agree to notify us immediately of any unauthorized use of your account.</p>
            <p>• You must be at least 18 years old or have parental consent to use this service.</p>
          </div>

          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">3. Tenant Responsibilities</h4>
            <p style="margin-bottom: 4px;">• Tenants must provide accurate business information and documentation.</p>
            <p style="margin-bottom: 4px;">• Tenants must comply with all applicable laws, regulations, and mall policies.</p>
            <p style="margin-bottom: 4px;">• Tenants are responsible for timely payment of rent and other applicable fees.</p>
            <p>• Tenants must maintain their stall space in accordance with mall standards.</p>
          </div>

          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">4. System Usage</h4>
            <p style="margin-bottom: 4px;">• You may use the TMS for legitimate business purposes related to your tenancy.</p>
            <p style="margin-bottom: 4px;">• You agree not to use the system for illegal activities or interfere with system operations.</p>
            <p>• We reserve the right to suspend or terminate accounts that violate these terms.</p>
          </div>

          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">5. Limitation of Liability</h4>
            <p>The TMS is provided "as is" without warranties of any kind. XentroMall shall not be liable for any indirect, incidental, or consequential damages arising from your use of the system.</p>
          </div>

          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">6. Contact Information</h4>
            <p>For questions about these Terms of Use, please contact XentroMall Management at admin@xentromall.com or visit our administration office.</p>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- Privacy Policy Modal -->
  <div id="privacyModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(8px); animation: fadeIn 0.3s ease-out;">
    <div style="background: white; border-radius: 24px; padding: 0; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); max-width: 600px; max-height: 80vh; width: 90%; animation: slideUp 0.4s ease-out; overflow: hidden;">
      <!-- Header -->
      <div style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); padding: 24px 32px; position: relative;">
        <h3 style="color: white; margin: 0; font-size: 24px; font-weight: 700;">Privacy Policy</h3>
        <p style="color: rgba(255, 255, 255, 0.9); margin: 4px 0 0; font-size: 14px;">XentroMall Tenant Management System</p>
        <button onclick="closePrivacyModal()" style="position: absolute; top: 20px; right: 20px; background: rgba(255, 255, 255, 0.2); border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s;">
          <svg xmlns="http://www.w3.org/2000/svg" fill="white" style="width: 16px; height: 16px;" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
          </svg>
        </button>
      </div>
      
      <!-- Content -->
      <div style="padding: 32px; overflow-y: auto; max-height: calc(80vh - 120px);" class="custom-scrollbar">
        <div style="color: #475569; font-size: 14px; line-height: 1.6; space-y: 16px;">
          
          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">1. Information We Collect</h4>
            <p style="margin-bottom: 4px;">• Personal Information: Name, email address, phone number, and contact details.</p>
            <p style="margin-bottom: 4px;">• Business Information: Business name, type, documents, and financial details.</p>
            <p>• Usage Data: Login times, pages visited, and actions performed within the system.</p>
          </div>

          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">2. How We Use Your Information</h4>
            <p style="margin-bottom: 4px;">• To process and manage your tenant applications and account.</p>
            <p style="margin-bottom: 4px;">• To communicate with you about your account and services.</p>
            <p style="margin-bottom: 4px;">• To improve our services and system functionality.</p>
            <p>• To comply with legal obligations and protect our rights.</p>
          </div>

          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">3. Data Security</h4>
            <p>We implement appropriate technical and organizational measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction. Your data is encrypted and stored on secure servers.</p>
          </div>

          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">4. Data Sharing</h4>
            <p style="margin-bottom: 4px;">• We do not sell your personal information to third parties.</p>
            <p style="margin-bottom: 4px;">• We may share your data with service providers who assist in operating our system.</p>
            <p>• We may disclose information when required by law or to protect our rights.</p>
          </div>

          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">5. Your Rights</h4>
            <p style="margin-bottom: 4px;">• You have the right to access, update, or delete your personal information.</p>
            <p style="margin-bottom: 4px;">• You can request a copy of your data or correct inaccuracies.</p>
            <p>• You may opt-out of certain communications and data processing activities.</p>
          </div>

          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">6. Cookies and Tracking</h4>
            <p>We use cookies and similar technologies to enhance your experience, remember preferences, and analyze system usage. You can control cookie settings through your browser preferences.</p>
          </div>

          <div>
            <h4 style="color: #1e293b; font-weight: 600; margin-bottom: 8px;">7. Contact Information</h4>
            <p>For privacy-related questions or to exercise your rights, contact our Data Protection Officer at privacy@xentromall.com.</p>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- Loading Modal Overlay -->
  <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(8px); animation: fadeIn 0.3s ease-out;">
    <!-- Modal Card -->
    <div style="background: white; border-radius: 24px; padding: 48px 64px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); max-width: 400px; animation: slideUp 0.4s ease-out;">
      <!-- Logo Icon -->
      <div style="width: 100px; height: 100px; margin: 0 auto 24px; background: linear-gradient(135deg, #10b981, #3b82f6); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); animation: logoFloat 2s ease-in-out infinite;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" style="width: 50px; height: 50px;">
          <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z" />
          <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.43z" />
        </svg>
      </div>
      
      <!-- Title -->
      <h3 style="color: #1e293b; margin: 0 0 8px; font-size: 24px; font-weight: 700; text-align: center;">XentroMall</h3>
      <p style="color: #64748b; margin: 0 0 32px; font-size: 15px; text-align: center;">Logging you in securely...</p>
      
      <!-- Progress Bar -->
      <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-bottom: 24px;">
        <div style="height: 100%; background: linear-gradient(90deg, #10b981, #3b82f6); border-radius: 999px; animation: progressBar 3s ease-out forwards;"></div>
      </div>
      
      <!-- Loading Dots -->
      <div style="display: flex; gap: 8px; justify-content: center;">
        <div style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; animation: bounce 1.4s ease-in-out 0s infinite;"></div>
        <div style="width: 8px; height: 8px; background: #3b82f6; border-radius: 50%; animation: bounce 1.4s ease-in-out 0.2s infinite;"></div>
        <div style="width: 8px; height: 8px; background: #8b5cf6; border-radius: 50%; animation: bounce 1.4s ease-in-out 0.4s infinite;"></div>
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
      from {
        width: 0%;
      }
      to {
        width: 100%;
      }
    }
    
    @keyframes bounce {
      0%, 80%, 100% { 
        transform: translateY(0);
        opacity: 0.4;
      }
      40% { 
        transform: translateY(-10px);
        opacity: 1;
      }
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

  <script>
    // Login form with 3 second delay
    (function(){
      const form = document.querySelector('form');
      const loadingOverlay = document.getElementById('loadingOverlay');
      const loginButton = document.getElementById('loginButton');
      
      if (form && loadingOverlay && loginButton) {
        loginButton.addEventListener('click', function(e) {
          // Check if form is valid
          if (form.checkValidity()) {
            e.preventDefault();
            
            // Show loading overlay
            loadingOverlay.style.display = 'flex';
            
            // Create a hidden submit button to bypass the event listener
            const hiddenSubmit = document.createElement('input');
            hiddenSubmit.type = 'submit';
            hiddenSubmit.name = 'login_submit';
            hiddenSubmit.style.display = 'none';
            form.appendChild(hiddenSubmit);
            
            // Wait 3 seconds then submit
            setTimeout(() => {
              hiddenSubmit.click();
            }, 3000);
          }
        });
      }
    })();

    // Password visibility toggle
    (function(){
      const input = document.getElementById('password');
      const toggle = document.getElementById('togglePassword');
      const icon = document.getElementById('eyeIcon');
      if (!input || !toggle) return;
      toggle.addEventListener('click', () => {
        const isPwd = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPwd ? 'text' : 'password');
        icon.innerHTML = isPwd
          ? '<path d="M12 5c-7 0-10 7-10 7s3 7 10 7c2.63 0 4.76-.9 6.44-2.1l2.28 2.28 1.41-1.41-2.18-2.18C21.58 14.58 22 12 22 12s-3-7-10-7zm0 12a5 5 0 01-5-5c0-.86.22-1.67.6-2.37l1.53 1.53A3 3 0 0012 15a3 3 0 002.84-2l1.74 1.74A5 5 0 0112 17z"/>'
          : '<path d="M12 5c-7 0-10 7-10 7s3 7 10 7 10-7 10-7-3-7-10-7zm0 12a5 5 0 110-10 5 5 0 010 10z"/><circle cx="12" cy="12" r="3" fill="white"/>';
      });
    })();

    // Button Ripple Effect
    (function(){
      const loginButton = document.getElementById('loginButton');
      if (!loginButton) return;

      function createRipple(event) {
        const button = event.currentTarget;
        const rect = button.getBoundingClientRect();
        
        // Calculate the diameter to ensure perfect circle coverage
        const diameter = Math.max(rect.width, rect.height) * 2;
        const radius = diameter / 2;
        
        // Get click position relative to button
        const x = event.clientX - rect.left - radius;
        const y = event.clientY - rect.top - radius;
        
        const ripple = document.createElement('span');
        ripple.className = 'ripple';
        
        // Ensure perfect circle by setting equal width and height
        ripple.style.width = diameter + 'px';
        ripple.style.height = diameter + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.style.borderRadius = '50%';
        
        button.appendChild(ripple);
        
        // Remove ripple after animation completes
        setTimeout(() => {
          if (ripple.parentNode) {
            ripple.parentNode.removeChild(ripple);
          }
        }, 800);
      }

      loginButton.addEventListener('click', createRipple);
      
      // Also add ripple effect on Enter key press when button is focused
      loginButton.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          createRipple(event);
        }
      });
    })();

    // Enhanced button feedback
    (function(){
      const loginButton = document.getElementById('loginButton');
      if (!loginButton) return;

      loginButton.addEventListener('mousedown', () => {
        loginButton.style.transform = 'translateY(1px) scale(0.98)';
      });

      loginButton.addEventListener('mouseup', () => {
        loginButton.style.transform = '';
      });

      loginButton.addEventListener('mouseleave', () => {
        loginButton.style.transform = '';
      });
    })();

    // Modal Functions
    function openTermsModal() {
      document.getElementById('termsModal').style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function closeTermsModal() {
      document.getElementById('termsModal').style.display = 'none';
      document.body.style.overflow = '';
    }

    function openPrivacyModal() {
      document.getElementById('privacyModal').style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function closePrivacyModal() {
      document.getElementById('privacyModal').style.display = 'none';
      document.body.style.overflow = '';
    }

    // Enable/Disable form fields based on checkbox
    (function(){
      const termsCheckbox = document.getElementById('termsAgreement');
      const usernameField = document.getElementById('username');
      const passwordField = document.getElementById('password');
      const loginButton = document.getElementById('loginButton');

      function toggleFormFields() {
        const isChecked = termsCheckbox.checked;
        usernameField.disabled = !isChecked;
        passwordField.disabled = !isChecked;
        loginButton.disabled = !isChecked;
      }

      if (termsCheckbox) {
        termsCheckbox.addEventListener('change', toggleFormFields);
      }
    })();

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
      const termsModal = document.getElementById('termsModal');
      const privacyModal = document.getElementById('privacyModal');
      
      if (event.target === termsModal) {
        closeTermsModal();
      }
      if (event.target === privacyModal) {
        closePrivacyModal();
      }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        closeTermsModal();
        closePrivacyModal();
      }
    });
  </script>
</body>
</html>
