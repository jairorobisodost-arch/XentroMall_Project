<?php
require 'config.php';

// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        $user_id = $_POST['user_id'];
        // Handle file uploads
        $upload_dir = 'uploads/' . $user_id . '/';
        
        // Clean the directory before uploading new files
        if (is_dir($upload_dir)) {
            // Delete all files in the directory
            $files = glob($upload_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        } else {
            mkdir($upload_dir, 0755, true);
        }

        // Process all uploaded files
        $uploaded_files = [];
        foreach ($_FILES as $field_name => $file_data) {
            if (is_array($file_data['name'])) {
                foreach ($file_data['name'] as $index => $name) {
                    if ($file_data['error'][$index] === UPLOAD_ERR_OK) {
                        $tmp_name = $file_data['tmp_name'][$index];
                        $file_name = uniqid() . '_' . basename($name);
                        $target_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($tmp_name, $target_path)) {
                            $uploaded_files[$field_name][] = $target_path;
                        }
                    }
                }
            } else {
                if ($file_data['error'] === UPLOAD_ERR_OK) {
                    $file_name = uniqid() . '_' . basename($file_data['name']);
                    $target_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($file_data['tmp_name'], $target_path)) {
                        $uploaded_files[$field_name] = $target_path;
                    }
                }
            }
        }

        // Ensure submission_count column exists
        $pdo->exec("ALTER TABLE tenant_details ADD COLUMN IF NOT EXISTS submission_count INT NOT NULL DEFAULT 1");

        // Update tenant status to 'pending' and increment submission attempt count
        $update_stmt = $pdo->prepare("UPDATE tenant_details SET status = NULL, submission_count = submission_count + 1 WHERE user_id = :user_id");
        $update_stmt->execute([':user_id' => $user_id]);
        
        // Check if the update was successful
        if ($update_stmt->rowCount() > 0) {
            echo '<script>alert("Registration successful with ' . count($uploaded_files) . ' files uploaded. Your application is now pending review."); window.location.href = "login.php";</script>';
        } else {
            // If no rows were updated, the user might not exist in tenant_details
            echo '<script>alert("Files uploaded successfully, but there was an issue updating your application status. Please contact support."); window.location.href = "login.php";</script>';
        }
        exit;

    } catch (PDOException $e) {
        // Handle database errors
        error_log("Database error: " . $e->getMessage());
        echo '<script>alert("An error occurred during registration. Please try again."); window.location.href = "register.php";</script>';
        exit;
    } catch (Exception $e) {
        // Handle other errors
        error_log("Error: " . $e->getMessage());
        echo '<script>alert("An error occurred during file upload. Please try again."); window.location.href = "register.php";</script>';
        exit;
    }
}
?>

