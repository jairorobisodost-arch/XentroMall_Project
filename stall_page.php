<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'create') {
            // Create new stall
            $stall_number = $_POST['stall_number'];
            $floor_area = $_POST['floor_area'];
            $monthly_rate = $_POST['monthly_rate'];
            $description = $_POST['description'];
            $status = $_POST['status'];
            
            // Handle image upload
            $image_path = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $target_dir = "uploads/stalls/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $target_file = $target_dir . basename($_FILES["image"]["name"]);
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $image_path = $target_file;
                }
            }

            // Prepare and bind
            $stmt = $conn->prepare("INSERT INTO stalls (stall_number, floor_area, monthly_rate, description, status, image_path) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdsss", $stall_number, $floor_area, $monthly_rate, $description, $status, $image_path);

            // Execute the statement
            if ($stmt->execute()) {
                echo "<script>alert('New stall created successfully.');</script>";
            } else {
                echo "<script>alert('Error: " . $stmt->error . "');</script>";
            }

            // Close statement
            $stmt->close();
            
        } elseif ($action == 'update' && isset($_POST['stall_id'])) {
            // Update existing stall
            $stall_id = $_POST['stall_id'];
            $stall_number = $_POST['stall_number'];
            $floor_area = $_POST['floor_area'];
            $monthly_rate = $_POST['monthly_rate'];
            $description = $_POST['description'];
            $status = $_POST['status'];
            
            // Check if new image is uploaded
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $target_dir = "uploads/stalls/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $target_file = $target_dir . basename($_FILES["image"]["name"]);
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $image_path = $target_file;
                    // Update with new image
                    $stmt = $conn->prepare("UPDATE stalls SET stall_number=?, floor_area=?, monthly_rate=?, description=?, status=?, image_path=? WHERE id=?");
                    $stmt->bind_param("ssdsssi", $stall_number, $floor_area, $monthly_rate, $description, $status, $image_path, $stall_id);
                }
            } else {
                // Update without changing image
                $stmt = $conn->prepare("UPDATE stalls SET stall_number=?, floor_area=?, monthly_rate=?, description=?, status=? WHERE id=?");
                $stmt->bind_param("ssdssi", $stall_number, $floor_area, $monthly_rate, $description, $status, $stall_id);
            }

            // Execute the statement
            if ($stmt->execute()) {
                echo "<script>alert('Stall updated successfully.');</script>";
            } else {
                echo "<script>alert('Error: " . $stmt->error . "');</script>";
            }

            // Close statement
            $stmt->close();
        }
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $stall_id = $_GET['delete'];
    
    // First, get the image path to delete the file
    $stmt = $conn->prepare("SELECT image_path FROM stalls WHERE id=?");
    $stmt->bind_param("i", $stall_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row && !empty($row['image_path'])) {
        // Delete the image file
        if (file_exists($row['image_path'])) {
            unlink($row['image_path']);
        }
    }
    
    // Now delete the stall record
    $stmt = $conn->prepare("DELETE FROM stalls WHERE id=?");
    $stmt->bind_param("i", $stall_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Stall deleted successfully.');</script>";
    } else {
        echo "<script>alert('Error deleting stall: " . $stmt->error . "');</script>";
    }
    
    $stmt->close();
}

// Check if we're editing an existing stall
$edit_mode = false;
$current_stall = null;

