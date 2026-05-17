<?php
require 'config.php';
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, documents FROM tenant_details GROUP BY documents ORDER BY cnt DESC LIMIT 10;");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($results);

$stmt = $pdo->prepare("SELECT id, user_id, documents FROM tenant_details WHERE documents = 'uploads/0/' OR documents LIKE '%/0/%';");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($results);
?>
