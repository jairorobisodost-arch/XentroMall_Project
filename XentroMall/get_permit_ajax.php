<?php
session_start();
require 'config.php';

// Simple approach - just check if we have any GET parameter
if ($_GET && count($_GET) > 0) {
    // Get the first parameter value (whatever it is)
    $permitNo = reset($_GET);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM work_permits WHERE permit_no = ?");
        $stmt->execute([$permitNo]);
        $permit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($permit) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'permit' => $permit]);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Permit not found: ' . $permitNo]);
            exit;
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// If no parameters, return error
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'No parameters received']);
?>
