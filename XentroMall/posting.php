<?php
session_start();
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $date = $_POST['date'];
    $category = $_POST['category'];

    try {
        // Insert announcement into database
        $stmt = $pdo->prepare("INSERT INTO announcements (title, description, date, category, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$title, $description, $date, $category]);
        
        // Get all tenant emails
        $emailStmt = $pdo->prepare("SELECT DISTINCT email FROM tenant_details WHERE email IS NOT NULL AND email != ''");
        $emailStmt->execute();
        $tenants = $emailStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($tenants)) {
            // Send email to all tenants
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'your-email@gmail.com'; // Change this
                $mail->Password = 'your-app-password'; // Change this
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                // Sender
                $mail->setFrom('noreply@xentromall.com', 'XentroMall Admin');
                
                // Add all tenants as BCC (hidden recipients)
                foreach ($tenants as $email) {
                    $mail->addBCC($email);
                }
                
                // Email content
                $mail->isHTML(true);
                $mail->Subject = "XentroMall Announcement: $title";
                
                $categoryLabel = [
                    'upcoming_events' => 'Upcoming Event',
                    'renewal' => 'Renewal Notice',
                    'general' => 'General Announcement'
                ][$category] ?? 'Announcement';
                
                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                        .badge { display: inline-block; background: #3b82f6; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; margin-bottom: 15px; }
                        .title { font-size: 24px; font-weight: bold; color: #1f2937; margin-bottom: 15px; }
                        .description { color: #4b5563; margin-bottom: 20px; }
                        .date { background: white; padding: 15px; border-left: 4px solid #10b981; margin: 20px 0; }
                        .footer { text-align: center; color: #6b7280; font-size: 12px; margin-top: 30px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1 style='margin: 0;'>XentroMall</h1>
                            <p style='margin: 5px 0 0 0; opacity: 0.9;'>Tenant Management System</p>
                        </div>
                        <div class='content'>
                            <span class='badge'>$categoryLabel</span>
                            <div class='title'>$title</div>
                            <div class='description'>$description</div>
                            <div class='date'>
                                <strong>📅 Date:</strong> " . date('F d, Y', strtotime($date)) . "
                            </div>
                            <p style='color: #6b7280; font-size: 14px;'>
                                For more information, please log in to your tenant dashboard or contact the mall administration.
                            </p>
                        </div>
                        <div class='footer'>
                            <p>© " . date('Y') . " XentroMall • All rights reserved</p>
                            <p>This is an automated message. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $mail->send();
                $success_message = "Announcement posted successfully and emails sent to " . count($tenants) . " tenant(s)!";
            } catch (Exception $e) {
                $success_message = "Announcement posted successfully, but email sending failed: {$mail->ErrorInfo}";
            }
        } else {
            $success_message = "Announcement posted successfully! (No tenant emails found)";
        }
        
    } catch (Exception $e) {
        $error_message = "Error posting announcement: " . $e->getMessage();
    }
}
?>

<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Post Announcement - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%);
        }
        
        .gradient-primary {
            background: linear-gradient(135deg, #059669 0%, #0ea5e9 100%);
        }
        
        .form-input {
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.2);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-6xl">
        <!-- Main Card -->
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            <div class="grid md:grid-cols-2 gap-0">
                
                <!-- Left Side - Branding -->
                <div class="gradient-primary p-12 flex flex-col justify-center items-center text-white relative overflow-hidden">
                    <!-- Background decoration -->
                    <div class="absolute -top-20 -right-20 w-64 h-64 bg-white/10 rounded-full"></div>
                    <div class="absolute -bottom-20 -left-20 w-48 h-48 bg-white/5 rounded-full"></div>
                    
                    <div class="relative z-10 text-center">
                        <!-- Logo -->
                        <div class="mb-8 flex justify-center">
                            <div class="bg-white/20 p-4 rounded-3xl backdrop-blur-sm">
                                <img src="img/logo.jpg" alt="XentroMall Logo" class="w-32 h-32 object-contain rounded-2xl" />
                            </div>
                        </div>
                        
                        <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-6 backdrop-blur-sm">
                            <i class="fas fa-bullhorn text-5xl"></i>
                        </div>
                        
                        <h1 class="text-4xl font-bold mb-4" style="font-family: 'Poppins', sans-serif;">Announcements</h1>
                        <p class="text-xl text-white/90 mb-6">Broadcast to All Tenants</p>
                        
                        <div class="space-y-4 mt-12">
                            <div class="flex items-center gap-3 text-white/90">
                                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <span>Email Notifications</span>
                            </div>
                            <div class="flex items-center gap-3 text-white/90">
                                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-users"></i>
                                </div>
                                <span>Reach All Tenants</span>
                            </div>
                            <div class="flex items-center gap-3 text-white/90">
                                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <span>Schedule Events</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Form -->
                <div class="p-12">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-2" style="font-family: 'Poppins', sans-serif;">Create Announcement</h2>
                        <p class="text-gray-600">Share important updates with your tenants</p>
                    </div>

                    <!-- Messages -->
                    <?php if ($success_message): ?>
                        <div class="mb-6 rounded-2xl border-l-4 border-emerald-500 bg-gradient-to-r from-emerald-50 to-green-50 text-emerald-700 px-5 py-4 flex items-center gap-3 shadow-md">
                            <i class="fas fa-check-circle text-2xl"></i>
                            <span class="font-medium"><?php echo htmlspecialchars($success_message); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="mb-6 rounded-2xl border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-pink-50 text-red-700 px-5 py-4 flex items-center gap-3 shadow-md">
                            <i class="fas fa-exclamation-circle text-2xl"></i>
                            <span class="font-medium"><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                    <?php endif; ?>
                
                    <form method="post" action="posting.php" class="space-y-5">
                        <div>
                            <label for="title" class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                                <i class="fas fa-heading text-emerald-600"></i>
                                Announcement Title
                            </label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                required 
                                class="form-input w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3.5 text-gray-900 placeholder-gray-400 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all font-medium"
                                placeholder="e.g., Christmas Sale 2025"
                            />
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                                <i class="fas fa-align-left text-blue-600"></i>
                                Description
                            </label>
                            <textarea 
                                id="description" 
                                name="description" 
                                required 
                                rows="4"
                                class="form-input w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3.5 text-gray-900 placeholder-gray-400 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all resize-none font-medium"
                                placeholder="Write the announcement details here..."
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="date" class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                                    <i class="fas fa-calendar text-amber-600"></i>
                                    Event Date
                                </label>
                                <input 
                                    type="date" 
                                    id="date" 
                                    name="date" 
                                    required 
                                    class="form-input w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3.5 text-gray-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all font-medium"
                                />
                            </div>

                            <div>
                                <label for="category" class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                                    <i class="fas fa-tag text-purple-600"></i>
                                    Category
                                </label>
                                <select 
                                    id="category" 
                                    name="category" 
                                    required 
                                    class="form-input w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3.5 text-gray-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all font-medium"
                                >
                                    <option value="" disabled selected>Select category</option>
                                    <option value="upcoming_events">📅 Upcoming Events</option>
                                    <option value="renewal">🔄 Renewal Notice</option>
                                    <option value="general">📢 General Announcement</option>
                                </select>
                            </div>
                        </div>

                        <button 
                            type="submit" 
                            class="w-full inline-flex items-center justify-center gap-3 rounded-xl gradient-primary px-6 py-4 text-white font-bold text-lg shadow-xl hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-emerald-600/30 active:scale-[.98] transition-all mt-6"
                        >
                            <i class="fas fa-paper-plane text-xl"></i>
                            <span>Post & Notify All Tenants</span>
                        </button>
                    </form>

                    <!-- Info Box -->
                    <div class="mt-6 rounded-xl bg-gradient-to-r from-blue-50 to-cyan-50 border-2 border-blue-200 px-5 py-4 text-sm text-blue-700">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-info-circle text-xl mt-0.5"></i>
                            <div>
                                <p class="font-bold mb-1">Important Notice</p>
                                <p>This announcement will be posted to the system and automatically emailed to all registered tenants.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Back Link -->
                    <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                        <a href="admin_dashboard.php" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Dashboard</span>
                        </a>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- Footer -->
        <p class="mt-8 text-center text-sm text-gray-500">
            © <?php echo date('Y'); ?> XentroMall Management System • All rights reserved
        </p>
    </div>
</body>
</html>
