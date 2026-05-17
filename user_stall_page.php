<?php
session_start();
require 'config.php';

// Initialize filters from GET parameters
$size_filter = isset($_GET['size']) ? trim($_GET['size']) : '';
$price_min = isset($_GET['price_min']) ? floatval($_GET['price_min']) : 0;
$price_max = isset($_GET['price_max']) ? floatval($_GET['price_max']) : PHP_FLOAT_MAX;
$location_filter = isset($_GET['location']) ? trim($_GET['location']) : '';

// Build base query using PDO
$query = "SELECT * FROM stalls WHERE status = 'available'";
$params = [];

// Add size filter if specified
if (!empty($size_filter)) {
    $query .= " AND floor_area LIKE :size";
    $params[':size'] = "%$size_filter%";
}

// Add price range filters
if ($price_min > 0 || $price_max < PHP_FLOAT_MAX) {
    if ($price_min > 0 && $price_max < PHP_FLOAT_MAX) {
        $query .= " AND monthly_rate BETWEEN :price_min AND :price_max";
        $params[':price_min'] = $price_min;
        $params[':price_max'] = $price_max;
    } elseif ($price_min > 0) {
        $query .= " AND monthly_rate >= :price_min";
        $params[':price_min'] = $price_min;
    } elseif ($price_max < PHP_FLOAT_MAX) {
        $query .= " AND monthly_rate <= :price_max";
        $params[':price_max'] = $price_max;
    }
}

// Add location filter if specified
if (!empty($location_filter)) {
    $query .= " AND description LIKE :location";
    $params[':location'] = "%$location_filter%";
}

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$stalls = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique sizes and locations for dropdowns
$sizes_stmt = $pdo->query("SELECT DISTINCT floor_area FROM stalls ORDER BY floor_area");
$locations_stmt = $pdo->query("SELECT DISTINCT description FROM stalls ORDER BY description");

$unique_sizes = $sizes_stmt->fetchAll(PDO::FETCH_COLUMN);
$unique_locations = $locations_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Available Stalls - Xentro Mall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { color-scheme: light only; }
        body { 
            font-family: 'Inter', sans-serif;
            background: #020617;
            overflow-x: hidden;
        }
        
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

        /* Background hero with image + premium gradient */
        .hero-bg { position: fixed; inset: 0; z-index: -10; overflow: hidden; }
        .hero-bg::before { 
            content: ""; 
            position: absolute; 
            inset: 0; 
            background: url('img/bg.jpg') center/cover no-repeat; 
            filter: brightness(0.4) saturate(1.2); 
            transform: scale(1.05);
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

        .stall-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stall-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 30px 60px -12px rgba(0,0,0,0.5);
            background: rgba(255, 255, 255, 0.05) !important;
        }

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
    </style>
