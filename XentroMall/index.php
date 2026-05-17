<?php
session_start();
$login_error = '';
if (isset($_SESSION['login_error'])) {
    $login_error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html class="scroll-smooth" lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>XentroMall — Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <meta name="color-scheme" content="light dark" />
  <style>
    :root { color-scheme: light only; }
    body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial; background:#0b1220; }
    /* Background hero with image + gradient overlay */
    .hero-bg { position: fixed; inset: 0; z-index: -10; overflow: hidden; }
    .hero-bg::before { content: ""; position: absolute; inset: 0; background: url('img/bg.jpg') center/cover no-repeat; filter: brightness(0.55) saturate(1.1); transform: scale(1.02); }
    .hero-bg::after { content: ""; position: absolute; inset: 0; background:
      radial-gradient(1200px 600px at 10% -10%, rgba(56,189,248,.25), transparent 60%),
      radial-gradient(900px 500px at 90% 110%, rgba(34,197,94,.20), transparent 60%),
      linear-gradient(180deg, rgba(2,6,23,.65), rgba(2,6,23,.65));
      mix-blend-mode: screen; }
  </style>
</head>
<body class="min-h-screen text-slate-800">
  <div class="hero-bg" aria-hidden="true"></div>

  <header class="fixed top-0 inset-x-0 z-40">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <nav class="mt-4 flex items-center justify-between rounded-2xl bg-white/10 backdrop-blur-xl ring-1 ring-white/15 px-4 py-3 shadow-lg">
        <!-- Brand -->
        <a href="#" class="flex items-center gap-3">
          <span class="h-10 w-10 overflow-hidden rounded-xl ring-1 ring-white/30 bg-white/70 flex items-center justify-center shadow">
            <img src="img/logo.jpg" alt="XentroMall" class="h-9 w-9 object-cover" />
          </span>
          <span class="text-white font-semibold tracking-tight text-lg">Xentro Mall</span>
        </a>

        <!-- Desktop nav -->
        <div class="hidden md:flex items-center gap-8">
          <a href="#" class="text-white/90 hover:text-white font-medium">Home</a>
          <a href="#about-system-section" class="text-white/90 hover:text-white font-medium">About</a>
          <a href="contact.php" class="text-white/90 hover:text-white font-medium">Contacts</a>
        </div>

        <!-- Actions -->
        <div class="hidden md:flex items-center gap-3">
          <a href="login.php" class="inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-blue-600 to-emerald-500 px-4 py-2.5 text-white font-semibold shadow-lg shadow-emerald-500/20 hover:from-blue-700 hover:to-emerald-600 focus:outline-none focus:ring-4 focus:ring-blue-600/30 transition">
            Login
          </a>
        </div>

        <!-- Mobile menu button -->
        <button id="mobile-menu-button" aria-label="Toggle menu" aria-expanded="false" class="md:hidden inline-flex items-center justify-center rounded-lg p-2 text-white/90 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/40">
          <svg id="hamburger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z"/></svg>
        </button>
      </nav>

      <!-- Mobile nav -->
      <div id="mobile-menu" class="hidden md:hidden mt-2 rounded-2xl bg-white/10 backdrop-blur-xl ring-1 ring-white/15 p-2 shadow-lg">
        <a href="#" class="block rounded-lg px-4 py-2.5 text-white/90 hover:bg-white/10 hover:text-white">Home</a>
        <a href="#about-system-section" class="block rounded-lg px-4 py-2.5 text-white/90 hover:bg-white/10 hover:text-white">About</a>
        <a href="contact.php" class="block rounded-lg px-4 py-2.5 text-white/90 hover:bg-white/10 hover:text-white">Contacts</a>
        <a href="login.php" class="mt-2 block rounded-lg bg-gradient-to-r from-blue-600 to-emerald-500 px-4 py-2.5 text-center text-white font-semibold shadow hover:from-blue-700 hover:to-emerald-600">Login</a>
      </div>

      <?php if (!empty($login_error)): ?>
      <div class="mt-3 rounded-xl border border-red-300/40 bg-red-50/80 text-red-700 px-4 py-3 text-sm">
        <?php echo htmlspecialchars($login_error, ENT_QUOTES); ?>
      </div>
      <?php endif; ?>
    </div>
  </header>

  <main>
    <!-- Hero -->
    <section class="relative pt-40 pb-32">
      <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="mx-auto max-w-4xl text-center">
          <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold tracking-tight text-white drop-shadow-lg mb-6">
            Welcome to <span class="bg-gradient-to-r from-blue-400 to-emerald-400 bg-clip-text text-transparent">Xentro Mall</span> Portal
          </h1>
          <p class="mt-6 text-xl md:text-2xl text-white/90 leading-relaxed max-w-2xl mx-auto">
            Your gateway to seamless tenant management and mall operations.
          </p>
          <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
            <a href="user_stall_page.php" class="group inline-flex items-center justify-center gap-3 rounded-2xl bg-gradient-to-r from-emerald-500 to-blue-500 px-8 py-4 text-white font-bold text-lg shadow-xl hover:from-emerald-600 hover:to-blue-600 transition-all duration-200 hover:scale-105 ring-2 ring-white/30">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                <path d="M3 3h18v13H3V3zm0 15h18v3H3v-3zM5 5v9h14V5H5z"/>
              </svg>
              <span>Explore Available Stall</span>
            </a>
          </div>

          <!-- Stats -->
          <div class="mt-16 grid grid-cols-3 gap-6 max-w-2xl mx-auto">
            <div class="rounded-2xl bg-white/10 backdrop-blur-xl ring-1 ring-white/20 p-4">
              <div class="text-3xl font-bold text-white">50+</div>
              <div class="text-sm text-white/70 mt-1">Available Stalls</div>
            </div>
            <div class="rounded-2xl bg-white/10 backdrop-blur-xl ring-1 ring-white/20 p-4">
              <div class="text-3xl font-bold text-white">100%</div>
              <div class="text-sm text-white/70 mt-1">Secure Portal</div>
            </div>
            <div class="rounded-2xl bg-white/10 backdrop-blur-xl ring-1 ring-white/20 p-4">
              <div class="text-3xl font-bold text-white">24/7</div>
              <div class="text-sm text-white/70 mt-1">Support</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Decorative bottom wave -->
      <div class="pointer-events-none absolute inset-x-0 bottom-0" aria-hidden="true">
        <svg class="w-full" viewBox="0 0 1440 80" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 40c120 0 120-40 240-40s120 40 240 40 120-40 240-40 120 40 240 40 120-40 240-40 120 40 240 40v40H0V40z" fill="rgba(255,255,255,0.08)"/></svg>
      </div>
    </section>

    <!-- Features -->
    <section class="relative py-20">
      <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Everything You Need</h2>
          <p class="text-white/70 text-lg max-w-2xl mx-auto">Powerful features designed to streamline your tenant management experience</p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <!-- Card 1 -->
          <div class="group relative rounded-2xl bg-white/90 backdrop-blur-xl ring-1 ring-white/20 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
            <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-blue-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative">
              <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-blue-600 to-blue-500 text-white flex items-center justify-center mb-4 shadow-lg shadow-blue-500/30 group-hover:scale-110 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M3 3h18v13H3V3zm0 15h18v3H3v-3zM5 5v9h14V5H5z"/></svg>
              </div>
              <h3 class="font-bold text-slate-900 mb-2 text-lg">Stalls Marketplace</h3>
              <p class="text-slate-600 text-sm leading-relaxed">Browse available stalls and apply with streamlined onboarding.</p>
            </div>
          </div>

          <!-- Card 2 -->
          <div class="group relative rounded-2xl bg-white/90 backdrop-blur-xl ring-1 ring-white/20 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
            <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-emerald-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative">
              <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-emerald-600 to-emerald-500 text-white flex items-center justify-center mb-4 shadow-lg shadow-emerald-500/30 group-hover:scale-110 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M12 3l9 4v6c0 5-9 8-9 8s-9-3-9-8V7l9-4zm0 2.18L6 7.09v5.03C6.43 15.5 9.64 17.34 12 18.25c2.36-.91 5.57-2.75 6-6.13V7.09l-6-1.91z"/></svg>
              </div>
              <h3 class="font-bold text-slate-900 mb-2 text-lg">Tenant Self‑Service</h3>
              <p class="text-slate-600 text-sm leading-relaxed">Manage profiles, documents, and renewals from one portal.</p>
            </div>
          </div>

          <!-- Card 3 -->
          <div class="group relative rounded-2xl bg-white/90 backdrop-blur-xl ring-1 ring-white/20 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
            <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-amber-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative">
              <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-amber-600 to-amber-500 text-white flex items-center justify-center mb-4 shadow-lg shadow-amber-500/30 group-hover:scale-110 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M11 21h2v-2h-2v2zM11 3h2v10h-2V3zm-4.95 1.64l1.41 1.41A7.96 7.96 0 004 12c0 2.21.9 4.21 2.35 5.65l-1.41 1.41A9.96 9.96 0 012 12c0-2.76 1.12-5.26 3.05-7.08zM17.65 5.05l1.41-1.41A9.96 9.96 0 0122 12a9.96 9.96 0 01-2.95 7.05l-1.41-1.41A7.96 7.96 0 0020 12a7.96 7.96 0 00-2.35-6.95z"/></svg>
              </div>
              <h3 class="font-bold text-slate-900 mb-2 text-lg">Maintenance & Support</h3>
              <p class="text-slate-600 text-sm leading-relaxed">Submit and track requests with transparent status updates.</p>
            </div>
          </div>

          <!-- Card 4 -->
          <div class="group relative rounded-2xl bg-white/90 backdrop-blur-xl ring-1 ring-white/20 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
            <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-violet-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative">
              <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-violet-600 to-violet-500 text-white flex items-center justify-center mb-4 shadow-lg shadow-violet-500/30 group-hover:scale-110 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M12 1L3 5v6c0 5 4 9 9 12 5-3 9-7 9-12V5l-9-4zm0 2.18L19 6v5c0 3.93-3.06 7.17-7 9.74C8.06 18.17 5 14.93 5 11V6l7-2.82zM11 7h2v5h-2zm0 6h2v2h-2z"/></svg>
              </div>
              <h3 class="font-bold text-slate-900 mb-2 text-lg">Secure & Compliant</h3>
              <p class="text-slate-600 text-sm leading-relaxed">Role‑based access and modern security best practices.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- About -->
    <section id="about-system-section" class="relative py-24">
      <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
          <div class="order-2 lg:order-1">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 backdrop-blur-xl ring-1 ring-white/20 px-4 py-2 mb-6">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-emerald-400">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
              </svg>
              <span class="text-sm font-medium text-white/90">Trusted Platform</span>
            </div>

            <h2 class="text-4xl md:text-5xl font-bold text-white mb-6">About</h2>
            <p class="text-xl text-white/90 leading-relaxed mb-6">
              Xentro Mall Portal streamlines tenant registration, document submission, profile management, maintenance requests, renewals, and payment tracking—centralized for efficient operations.
            </p>
            <p class="text-lg text-white/80 leading-relaxed mb-8">
              Built with security and user experience in mind, the portal features secure login, role‑based access, and a responsive interface that adapts across devices.
            </p>

            <!-- Feature List -->
            <div class="space-y-4">
              <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-500/20 flex items-center justify-center mt-0.5">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-emerald-400">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                  </svg>
                </div>
                <div>
                  <h4 class="text-white font-semibold mb-1">Streamlined Operations</h4>
                  <p class="text-white/70 text-sm">Manage everything from a single, intuitive dashboard</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-500/20 flex items-center justify-center mt-0.5">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-blue-400">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                  </svg>
                </div>
                <div>
                  <h4 class="text-white font-semibold mb-1">Secure & Reliable</h4>
                  <p class="text-white/70 text-sm">Enterprise-grade security protecting your data 24/7</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-6 h-6 rounded-full bg-violet-500/20 flex items-center justify-center mt-0.5">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-violet-400">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                  </svg>
                </div>
                <div>
                  <h4 class="text-white font-semibold mb-1">Mobile Responsive</h4>
                  <p class="text-white/70 text-sm">Access from any device, anywhere, anytime</p>
                </div>
              </div>
            </div>
          </div>

          <div class="order-1 lg:order-2">
            <div class="relative">
              <div class="absolute -inset-4 rounded-3xl bg-gradient-to-r from-blue-500/30 via-emerald-400/30 to-violet-500/30 blur-3xl animate-pulse" aria-hidden="true"></div>
              <div class="relative rounded-3xl overflow-hidden ring-1 ring-white/20 shadow-2xl bg-white/10 backdrop-blur-sm">
                <img src="img/stall.png" alt="Stalls" class="w-full h-full object-cover" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="relative border-t border-white/10 bg-black/30 backdrop-blur-xl">
    <div class="mx-auto max-w-7xl px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
        <!-- Brand -->
        <div>
          <div class="flex items-center gap-3 mb-4">
            <span class="h-10 w-10 overflow-hidden rounded-xl ring-1 ring-white/30 bg-white/70 flex items-center justify-center shadow">
              <img src="img/logo.jpg" alt="XentroMall" class="h-9 w-9 object-cover" />
            </span>
            <span class="text-white font-semibold tracking-tight text-lg">Xentro Mall</span>
          </div>
          <p class="text-white/60 text-sm leading-relaxed">Your trusted partner for seamless tenant management and mall operations.</p>
        </div>

        <!-- Quick Links -->
        <div>
          <h3 class="text-white font-semibold mb-4">Quick Links</h3>
          <ul class="space-y-2">
            <li><a href="#" class="text-white/60 hover:text-white text-sm transition">Home</a></li>
            <li><a href="#about-system-section" class="text-white/60 hover:text-white text-sm transition">About</a></li>
            <li><a href="user_stall_page.php" class="text-white/60 hover:text-white text-sm transition">Available Stalls</a></li>
            <li><a href="contact.php" class="text-white/60 hover:text-white text-sm transition">Contact Us</a></li>
          </ul>
        </div>

        <!-- Contact Info -->
        <div>
          <h3 class="text-white font-semibold mb-4">Get Started</h3>
          <p class="text-white/60 text-sm mb-4">Ready to join our community?</p>
          <a href="login.php" class="inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-blue-600 to-emerald-500 px-4 py-2.5 text-white text-sm font-semibold shadow-lg shadow-emerald-500/20 hover:from-blue-700 hover:to-emerald-600 transition">
            Sign In Now
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
              <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd"/>
            </svg>
          </a>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div class="pt-6 border-t border-white/10 flex flex-col md:flex-row items-center justify-between gap-4">
        <p class="text-xs text-white/60">© <?php echo date('Y'); ?> XentroMall • All rights reserved</p>
        <div class="flex items-center gap-4">
          <span class="text-xs text-white/60">Tenant Management System</span>
          <span class="text-xs text-white/40">•</span>
          <span class="text-xs text-white/60">Powered by Modern Technology</span>
        </div>
      </div>
    </div>
  </footer>

  <script>
    // Mobile menu toggle
    (function() {
      const btn = document.getElementById('mobile-menu-button');
      const menu = document.getElementById('mobile-menu');
      if (!btn || !menu) return;
      btn.addEventListener('click', () => {
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!expanded));
        menu.classList.toggle('hidden');
      });
    })();
  </script>
</body>
</html>