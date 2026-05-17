<?php
session_start();
require 'config.php';
require 'application_helper.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = $_POST['applicationId'] ?? null; // Treat applicationId as user_id from tenant_details
$actionType = $_POST['actionType'] ?? null;
$feedbackText = $_POST['feedbackText'] ?? null;

if (!$userId || !$actionType || !$feedbackText) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Use user_id directly from POST applicationId
    $userId = $userId;

    // Fetch tenant email from tenant_details by user_id
    $stmt = $pdo->prepare("SELECT email FROM tenant_details WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        echo json_encode(['success' => false, 'message' => 'Tenant details not found']);
        exit;
    }

    $tenantEmail = $tenant['email'];

    // Update tenant_details status and admin_feedback by user_id
    $status = strtolower($actionType);
    $stmtUpdate = $pdo->prepare("UPDATE tenant_details SET status = :status, admin_feedback = :feedback WHERE user_id = :user_id");
    $stmtUpdate->execute([
        'status' => $status,
        'feedback' => $feedbackText,
        'user_id' => $userId
    ]);

    // Log status change to history
    $stmtTD = $pdo->prepare("SELECT id, COALESCE(submission_count, 1) as submission_count FROM tenant_details WHERE user_id = :user_id");
    $stmtTD->execute(['user_id' => $userId]);
    $tdRow = $stmtTD->fetch();
    if ($tdRow) {
        logApplicationStatus($pdo, $tdRow['id'], $userId, $status, $feedbackText, $tdRow['submission_count']);
    }

    // Re-implement PHPMailer email sending with debugging for SMTP configuration verification
    if (!filter_var($tenantEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid tenant email: $tenantEmail");
        echo json_encode(['success' => false, 'message' => 'Invalid tenant email']);
        exit;
    }

    $mail = new PHPMailer(true);

    try {
        // SMTP server configuration - replace with your SMTP details
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'jairopogirobiso@gmail.com'; // SMTP username
        $mail->Password = 'wedi stuc gbbz qisl'; // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Encryption
        $mail->Port = 587; // TCP port

        $mail->setFrom('no-reply@xentromall.com', 'XentroMall Admin');
        $mail->addAddress($tenantEmail);

        $mail->Subject = "Your Application has been $actionType";
        $mail->Body = "Dear Tenant,\n\nYour application has been reviewed by the admin and marked as '$actionType'.\n\nFeedback from admin:\n$feedbackText\n\nThank you.";

        $mail->SMTPDebug = 2; // Enable verbose debug output
        $debugOutput = '';
        $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
            $debugOutput .= "Level $level; message: $str\n";
            error_log("PHPMailer debug level $level; message: $str");
        };
        try {
            $mail->send();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log("PHPMailer exception: " . $e->getMessage());
            error_log("PHPMailer error info: " . $mail->ErrorInfo);
            // Return detailed error message and debug output in JSON response for debugging
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage() . ' PHPMailer error info: ' . $mail->ErrorInfo,
                'debug' => $debugOutput
            ]);
        }
    } catch (Exception $e) {
        error_log("General mail setup exception: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Mail setup failed: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