<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>Tenant Registration - Xentro Mall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&amp;display=swap" rel="stylesheet"/>
    <style>
        :root { color-scheme: light only; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0b1220;
        }
        /* Background hero with image + gradient overlay */
        .hero-bg { position: fixed; inset: 0; z-index: -10; overflow: hidden; }
        .hero-bg::before { content: ""; position: absolute; inset: 0; background: url('img/bg.jpg') center/cover no-repeat; filter: brightness(0.55) saturate(1.1); transform: scale(1.02); }
        .hero-bg::after { content: ""; position: absolute; inset: 0; background:
          radial-gradient(1200px 600px at 10% -10%, rgba(56,189,248,.25), transparent 60%),
          radial-gradient(900px 500px at 90% 110%, rgba(34,197,94,.20), transparent 60%),
          linear-gradient(180deg, rgba(2,6,23,.65), rgba(2,6,23,.65));
          mix-blend-mode: screen; }
        #requirements-container {
            max-height: 200px;
            overflow-y: auto;
        }
        #requirements-container::-webkit-scrollbar {
            width: 6px;
        }
        #requirements-container::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, #3b82f6, #10b981);
            border-radius: 10px;
        }
        input[type="radio"] {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid #3b82f6;
            border-radius: 9999px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
            vertical-align: middle;
        }
        input[type="radio"]:checked {
            border-color: #10b981;
            background: linear-gradient(135deg, #3b82f6, #10b981);
        }
        input[type="radio"]:checked::after {
            content: "";
            position: absolute;
            top: 0.25rem;
            left: 0.25rem;
            width: 0.5rem;
            height: 0.5rem;
            background: white;
            border-radius: 9999px;
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">
    <div class="hero-bg" aria-hidden="true"></div>

    <!-- Header -->
    <header class="fixed top-0 inset-x-0 z-40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <nav class="mt-4 flex items-center justify-between rounded-2xl bg-white/10 backdrop-blur-xl ring-1 ring-white/15 px-4 py-3 shadow-lg">
                <a href="index.php" class="flex items-center gap-3">
                    <span class="h-10 w-10 overflow-hidden rounded-xl ring-1 ring-white/30 bg-white/70 flex items-center justify-center shadow">
                        <img src="img/logo.jpg" alt="XentroMall" class="h-9 w-9 object-cover" />
                    </span>
                    <span class="text-white font-semibold tracking-tight text-lg">Xentro Mall</span>
                </a>
                <a href="user_stall_page.php" class="inline-flex items-center gap-2 rounded-lg bg-white/10 backdrop-blur px-4 py-2 text-white hover:bg-white/20 transition">
                    <i class="fas fa-arrow-left"></i>
                    <span class="hidden sm:inline">Back</span>
                </a>
            </nav>
        </div>
    </header>

    <div class="container mx-auto px-4 pt-28 pb-12">
        <div class="max-w-4xl mx-auto">
            <!-- Hero Section -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2 rounded-full bg-white/10 backdrop-blur-xl ring-1 ring-white/20 px-4 py-2 mb-6">
                    <i class="fas fa-redo text-emerald-400"></i>
                    <span class="text-sm font-medium text-white/90">Document Resubmission</span>
                </div>
                <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">
                    Resubmit <span class="bg-gradient-to-r from-blue-400 to-emerald-400 bg-clip-text text-transparent">Requirements</span>
                </h1>
                <p class="text-lg text-white/80 max-w-2xl mx-auto">Please upload all required documents for your business type</p>
            </div>

            <!-- Form Card -->
            <div class="bg-white/90 backdrop-blur-xl rounded-2xl shadow-2xl ring-1 ring-white/20 p-8 md:p-10">
                <form action="" class="space-y-8" enctype="multipart/form-data" method="POST">
                    <input type="hidden" value="<?php echo isset($_GET['user_id']) ? htmlspecialchars($_GET['user_id']) : ''; ?>" name="user_id" />
                    
                    <!-- Business Type Selection -->
                    <div>
                        <label class="block text-slate-900 font-bold text-lg mb-4 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-600 to-emerald-500 text-white flex items-center justify-center">
                                <i class="fas fa-building text-sm"></i>
                            </div>
                            Business Type <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <label class="relative flex items-center justify-center cursor-pointer p-4 rounded-xl border-2 border-slate-300 hover:border-blue-500 transition-all group">
                                <input name="business_type" onclick="toggleRequirements()" required="" type="radio" value="corporation" class="absolute opacity-0"/>
                                <div class="text-center">
                                    <i class="fas fa-building text-3xl text-slate-400 group-hover:text-blue-600 mb-2 transition"></i>
                                    <span class="block font-semibold text-slate-700 group-hover:text-blue-600 transition">Corporation</span>
                                </div>
                            </label>
                            <label class="relative flex items-center justify-center cursor-pointer p-4 rounded-xl border-2 border-slate-300 hover:border-emerald-500 transition-all group">
                                <input name="business_type" onclick="toggleRequirements()" type="radio" value="sole" class="absolute opacity-0"/>
                                <div class="text-center">
                                    <i class="fas fa-user-tie text-3xl text-slate-400 group-hover:text-emerald-600 mb-2 transition"></i>
                                    <span class="block font-semibold text-slate-700 group-hover:text-emerald-600 transition">Sole Proprietorship</span>
                                </div>
                            </label>
                            <label class="relative flex items-center justify-center cursor-pointer p-4 rounded-xl border-2 border-slate-300 hover:border-violet-500 transition-all group">
                                <input name="business_type" onclick="toggleRequirements()" type="radio" value="franchisee" class="absolute opacity-0"/>
                                <div class="text-center">
                                    <i class="fas fa-handshake text-3xl text-slate-400 group-hover:text-violet-600 mb-2 transition"></i>
                                    <span class="block font-semibold text-slate-700 group-hover:text-violet-600 transition">Franchisee</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Requirements Display -->
                    <div class="rounded-xl p-5 bg-gradient-to-br from-blue-50 to-emerald-50 border-2 border-blue-200" id="requirements-container">
                        <div class="hidden" id="corporation-requirements">
                            <h3 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                                <i class="fas fa-list-check text-blue-600"></i>
                                Corporation Requirements:
                            </h3>
                            <ul class="space-y-2 text-slate-700">
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>Letter of Intent/Concept Papers</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>Company Profile</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>SEC Registration</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>Secretary's Certificate of Authorized Signatory</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>BIR Form 2303</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>2 Valid IDs with 3 Specimen Signatures of Authorized Signatory</span></li>
                            </ul>
                        </div>
                        <div class="hidden" id="sole-requirements">
                            <h3 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                                <i class="fas fa-list-check text-emerald-600"></i>
                                Sole Proprietorship Requirements:
                            </h3>
                            <ul class="space-y-2 text-slate-700">
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>Letter of Intent/Concept Papers</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>DTI permit</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>BIR Form 2303</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>2 Valid IDs with 3 Specimen Signatures of Authorized Signatory</span></li>
                            </ul>
                        </div>
                        <div class="hidden" id="franchisee-requirements">
                            <h3 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                                <i class="fas fa-list-check text-violet-600"></i>
                                Franchisee Requirements:
                            </h3>
                            <ul class="space-y-2 text-slate-700">
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>Letter of Intent/Concept Papers</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>Enrollment letter from Franchisor</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>Photocopy of Franchise Agreement</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>BIR Form 2303</span></li>
                                <li class="flex items-start gap-2"><i class="fas fa-check-circle text-emerald-500 mt-1"></i><span>2 Valid IDs with 3 Specimen Signatures of Authorized Signatory</span></li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Document Upload Section -->
                    <div id="document-upload-section">
                        <div id="corporation-files" class="hidden">
                            <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2 text-lg">
                                <i class="fas fa-cloud-upload-alt text-blue-600"></i>
                                Upload Corporation Documents:
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-blue-600"></i>
                                        Letter of Intent/Concept Papers <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" name="letter_intent" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-blue-600"></i>
                                        Company Profile <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" name="company_profile" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-blue-600"></i>
                                        SEC Registration <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" name="sec_registration" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-blue-600"></i>
                                        Secretary's Certificate <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" name="secretary_certificate" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-blue-600"></i>
                                        BIR Form 2303 <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" name="bir_2303" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-id-card text-blue-600"></i>
                                        Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" name="valid_id_1" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-id-card text-blue-600"></i>
                                        Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" name="valid_id_2" type="file"/>
                                </div>
                            </div>
                        </div>

                        <div id="sole-files" class="hidden">
                            <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2 text-lg">
                                <i class="fas fa-cloud-upload-alt text-emerald-600"></i>
                                Upload Sole Proprietorship Documents:
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-emerald-600"></i>
                                        Letter of Intent/Concept Papers <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" name="letter_intent_2" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-emerald-600"></i>
                                        DTI Permit <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" name="dti_permit_2" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-emerald-600"></i>
                                        BIR Form 2303 <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" name="bir_2303_2" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-id-card text-emerald-600"></i>
                                        Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" name="valid_id_1_sole" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-id-card text-emerald-600"></i>
                                        Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" name="valid_id_2_sole" type="file"/>
                                </div>
                            </div>
                        </div>

                        <div id="franchisee-files" class="hidden">
                            <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2 text-lg">
                                <i class="fas fa-cloud-upload-alt text-violet-600"></i>
                                Upload Franchisee Documents:
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-violet-600"></i>
                                        Letter of Intent/Concept Papers <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition" name="letter_intent_3" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-violet-600"></i>
                                        Enrollment Letter from Franchisor <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition" name="enrollment_letter_3" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-violet-600"></i>
                                        Photocopy of Franchise Agreement <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition" name="franchise_agreement_3" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-file-alt text-violet-600"></i>
                                        BIR Form 2303 <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition" name="bir_2303_3" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-id-card text-violet-600"></i>
                                        Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition" name="valid_id_1_franchisee" type="file"/>
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-id-card text-violet-600"></i>
                                        Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span>
                                    </label>
                                    <input class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition" name="valid_id_2_franchisee" type="file"/>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col md:flex-row gap-4 pt-6 border-t border-slate-200">
                        <button class="flex-1 inline-flex items-center justify-center gap-2 bg-gradient-to-r from-blue-600 to-emerald-500 hover:from-blue-700 hover:to-emerald-600 text-white px-8 py-3.5 rounded-xl font-semibold shadow-lg shadow-emerald-500/30 transition-all hover:scale-105" type="submit">
                            <i class="fas fa-paper-plane"></i>
                            <span>Resubmit Documents</span>
                        </button>
                        <a class="flex-1 inline-flex items-center justify-center gap-2 bg-white/80 hover:bg-white text-slate-700 px-8 py-3.5 rounded-xl font-semibold shadow-lg transition-all hover:scale-105 border-2 border-slate-300" href="user_stall_page.php">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Stalls</span>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
    function toggleRequirements() {
        const type = document.querySelector('input[name="business_type"]:checked')?.value;
        const corp = document.getElementById('corporation-requirements');
        const sole = document.getElementById('sole-requirements');
        const fran = document.getElementById('franchisee-requirements');
        const corpFiles = document.getElementById('corporation-files');
        const soleFiles = document.getElementById('sole-files');
        const franFiles = document.getElementById('franchisee-files');

        // Hide all requirements and file upload sections first
        corp.classList.add('hidden');
        sole.classList.add('hidden');
        fran.classList.add('hidden');
        corpFiles.classList.add('hidden');
        soleFiles.classList.add('hidden');
        franFiles.classList.add('hidden');

        // Remove required attribute from all file inputs
        document.querySelectorAll('#document-upload-section input[type="file"]').forEach(input => {
            input.removeAttribute('required');
        });

        // Show the selected one and set required attributes
        if (type === 'corporation') {
            corp.classList.remove('hidden');
            corpFiles.classList.remove('hidden');
            // Set required for corporation files
            corpFiles.querySelectorAll('input[type="file"]').forEach(input => {
                input.setAttribute('required', 'required');
            });
        } else if (type === 'sole') {
            sole.classList.remove('hidden');
            soleFiles.classList.remove('hidden');
            // Set required for sole proprietorship files
            soleFiles.querySelectorAll('input[type="file"]').forEach(input => {
                input.setAttribute('required', 'required');
            });
        } else if (type === 'franchisee') {
            fran.classList.remove('hidden');
            franFiles.classList.remove('hidden');
            // Set required for franchisee files
            franFiles.querySelectorAll('input[type="file"]').forEach(input => {
                input.setAttribute('required', 'required');
            });
        }
    }

    // Also need to handle form submission to ensure validation
    document.querySelector('form').addEventListener('submit', function(e) {
        // Re-validate the visible file inputs
        const type = document.querySelector('input[name="business_type"]:checked')?.value;
        let fileInputs = [];
        
        if (type === 'corporation') {
            fileInputs = document.querySelectorAll('#corporation-files input[type="file"]');
        } else if (type === 'sole') {
            fileInputs = document.querySelectorAll('#sole-files input[type="file"]');
        } else if (type === 'franchisee') {
            fileInputs = document.querySelectorAll('#franchisee-files input[type="file"]');
        }
        
        let isValid = true;
        fileInputs.forEach(input => {
            if (!input.value) {
                isValid = false;
                input.classList.add('border-red-500');
            } else {
                input.classList.remove('border-red-500');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please upload all required documents for your selected business type.');
        }
    });
</script>

</body>
</html>