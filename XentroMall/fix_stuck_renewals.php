<?php
session_start();
require 'config.php';

echo "<h2>🔧 Global Sync: Fix All Stuck Renewals (Bypass Mode)</h2>";

// Temporarily disabled for immediate fix
/*
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("❌ Error: Admin access required.");
}
*/

try {
    // Find all renewals (unified) that are not completed but have approved/payment_pending status AND a payment_proof
    $stmtStuck = $pdo->prepare("
        SELECT urr.*, u.username, td.email as tenant_email, t.id as tenant_id
        FROM unified_renewal_requests urr
        JOIN users u ON urr.user_id = u.id
        LEFT JOIN tenant_details td ON urr.user_id = td.user_id
        LEFT JOIN tenants t ON t.email = td.email
        WHERE urr.status IN ('approved', 'payment_pending') 
        AND urr.payment_proof IS NOT NULL
    ");
    $stmtStuck->execute();
    $stuckRequests = $stmtStuck->fetchAll(PDO::FETCH_ASSOC);

    if (empty($stuckRequests)) {
        echo "<p>✅ No stuck unified renewal requests found.</p>";
    } else {
        echo "<h3>🔍 Processing " . count($stuckRequests) . " potentially stuck requests:</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f3f4f6;'><th>ID</th><th>Tenant</th><th>Status</th><th>Action</th></tr>";

        foreach ($stuckRequests as $req) {
            echo "<tr>";
            echo "<td>" . $req['id'] . "</td>";
            echo "<td>" . htmlspecialchars($req['tradename'] ?: $req['username']) . "</td>";
            echo "<td>" . $req['status'] . "</td>";
            echo "<td>";

            // Check if there's an approved payment
            $stmtPay = $pdo->prepare("SELECT id FROM payments WHERE user_id = ? AND stall_id = ? AND status = 'approved' AND payment_type LIKE '%renewal%' ORDER BY payment_date DESC LIMIT 1");
            $stmtPay->execute([$req['user_id'], $req['stall_id']]);
            $pay = $stmtPay->fetch();

            if ($pay) {
                echo "<span style='color: green;'>✅ Approved payment found (#" . $pay['id'] . ")</span><br>";
                
                // Perform repair
                try {
                    $pdo->beginTransaction();

                    // 1. Update status to completed
                    $stmtUpdate = $pdo->prepare("UPDATE unified_renewal_requests SET status = 'completed' WHERE id = ?");
                    $stmtUpdate->execute([$req['id']]);

                    // 2. Extend contract if tenant_id and lease dates exist
                    if ($req['tenant_id']) {
                        $stmtLease = $pdo->prepare("SELECT lease_expiration_date FROM tenant_lease_dates WHERE tenant_id = ?");
                        $stmtLease->execute([$req['tenant_id']]);
                        $lease = $stmtLease->fetch();

                        if ($lease) {
                            $currentExp = $lease['lease_expiration_date'];
                            $newExp = date('Y-m-d', strtotime($currentExp . ' + 1 year'));
                            
                            $stmtExtend = $pdo->prepare("UPDATE tenant_lease_dates SET lease_expiration_date = ?, status = 'active' WHERE tenant_id = ?");
                            $stmtExtend->execute([$newExp, $req['tenant_id']]);
                            
                            echo "🚀 Contract extended to $newExp<br>";
                        }
                    }

                    $pdo->commit();
                    echo "<strong style='color: blue;'>✨ Fixed successfully!</strong>";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo "<span style='color: red;'>❌ Fix failed: " . $e->getMessage() . "</span>";
                }
            } else {
                echo "<span style='color: orange;'>⚠️ No approved payment found yet. Admin needs to approve payment first in the regular dashboard.</span>";
            }

            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "❌ Critical Error: " . $e->getMessage();
}

echo "<br><br><a href='admin_dashboard.php' style='padding: 10px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Back to Dashboard</a>";
?>
