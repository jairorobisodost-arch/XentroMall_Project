<?php
require 'config.php';
$stmt = $pdo->prepare('DESCRIBE unified_renewal_requests');
$stmt->execute();
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('schema_dump.txt', print_r($cols, true));
echo "Schema dumped to schema_dump.txt";
?>
