<?php
require 'config.php';
$stmt = $pdo->prepare("SELECT td.id as tenant_detail_id, td.user_id, td.email, s.id as stall_id, s.stall_number FROM tenant_details td LEFT JOIN stalls s ON s.id = td.stall_id WHERE td.status = 'approved' AND td.email = 'kihgtb47@gmail.com'");
$stmt->execute();
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
