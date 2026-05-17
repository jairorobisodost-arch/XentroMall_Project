<?php
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function normalize_component($value) {
    if (!is_string($value)) {
        return $value;
    }

    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    if (function_exists('mb_convert_case')) {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    return ucwords(strtolower($value));
}

function normalize_address($value) {
    if (!is_string($value)) {
        return $value;
    }

    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    if (function_exists('mb_convert_case')) {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    return ucwords(strtolower($value));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $tradename = $_POST['tradename'] ?? '';
    $store_premises = $_POST['store_premises'] ?? '';
    $store_location = $_POST['store_location'] ?? '';
    $ownership = $_POST['ownership'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    $business_address = '';
    $tin = $_POST['tin'] ?? '';
    $office_tel = $_POST['office_tel'] ?? '';
    $tenant_rep_last_name = $_POST['tenant_rep_last_name'] ?? '';
    $tenant_rep_first_name = $_POST['tenant_rep_first_name'] ?? '';
    $tenant_rep_middle_name = $_POST['tenant_rep_middle_name'] ?? '';
    $tenant_rep_suffix = $_POST['tenant_rep_suffix'] ?? '';
    $contact_last_name = $_POST['contact_last_name'] ?? '';
    $contact_first_name = $_POST['contact_first_name'] ?? '';
    $contact_middle_name = $_POST['contact_middle_name'] ?? '';
    $contact_suffix = $_POST['contact_suffix'] ?? '';
    $position = $_POST['position'] ?? '';
    $contact_tel = $_POST['contact_tel'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $prepared_by = $_POST['prepared_by'] ?? '';
    $business_type = $_POST['business_type'] ?? '';
    $stall_id = $_POST['stall_id'] ?? '';
    $addr_house = $_POST['addr_house'] ?? '';
    $addr_street = $_POST['addr_street'] ?? '';
    $addr_barangay = $_POST['addr_barangay'] ?? '';
    $addr_city = $_POST['addr_city'] ?? '';
    $addr_province = $_POST['addr_province'] ?? '';
    $addr_zip = $_POST['addr_zip'] ?? '';

    // Normalize component names
    $tradename = normalize_component($tradename);
    $company_name = normalize_component($company_name);
    $prepared_by = normalize_component($prepared_by);

    // Normalize addresses and related fields
    $store_premises = normalize_address($store_premises);
    $store_location = normalize_address($store_location);

    // Normalize name components
    $tenant_rep_last_name = normalize_component($tenant_rep_last_name);
    $tenant_rep_first_name = normalize_component($tenant_rep_first_name);
    $tenant_rep_middle_name = normalize_component($tenant_rep_middle_name);
    $tenant_rep_suffix = normalize_component($tenant_rep_suffix);

    $contact_last_name = normalize_component($contact_last_name);
    $contact_first_name = normalize_component($contact_first_name);
    $contact_middle_name = normalize_component($contact_middle_name);
    $contact_suffix = normalize_component($contact_suffix);

    // Normalize address components
    $addr_house = normalize_address($addr_house);
    $addr_street = normalize_address($addr_street);
    $addr_barangay = normalize_address($addr_barangay);
    $addr_city = normalize_address($addr_city);
    $addr_province = normalize_address($addr_province);
    $addr_zip = trim($addr_zip);

    // Compose full names
    $tenant_representative = '';
    $name_core_rep = trim($tenant_rep_first_name . ' ' . $tenant_rep_middle_name);
    $name_core_rep = preg_replace('/\s+/', ' ', $name_core_rep);
    if (!empty($tenant_rep_last_name) && !empty($name_core_rep)) {
        $tenant_representative = $tenant_rep_last_name . ', ' . $name_core_rep;
    } else {
        $tenant_representative = trim($tenant_rep_last_name . ' ' . $name_core_rep);
    }
    if (!empty($tenant_rep_suffix)) {
        $tenant_representative = trim($tenant_representative . ' ' . $tenant_rep_suffix);
    }
    $tenant_representative = preg_replace('/\s+/', ' ', $tenant_representative);

    $contact_person = '';
    $name_core_contact = trim($contact_first_name . ' ' . $contact_middle_name);
    $name_core_contact = preg_replace('/\s+/', ' ', $name_core_contact);
    if (!empty($contact_last_name) && !empty($name_core_contact)) {
        $contact_person = $contact_last_name . ', ' . $name_core_contact;
    } else {
        $contact_person = trim($contact_last_name . ' ' . $name_core_contact);
    }
    if (!empty($contact_suffix)) {
        $contact_person = trim($contact_person . ' ' . $contact_suffix);
    }
    $contact_person = preg_replace('/\s+/', ' ', $contact_person);

    // Compose business address
    $address_parts = [];
    if (!empty($addr_house)) $address_parts[] = $addr_house;
    if (!empty($addr_street)) $address_parts[] = $addr_street;
    if (!empty($addr_barangay)) $address_parts[] = 'Brgy. ' . $addr_barangay;
    if (!empty($addr_city)) $address_parts[] = $addr_city;
    if (!empty($addr_province)) $address_parts[] = $addr_province;
    if (!empty($addr_zip)) $address_parts[] = $addr_zip;

    $business_address = implode(', ', $address_parts);

    // Validate required fields
    $required_fields = [$email, $password, $tradename, $store_premises, $store_location, 
                       $ownership, $company_name, $business_address, $prepared_by, $business_type, $stall_id];
    
    foreach ($required_fields as $field) {
        if (empty($field)) {
            die('Please fill all required fields.');
        }
    }

    // Use email as username for login
    $username = $email; // Use email as username
    $userEmail = $email;

    // Check if email already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $userEmail]);
    if ($stmt->fetch()) {
        die('Email already exists.');
    }

    // Create user with email verification
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $verificationToken = bin2hex(random_bytes(32));
    $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, role, email_verified, verification_token, verification_token_expires) VALUES (:username, :email, :password, :role, :email_verified, :verification_token, :verification_token_expires)');
    $stmt->execute([
        'username' => $username,
        'email' => $userEmail,
        'password' => $passwordHash,
        'role' => 'tenant',
        'email_verified' => false,
        'verification_token' => $verificationToken,
        'verification_token_expires' => $tokenExpires
    ]);
    $user_id = $pdo->lastInsertId();

    // Handle file uploads
    $upload_dir = 'uploads/' . $user_id . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Process all uploaded files
    $uploaded_files = [];
    foreach ($_FILES as $field_name => $file_data) {
        if (is_array($file_data['name'])) {
            // Handle multiple files in one field
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
            // Handle single file upload
            if ($file_data['error'] === UPLOAD_ERR_OK) {
                $file_name = uniqid() . '_' . basename($file_data['name']);
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($file_data['tmp_name'], $target_path)) {
                    $uploaded_files[$field_name] = $target_path;
                }
            }
        }
    }

    // Insert tenant details
    $stmt = $pdo->prepare('INSERT INTO tenant_details 
        (user_id, tradename, store_premises, store_location, ownership, company_name, 
         business_address, tin, office_tel, tenant_representative, contact_person, 
         position, contact_tel, mobile, email, prepared_by, business_type, documents, stall_id) 
        VALUES 
        (:user_id, :tradename, :store_premises, :store_location, :ownership, :company_name, 
         :business_address, :tin, :office_tel, :tenant_representative, :contact_person, 
         :position, :contact_tel, :mobile, :email, :prepared_by, :business_type, :documents, :stall_id)');
    
    $stmt->execute([
        'user_id' => $user_id,
        'tradename' => $tradename,
        'store_premises' => $store_premises,
        'store_location' => $store_location,
        'ownership' => $ownership,
        'company_name' => $company_name,
        'business_address' => $business_address,
        'tin' => $tin,
        'office_tel' => $office_tel,
        'tenant_representative' => $tenant_representative,
        'contact_person' => $contact_person,
        'position' => $position,
        'contact_tel' => $contact_tel,
        'mobile' => $mobile,
        'email' => $email, // Use email from top section
        'prepared_by' => $prepared_by,
        'business_type' => $business_type,
        'documents' => $upload_dir, // Storing just the directory path
        'stall_id' => $stall_id
    ]);
    
    $tenant_details_id = $pdo->lastInsertId();
    
    // Get stall information
    $stmtStall = $pdo->prepare('SELECT stall_number, monthly_rate, description FROM stalls WHERE id = ?');
    $stmtStall->execute([$stall_id]);
    $stall = $stmtStall->fetch();
    $stall_number = $stall['stall_number'] ?? 'N/A';
    $stall_location = $stall['description'] ?? 'N/A';
    $monthly_rate = $stall['monthly_rate'] ?? '0.00';

    // Send email verification to tenant
    error_log("=== SENDING EMAIL VERIFICATION === New Tenant Registration");
    error_log("Tenant: $tradename, Email: $email");
    
    $verificationLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/xentromall/verify_email.php?token=' . $verificationToken;
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug [$level]: $str");
        };
        
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mallxentro5@gmail.com';
        $mail->Password = 'iwld cjlr kmcy bxab';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        // Fix SSL certificate verification issue
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom('mallxentro5@gmail.com', 'XentroMall Management');
        $mail->addAddress($email, $tradename);
        $mail->isHTML(true);
        
        $mail->Subject = 'Verify Your Email Address - XentroMall Registration';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;'>
                <div style='background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <h2 style='color: #10b981; margin: 0;'>XentroMall</h2>
                        <p style='color: #6b7280; margin: 5px 0 0 0;'>Tenant Management System</p>
                    </div>
                    
                    <h3 style='color: #1f2937; margin-bottom: 20px;'>Verify Your Email Address</h3>
                    
                    <p style='color: #4b5563; line-height: 1.6; margin-bottom: 25px;'>
                        Thank you for registering with XentroMall! Please click the button below to verify your email address and complete your registration.
                    </p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$verificationLink' style='background: linear-gradient(135deg, #10b981, #3b82f6); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>
                            Verify Email Address
                        </a>
                    </div>
                    
                    <p style='color: #6b7280; font-size: 14px; text-align: center; margin-bottom: 20px;'>
                        This link will expire in 24 hours.
                    </p>
                    
                    <div style='background: #f9fafb; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #e5e7eb;'>
                        <p style='color: #6b7280; font-size: 12px; margin: 0 0 8px 0; text-align: center;'>
                            If the button above doesn't work, copy and paste this link into your browser:
                        </p>
                        <p style='color: #1f2937; font-size: 11px; margin: 0; word-break: break-all; text-align: center; font-family: monospace; background: #f3f4f6; padding: 8px; border-radius: 4px;'>
                            $verificationLink
                        </p>
                    </div>
                    
                    <div style='background: #f3f4f6; padding: 20px; border-radius: 8px; margin-top: 25px;'>
                        <h4 style='color: #374151; margin: 0 0 10px 0; font-size: 16px;'>Registration Details:</h4>
                        <p style='color: #6b7280; margin: 5px 0;'><strong>Trade Name:</strong> $tradename</p>
                        <p style='color: #6b7280; margin: 5px 0;'><strong>Email:</strong> $email</p>
                        <p style='color: #6b7280; margin: 5px 0;'><strong>Stall:</strong> $stall_number</p>
                    </div>
                    
                    <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;'>
                        <p style='color: #9ca3af; font-size: 12px; margin: 0;'>
                            This is an automated message from XentroMall Management System<br>
                            © " . date('Y') . " XentroMall. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        ";
        
        if($mail->send()) {
            error_log("✅✅✅ VERIFICATION EMAIL SENT SUCCESSFULLY to: $email");
        } else {
            error_log("❌ Verification email failed: " . $mail->ErrorInfo);
        }
        
    } catch (Exception $e) {
        error_log("❌ Email verification error: " . $e->getMessage());
    }

    // Send email notification to admin
    error_log("=== SENDING ADMIN NOTIFICATION === New Tenant Registration");
    error_log("Tenant: $tradename, Email: $email, Stall: $stall_number");
    
    $adminEmail = 'mallxentro5@gmail.com';
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug [$level]: $str");
        };
        
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mallxentro5@gmail.com';
        $mail->Password = 'iwld cjlr kmcy bxab';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->Timeout = 30;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom('mallxentro5@gmail.com', 'XentroMall System');
        $mail->addAddress($adminEmail, 'XentroMall Admin');

        // Content
        $mail->isHTML(true);
        $mail->Subject = '🆕 New Tenant Application - XentroMall';
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                .header { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                .header p { margin: 10px 0 0 0; font-size: 14px; opacity: 0.9; }
                .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #3b82f6; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                .info-box h3 { margin-top: 0; color: #3b82f6; font-size: 18px; }
                .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
                .info-row:last-child { border-bottom: none; }
                .info-label { font-weight: bold; color: #374151; width: 180px; }
                .info-value { color: #1f2937; flex: 1; }
                .badge { display: inline-block; background: #3b82f6; color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                .action-button { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🆕 New Tenant Application</h1>
                    <p>XentroMall Management System</p>
                </div>
                <div class='content'>
                    <p style='font-size: 16px; color: #1f2937; margin-bottom: 20px;'>
                        <strong>Good news!</strong> A new tenant has submitted an application and is waiting for your review.
                    </p>
                    
                    <div class='info-box'>
                        <h3>📋 Application Details</h3>
                        <div class='info-row'>
                            <div class='info-label'>Application ID:</div>
                            <div class='info-value'>#$tenant_details_id</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Status:</div>
                            <div class='info-value'><span class='badge'>Pending Review</span></div>
                        </div>
                    </div>
                    
                    <div class='info-box'>
                        <h3>🏢 Business Information</h3>
                        <div class='info-row'>
                            <div class='info-label'>Trade Name:</div>
                            <div class='info-value'><strong>$tradename</strong></div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Company Name:</div>
                            <div class='info-value'>$company_name</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Business Type:</div>
                            <div class='info-value'>$business_type</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Business Address:</div>
                            <div class='info-value'>$business_address</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>TIN:</div>
                            <div class='info-value'>$tin</div>
                        </div>
                    </div>
                    
                    <div class='info-box'>
                        <h3>📍 Stall Information</h3>
                        <div class='info-row'>
                            <div class='info-label'>Stall Number:</div>
                            <div class='info-value'><strong>$stall_number</strong></div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Monthly Rate:</div>
                            <div class='info-value'><strong>₱" . number_format($monthly_rate, 2) . "</strong></div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Stall Location:</div>
                            <div class='info-value'>$stall_location</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Store Location:</div>
                            <div class='info-value'>$store_location</div>
                        </div>
                    </div>
                    
                    <div class='info-box'>
                        <h3>👤 Contact Information</h3>
                        <div class='info-row'>
                            <div class='info-label'>Contact Person:</div>
                            <div class='info-value'>$contact_person</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Position:</div>
                            <div class='info-value'>$position</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Email:</div>
                            <div class='info-value'>$email</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Mobile:</div>
                            <div class='info-value'>$mobile</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Office Tel:</div>
                            <div class='info-value'>$office_tel</div>
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <p style='font-size: 15px; color: #374151; margin-bottom: 15px;'>
                            Please review this application and take appropriate action.
                        </p>
                        <a href='http://localhost/Jai/XentroMall/admin_dashboard.php' class='action-button'>
                            Review Application →
                        </a>
                    </div>
                    
                    <div class='footer'>
                        <p style='margin: 5px 0;'>This is an automated notification from XentroMall Management System</p>
                        <p style='margin: 5px 0;'>© " . date('Y') . " XentroMall. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        if($mail->send()) {
            error_log("✅✅✅ ADMIN EMAIL SENT SUCCESSFULLY for new tenant: $tradename");
        } else {
            error_log("❌ Admin email failed: " . $mail->ErrorInfo);
        }
    } catch (Exception $e) {
        error_log("❌ Exception sending admin email: " . $e->getMessage());
    }

    echo '<script>alert("Registration successful with ' . count($uploaded_files) . ' files uploaded."); window.location.href = "login.php";</script>';
    exit;
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
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
        }
        /* Custom scrollbar for requirements container */
        #requirements-container {
            max-height: 180px;
            overflow-y: auto;
        }
        /* Hide default radio button */
        input[type="radio"] {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid #10b981;
            border-radius: 9999px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
            vertical-align: middle;
        }
        input[type="radio"]:checked {
            border-color: #22c55e; /* green */
            background-color: #22c55e;
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
        .document-upload {
            margin-bottom: 1rem;
        }
        .document-upload label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #1e40af;
        }
    </style>
</head>
<body class="min-h-screen p-2 py-4">
<div class="w-full max-w-4xl bg-white rounded-xl shadow-lg p-4 md:p-6 mx-auto">
    <!-- Header with Logo -->
    <div class="flex flex-col items-center mb-4">
        <img src="img/logo.jpg" alt="XentroMall Logo" class="w-16 h-16 object-contain mb-2" />
        <h1 class="text-xl font-bold text-slate-900 text-center mb-1">
            XentroMall Tenant Registration
        </h1>
        <p class="text-xs text-slate-600 text-center">Complete your tenant information and document submission</p>
    </div>
    <form action="" class="space-y-4" enctype="multipart/form-data" method="POST">
        <input type="hidden" value="<?php echo $_GET['stall_id'];?>" name="stall_id">
        
        <!-- Tenant Information Fields (same as before) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="tradename">
                    Trade Name <span class="text-red-500">*</span>
                </label>
                <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="tradename" name="tradename" required="" type="text"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="store_premises">
                    Use of Store Premises <span class="text-red-500">*</span>
                </label>
                <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="store_premises" name="store_premises" required="" type="text"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="store_location">
                    Location/s <span class="text-red-500">*</span>
                </label>
                <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="store_location" name="store_location" required="" type="text"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="ownership">
                    Ownership <span class="text-red-500">*</span>
                </label>
                <select class="w-full rounded-md border border-blue-300 px-4 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-green-400" id="ownership" name="ownership" required="">
                    <option value="">Select ownership</option>
                    <option value="Corporation">Corporation</option>
                    <option value="Sole Proprietor">Sole Proprietor</option>
                    <option value="Partnership">Partnership</option>
                </select>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="company_name">
                    Company Name <span class="text-red-500">*</span>
                </label>
                <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="company_name" name="company_name" required="" type="text"/>
            </div>
            <div class="md:col-span-2">
                <label class="block text-green-700 font-semibold mb-1" for="addr_house">
                    Business Address <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="addr_house" class="block text-sm text-gray-600 mb-1">House/Unit, Building</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="addr_house" name="addr_house" type="text" placeholder="House/Unit, Building" required/>
                    </div>
                    <div>
                        <label for="addr_street" class="block text-sm text-gray-600 mb-1">Street</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="addr_street" name="addr_street" type="text" placeholder="Street" required/>
                    </div>
                    <div>
                        <label for="addr_barangay" class="block text-sm text-gray-600 mb-1">Barangay</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="addr_barangay" name="addr_barangay" type="text" placeholder="Barangay" required/>
                    </div>
                    <div>
                        <label for="addr_city" class="block text-sm text-gray-600 mb-1">City/Municipality</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="addr_city" name="addr_city" type="text" placeholder="City/Municipality" required/>
                    </div>
                    <div>
                        <label for="addr_province" class="block text-sm text-gray-600 mb-1">Province</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="addr_province" name="addr_province" type="text" placeholder="Province" required/>
                    </div>
                    <div>
                        <label for="addr_zip" class="block text-sm text-gray-600 mb-1">ZIP Code</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="addr_zip" name="addr_zip" type="text" placeholder="ZIP Code" required/>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="tin">
                    Tax Identification Number (TIN)
                </label>
                <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="tin" name="tin" type="text"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="office_tel">
                    Office Telephone Number
                </label>
                <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="office_tel" name="office_tel" type="text"/>
            </div>
            <div class="md:col-span-2">
                <label class="block text-green-700 font-semibold mb-1">
                    Tenant Representative Name
                </label>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="tenant_rep_last_name" class="block text-sm text-gray-600 mb-1">Last Name</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="tenant_rep_last_name" name="tenant_rep_last_name" type="text" placeholder="Last Name"/>
                    </div>
                    <div>
                        <label for="tenant_rep_first_name" class="block text-sm text-gray-600 mb-1">First Name</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="tenant_rep_first_name" name="tenant_rep_first_name" type="text" placeholder="First Name"/>
                    </div>
                    <div>
                        <label for="tenant_rep_middle_name" class="block text-sm text-gray-600 mb-1">Middle Name</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="tenant_rep_middle_name" name="tenant_rep_middle_name" type="text" placeholder="Middle Name"/>
                    </div>
                    <div>
                        <label for="tenant_rep_suffix" class="block text-sm text-gray-600 mb-1">Suffix</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="tenant_rep_suffix" name="tenant_rep_suffix" type="text" placeholder="Suffix"/>
                    </div>
                </div>
            </div>
            <div class="md:col-span-2">
                <label class="block text-green-700 font-semibold mb-1">
                    Contact Person Name
                </label>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="contact_last_name" class="block text-sm text-gray-600 mb-1">Last Name</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="contact_last_name" name="contact_last_name" type="text" placeholder="Last Name"/>
                    </div>
                    <div>
                        <label for="contact_first_name" class="block text-sm text-gray-600 mb-1">First Name</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="contact_first_name" name="contact_first_name" type="text" placeholder="First Name"/>
                    </div>
                    <div>
                        <label for="contact_middle_name" class="block text-sm text-gray-600 mb-1">Middle Name</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="contact_middle_name" name="contact_middle_name" type="text" placeholder="Middle Name"/>
                    </div>
                    <div>
                        <label for="contact_suffix" class="block text-sm text-gray-600 mb-1">Suffix</label>
                        <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="contact_suffix" name="contact_suffix" type="text" placeholder="Suffix"/>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="position">
                    Position
                </label>
                <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="position" name="position" type="text"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="contact_tel">
                    Telephone Number
                </label>
                <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="contact_tel" name="contact_tel" type="text"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="mobile">
                    Mobile Number
                </label>
                <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="mobile" name="mobile" type="text"/>
            </div>
        </div>
        
        <div>
            <h2 class="text-xl font-semibold text-green-600 mb-2">Consent</h2>
            <p class="text-gray-700 bg-green-50 p-4 rounded-md border border-green-200">
                I hereby give full consent to the Lessor to collect, record, organize, store, update, use, consolidate, block, erase or process information, whether personal, sensitive or privileged, pertaining to myself and the subject hereof.
            </p>
        </div>
        
        <div>
            <label class="block text-green-700 font-semibold mb-1" for="prepared_by">
                Prepared by:
            </label>
            <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="prepared_by" name="prepared_by" type="text"/>
        </div>
        
        <!-- Document Submission Fields -->
        <div>
            <label class="block text-green-700 font-semibold mb-2">
                Business Type <span class="text-red-500">*</span>
            </label>
            <div class="flex flex-col md:flex-row md:space-x-8 space-y-3 md:space-y-0">
                <label class="inline-flex items-center cursor-pointer text-blue-700 font-medium">
                    <input name="business_type" onclick="toggleRequirements()" required="" type="radio" value="corporation"/>
                    <span>Corporation</span>
                </label>
                <label class="inline-flex items-center cursor-pointer text-blue-700 font-medium">
                    <input name="business_type" onclick="toggleRequirements()" type="radio" value="sole"/>
                    <span>Sole Proprietorship</span>
                </label>
                <label class="inline-flex items-center cursor-pointer text-blue-700 font-medium">
                    <input name="business_type" onclick="toggleRequirements()" type="radio" value="franchisee"/>
                    <span>Franchisee</span>
                </label>
            </div>
        </div>
        
        <div class="border border-blue-300 rounded-md p-4 bg-blue-50 text-blue-900 space-y-3" id="requirements-container">
            <div class="hidden" id="corporation-requirements">
                <h3 class="font-semibold text-green-700 mb-2">Corporation Requirements:</h3>
                <ul class="list-disc list-inside space-y-1">
                    <li>Letter of Intent/Concept Papers</li>
                    <li>Company Profile</li>
                    <li>SEC Registration</li>
                    <li>Secretary's Certificate of Authorized Signatory</li>
                    <li>BIR Form 2303</li>
                    <li>2 Valid IDs with 3 Specimen Signatures of Authorized Signatory</li>
                </ul>
            </div>
            <div class="hidden" id="sole-requirements">
                <h3 class="font-semibold text-green-700 mb-2">Sole Proprietorship Requirements:</h3>
                <ul class="list-disc list-inside space-y-1">
                    <li>Letter of Intent/Concept Papers</li>
                    <li>DTI permit</li>
                    <li>BIR Form 2303</li>
                    <li>2 Valid IDs with 3 Specimen Signatures of Authorized Signatory</li>
                </ul>
            </div>
            <div class="hidden" id="franchisee-requirements">
                <h3 class="font-semibold text-green-700 mb-2">Franchisee Requirements:</h3>
                <ul class="list-disc list-inside space-y-1">
                    <li>Letter of Intent/Concept Papers</li>
                    <li>Enrollment letter from Franchisor</li>
                    <li>Photocopy of Franchise Agreement</li>
                    <li>BIR Form 2303</li>
                    <li>2 Valid IDs with 3 Specimen Signatures of Authorized Signatory</li>
                </ul>
            </div>
        </div>
        
<!-- Replace the document upload section with this: -->
<div id="document-upload-section">
    <div id="corporation-files" class="hidden">
        <h3 class="font-semibold text-green-700 mb-2">Upload Corporation Documents:</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-green-700 font-semibold mb-1">Letter of Intent/Concept Papers <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="letter_intent" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">Company Profile <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="company_profile" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">SEC Registration <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="sec_registration" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">Secretary's Certificate <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="secretary_certificate" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">BIR Form 2303 <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="bir_2303" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id_1" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id_2" type="file"/>
            </div>
        </div>
    </div>

    <div id="sole-files" class="hidden">
        <h3 class="font-semibold text-green-700 mb-2">Upload Sole Proprietorship Documents:</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-green-700 font-semibold mb-1">Letter of Intent/Concept Papers <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="letter_intent_2" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">DTI Permit <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="dti_permit_2" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">BIR Form 2303 <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="bir_2303_2" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id_1_sole" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id_2_sole" type="file"/>
            </div>
        </div>
    </div>

    <div id="franchisee-files" class="hidden">
        <h3 class="font-semibold text-green-700 mb-2">Upload Franchisee Documents:</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-green-700 font-semibold mb-1">Letter of Intent/Concept Papers <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="letter_intent_3" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">Enrollment Letter from Franchisor <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="enrollment_letter_3" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">Photocopy of Franchise Agreement <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="franchise_agreement_3" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">BIR Form 2303 <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="bir_2303_3" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id_1_franchisee" type="file"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1">Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span></label>
                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id_2_franchisee" type="file"/>
            </div>
        </div>
    </div>
</div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="email">
                    Email Address <span class="text-red-500">*</span>
                </label>
                <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="email" name="email" required type="email" placeholder="Enter your email address"/>
            </div>
            <div>
                <label class="block text-green-700 font-semibold mb-1" for="password">
                    Password <span class="text-red-500">*</span>
                </label>
                <input class="w-full rounded-md border border-blue-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" id="password" name="password" required="" type="password"/>
            </div>
        </div>
        
        <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
            <button class="w-full md:w-auto bg-green-500 hover:bg-green-600 text-white font-semibold rounded-md px-8 py-3 shadow-md transition duration-300 flex items-center justify-center space-x-2" type="submit">
                <i class="fas fa-paper-plane"></i>
                <span>Submit</span>
            </button>
            <a class="w-full md:w-auto border-2 border-blue-500 text-blue-600 hover:bg-blue-100 font-semibold rounded-md px-8 py-3 shadow-md transition duration-300 flex items-center justify-center space-x-2" href="user_stall_page.php">
                <span>Back</span>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </form>
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