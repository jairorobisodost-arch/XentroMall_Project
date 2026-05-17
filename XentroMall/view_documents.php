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

if (empty($app_id)) {
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
            transition: all 0.3s ease;
        }
        
        .document-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 300px;
            object-fit: contain;
            cursor: pointer;
        }
        
        .category-section {
            scroll-margin-top: 100px;
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
            background-color: rgba(0, 0, 0, 0.9);
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
        <div class="bg-white rounded-3xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">
                        <i class="fas fa-folder-open text-emerald-600 mr-3"></i>
                        Documents
                    </h1>
                    <p class="text-gray-600">
                        Application by: <span class="font-semibold text-emerald-600"><?php echo htmlspecialchars($tradename); ?></span>
                        <span class="text-gray-400 mx-2">|</span>
                        User ID: <span class="font-semibold"><?php echo htmlspecialchars($user_id); ?></span>
                    </p>
                </div>
                <a href="admin_dashboard.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-xl transition-colors flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>

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
            ?>
            <div class="category-section mb-8" id="<?php echo str_replace(' ', '-', strtolower($category)); ?>">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 bg-<?php echo $cat_info['color']; ?>-100 rounded-xl flex items-center justify-center">
                        <i class="fas <?php echo $cat_info['icon']; ?> text-<?php echo $cat_info['color']; ?>-600 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900" style="font-family: 'Poppins', sans-serif;">
                            <?php echo htmlspecialchars($category); ?>
                        </h2>
                        <p class="text-sm text-gray-600"><?php echo count($category_files); ?> file(s)</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
                    <div class="document-card bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                        <!-- Preview Area -->
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-4 flex items-center justify-center relative" style="min-height: 200px;">
                            <!-- Document Type Badge -->
                            <div class="absolute top-2 right-2 bg-<?php echo $cat_info['color']; ?>-500 text-white px-3 py-2 rounded-lg text-xs font-bold z-10 shadow-lg">
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
                                   class="flex-1 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white px-3 py-2 rounded-xl transition-all text-center text-xs font-bold shadow-md">
                                    <i class="fas fa-eye mr-1"></i>
                                    View
                                </a>
                                <a href="<?php echo htmlspecialchars($file_path); ?>" 
                                   download 
                                   class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-3 py-2 rounded-xl transition-all text-center text-xs font-bold shadow-md">
                                    <i class="fas fa-download mr-1"></i>
                                    Download
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
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
