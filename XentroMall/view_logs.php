<?php
// View recent error logs
echo "<h3>Recent Error Logs</h3>";

// Try to find error log file
$possibleLogFiles = [
    'C:/xampp/apache/logs/error.log',
    'C:/xampp/php/logs/php_error_log',
    'C:/xampp/apache/logs/error.log',
    '/var/log/apache2/error.log',
    '/var/log/php_errors.log'
];

$foundLog = null;
foreach ($possibleLogFiles as $logFile) {
    if (file_exists($logFile)) {
        $foundLog = $logFile;
        break;
    }
}

if ($foundLog) {
    echo "<p><strong>Log File:</strong> " . htmlspecialchars($foundLog) . "</p>";
    
    // Read last 50 lines
    $lines = file($foundLog);
    $recentLines = array_slice($lines, -50);
    
    echo "<h4>Last 50 Log Entries:</h4>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; font-size: 12px; max-height: 400px; overflow-y: auto;'>";
    foreach ($recentLines as $line) {
        // Highlight lines with our error messages
        if (strpos($line, 'Business Type:') !== false || 
            strpos($line, 'FILES Data:') !== false || 
            strpos($line, 'Expected files:') !== false ||
            strpos($line, 'Successfully uploaded:') !== false ||
            strpos($line, 'Failed to move file:') !== false) {
            echo "<span style='background: yellow; color: black;'>" . htmlspecialchars($line) . "</span>";
        } else {
            echo htmlspecialchars($line);
        }
    }
    echo "</pre>";
} else {
    echo "<p>No error log file found. Checking PHP error reporting...</p>";
    
    // Try to get PHP errors from ini_get
    echo "<h4>PHP Error Settings:</h4>";
    echo "<p>display_errors: " . ini_get('display_errors') . "</p>";
    echo "<p>error_reporting: " . ini_get('error_reporting') . "</p>";
    echo "<p>error_log: " . ini_get('error_log') . "</p>";
    
    // Create a simple test to generate an error
    echo "<h4>Test Error Logging:</h4>";
    error_log("Test error message from debug script");
    echo "<p>Test error logged. Check your error log file.</p>";
}

// Also check if there are any recent uploads
echo "<h3>Recent Uploads Check</h3>";
$uploadDir = 'uploads/renewal_requests/';
if (is_dir($uploadDir)) {
    $userDirs = glob($uploadDir . '*', GLOB_ONLYDIR);
    echo "<p><strong>Upload Directory:</strong> " . htmlspecialchars($uploadDir) . "</p>";
    echo "<p><strong>User Directories Found:</strong> " . count($userDirs) . "</p>";
    
    foreach ($userDirs as $userDir) {
        $userId = basename($userDir);
        $files = glob($userDir . '/*');
        echo "<h4>User " . htmlspecialchars($userId) . " Files (" . count($files) . "):</h4>";
        foreach ($files as $file) {
            $fileInfo = pathinfo($file);
            echo "<p style='margin-left: 20px; font-size: 12px;'>";
            echo "<strong>" . htmlspecialchars($fileInfo['filename']) . "</strong> ";
            echo "(" . number_format(filesize($file)) . " bytes) ";
            echo "- " . date('Y-m-d H:i:s', filemtime($file));
            echo "</p>";
        }
    }
} else {
    echo "<p>Upload directory not found: " . htmlspecialchars($uploadDir) . "</p>";
}

echo "<p><a href='unified_renewal_form.php'>Back to form</a></p>";
?>