</head>
<body class="min-h-screen text-slate-100 selection:bg-emerald-500/30">
    <div class="hero-bg" aria-hidden="true"></div>

    <!-- Header -->
    <header class="fixed top-0 inset-x-0 z-50 transition-all duration-300" id="main-header">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <nav class="mt-4 flex items-center justify-between rounded-2xl glass px-4 py-3 shadow-2xl transition-all duration-300" id="nav-container">
                <a href="index.php" class="flex items-center gap-3 group">
                    <span class="h-10 w-10 overflow-hidden rounded-xl ring-2 ring-white/20 bg-white/10 flex items-center justify-center shadow-lg group-hover:ring-emerald-400/50 transition-all">
                        <img src="img/logo.jpg" alt="XentroMall" class="h-9 w-9 object-cover" />
                    </span>
                    <span class="text-white font-bold tracking-tight text-xl">Xentro<span class="text-emerald-400">Mall</span></span>
                </a>
                <a href="index.php" class="inline-flex items-center gap-2 rounded-xl bg-white/5 backdrop-blur-xl px-5 py-2.5 text-white font-bold hover:bg-white/15 transition-all active:scale-95 border border-white/10">
                    <i class="fas fa-arrow-left text-emerald-400"></i>
                    <span class="hidden sm:inline">Back to Home</span>
                </a>
            </nav>
        </div>
    </header>

    <div class="container mx-auto px-4 pt-40 pb-20 relative">
        <!-- Hero Section -->
        <div class="text-center mb-16">
            <div data-aos="fade-down" data-aos-duration="1000" class="inline-flex items-center gap-2 rounded-full glass px-4 py-1.5 mb-8 ring-1 ring-white/10 shadow-xl">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="text-xs font-bold tracking-widest uppercase text-emerald-400">Premium Marketplace</span>
            </div>
            <h1 class="text-5xl md:text-7xl font-black text-white mb-6 tracking-tight" data-aos="zoom-out-up">Find Your Perfect <span class="bg-gradient-to-r from-blue-400 via-emerald-400 to-blue-400 bg-[length:200%_auto] bg-clip-text text-transparent animate-gradient">Space</span></h1>
            <p class="text-xl text-white/50 max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="200">Browse our curated collection of premium mall stalls and elevate your business to new heights.</p>
        </div>
        
        <!-- Filter Section -->
        <div class="glass rounded-[2rem] shadow-2xl p-8 mb-16 border border-white/5" data-aos="fade-up" data-aos-delay="300">
            <div class="flex items-center gap-4 mb-8">
                <div class="h-12 w-12 rounded-2xl bg-emerald-500 text-white flex items-center justify-center shadow-2xl shadow-emerald-500/20">
                    <i class="fas fa-sliders-h text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-white">Smart Filters</h2>
                    <p class="text-sm text-white/40">Optimize your search with multi-parameter filtering</p>
                </div>
            </div>
            
            <form method="get" action="" class="space-y-8">
                <!-- Quick Filters -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Size Filter -->
                    <div class="space-y-3">
                        <label for="size" class="flex items-center gap-2 text-sm font-bold tracking-widest uppercase text-white/40 ml-1">
                            <i class="fas fa-expand-arrows-alt text-emerald-400"></i> Stall Size
                        </label>
                        <select id="size" name="size" class="w-full px-5 py-4 glass rounded-2xl focus:ring-2 focus:ring-emerald-500 focus:outline-none transition-all text-white font-medium appearance-none cursor-pointer">
                            <option value="" class="bg-[#020617]">📏 All Sizes</option>
                            <?php foreach ($unique_sizes as $size): ?>
                                <option value="<?= htmlspecialchars($size) ?>" <?= $size_filter === $size ? 'selected' : '' ?> class="bg-[#020617]">
                                    📐 <?= htmlspecialchars($size) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Price Range Filter -->
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm font-bold tracking-widest uppercase text-white/40 ml-1">
                            <i class="fas fa-tag text-blue-400"></i> Monthly Budget
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="number" name="price_min" placeholder="Min" 
                                   class="w-full px-4 py-4 glass rounded-2xl focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all text-white font-medium placeholder:text-white/20" 
                                   value="<?= $price_min > 0 ? htmlspecialchars($price_min) : '' ?>"
                                   min="0" step="100">
                            <input type="number" name="price_max" placeholder="Max" 
                                   class="w-full px-4 py-4 glass rounded-2xl focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all text-white font-medium placeholder:text-white/20" 
                                   value="<?= $price_max < PHP_FLOAT_MAX ? htmlspecialchars($price_max) : '' ?>"
                                   min="0" step="100">
                        </div>
                    </div>
                    
                    <!-- Location Filter -->
                    <div class="space-y-3">
                        <label for="location" class="flex items-center gap-2 text-sm font-bold tracking-widest uppercase text-white/40 ml-1">
                            <i class="fas fa-map-pin text-violet-400"></i> Floor Level
                        </label>
                        <select id="location" name="location" class="w-full px-5 py-4 glass rounded-2xl focus:ring-2 focus:ring-violet-500 focus:outline-none transition-all text-white font-medium appearance-none cursor-pointer">
                            <option value="" class="bg-[#020617]">📍 All Locations</option>
                            <?php foreach ($unique_locations as $location): ?>
                                <option value="<?= htmlspecialchars($location) ?>" <?= $location_filter === $location ? 'selected' : '' ?> class="bg-[#020617]">
                                    🏢 <?= htmlspecialchars($location) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-between items-center pt-4 border-t border-white/5">
                    <div class="text-sm font-medium text-white/40">
                        <?php if (count($stalls) > 0): ?>
                            Found <span class="text-white font-black"><?= count($stalls) ?></span> available spatial opportunities
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-4 w-full sm:w-auto">
                        <a href="?" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 glass hover:bg-white/10 text-white px-8 py-4 rounded-2xl font-bold transition-all active:scale-95">
                            <i class="fas fa-sync-alt text-sm"></i> Reset
                        </a>
                        <button type="submit" class="btn-premium flex-1 sm:flex-none inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white px-8 py-4 rounded-2xl font-black shadow-2xl shadow-emerald-500/20 transition-all active:scale-95">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Stalls Grid -->
        <?php if (count($stalls) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php $delay = 100; foreach ($stalls as $stall): ?>
                    <div class="group relative glass rounded-[2.5rem] shadow-2xl border border-white/5 overflow-hidden stall-card flex flex-col" data-aos="fade-up" data-aos-delay="<?= $delay; $delay += 50; ?>">
                        <div class="relative h-64 overflow-hidden">
                            <div class="absolute inset-0 bg-gradient-to-t from-[#020617] via-transparent to-transparent z-10"></div>
                            <div class="absolute top-6 left-6 z-20">
                                <span class="bg-emerald-500 text-white text-[10px] font-black uppercase tracking-[0.2em] px-4 py-2 rounded-xl shadow-2xl">
                                    Available
                                </span>
                            </div>
                            <?php if (!empty($stall['image_path'])): ?>
                                <img src="<?= htmlspecialchars($stall['image_path']) ?>" alt="<?= htmlspecialchars($stall['stall_number']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                            <?php else: ?>
                                <div class="w-full h-full bg-white/5 flex items-center justify-center text-white/20">
                                    <i class="fas fa-shop text-6xl"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-8 flex-1 flex flex-col">
                            <div class="flex justify-between items-start mb-6">
                                <h3 class="text-3xl font-black text-white tracking-tight"><?= htmlspecialchars($stall['stall_number']) ?></h3>
                                <div class="text-right">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-emerald-400 mb-1">Monthly Rate</div>
                                    <div class="text-2xl font-black text-white">₱<?= number_format($stall['monthly_rate'], 0) ?></div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-8">
                                <div class="glass-dark p-4 rounded-2xl">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-2">Area Size</div>
                                    <div class="flex items-center gap-2 font-bold text-white">
                                        <i class="fas fa-expand text-blue-400"></i> <?= htmlspecialchars($stall['floor_area']) ?>
                                    </div>
                                </div>
                                <div class="glass-dark p-4 rounded-2xl">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-2">Floor</div>
                                    <div class="flex items-center gap-2 font-bold text-white">
                                        <i class="fas fa-layer-group text-violet-400"></i> Level 1
                                    </div>
                                </div>
                            </div>
                            
                            <p class="text-sm text-white/40 leading-relaxed mb-10 flex items-start gap-3 flex-1">
                                <i class="fas fa-info-circle mt-1 text-emerald-400"></i>
                                <?= htmlspecialchars($stall['description']) ?>
                            </p>

                            <button onclick="showConfirmationModal('<?php echo $stall['id']; ?>', '<?php echo htmlspecialchars($stall['stall_number'], ENT_QUOTES); ?>')" 
                                class="btn-premium w-full inline-flex items-center justify-center gap-3 bg-white text-slate-950 px-6 py-4 rounded-2xl font-black transition-all active:scale-95 shadow-2xl">
                                <i class="fas fa-file-signature"></i> Launch Application
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white/90 backdrop-blur-xl rounded-2xl shadow-xl ring-1 ring-white/20 p-12 text-center">
                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center">
                    <i class="fas fa-store-slash text-4xl text-slate-400"></i>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 mb-2">No Stalls Found</h3>
                <p class="text-slate-600 mb-6">Try adjusting your filters or check back later for new listings</p>
                <a href="?" class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-600 to-emerald-500 hover:from-blue-700 hover:to-emerald-600 text-white px-6 py-3 rounded-xl font-semibold shadow-lg shadow-emerald-500/30 transition-all hover:scale-105">
                    <i class="fas fa-redo"></i> Reset Filters
                </a>
            </div>
        <?php endif; ?>
    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 bg-[#020617]/80 backdrop-blur-md flex items-center justify-center hidden z-50 p-6">
        <div class="glass-dark p-10 rounded-[2.5rem] max-w-md w-full shadow-[0_0_100px_rgba(16,185,129,0.1)] border border-white/10" data-aos="zoom-in" data-aos-duration="400">
            <div class="w-20 h-20 mx-auto mb-8 rounded-[2rem] bg-emerald-500 flex items-center justify-center shadow-2xl shadow-emerald-500/40">
                <i class="fas fa-question text-3xl text-white"></i>
            </div>
            <h3 class="text-3xl font-black text-center mb-4 text-white tracking-tight">Confirm Selection</h3>
            <p class="text-center text-white/40 mb-2 font-medium">You have selected stall unit:</p>
            <p class="text-center text-2xl font-black text-emerald-400 mb-8"><span id="selectedStallNumber"></span></p>
            
            <div class="flex flex-col gap-3">
                <a id="confirmButton" href="#" class="btn-premium w-full inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-4 rounded-2xl font-black transition-all active:scale-95 shadow-2xl shadow-emerald-500/20">
                    <i class="fas fa-check"></i> Proced with Application
                </a>
                <button onclick="hideConfirmationModal()" class="w-full px-6 py-4 glass hover:bg-white/10 text-white rounded-2xl font-bold transition-all active:scale-95">
                    Cancel Selection
                </button>
            </div>
        </div>
    </div>

    <!-- Final Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
    // Initialize AOS
    AOS.init({
        once: true,
        duration: 800,
        easing: 'ease-out-quad',
        offset: 50
    });

    // Interaction & UI Logic
    (function() {
        const navContainer = document.getElementById('nav-container');

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

        // Global variable to store the current stall ID
        window.showConfirmationModal = function(stallId, stallNumber) {
            document.getElementById('selectedStallNumber').textContent = stallNumber;
            document.getElementById('confirmButton').href = 'tenant_register.php?stall_id=' + stallId;
            document.getElementById('confirmationModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Re-trigger AOS for modal if needed
            const modalBox = document.querySelector('#confirmationModal > div');
            modalBox.classList.remove('aos-animate');
            setTimeout(() => modalBox.classList.add('aos-animate'), 10);
        }

        window.hideConfirmationModal = function() {
            document.getElementById('confirmationModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('confirmationModal').addEventListener('click', function(e) {
            if (e.target === this) hideConfirmationModal();
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideConfirmationModal();
        });
    })();
    </script>
</body>
</html>