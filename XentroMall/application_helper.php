<?php
/**
 * Helper function to log application status changes to history
 */
function logApplicationStatus($pdo, $tenantDetailId, $userId, $status, $feedback = '', $submissionCount = 1) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO application_status_history 
            (tenant_detail_id, user_id, status, feedback, submission_count, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$tenantDetailId, $userId, $status, $feedback, $submissionCount]);
    } catch (Exception $e) {
        error_log("Error logging application status: " . $e->getMessage());
        return false;
    }
}
