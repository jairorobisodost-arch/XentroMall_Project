<?php
require 'config.php';

echo "<h2>🔄 XentroMall Payment Reset Tool</h2>";

try {
    $pdo->beginTransaction();

    // 1. Identify common issues for user_id = 4 (based on recent report)
    $stmtFind = $pdo->prepare("SELECT id, tradename, payment_proof, status FROM unified_renewal_requests WHERE user_id = 4 ORDER BY id DESC LIMIT 1");
    $stmtFind->execute();
    $request = $stmtFind->fetch();

    if ($request) {
        echo "<p>Found Request ID: <b>{$request['id']}</b> for <b>{$request['tradename']}</b></p>";
        echo "<p>Current Status: <b>{$request['status']}</b></p>";
        echo "<p>Current Proof Path: <i>" . ($request['payment_proof'] ?: 'NONE') . "</i></p>";

        // Reset the proof and make sure status allows Pay Now
        $stmtReset = $pdo->prepare("UPDATE unified_renewal_requests SET payment_proof = NULL, status = 'approved' WHERE id = ?");
        $stmtReset->execute([$request['id']]);
        echo "<p style='color: green;'>✅ Record in unified_renewal_requests has been reset (Proof cleared, Status set to 'approved').</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ No recent renewal request found for user_id 4.</p>";
    }

    // 2. Clean up any "failed" payments for user_id 4 with amount 45000
    $stmtDel = $pdo->prepare("DELETE FROM payments WHERE user_id = 4 AND amount = 45000 AND status = 'pending'");
    $stmtDel->execute();
    $rows = $stmtDel->rowCount();
    echo "<p>Deleted <b>{$rows}</b> potentially broken payment records from 'payments' table.</p>";

    $pdo->commit();
    echo "<h3>🎉 Reset Complete!</h3>";
    echo "<p>Maaari mo nang i-refresh ang iyong <b>Tenant Dashboard</b>. Dapat lumabas na ulit ang <b>'Pay Now'</b> button.</p>";
    echo "<p><a href='tenant_dashboard.php?page=renewal'>Bumalik sa Dashboard</a></p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
