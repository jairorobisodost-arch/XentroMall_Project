<?php
require 'config.php';
$stmt = $pdo->prepare('SELECT id, tradename, status, payment_proof FROM unified_renewal_requests WHERE id IN (13, 14)');
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('status_check.txt', print_r($results, true));
echo "Status checked";
?>