if (isset($_GET['edit'])) {
    $stall_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM stalls WHERE id=?");
    $stmt->bind_param("i", $stall_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_stall = $result->fetch_assoc();
    
    if ($current_stall) {
        $edit_mode = true;
    }
    
    $stmt->close();
}

// Fetch all stalls for the management table
$stalls_result = $conn->query("SELECT * FROM stalls ORDER BY stall_number");
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Stall Management - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%);
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        
        .gradient-primary {
            background: linear-gradient(135deg, #059669 0%, #0ea5e9 100%);
        }
        
        .stall-card {
            transition: all 0.3s ease;
        }
        
        .stall-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .preview-image {
            max-height: 300px;
            object-fit: cover;
            width: 100%;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
            border-color: #059669;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="min-h-screen p-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="glass-effect rounded-3xl shadow-2xl p-6 mb-6 animate-fade-in">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 gradient-primary rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-store text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Stall Management</h1>
                        <p class="text-gray-600 mt-1">Manage your mall stalls and availability</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <a href="admin_dashboard.php" class="flex-1 sm:flex-none justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 sm:px-6 py-3 rounded-xl transition-all flex items-center gap-2 font-medium shadow-sm text-sm">
                        <i class="fas fa-arrow-left"></i>
                        <span>Dashboard</span>
                    </a>
                    <?php if (!isset($_GET['edit']) && !isset($_GET['create'])): ?>
                    <a href="?create" class="flex-1 sm:flex-none justify-center gradient-primary hover:opacity-90 text-white px-4 sm:px-6 py-3 rounded-xl transition-all flex items-center gap-2 font-medium shadow-lg text-sm">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Stall</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!isset($_GET['edit']) && !isset($_GET['create']) && !isset($_GET['delete'])): ?>
            <!-- Stall Cards Grid -->
            <?php if ($stalls_result->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-fade-in">
                    <?php while ($stall = $stalls_result->fetch_assoc()): ?>
                        <div class="stall-card glass-effect rounded-2xl shadow-lg overflow-hidden">
                            <!-- Stall Image -->
                            <div class="relative h-48 bg-gradient-to-br from-emerald-400 to-blue-500 overflow-hidden">
                                <?php if (!empty($stall['image_path']) && file_exists($stall['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($stall['image_path']); ?>" alt="Stall <?php echo htmlspecialchars($stall['stall_number']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center">
                                        <i class="fas fa-store text-white text-6xl opacity-50"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Status Badge -->
                                <div class="absolute top-4 right-4">
                                    <span class="px-3 py-1.5 rounded-full text-xs font-semibold shadow-lg backdrop-blur-sm
                                        <?php echo $stall['status'] == 'available' ? 'bg-green-500 text-white' : 
                                              ($stall['status'] == 'reserved' ? 'bg-yellow-500 text-white' : 'bg-red-500 text-white'); ?>">
                                        <i class="fas <?php echo $stall['status'] == 'available' ? 'fa-check-circle' : 
                                              ($stall['status'] == 'reserved' ? 'fa-clock' : 'fa-times-circle'); ?> mr-1"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $stall['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Stall Details -->
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-xl font-bold text-gray-900">
                                        <i class="fas fa-hashtag text-emerald-600 text-sm"></i>
                                        <?php echo htmlspecialchars($stall['stall_number']); ?>
                                    </h3>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-emerald-600">₱<?php echo number_format($stall['monthly_rate'], 0); ?></p>
                                        <p class="text-xs text-gray-500">per month</p>
                                    </div>
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex items-center gap-2 text-gray-600">
                                        <i class="fas fa-ruler-combined text-emerald-600 w-5"></i>
                                        <span class="text-sm"><?php echo htmlspecialchars($stall['floor_area']); ?></span>
                                    </div>
                                    <div class="flex items-start gap-2 text-gray-600">
                                        <i class="fas fa-info-circle text-emerald-600 w-5 mt-0.5"></i>
                                        <span class="text-sm line-clamp-2"><?php echo htmlspecialchars($stall['description']); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="flex gap-2 pt-4 border-t border-gray-100">
                                    <a href="?edit=<?php echo $stall['id']; ?>" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 font-medium shadow-sm">
                                        <i class="fas fa-edit"></i>
                                        <span>Edit</span>
                                    </a>
                                    <a href="?delete=<?php echo $stall['id']; ?>" onclick="return confirm('Are you sure you want to delete this stall?')" class="flex-1 bg-red-500 hover:bg-red-600 text-white px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 font-medium shadow-sm">
                                        <i class="fas fa-trash-alt"></i>
                                        <span>Delete</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="glass-effect rounded-3xl shadow-lg p-12 text-center animate-fade-in">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-store-slash text-5xl text-gray-400"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">No Stalls Yet</h2>
                    <p class="text-gray-600 mb-6">Start by creating your first stall to manage your mall spaces.</p>
                    <a href="?create" class="gradient-primary hover:opacity-90 text-white px-8 py-3 rounded-xl transition-all inline-flex items-center gap-2 font-medium shadow-lg">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create First Stall</span>
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Create/Edit Stall Form -->
            <div class="glass-effect rounded-3xl shadow-2xl p-8 sm:p-10 animate-fade-in max-w-3xl mx-auto">
                <div class="flex items-center justify-center gap-3 mb-8">
                    <div class="w-12 h-12 gradient-primary rounded-xl flex items-center justify-center">
                        <i class="fas <?php echo $edit_mode ? 'fa-edit' : 'fa-plus-circle'; ?> text-white text-xl"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        <?php echo $edit_mode ? 'Edit Stall Details' : 'Create New Stall'; ?>
                    </h1>
                </div>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $edit_mode ? 'update' : 'create'; ?>">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="stall_id" value="<?php echo $current_stall['id']; ?>">
                    <?php endif; ?>
                    
                    <div>
                        <label for="stall_number" class="block text-gray-700 font-semibold mb-2 flex items-center gap-2">
                            <i class="fas fa-hashtag text-emerald-600"></i>
                            Stall Number
                        </label>
                        <input 
                            type="text" 
                            id="stall_number" 
                            name="stall_number" 
                            required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-700 placeholder-gray-400 transition"
                            placeholder="e.g., ST-001"
                            autocomplete="off"
                            value="<?php echo $edit_mode ? htmlspecialchars($current_stall['stall_number']) : ''; ?>"
                        />
                    </div>
                    
                    <div>
                        <label for="floor_area" class="block text-gray-700 font-semibold mb-2 flex items-center gap-2">
                            <i class="fas fa-ruler-combined text-emerald-600"></i>
                            Floor Area (Dimensions)
                        </label>
                        <input 
                            type="text" 
                            id="floor_area" 
                            name="floor_area" 
                            required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-700 placeholder-gray-400 transition"
                            placeholder="e.g., 5m x 5m or 25 sqm"
                            autocomplete="off"
                            value="<?php echo $edit_mode ? htmlspecialchars($current_stall['floor_area']) : ''; ?>"
                        />
                    </div>
                    
                    <div>
                        <label for="monthly_rate" class="block text-gray-700 font-semibold mb-2 flex items-center gap-2">
                            <i class="fas fa-peso-sign text-emerald-600"></i>
                            Monthly Rate / Rental Fee
                        </label>
                        <input 
                            type="number" 
                            id="monthly_rate" 
                            name="monthly_rate" 
                            required 
                            step="0.01"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-700 placeholder-gray-400 transition"
                            placeholder="e.g., 5000.00"
                            autocomplete="off"
                            value="<?php echo $edit_mode ? htmlspecialchars($current_stall['monthly_rate']) : ''; ?>"
                        />
                    </div>
                    
                    <div>
                        <label for="description" class="block text-gray-700 font-semibold mb-2 flex items-center gap-2">
                            <i class="fas fa-align-left text-emerald-600"></i>
                            Description
                        </label>
                        <textarea 
                            id="description" 
                            name="description" 
                            required 
                            rows="4"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-700 placeholder-gray-400 transition resize-y"
                            placeholder="e.g., Corner stall, near entrance, with glass display"
                        ><?php echo $edit_mode ? htmlspecialchars($current_stall['description']) : ''; ?></textarea>
                    </div>
                    
                    <div>
                        <label for="status" class="block text-gray-700 font-semibold mb-2 flex items-center gap-2">
                            <i class="fas fa-toggle-on text-emerald-600"></i>
                            Status
                        </label>
                        <select 
                            id="status" 
                            name="status" 
                            required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-700 transition cursor-pointer"
                        >
                            <option value="" disabled <?php echo !$edit_mode ? 'selected' : ''; ?>>-- Select Status --</option>
                            <option value="available" <?php echo ($edit_mode && $current_stall['status'] == 'available') ? 'selected' : ''; ?>>✓ Available</option>
                            <option value="not_available" <?php echo ($edit_mode && $current_stall['status'] == 'not_available') ? 'selected' : ''; ?>>✗ Not Available</option>
                            <option value="reserved" <?php echo ($edit_mode && $current_stall['status'] == 'reserved') ? 'selected' : ''; ?>>⏱ Reserved</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="image" class="block text-gray-700 font-semibold mb-2 flex items-center gap-2">
                            <i class="fas fa-image text-emerald-600"></i>
                            Stall Image
                        </label>
                        <input 
                            type="file" 
                            id="image" 
                            name="image" 
                            accept="image/*"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-700 transition cursor-pointer file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                            onchange="previewImage(this)"
                        />
                        <?php if ($edit_mode && !empty($current_stall['image_path'])): ?>
                            <div class="mt-4 p-4 bg-gray-50 rounded-xl">
                                <p class="text-sm text-gray-600 mb-2 font-medium">Current Image:</p>
                                <img src="<?php echo htmlspecialchars($current_stall['image_path']); ?>" alt="Current Stall Image" class="w-full h-48 object-cover rounded-lg border-2 border-gray-200" />
                            </div>
                        <?php endif; ?>
                        <div id="imagePreview" class="mt-4 hidden">
                            <p class="text-sm text-gray-600 mb-2 font-medium">Preview:</p>
                            <img id="preview" src="#" alt="Stall Preview" class="w-full h-48 object-cover rounded-lg border-2 border-emerald-200" />
                        </div>
                    </div>
                    
                    <div class="flex gap-4 pt-4">
                        <button 
                            type="submit" 
                            class="flex-1 gradient-primary hover:opacity-90 text-white font-semibold py-4 rounded-xl shadow-lg flex items-center justify-center gap-3 transition-all"
                        >
                            <i class="fas <?php echo $edit_mode ? 'fa-save' : 'fa-plus-circle'; ?>"></i>
                            <?php echo $edit_mode ? 'Update Stall' : 'Create Stall'; ?>
                        </button>
                        
                        <a 
                            href="stall_page.php" 
                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-4 rounded-xl shadow-md flex items-center justify-center gap-3 transition-all"
                        >
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('preview');
            const imagePreview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    imagePreview.classList.remove('hidden');
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.src = "#";
                imagePreview.classList.add('hidden');
            }
        }
    </script>

    <style>
        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(10px);}
            to {opacity: 1; transform: translateY(0);}
        }
        .animate-fadeIn {
            animation: fadeIn 0.6s ease forwards;
        }
        @keyframes bounceSlow {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-6px);
            }
        }
        .animate-bounce-slow {
            animation: bounceSlow 2.5s infinite;
        }
    </style>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>