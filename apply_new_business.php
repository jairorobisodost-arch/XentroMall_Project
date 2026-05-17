<?php
session_start();
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$stallId = $_GET['stall_id'] ?? '';

if (empty($stallId)) {
    $_SESSION['error_message'] = "Invalid stall selection.";
    header('Location: apply_additional_stall.php');
    exit;
}

// Fetch stall details
$stmtStall = $pdo->prepare("SELECT * FROM stalls WHERE id = ? AND status = 'available'");
$stmtStall->execute([$stallId]);
$stall = $stmtStall->fetch(PDO::FETCH_ASSOC);

if (!$stall) {
    $_SESSION['error_message'] = "Stall not available.";
    header('Location: apply_additional_stall.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tradename = $_POST['tradename'] ?? '';
    $store_premises = $_POST['store_premises'] ?? '';
    $store_location = $_POST['store_location'] ?? '';
    $ownership = $_POST['ownership'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    $business_address = '';
    $addr_house = $_POST['addr_house'] ?? '';
    $addr_street = $_POST['addr_street'] ?? '';
    $addr_barangay = $_POST['addr_barangay'] ?? '';
    $addr_city = $_POST['addr_city'] ?? '';
    $addr_province = $_POST['addr_province'] ?? '';
    $addr_zip = $_POST['addr_zip'] ?? '';

    // Compose business address like tenant_register.php
    $address_parts = [];
    if (!empty($addr_house))
        $address_parts[] = $addr_house;
    if (!empty($addr_street))
        $address_parts[] = $addr_street;
    if (!empty($addr_barangay))
        $address_parts[] = 'Brgy. ' . $addr_barangay;
    if (!empty($addr_city))
        $address_parts[] = $addr_city;
    if (!empty($addr_province))
        $address_parts[] = $addr_province;
    if (!empty($addr_zip))
        $address_parts[] = $addr_zip;

    $business_address = implode(', ', $address_parts);
    $tin = $_POST['tin'] ?? '';
    $office_tel = $_POST['office_tel'] ?? '';
    $tenant_representative = $_POST['tenant_representative'] ?? '';
    $contact_person = $_POST['contact_person'] ?? '';
    $position = $_POST['position'] ?? '';
    $contact_tel = $_POST['contact_tel'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $email_tenant = $_POST['email_tenant'] ?? '';
    $prepared_by = $_POST['prepared_by'] ?? '';
    $business_type = $_POST['business_type'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';

    // Validate required fields
    if (empty($tradename) || empty($company_name) || empty($business_type) || empty($ownership)) {
        $_SESSION['error_message'] = "Please fill all required fields (Trade Name, Company Name, Business Type, and Business Structure).";
    } else {
        try {
            // Handle file uploads
            $upload_dir = 'uploads/' . $userId . '_' . time() . '/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $safeTradename = preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '_', $tradename));
            $dateStr = date('Y_m_d');

            // Process all uploaded files
            foreach ($_FILES as $field_name => $file_data) {
                if (is_array($file_data['name'])) {
                    foreach ($file_data['name'] as $index => $name) {
                        if ($file_data['error'][$index] === UPLOAD_ERR_OK) {
                            $tmp_name = $file_data['tmp_name'][$index];
                            $ext = pathinfo($name, PATHINFO_EXTENSION);
                            $docLabel = str_replace(' ', '', ucwords(str_replace('_', ' ', $field_name)));
                            $file_name = $docLabel . "_" . $safeTradename . "_" . $dateStr . "_" . substr(uniqid(), -5) . "." . $ext;
                            $target_path = $upload_dir . $file_name;
                            move_uploaded_file($tmp_name, $target_path);
                        }
                    }
                } else {
                    if ($file_data['error'] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($file_data['name'], PATHINFO_EXTENSION);
                        $docLabel = str_replace(' ', '', ucwords(str_replace('_', ' ', $field_name)));
                        $file_name = $docLabel . "_" . $safeTradename . "_" . $dateStr . "_" . substr(uniqid(), -5) . "." . $ext;
                        $target_path = $upload_dir . $file_name;
                        move_uploaded_file($file_data['tmp_name'], $target_path);
                    }
                }
            }

            // Insert new tenant application
            $stmt = $pdo->prepare("
                INSERT INTO tenant_details (
                    user_id, tradename, store_premises, store_location, ownership,
                    company_name, business_address, tin, office_tel, tenant_representative,
                    contact_person, position, contact_tel, mobile, email, prepared_by,
                    business_type, documents, status, stall_id, first_name, last_name
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $tradename,
                $store_premises,
                $store_location,
                $ownership,
                $company_name,
                $business_address,
                $tin,
                $office_tel,
                $tenant_representative,
                $contact_person,
                $position,
                $contact_tel,
                $mobile,
                $email_tenant,
                $prepared_by,
                $business_type,
                $upload_dir,
                $stallId,
                $first_name,
                $last_name
            ]);

            $_SESSION['success_message'] = "✅ New business application submitted successfully!";

            // Send email notification to admin
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'mallxentro5@gmail.com';
                $mail->Password = 'iwld cjlr kmcy bxab';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('mallxentro5@gmail.com', 'XentroMall System');
                $mail->addAddress('mallxentro5@gmail.com', 'XentroMall Admin');

                $mail->isHTML(true);
                $mail->Subject = "🏢 New Business - Additional Stall Application";
                $mail->Body = "
                <h2>New Business Application for Additional Stall</h2>
                <p><strong>Trade Name:</strong> $tradename</p>
                <p><strong>Company:</strong> $company_name</p>
                <p><strong>Business Type:</strong> $business_type</p>
                <p><strong>Stall:</strong> {$stall['stall_number']}</p>
                <p><strong>Contact:</strong> $mobile</p>
                <p>This is a NEW BUSINESS application from an existing tenant.</p>
                ";

                $mail->send();
            } catch (Exception $e) {
                error_log("Email failed: " . $e->getMessage());
            }

            header("Location: tenant_dashboard.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply New Business - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
        }

        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>

<body class="min-h-screen gradient-bg">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="glass-effect rounded-2xl p-6 mb-6 shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-file-alt text-green-600"></i> New Business Application
                    </h1>
                    <p class="text-gray-600 mt-2">Apply for Stall <?= htmlspecialchars($stall['stall_number']) ?> with
                        different business</p>
                </div>
                <a href="apply_additional_stall.php"
                    class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg glass-effect">
                <p class="font-bold"><i class="fas fa-exclamation-circle mr-2"></i> Error</p>
                <p><?= htmlspecialchars($_SESSION['error_message']) ?></p>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Stall Info -->
        <div class="glass-effect rounded-2xl p-6 mb-6 shadow-xl">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-store text-green-600"></i> Selected Stall
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Stall Number</p>
                    <p class="font-bold"><?= htmlspecialchars($stall['stall_number']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Description</p>
                    <p class="font-bold"><?= htmlspecialchars($stall['description']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Floor Area</p>
                    <p class="font-bold"><?= htmlspecialchars($stall['floor_area']) ?> sq.m</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Monthly Rate</p>
                    <p class="font-bold text-green-600">₱<?= number_format($stall['monthly_rate'], 2) ?></p>
                </div>
            </div>
        </div>

        <!-- Application Form -->
        <form method="POST" enctype="multipart/form-data" class="glass-effect rounded-2xl p-8 shadow-xl">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-building text-green-600"></i> Business Information
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Trade Name -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-store mr-1"></i> Trade Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="tradename" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                </div>

                <!-- Company Name -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-building mr-1"></i> Company Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="company_name" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                </div>

                <!-- Business Type -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-tag mr-1"></i> Business Type <span class="text-red-500">*</span>
                    </label>
                    <select name="business_type" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                        <option value="">Select Type</option>
                        <option value="Inline">Inline</option>
                        <option value="Exhibit">Exhibit</option>
                        <option value="Kiosk">Kiosk</option>
                        <option value="Food Cart">Food Cart</option>
                    </select>
                </div>

                <!-- TIN -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-id-card mr-1"></i> TIN
                    </label>
                    <input type="text" name="tin"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                        inputmode="numeric" maxlength="12"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12)">
                </div>

                <!-- Business Address -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-1"></i> Business Address <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label for="addr_house" class="block text-xs text-gray-600 mb-1">House/Unit,
                                Building</label>
                            <input type="text" name="addr_house" id="addr_house" required
                                placeholder="House/Unit, Building"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                        </div>
                        <div>
                            <label for="addr_street" class="block text-xs text-gray-600 mb-1">Street</label>
                            <input type="text" name="addr_street" id="addr_street" required placeholder="Street"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                        </div>
                        <div>
                            <label for="addr_barangay" class="block text-xs text-gray-600 mb-1">Barangay</label>
                            <input type="text" name="addr_barangay" id="addr_barangay" required placeholder="Barangay"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                        <div>
                            <label for="addr_city" class="block text-xs text-gray-600 mb-1">City/Municipality</label>
                            <input type="text" name="addr_city" id="addr_city" required placeholder="City/Municipality"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                        </div>
                        <div>
                            <label for="addr_province" class="block text-xs text-gray-600 mb-1">Province</label>
                            <input type="text" name="addr_province" id="addr_province" required placeholder="Province"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                        </div>
                        <div>
                            <label for="addr_zip" class="block text-xs text-gray-600 mb-1">ZIP Code</label>
                            <input type="text" name="addr_zip" id="addr_zip" placeholder="ZIP Code" maxlength="4"
                                inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                        </div>
                    </div>
                </div>

                <!-- Store Premises -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-home mr-1"></i> Store Premises
                    </label>
                    <input type="text" name="store_premises"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                </div>

                <!-- Store Location -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-location-dot mr-1"></i> Store Location
                    </label>
                    <input type="text" name="store_location"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                </div>

                <!-- Ownership / Business Structure -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-building mr-1"></i> Business Structure / Ownership <span
                            class="text-red-500">*</span>
                    </label>
                    <select name="ownership" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                        <option value="">Select Business Structure</option>
                        <option value="Corporation">Corporation</option>
                        <option value="Sole Proprietorship">Sole Proprietorship</option>
                        <option value="Franchisee">Franchisee</option>
                        <option value="Partnership">Partnership</option>
                        <option value="Cooperative">Cooperative</option>
                        <option value="One Person Corporation (OPC)">One Person Corporation (OPC)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Note:</strong> Corporation/Partnership/Cooperative requires SEC, Sole Proprietorship
                        requires DTI, Franchisee requires Franchise Agreement
                    </p>
                </div>

                <!-- Office Tel -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-phone mr-1"></i> Office Telephone
                    </label>
                    <input type="text" name="office_tel"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                        inputmode="numeric" maxlength="8"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8)">
                </div>
            </div>

            <h3 class="text-xl font-bold text-gray-800 mt-8 mb-4">
                <i class="fas fa-user text-green-600"></i> Contact Information
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- First Name -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-user mr-1"></i> First Name
                    </label>
                    <input type="text" name="first_name"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                </div>

                <!-- Last Name -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-user mr-1"></i> Last Name
                    </label>
                    <input type="text" name="last_name"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                </div>

                <!-- Tenant Representative -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-user-tie mr-1"></i> Tenant Representative
                    </label>
                    <input type="text" name="tenant_representative"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                </div>

                <!-- Contact Person -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-user mr-1"></i> Contact Person
                    </label>
                    <input type="text" name="contact_person"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                </div>

                <!-- Position -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-briefcase mr-1"></i> Position
                    </label>
                    <input type="text" name="position"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                </div>

                <!-- Contact Tel -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-phone mr-1"></i> Contact Telephone
                    </label>
                    <input type="text" name="contact_tel"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                        inputmode="numeric" maxlength="8"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8)">
                </div>

                <!-- Mobile -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-mobile-alt mr-1"></i> Mobile Number
                    </label>
                    <input type="text" name="mobile"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                        inputmode="numeric" maxlength="11"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-1"></i> Email Address
                    </label>
                    <input type="email" name="email_tenant"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                </div>

                <!-- Prepared By -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-user-edit mr-1"></i> Prepared By
                    </label>
                    <input type="text" name="prepared_by"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                </div>
            </div>

            <h3 class="text-xl font-bold text-gray-800 mt-8 mb-4">
                <i class="fas fa-file-upload text-green-600"></i> Upload Documents
            </h3>

            <!-- Document Upload Sections (Dynamic based on Ownership) -->
            <div id="document-upload-section">
                <!-- Corporation Documents -->
                <div id="corporation-files" class="hidden space-y-4">
                    <h4 class="font-semibold text-green-700 mb-3">Corporation Documents:</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Letter of Intent/Concept Papers <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="letter_intent" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Company Profile <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="company_profile" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                SEC Registration <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="sec_registration" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Secretary's Certificate <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="secretary_certificate" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                BIR Form 2303 <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="bir_2303" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="valid_id_1" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="valid_id_2" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                    </div>
                </div>

                <!-- Sole Proprietorship Documents -->
                <div id="sole-files" class="hidden space-y-4">
                    <h4 class="font-semibold text-green-700 mb-3">Sole Proprietorship Documents:</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Letter of Intent/Concept Papers <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="letter_intent_2" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                DTI Permit <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="dti_permit_2" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                BIR Form 2303 <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="bir_2303_2" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="valid_id_1_sole" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="valid_id_2_sole" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                    </div>
                </div>

                <!-- Franchisee Documents -->
                <div id="franchisee-files" class="hidden space-y-4">
                    <h4 class="font-semibold text-green-700 mb-3">Franchisee Documents:</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Letter of Intent/Concept Papers <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="letter_intent_3" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Enrollment Letter from Franchisor <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="enrollment_letter_3" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Photocopy of Franchise Agreement <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="franchise_agreement_3" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                BIR Form 2303 <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="bir_2303_3" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="valid_id_1_franchisee" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="valid_id_2_franchisee" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex gap-4">
                <button type="submit"
                    class="flex-1 bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700 text-white font-bold py-4 px-6 rounded-lg transition transform hover:scale-105 shadow-lg">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Application
                </button>
                <a href="apply_additional_stall.php"
                    class="px-6 py-4 bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold rounded-lg transition">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        // Show/hide document sections based on ownership selection
        document.querySelector('select[name="ownership"]').addEventListener('change', function () {
            const corporationFiles = document.getElementById('corporation-files');
            const soleFiles = document.getElementById('sole-files');
            const franchiseeFiles = document.getElementById('franchisee-files');

            // Hide all sections first
            corporationFiles.classList.add('hidden');
            soleFiles.classList.add('hidden');
            franchiseeFiles.classList.add('hidden');

            // Show the appropriate section
            const selectedValue = this.value;
            if (selectedValue === 'Corporation') {
                corporationFiles.classList.remove('hidden');
            } else if (selectedValue === 'Sole Proprietorship') {
                soleFiles.classList.remove('hidden');
            } else if (selectedValue === 'Franchisee') {
                franchiseeFiles.classList.remove('hidden');
            } else if (selectedValue === 'Partnership') {
                corporationFiles.classList.remove('hidden'); // Partnership uses same docs as Corporation
            } else if (selectedValue === 'Cooperative') {
                corporationFiles.classList.remove('hidden'); // Cooperative uses same docs as Corporation
            } else if (selectedValue === 'One Person Corporation (OPC)') {
                corporationFiles.classList.remove('hidden'); // OPC uses same docs as Corporation
            }
        });
    </script>
</body>

</html>