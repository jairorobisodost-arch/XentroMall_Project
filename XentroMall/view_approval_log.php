<?php
echo "<h1>📝 Approval Log Viewer</h1>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #f5f5f5; }
    .log { background: #1f2937; color: #10b981; padding: 20px; border-radius: 8px; font-family: monospace; white-space: pre-wrap; }
    .success { color: #10b981; }
    .error { color: #ef4444; }
    .warning { color: #f59e0b; }
    .info { background: #dbeafe; color: #1e40af; padding: 15px; border-radius: 8px; margin: 10px 0; }
</style>";

$logFile = __DIR__ . '/approval_log.txt';

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if ($content) {
        echo "<div class='info'>✅ Log file found with " . substr_count($content, "\n") . " entries</div>";
        echo "<div class='log'>" . htmlspecialchars($content) . "</div>";
        
        echo "<hr>";
        echo "<h3>Actions:</h3>";
        echo "<a href='?clear=1' style='background: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; display: inline-block; margin: 10px 0;'>Clear Log</a>";
        
        if (isset($_GET['clear'])) {
            file_put_contents($logFile, '');
            echo "<script>alert('Log cleared!'); window.location='view_approval_log.php';</script>";
        }
    } else {
        echo "<div class='info'>⚠️ Log file is empty. No approvals have been processed yet.</div>";
    }
} else {
    echo "<div class='info'>❌ Log file not found. It will be created when you approve an application.</div>";
}

echo "<hr>";
echo "<h3>📋 Instructions:</h3>";
echo "<ol>";
echo "<li>Go to Admin Dashboard → Applications</li>";
echo "<li>Click 'Approve' or 'Decline' on any application</li>";
echo "<li>Refresh this page to see the log</li>";
echo "</ol>";
?>
