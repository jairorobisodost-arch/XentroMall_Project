<?php
session_start();
require 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get the application ID from URL
$app_id = $_GET['id'] ?? '';

if (!isset($_GET['id']) || $_GET['id'] === '') {
    die('Invalid application ID.');
}

// Fetch the documents directory for this application
try {
    $stmt = $pdo->prepare('SELECT documents, tradename, user_id FROM tenant_details WHERE id = :id');
    $stmt->execute(['id' => $app_id]);
    $app = $stmt->fetch();
    
    if (!$app) {
        die('Application not found.');
    }
    
    $documents_dir = $app['documents'];
    $tradename = $app['tradename'];
    $user_id = $app['user_id'];
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// Get all files in the documents directory and categorize them
$files = [];
$categorized_docs = [
    'Letter of Intent' => [],
    'BIR Form 2303' => [],
    'DTI Permit' => [],
    'Business Permit' => [],
    'Mayor\'s Permit' => [],
    'SEC Registration' => [],
    'Supporting Documents' => []
];

if (!empty($documents_dir) && is_dir($documents_dir)) {
    $all_files = array_diff(scandir($documents_dir), ['.', '..']);
    
    foreach ($all_files as $file) {
        $file_lower = strtolower($file);
        $categorized = false;
        
        // Enhanced categorization based on filename patterns
        if (strpos($file_lower, 'letter') !== false || strpos($file_lower, 'intent') !== false || strpos($file_lower, 'loi') !== false) {
            $categorized_docs['Letter of Intent'][] = $file;
            $categorized = true;
        } elseif (strpos($file_lower, 'bir') !== false || strpos($file_lower, '2303') !== false || strpos($file_lower, 'form') !== false) {
            // Check if it's specifically BIR Form 2303
            if (strpos($file_lower, '2303') !== false || (strpos($file_lower, 'bir') !== false && strpos($file_lower, 'form') !== false)) {
                $categorized_docs['BIR Form 2303'][] = $file;
            } else {
                $categorized_docs['BIR Form 2303'][] = $file;
            }
            $categorized = true;
        } elseif (strpos($file_lower, 'dti') !== false || strpos($file_lower, 'permit') !== false) {
            $categorized_docs['DTI Permit'][] = $file;
            $categorized = true;
        } elseif (strpos($file_lower, 'business') !== false && strpos($file_lower, 'permit') !== false) {
            $categorized_docs['Business Permit'][] = $file;
            $categorized = true;
        } elseif (strpos($file_lower, 'mayor') !== false || strpos($file_lower, 'barangay') !== false) {
            $categorized_docs['Mayor\'s Permit'][] = $file;
            $categorized = true;
        } elseif (strpos($file_lower, 'sec') !== false || strpos($file_lower, 'registration') !== false) {
            $categorized_docs['SEC Registration'][] = $file;
            $categorized = true;
        }
        
        if (!$categorized) {
            $categorized_docs['Supporting Documents'][] = $file;
        }
        
        $files[] = $file;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Documents - <?php echo htmlspecialchars($tradename); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%);
        }
        
        .gradient-primary {
            background: linear-gradient(135deg, #059669 0%, #0ea5e9 100%);
        }
        
        .document-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .document-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }
        
        .image-preview {
            max-width: 100%;
            height: 180px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .image-preview:hover {
            transform: scale(1.05);
        }
        
        .filter-chip {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .filter-chip.active {
            background-color: #059669 !important;
            color: white !important;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
            transform: translateY(-2px);
        }

        .category-section {
            display: contents; /* Allow children to be siblings in a shared grid */
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="min-h-screen p-6">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-3xl shadow-lg p-6 mb-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 gradient-primary rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-folder-open text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 leading-tight">Documents</h1>
                        <p class="text-gray-500 font-medium tracking-wide">
                            <span class="text-emerald-600"><?php echo htmlspecialchars($tradename); ?></span>
                            <span class="text-gray-300 mx-2">•</span>
                            ID: #<?php echo htmlspecialchars($user_id); ?>
                        </p>
                    </div>
                </div>
                <a href="admin_dashboard.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-6 py-3 rounded-2xl transition-all flex items-center gap-3 border border-gray-200 group">
                    <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                    Dashboard
                </a>
            </div>
        </div>

        <?php if (!empty($files)): ?>
        <!-- Category Filters -->
        <div class="mb-8 overflow-x-auto pb-4 -mx-2 px-2 scrollbar-none">
            <div class="flex items-center gap-3 min-w-max">
                <div onclick="filterDocs('all', this)" class="filter-chip active bg-white border-2 border-emerald-100 text-emerald-700 px-5 py-2.5 rounded-2xl font-bold shadow-sm whitespace-nowrap">
                    All Files (<?php echo count($files); ?>)
                </div>
                <?php
                $category_icons = [
                    'Letter of Intent' => ['icon' => 'fa-file-signature', 'color' => 'emerald'],
                    'BIR Form 2303' => ['icon' => 'fa-file-invoice-dollar', 'color' => 'blue'],
                    'DTI Permit' => ['icon' => 'fa-certificate', 'color' => 'amber'],
                    'Business Permit' => ['icon' => 'fa-building', 'color' => 'purple'],
                    'Mayor\'s Permit' => ['icon' => 'fa-id-card', 'color' => 'pink'],
                    'SEC Registration' => ['icon' => 'fa-landmark', 'color' => 'indigo'],
                    'Supporting Documents' => ['icon' => 'fa-folder-open', 'color' => 'gray']
                ];

                foreach ($categorized_docs as $category => $category_files):
                    if (empty($category_files)) continue;
                    $cat_info = $category_icons[$category];
                    $cat_slug = str_replace(' ', '-', strtolower($category));
                ?>
                <div onclick="filterDocs('<?php echo $cat_slug; ?>', this)" 
                     class="filter-chip bg-white border-2 border-gray-100 text-gray-600 hover:border-<?php echo $cat_info['color']; ?>-200 px-5 py-2.5 rounded-2xl font-bold shadow-sm flex items-center gap-2 transition-all whitespace-nowrap">
                    <i class="fas <?php echo $cat_info['icon']; ?> text-<?php echo $cat_info['color']; ?>-500"></i>
                    <?php echo $category; ?>
                    <span class="bg-gray-100 px-2 py-0.5 rounded-md text-[10px]"><?php echo count($category_files); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($files)): ?>
            <!-- No Documents -->
            <div class="bg-white rounded-3xl shadow-lg p-12 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-file-excel text-4xl text-gray-400"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">No Documents Found</h2>
                <p class="text-gray-600">This application doesn't have any uploaded documents.</p>
            </div>
        <?php else: ?>
            <!-- Document Categories -->
            <!-- Unified Document Grid -->
            <div id="documents-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php 
            foreach ($categorized_docs as $category => $category_files): 
                if (empty($category_files)) continue;
                $cat_info = $category_icons[$category];
                $cat_slug = str_replace(' ', '-', strtolower($category));
            ?>
                    <?php foreach ($category_files as $file): ?>
                    <?php 
                        $file_path = $documents_dir . $file;
                        $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
                        $is_pdf = $file_extension === 'pdf';
                        $file_size = filesize($file_path);
                        $file_size_formatted = $file_size < 1024 ? $file_size . ' B' : 
                                              ($file_size < 1048576 ? round($file_size / 1024, 2) . ' KB' : 
                                              round($file_size / 1048576, 2) . ' MB');
                    ?>
                    <div class="document-card doc-item bg-white rounded-3xl shadow-lg overflow-hidden border border-gray-100 group" data-category="<?php echo $cat_slug; ?>">
                        <!-- Preview Area -->
                        <div class="bg-gray-50 flex items-center justify-center relative overflow-hidden h-48 border-b border-gray-100">
                            <!-- Category Indicator -->
                            <div class="absolute top-3 left-3 bg-white/90 backdrop-blur-md text-gray-700 px-3 py-1.5 rounded-xl text-[10px] font-black z-10 shadow-sm border border-gray-100 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-<?php echo $cat_info['color']; ?>-500"></span>
                                <?php echo htmlspecialchars($category); ?>
                            </div>
                            
                            <?php if ($is_image): ?>
                                <img src="<?php echo htmlspecialchars($file_path); ?>" 
                                     alt="<?php echo htmlspecialchars($file); ?>" 
                                     class="image-preview rounded-lg shadow-md w-full h-full object-contain"
                                     onclick="openModal('<?php echo htmlspecialchars($file_path); ?>')">
                            <?php elseif ($is_pdf): ?>
                                <div class="text-center">
                                    <i class="fas fa-file-pdf text-6xl text-red-500 mb-3"></i>
                                    <p class="text-sm font-medium text-gray-700">PDF Document</p>
                                </div>
                            <?php else: ?>
                                <div class="text-center">
                                    <i class="fas fa-file text-6xl text-gray-400 mb-3"></i>
                                    <p class="text-sm font-medium text-gray-700"><?php echo strtoupper($file_extension); ?> File</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- File Info -->
                        <div class="p-4">
                            <h3 class="font-bold text-gray-900 mb-2 truncate text-sm" title="<?php echo htmlspecialchars($file); ?>">
                                <?php echo htmlspecialchars($file); ?>
                            </h3>
                            <div class="flex items-center justify-between text-xs text-gray-600 mb-3">
                                <span class="flex items-center gap-1 bg-gray-100 px-2 py-1 rounded-lg">
                                    <i class="fas fa-hdd"></i>
                                    <?php echo $file_size_formatted; ?>
                                </span>
                                <span class="bg-<?php echo $cat_info['color']; ?>-100 text-<?php echo $cat_info['color']; ?>-700 px-2 py-1 rounded-lg font-bold">
                                    <?php echo strtoupper($file_extension); ?>
                                </span>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex gap-2">
                                <a href="<?php echo htmlspecialchars($file_path); ?>" 
                                   target="_blank" 
                                   class="flex-1 gradient-primary text-white p-2.5 rounded-xl transition-all text-center text-[11px] font-bold shadow-lg hover:shadow-emerald-200">
                                    <i class="fas fa-expand mr-1.5"></i>
                                    Open
                                </a>
                                <a href="<?php echo htmlspecialchars($file_path); ?>" 
                                   download 
                                   class="flex-1 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 p-2.5 rounded-xl transition-all text-center text-[11px] font-bold shadow-sm">
                                    <i class="fas fa-download mr-1.5"></i>
                                    Save
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Summary -->
            <div class="bg-white rounded-2xl shadow-lg p-6 mt-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-file-alt text-emerald-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900"><?php echo count($files); ?></p>
                            <p class="text-gray-600 text-sm">Total Documents</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Documents Directory:</p>
                        <p class="text-sm font-mono text-gray-900 bg-gray-50 px-3 py-1 rounded-lg mt-1">
                            <?php echo htmlspecialchars($documents_dir); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal" onclick="closeModal()">
        <span class="absolute top-8 right-8 text-white text-5xl font-bold cursor-pointer hover:text-gray-300 z-50">&times;</span>
        <img id="modalImage" class="modal-content">
    </div>

    <script>
        function filterDocs(category, el) {
            // Update active state
            document.querySelectorAll('.filter-chip').forEach(chip => {
                chip.classList.remove('active');
            });
            el.classList.add('active');

            const items = document.querySelectorAll('.doc-item');
            
            items.forEach(item => {
                item.style.opacity = '0';
                item.style.transform = 'scale(0.95)';
                
                setTimeout(() => {
                    if (category === 'all' || item.getAttribute('data-category') === category) {
                        item.style.display = 'block';
                        setTimeout(() => {
                            item.style.opacity = '1';
                            item.style.transform = 'scale(1)';
                        }, 50);
                    } else {
                        item.style.display = 'none';
                    }
                }, 300);
            });
        }

        function openModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.classList.add('active');
            modalImg.src = imageSrc;
        }

        function closeModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.remove('active');
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>
