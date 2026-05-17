<?php
require 'config.php';
try {
    $stmt = $pdo->query("DESCRIBE tenant_expenses");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($columns);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
