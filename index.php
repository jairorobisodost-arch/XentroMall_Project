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
  <!-- AOS Animation Library -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <meta name="color-scheme" content="light dark" />
  <style>
    :root { color-scheme: light only; }
    body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial; background:#020617; overflow-x: hidden; }
    
    /* Smooth transition for glassmorphism */
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
        radial-gradient(circle at 20% 30%, rgba(59, 130, 246, 0.15), transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(16, 185, 129, 0.1), transparent 50%),
        linear-gradient(to bottom, transparent, rgba(2, 6, 23, 0.95));
    }

    /* Custom button effect */
    .btn-premium {
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
    }
    .btn-premium::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
      transform: rotate(45deg);
      transition: 0.5s;
    }
    .btn-premium:hover::after {
      left: 120%;
    }

    /* Hide horizontal scroll for animations */
    .aos-init {
        overflow-x: hidden;
    }
  </style>
</head>
<body class="min-h-screen text-slate-100 selection:bg-emerald-500/30">
  <div class="hero-bg" aria-hidden="true"></div>

  <header class="fixed top-0 inset-x-0 z-50 transition-all duration-300" id="main-header">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <nav class="mt-4 flex items-center justify-between rounded-2xl glass px-4 py-3 shadow-2xl transition-all duration-300" id="nav-container">
        <!-- Brand -->
        <a href="#" class="flex items-center gap-3 group">
          <span class="h-10 w-10 overflow-hidden rounded-xl ring-2 ring-white/20 bg-white/10 flex items-center justify-center shadow-lg group-hover:ring-emerald-400/50 transition-all">
            <img src="img/logo.jpg" alt="XentroMall" class="h-9 w-9 object-cover" />
          </span>
          <span class="text-white font-bold tracking-tight text-xl">Xentro<span class="text-emerald-400">Mall</span></span>
        </a>

        <!-- Desktop nav -->
        <div class="hidden md:flex items-center gap-10">
          <a href="#" class="text-white/80 hover:text-white font-medium transition-colors relative after:content-[''] after:absolute after:bottom-[-4px] after:left-0 after:w-0 after:h-0.5 after:bg-emerald-400 after:transition-all hover:after:w-full">Home</a>
          <a href="#about-system-section" class="text-white/80 hover:text-white font-medium transition-colors relative after:content-[''] after:absolute after:bottom-[-4px] after:left-0 after:w-0 after:h-0.5 after:bg-emerald-400 after:transition-all hover:after:w-full">About</a>
          <button onclick="toggleContactModal(true)" class="text-white/80 hover:text-white font-medium transition-colors relative after:content-[''] after:absolute after:bottom-[-4px] after:left-0 after:w-0 after:h-0.5 after:bg-emerald-400 after:transition-all hover:after:w-full">Contacts</button>
        </div>

        <!-- Actions -->
        <div class="hidden md:flex items-center gap-4">
          <a href="login.php" class="btn-premium inline-flex items-center justify-center rounded-xl bg-emerald-500 px-6 py-2.5 text-white font-bold shadow-lg shadow-emerald-500/20 hover:bg-emerald-600 focus:outline-none focus:ring-4 focus:ring-emerald-500/30 transition-all active:scale-95">
            Login
          </a>
        </div>

        <!-- Mobile menu button -->
        <button id="mobile-menu-button" aria-label="Toggle menu" aria-expanded="false" class="md:hidden inline-flex items-center justify-center rounded-xl p-2.5 text-white/90 hover:bg-white/10 focus:outline-none transition-colors">
          <svg id="hamburger" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-6 w-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16m-7 6h7" />
          </svg>
        </button>
      </nav>

      <!-- Mobile nav -->
      <div id="mobile-menu" class="hidden md:hidden mt-2 rounded-2xl glass-dark p-4 shadow-2xl border border-white/10">
        <div class="flex flex-col gap-2">
          <a href="#" class="block rounded-lg px-4 py-3 text-white/90 hover:bg-white/5 transition-colors font-medium">Home</a>
          <a href="#about-system-section" class="block rounded-lg px-4 py-3 text-white/90 hover:bg-white/5 transition-colors font-medium">About</a>
          <button onclick="toggleContactModal(true)" class="w-full text-left block rounded-lg px-4 py-3 text-white/90 hover:bg-white/5 transition-colors font-medium">Contacts</button>
          <hr class="border-white/10 my-2">
          <a href="login.php" class="block rounded-xl bg-emerald-500 px-4 py-3 text-center text-white font-bold shadow hover:bg-emerald-600 transition-colors">Login</a>
        </div>
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
    <section class="relative pt-48 pb-32 overflow-hidden">
      <div class="mx-auto max-w-7xl px-6 lg:px-8 relative">
        <div class="mx-auto max-w-4xl text-center">
          <div data-aos="fade-down" data-aos-duration="1000" class="inline-flex items-center gap-2 rounded-full glass px-4 py-1.5 mb-8 ring-1 ring-white/10 shadow-xl">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span class="text-xs font-bold tracking-widest uppercase text-emerald-400">Digital Portal 2.0</span>
          </div>

          <h1 class="text-6xl md:text-8xl font-black tracking-tighter text-white mb-8 leading-[0.9]" data-aos="zoom-out-up" data-aos-duration="1200">
            Elevate Your <span class="bg-gradient-to-r from-emerald-400 via-blue-400 to-emerald-400 bg-[length:200%_auto] bg-clip-text text-transparent animate-gradient">Mall Experience</span>
          </h1>
          
          <p class="mt-8 text-lg md:text-xl text-white/70 leading-relaxed max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            Xentro Mall's premier digital gateway. Seamlessly manage stalls, applications, and tenant services in one unified, high-performance platform.
          </p>

          <div class="mt-12 flex flex-wrap items-center justify-center gap-6" data-aos="fade-up" data-aos-delay="400" data-aos-duration="1000">
            <a href="user_stall_page.php" class="btn-premium group relative inline-flex items-center justify-center gap-3 rounded-2xl bg-white px-8 py-4 text-slate-950 font-bold text-lg shadow-2xl hover:bg-emerald-50 transition-all duration-300 active:scale-95">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-5 h-5 group-hover:translate-x-1 transition-transform">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
              </svg>
              <span>Explore Stalls</span>
            </a>
            <a href="#about-system-section" class="inline-flex items-center justify-center gap-2 rounded-2xl glass px-8 py-4 text-white font-bold text-lg hover:bg-white/10 transition-all active:scale-95">
              Learn More
            </a>
          </div>

          <!-- Stats -->
          <div class="mt-24 grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-8 max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="600" data-aos-duration="1000">
            <div class="rounded-3xl glass p-6 group hover:translate-y-[-5px] transition-all duration-300">
              <div class="text-4xl font-black text-emerald-400 mb-1">50+</div>
              <div class="text-xs font-bold uppercase tracking-widest text-white/50">Available Stalls</div>
            </div>
            <div class="rounded-3xl glass p-6 group hover:translate-y-[-5px] transition-all duration-300">
              <div class="text-4xl font-black text-blue-400 mb-1">100%</div>
              <div class="text-xs font-bold uppercase tracking-widest text-white/50">Secure Portal</div>
            </div>
            <div class="rounded-3xl glass p-6 group hover:translate-y-[-5px] transition-all duration-300 col-span-2 md:col-span-1">
              <div class="text-4xl font-black text-white mb-1">24/7</div>
              <div class="text-xs font-bold uppercase tracking-widest text-white/50">Expert Support</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Scroll indicator -->
      <div class="absolute bottom-10 left-1/2 -translate-x-1/2 flex flex-col items-center gap-3 opacity-50 animate-bounce">
        <div class="w-6 h-10 rounded-full border-2 border-white flex justify-center p-1">
          <div class="w-1 h-2 bg-white rounded-full"></div>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section class="relative py-32 bg-[#020617]/50 lg:bg-transparent">
      <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center mb-20" data-aos="fade-up">
          <h2 class="text-4xl md:text-5xl font-black text-white mb-6">Designed for <span class="text-emerald-400">Excellence</span></h2>
          <p class="text-white/50 text-xl max-w-2xl mx-auto">Discover the powerful tools built to scale your business within Xentro Mall.</p>
        </div>

        <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
          <!-- Card 1 -->
          <div class="group relative rounded-3xl glass p-8 shadow-2xl transition-all duration-500 hover:bg-white/5" data-aos="fade-up" data-aos-delay="100">
            <div class="h-14 w-14 rounded-2xl bg-emerald-500 text-white flex items-center justify-center mb-8 shadow-2xl shadow-emerald-500/20 group-hover:scale-110 group-hover:rotate-3 transition-all duration-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H22.5m-15.36 0V12m0 0v-5.25a2.25 2.25 0 012.25-2.25h1.35m-1.35 2.25h11.25a2.25 2.25 0 012.25 2.25V12M6.75 12h11.25" />
              </svg>
            </div>
            <h3 class="font-bold text-white mb-4 text-xl">Stall Management</h3>
            <p class="text-white/50 text-sm leading-relaxed">Dynamic marketplace for exploring and applying for premium mall locations.</p>
          </div>

          <!-- Card 2 -->
          <div class="group relative rounded-3xl glass p-8 shadow-2xl transition-all duration-500 hover:bg-white/5" data-aos="fade-up" data-aos-delay="200">
            <div class="h-14 w-14 rounded-2xl bg-blue-500 text-white flex items-center justify-center mb-8 shadow-2xl shadow-blue-500/20 group-hover:scale-110 group-hover:-rotate-3 transition-all duration-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
              </svg>
            </div>
            <h3 class="font-bold text-white mb-4 text-xl">Tenant Portal</h3>
            <p class="text-white/50 text-sm leading-relaxed">Self-service dashboard for managing profiles, documents, and business metrics.</p>
          </div>

          <!-- Card 3 -->
          <div class="group relative rounded-3xl glass p-8 shadow-2xl transition-all duration-500 hover:bg-white/5" data-aos="fade-up" data-aos-delay="300">
            <div class="h-14 w-14 rounded-2xl bg-indigo-500 text-white flex items-center justify-center mb-8 shadow-2xl shadow-indigo-500/20 group-hover:scale-110 group-hover:rotate-3 transition-all duration-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="font-bold text-white mb-4 text-xl">Smart Support</h3>
            <p class="text-white/50 text-sm leading-relaxed">Rapid response system for maintenance and operational assistance.</p>
          </div>

          <!-- Card 4 -->
          <div class="group relative rounded-3xl glass p-8 shadow-2xl transition-all duration-500 hover:bg-white/5" data-aos="fade-up" data-aos-delay="400">
            <div class="h-14 w-14 rounded-2xl bg-teal-500 text-white flex items-center justify-center mb-8 shadow-2xl shadow-teal-500/20 group-hover:scale-110 group-hover:-rotate-3 transition-all duration-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.74c0 3.823 1.77 7.234 4.5 9.434a11.935 11.935 0 009 0c2.73-2.2 4.5-5.611 4.5-9.434a11.99 11.99 0 00-.598-3.74A11.959 11.959 0 0112 2.714z" />
              </svg>
            </div>
            <h3 class="font-bold text-white mb-4 text-xl">Bank-Level Security</h3>
            <p class="text-white/50 text-sm leading-relaxed">Enterprise-grade encryption protecting every transaction and user data.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- About -->
    <section id="about-system-section" class="relative py-32 overflow-hidden">
      <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
          <div class="order-2 lg:order-1" data-aos="fade-right">
            <div class="inline-flex items-center gap-2 rounded-full glass px-4 py-2 mb-8">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-emerald-400">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
              </svg>
              <span class="text-xs font-bold tracking-widest uppercase text-white/70">Trusted Management Platform</span>
            </div>

            <h2 class="text-5xl font-black text-white mb-8">Redefining Mall <span class="text-emerald-400">Connectivity</span></h2>
            <p class="text-xl text-white/60 leading-relaxed mb-8">
              Xentro Mall Portal is a high-performance ecosystem designed to streamline every facet of mall operations. From rapid stall applications to real-time payment tracking, we provide the tools you need to thrive.
            </p>

            <!-- Feature List -->
            <div class="space-y-6">
              <div class="flex items-start gap-4 group">
                <div class="flex-shrink-0 w-10 h-10 rounded-xl glass flex items-center justify-center mt-1 group-hover:bg-emerald-500 transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-emerald-400 group-hover:text-white transition-colors">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                  </svg>
                </div>
                <div>
                  <h4 class="text-white font-bold text-lg mb-1">Optimized Workflow</h4>
                  <p class="text-white/40 text-sm">Automated processes that save time and eliminate manual errors in tenant registration.</p>
                </div>
              </div>
              <div class="flex items-start gap-4 group">
                <div class="flex-shrink-0 w-10 h-10 rounded-xl glass flex items-center justify-center mt-1 group-hover:bg-blue-500 transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-400 group-hover:text-white transition-colors">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.74c0 3.823 1.77 7.234 4.5 9.434a11.935 11.935 0 009 0c2.73-2.2 4.5-5.611 4.5-9.434a11.99 11.99 0 00-.598-3.74A11.959 11.959 0 0112 2.714z" />
                  </svg>
                </div>
                <div>
                  <h4 class="text-white font-bold text-lg mb-1">Data Sovereignty</h4>
                  <p class="text-white/40 text-sm">Your data is yours. Protected by advanced encryption and role-based access controls.</p>
                </div>
              </div>
            </div>
          </div>

          <div class="order-1 lg:order-2" data-aos="zoom-in" data-aos-delay="200">
            <div class="relative group">
              <div class="absolute -inset-10 rounded-full bg-emerald-500/10 blur-[120px] opacity-70 group-hover:opacity-100 transition-opacity"></div>
              <div class="relative rounded-[2.5rem] overflow-hidden ring-1 ring-white/10 shadow-[0_0_80px_rgba(0,0,0,0.5)] glass-dark p-2">
                <img src="img/stall.png" alt="Stalls Experience" class="w-full h-auto rounded-[2rem] shadow-2xl transition-transform duration-700 group-hover:scale-105" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="relative border-t border-white/5 bg-[#020617]/80 backdrop-blur-3xl pt-24 pb-12 overflow-hidden">
    <!-- Footer glass overlay -->
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-emerald-500/50 to-transparent"></div>

    <div class="mx-auto max-w-7xl px-6 lg:px-8">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-12 mb-20">
        <!-- Brand -->
        <div class="md:col-span-5">
          <a href="#" class="flex items-center gap-3 mb-8 group">
            <span class="h-12 w-12 overflow-hidden rounded-2xl ring-2 ring-white/20 bg-white/10 flex items-center justify-center shadow-xl group-hover:ring-emerald-400 transition-all">
              <img src="img/logo.jpg" alt="XentroMall" class="h-10 w-10 object-cover" />
            </span>
            <span class="text-white font-black tracking-tighter text-2xl">Xentro<span class="text-emerald-400">Mall</span></span>
          </a>
          <p class="text-white/40 text-lg leading-relaxed max-w-sm">
            Empowering mall businesses through innovative digital solutions and seamless tenant integration.
          </p>
          <div class="mt-8 flex gap-4">
            <!-- Social Icons (Placeholder styles) -->
            <a href="#" class="w-10 h-10 rounded-xl glass hover:bg-white/10 flex items-center justify-center transition-all group">
               <svg class="w-5 h-5 text-white/50 group-hover:text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
            </a>
            <a href="#" class="w-10 h-10 rounded-xl glass hover:bg-white/10 flex items-center justify-center transition-all group">
               <svg class="w-5 h-5 text-white/50 group-hover:text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>
            </a>
          </div>
        </div>

        <!-- Sitemap -->
        <div class="md:col-span-2">
          <h3 class="text-white font-bold mb-6">Explore</h3>
          <ul class="space-y-4">
            <li><a href="#" class="text-white/30 hover:text-emerald-400 transition-colors text-sm">Marketplace</a></li>
            <li><a href="#about-system-section" class="text-white/30 hover:text-emerald-400 transition-colors text-sm">The Vision</a></li>
            <li><button onclick="toggleContactModal(true)" class="text-white/30 hover:text-emerald-400 transition-colors text-sm">Contacts</button></li>
          </ul>
        </div>

        <!-- Support -->
        <div class="md:col-span-2">
          <h3 class="text-white font-bold mb-6">Platform</h3>
          <ul class="space-y-4">
            <li><a href="login.php" class="text-white/30 hover:text-emerald-400 transition-colors text-sm">Tenant Login</a></li>
            <li><a href="#" class="text-white/30 hover:text-emerald-400 transition-colors text-sm">Admin Access</a></li>
            <li><a href="#" class="text-white/30 hover:text-emerald-400 transition-colors text-sm">Support Center</a></li>
          </ul>
        </div>

        <!-- Contact Info -->
        <div class="md:col-span-3">
          <h3 class="text-white font-bold mb-6">Stay Connected</h3>
          <p class="text-white/30 text-sm mb-6 leading-relaxed">Join our mailing list for updates on available stalls and mall news.</p>
          <div class="flex gap-2">
            <input type="email" placeholder="Email address" class="w-full glass rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 text-white" />
            <button class="bg-emerald-500 hover:bg-emerald-600 p-3 rounded-xl transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 text-white">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div class="pt-12 border-t border-white/5 flex flex-col md:flex-row items-center justify-between gap-6">
        <p class="text-[10px] font-bold tracking-[0.2em] uppercase text-white/20">
          © <?php echo date('Y'); ?> Xentro Mall Corporation • Built with Integrity
        </p>
        <div class="flex items-center gap-6">
          <a href="#" class="text-[10px] font-bold tracking-[0.2em] uppercase text-white/20 hover:text-emerald-400 transition-colors">Privacy Policy</a>
          <a href="#" class="text-[10px] font-bold tracking-[0.2em] uppercase text-white/20 hover:text-emerald-400 transition-colors">Terms of Service</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- AOS Initialization -->
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
    // Initialize Animations
    AOS.init({
      once: true,
      duration: 800,
      easing: 'ease-out-quad',
      offset: 100
    });

    // Interaction & UI Logic
    (function() {
      const header = document.getElementById('main-header');
      const navContainer = document.getElementById('nav-container');
      const mobileBtn = document.getElementById('mobile-menu-button');
      const mobileMenu = document.getElementById('mobile-menu');

      // Scroll effect for header
      window.addEventListener('scroll', () => {
        if (window.scrollY > 20) {
          navContainer.classList.add('mt-2', 'py-2');
          navContainer.classList.remove('mt-4', 'py-3');
          navContainer.style.background = 'rgba(2, 6, 23, 0.8)';
        } else {
          navContainer.classList.remove('mt-2', 'py-2');
          navContainer.classList.add('mt-4', 'py-3');
          navContainer.style.background = '';
        }
      });

      // Mobile menu toggle
      if (mobileBtn && mobileMenu) {
        mobileBtn.addEventListener('click', () => {
          const expanded = mobileBtn.getAttribute('aria-expanded') === 'true';
          mobileBtn.setAttribute('aria-expanded', String(!expanded));
          mobileMenu.classList.toggle('hidden');
          mobileMenu.classList.toggle('animate-fade-in-down');
        });
      }

      // Contact Modal Logic
      window.toggleContactModal = function(show) {
        const modal = document.getElementById('contactModal');
        const modalContent = modal.querySelector('div[data-aos]');
        
        if (show) {
          modal.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
          // Re-trigger AOS
          modalContent.classList.remove('aos-animate');
          setTimeout(() => modalContent.classList.add('aos-animate'), 10);
        } else {
          modal.classList.add('hidden');
          document.body.style.overflow = 'auto';
        }
      }

      // Close modal on outside click
      document.getElementById('contactModal').addEventListener('click', function(e) {
        if (e.target === this) toggleContactModal(false);
      });

      // Escape key to close
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') toggleContactModal(false);
      });

      // Smooth scroll for anchors
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
          e.preventDefault();
          const target = document.querySelector(this.getAttribute('href'));
          if (target) {
            target.scrollIntoView({
              behavior: 'smooth'
            });
            // Close mobile menu if open
            if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
              mobileBtn.click();
            }
          }
        });
      });
    })();
  </script>
  <!-- Contact Us Modal -->
  <div id="contactModal" class="fixed inset-0 bg-[#020617]/90 backdrop-blur-xl flex items-center justify-center hidden z-[100] p-4 md:p-6">
    <div class="glass-dark rounded-[2.5rem] max-w-4xl w-full shadow-[0_0_100px_rgba(16,185,129,0.15)] border border-white/10 overflow-hidden relative" data-aos="zoom-in" data-aos-duration="500">
      <!-- Close Button -->
      <button onclick="toggleContactModal(false)" class="absolute top-6 right-6 h-12 w-12 rounded-2xl glass flex items-center justify-center text-white hover:bg-white/10 transition-all z-10">
        <i class="fas fa-times text-xl"></i>
      </button>

      <div class="grid md:grid-cols-5 h-full">
        <!-- Sidebar Info -->
        <div class="md:col-span-2 bg-emerald-500/10 p-8 md:p-12 border-r border-white/5">
          <div class="h-16 w-16 rounded-2xl bg-emerald-500 text-white flex items-center justify-center mb-8 shadow-2xl shadow-emerald-500/20">
            <i class="fas fa-headset text-3xl"></i>
          </div>
          <h3 class="text-3xl font-black text-white mb-4 tracking-tight">Get in <span class="text-emerald-400">Touch</span></h3>
          <p class="text-white/50 text-sm leading-relaxed mb-10 font-medium">Our dedicated team is here to support your business expansion at Xentro Mall.</p>
          
          <div class="space-y-6">
            <div class="flex items-center gap-4 group">
              <div class="w-10 h-10 rounded-xl glass flex items-center justify-center text-emerald-400 group-hover:bg-emerald-500 group-hover:text-white transition-all">
                <i class="fas fa-phone-alt"></i>
              </div>
              <div class="text-sm font-bold text-white/80">+63 912 345 6789</div>
            </div>
            <div class="flex items-center gap-4 group">
              <div class="w-10 h-10 rounded-xl glass flex items-center justify-center text-blue-400 group-hover:bg-blue-500 group-hover:text-white transition-all">
                <i class="fas fa-envelope"></i>
              </div>
              <div class="text-sm font-bold text-white/80">admin@xentromall.com</div>
            </div>
            <div class="flex items-center gap-4 group">
              <div class="w-10 h-10 rounded-xl glass flex items-center justify-center text-violet-400 group-hover:bg-violet-500 group-hover:text-white transition-all">
                <i class="fas fa-map-marker-alt"></i>
              </div>
              <div class="text-sm font-bold text-white/80">Main Highway, Xentro Mall
              </div>
            </div>
          </div>
        </div>

        <!-- Contact Form / Departments -->
        <div class="md:col-span-3 p-8 md:p-12">
          <h4 class="text-[10px] font-black uppercase tracking-[0.3em] text-emerald-400 mb-8">Department Inquiries</h4>
          
          <div class="space-y-4">
            <!-- Dept 1 -->
            <div class="glass-dark p-6 rounded-3xl border border-white/5 hover:bg-white/5 transition-all group">
              <div class="flex justify-between items-center">
                <div>
                  <h5 class="text-white font-black text-lg">Mall Administration</h5>
                  <p class="text-white/30 text-xs">General inquiries and concerns</p>
                </div>
                <a href="mailto:admin@xentromall.com" class="w-10 h-10 rounded-xl glass flex items-center justify-center text-white/40 group-hover:bg-emerald-500 group-hover:text-white transition-all">
                  <i class="fas fa-paper-plane text-xs"></i>
                </a>
              </div>
            </div>
            <!-- Dept 2 -->
            <div class="glass-dark p-6 rounded-3xl border border-white/5 hover:bg-white/5 transition-all group">
              <div class="flex justify-between items-center">
                <div>
                  <h5 class="text-white font-black text-lg">Leasing & Sales</h5>
                  <p class="text-white/30 text-xs">Stall availability and applications</p>
                </div>
                <a href="mailto:leasing@xentromall.com" class="w-10 h-10 rounded-xl glass flex items-center justify-center text-white/40 group-hover:bg-blue-500 group-hover:text-white transition-all">
                  <i class="fas fa-paper-plane text-xs"></i>
                </a>
              </div>
            </div>
            <!-- Dept 3 -->
            <div class="glass-dark p-6 rounded-3xl border border-white/5 hover:bg-white/5 transition-all group">
              <div class="flex justify-between items-center">
                <div>
                  <h5 class="text-white font-black text-lg">Platform Support</h5>
                  <p class="text-white/30 text-xs">Technical and system assistance</p>
                </div>
                <a href="mailto:support@xentromall.com" class="w-10 h-10 rounded-xl glass flex items-center justify-center text-white/40 group-hover:bg-violet-500 group-hover:text-white transition-all">
                  <i class="fas fa-paper-plane text-xs"></i>
                </a>
              </div>
            </div>
          </div>

          <div class="mt-10 pt-8 border-t border-white/5">
            <p class="text-[10px] font-bold text-white/20 uppercase tracking-widest text-center">Typical response time: <span class="text-emerald-400/50">&lt; 24 Hours</span></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>