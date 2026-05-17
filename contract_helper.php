<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin_settings_helper.php';

/**
 * Ensure tenant_contracts table exists.
 */
function ensureTenantContractsTable(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $initialized = true;

    $sql = "
        CREATE TABLE IF NOT EXISTS tenant_contracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_detail_id INT NOT NULL,
            tenant_user_id INT NOT NULL,
            stall_id INT DEFAULT NULL,
            contract_status ENUM('draft','final') DEFAULT 'draft',
            contract_path VARCHAR(255) NOT NULL,
            contract_html LONGTEXT,
            lease_start_date DATE DEFAULT NULL,
            lease_end_date DATE DEFAULT NULL,
            version INT DEFAULT 1,
            generated_by INT NOT NULL,
            notes TEXT DEFAULT NULL,
            send_email TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_detail_id (tenant_detail_id),
            INDEX idx_tenant_user_id (tenant_user_id),
            INDEX idx_stall_id (stall_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    try {
        $pdo->exec($sql);
    }
    catch (PDOException $e) {
        error_log('Failed to ensure tenant_contracts table: ' . $e->getMessage());
    }
}

/**
 * Get next contract version number for a tenant detail record.
 */
function getNextContractVersion(PDO $pdo, int $tenantDetailId): int
{
    ensureTenantContractsTable($pdo);

    try {
        $stmt = $pdo->prepare("SELECT MAX(version) FROM tenant_contracts WHERE tenant_detail_id = ?");
        $stmt->execute([$tenantDetailId]);
        $maxVersion = (int)$stmt->fetchColumn();
        return $maxVersion + 1;
    }
    catch (PDOException $e) {
        error_log('Failed to determine next contract version: ' . $e->getMessage());
        return 1;
    }
}

/**
 * Retrieve admin signature data as base64.
 *
 * @return array{dataUri: string, path: string}|null
 */
function getAdminSignatureData(): ?array
{
    $signaturePath = getAdminSignaturePath();
    if (!$signaturePath || !file_exists($signaturePath)) {
        return null;
    }

    $content = file_get_contents($signaturePath);
    if ($content === false) {
        return null;
    }

    $mime = mime_content_type($signaturePath) ?: 'image/png';
    $base64 = base64_encode($content);

    return [
        'dataUri' => 'data:' . $mime . ';base64,' . $base64,
        'path' => $signaturePath
    ];
}

/**
 * Build the base contracts directory if it does not exist.
 */
function ensureContractsDirectory(): string
{
    $baseDir = __DIR__ . '/uploads/contracts/';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }
    return $baseDir;
}

/**
 * Create (if needed) and return the tenant-specific contracts directory.
 */
function getTenantContractsDirectory(int $tenantDetailId): string
{
    $baseDir = ensureContractsDirectory();
    $tenantDir = $baseDir . $tenantDetailId . '/';
    if (!is_dir($tenantDir)) {
        mkdir($tenantDir, 0755, true);
    }
    return $tenantDir;
}

?>

