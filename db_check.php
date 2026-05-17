<?php
require 'config.php';
$stmt = $pdo->prepare("SELECT id, tradename, user_id, documents, submission_count FROM tenant_details WHERE documents LIKE '%uploads/0/%' OR user_id = 0;");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($results);
?>
