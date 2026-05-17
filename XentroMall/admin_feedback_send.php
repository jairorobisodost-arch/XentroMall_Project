<?php
session_start();
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['application_id'] ?? null;
    $status = $_POST['status'] ?? null;
    $feedback = $_POST['remarks'] ?? null;

    if (!$userId || !$status || !$feedback) {
        die('Missing required fields');
    }

    try {
        // Update tenant_details status and admin_feedback by user_id
        $stmtUpdate = $pdo->prepare("UPDATE tenant_details SET status = :status, admin_feedback = :feedback WHERE user_id = :user_id");
        $stmtUpdate->execute([
            'status' => strtolower($status),
            'feedback' => $feedback,
            'user_id' => $userId
        ]);

        // Fetch tenant email
        $stmt = $pdo->prepare("SELECT email FROM tenant_details WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            die('Tenant details not found');
        }

        $tenantEmail = $tenant['email'];

        if (!filter_var($tenantEmail, FILTER_VALIDATE_EMAIL)) {
            die('Invalid tenant email');
        }

        // Send email notification
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'jairopogirobiso@gmail.com';
            $mail->Password = 'wedi stuc gbbz qisl';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('no-reply@xentromall.com', 'XentroMall Admin');
            $mail->addAddress($tenantEmail);

            $mail->Subject = "Your Application has been $status";
            $mail->Body = "Dear Tenant,\n\nYour application has been reviewed by the admin and marked as '$status'.\n\nFeedback from admin:\n$feedback\n\nThank you.";

            $mail->send();
        } catch (Exception $e) {
            error_log("PHPMailer exception: " . $e->getMessage());
            die('Failed to send email: ' . $e->getMessage());
        }

        // Redirect back to admin application review or dashboard
        header('Location: admin_application_review.php');
        exit;
    } catch (Exception $e) {
        die('Server error: ' . $e->getMessage());
    }
} else {
    die('Invalid request method');
}
?>
