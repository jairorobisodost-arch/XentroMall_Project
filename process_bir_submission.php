<?php
session_start();
require 'config.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantId = $_POST['tenant_id'] ?? '';
    $birFormType = $_POST['bir_form_type'] ?? '';
    $birNumber = $_POST['bir_number'] ?? '';
    $submissionDate = $_POST['submission_date'] ?? '';
    $status = 'pending';

    // Handle file upload
    $targetDir = "uploads/bir_documents/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = '';
    if (isset($_FILES['bir_document']) && $_FILES['bir_document']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['bir_document']['tmp_name'];
        $fileName = time() . '_' . basename($_FILES['bir_document']['name']);
        $targetPath = $targetDir . $fileName;
        
        // Move the uploaded file
        if (!move_uploaded_file($fileTmpPath, $targetPath)) {
            $message = "Error uploading file.";
            header("Location: tenant_dashboard.php?bir_message=" . urlencode($message) . "&success=false");
            exit();
        }
    } else {
        $message = "Please upload a BIR document.";
        header("Location: tenant_dashboard.php?bir_message=" . urlencode($message) . "&success=false");
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO bir_submissions 
            (tenant_id, bir_form_type, bir_number, submission_date, document_path, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$tenantId, $birFormType, $birNumber, $submissionDate, $fileName, $status]);
        
        $message = "BIR document submitted successfully for verification.";
        $success = true;
    } catch (PDOException $e) {
        // Delete the uploaded file if there was a database error
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
        $message = "Database error: " . $e->getMessage();
    }
}

header("Location: tenant_dashboard.php?bir_message=" . urlencode($message) . "&success=" . ($success ? 'true' : 'false'));
exit();
?>
