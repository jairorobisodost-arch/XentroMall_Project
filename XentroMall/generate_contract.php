<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'config.php';
require __DIR__ . '/vendor/autoload.php';
require_once 'admin_settings_helper.php';
require_once 'contract_helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$adminUserId = (int) $_SESSION['user_id'];
$tenantDetailId = isset($_GET['tenant_detail_id']) ? (int) $_GET['tenant_detail_id'] : 0;

ensureTenantContractsTable($pdo);
initializeAdminSettings();

if (!$tenantDetailId) {
    die('DEBUG: tenant_detail_id is 0 or missing. Raw GET value: ' . htmlspecialchars($_GET['tenant_detail_id'] ?? 'NOT SET') . '. Full URL: ' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''));
}

// Fetch tenant detail, stall, user, lease info
$tenantQuery = $pdo->prepare("
    SELECT
        td.*,
        u.email AS tenant_email,
        u.username AS tenant_username,
        td.mobile AS tenant_mobile,
        s.stall_number,
        s.description AS stall_description,
        s.monthly_rate,
        t.id AS tenant_entry_id,
        tld.lease_start_date,
        tld.lease_expiration_date
    FROM tenant_details td
    LEFT JOIN users u ON u.id = td.user_id
    LEFT JOIN stalls s ON s.id = td.stall_id
    LEFT JOIN tenants t ON t.email = td.email
    LEFT JOIN tenant_lease_dates tld ON tld.tenant_id = t.id
    WHERE td.id = ?
");
$tenantQuery->execute([$tenantDetailId]);
$tenant = $tenantQuery->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    die('DEBUG: Tenant not found in DB for tenant_detail_id=' . $tenantDetailId . '. Check if this ID exists in tenant_details table.');
}

$adminStmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$adminStmt->execute([$adminUserId]);
$adminUser = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $adminUser['username'] ?? 'Mall Administrator';
$adminEmail = $adminUser['email'] ?? getAdminEmail();
$adminTitle = getAdminTitle();
$adminSignature = getAdminSignatureData();

$defaultLeaseStart = $tenant['lease_start_date'] ?: date('Y-m-d');
$defaultLeaseEnd = $tenant['lease_expiration_date'] ?: date('Y-m-d', strtotime('+6 months', strtotime($defaultLeaseStart)));

$formErrors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_contract'])) {
    $status = $_POST['contract_status'] ?? 'draft';
    $leaseStart = $_POST['lease_start_date'] ?? $defaultLeaseStart;
    $leaseEnd = $_POST['lease_end_date'] ?? $defaultLeaseEnd;
    $lesseeName = trim($_POST['lessee_name'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $tenantEmail = trim($_POST['tenant_email'] ?? ($tenant['tenant_email'] ?? ''));
    $tenantContact = trim($_POST['tenant_contact'] ?? ($tenant['mobile'] ?? ''));
    $stallSize = trim($_POST['stall_size'] ?? '');
    $monthlyRent = (float) ($_POST['monthly_rent'] ?? $tenant['monthly_rate'] ?? 0);
    $paymentDue = trim($_POST['payment_due'] ?? '5th of the month');
    $paymentMode = trim($_POST['payment_mode'] ?? 'Bank Deposit / GCash');
    $latePenalty = trim($_POST['late_penalty'] ?? '5% per day of delay');
    $advancePayment = trim($_POST['advance_payment'] ?? '1 month advance');
    $securityDeposit = trim($_POST['security_deposit'] ?? (getSecurityDepositMonths() . ' months security deposit'));
    $utilitiesNote = trim($_POST['utilities_note'] ?? 'The tenant shall shoulder electricity and water consumption.');
    $terminationNote = trim($_POST['termination_note'] ?? 'Either party may terminate the contract by giving 30 days written notice.');
    $liabilityNote = trim($_POST['liability_note'] ?? 'The tenant shall be liable for any damages to mall property caused by negligence.');
    $rulesRaw = $_POST['rules'] ?? '';
    $sendEmail = isset($_POST['send_email']) && $_POST['send_email'] === '1';
    $notes = trim($_POST['notes'] ?? '');

    if ($status === 'final' && !$adminSignature) {
        $formErrors[] = 'Please upload an admin e-signature before finalizing the contract.';
    }

    if (empty($lesseeName)) {
        $formErrors[] = 'Lessee name is required.';
    }

    if (empty($companyName)) {
        $formErrors[] = 'Company name or trade name is required.';
    }

    if (empty($tenantEmail) || !filter_var($tenantEmail, FILTER_VALIDATE_EMAIL)) {
        $formErrors[] = 'A valid tenant email address is required.';
    }

    if (empty($leaseStart) || empty($leaseEnd)) {
        $formErrors[] = 'Please provide lease start and end dates.';
    } elseif (strtotime($leaseStart) > strtotime($leaseEnd)) {
        $formErrors[] = 'Lease end date must be later than lease start date.';
    }

    if ($monthlyRent <= 0) {
        $formErrors[] = 'Monthly rent must be greater than zero.';
    }

    $rules = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rulesRaw)));

    if (empty($formErrors)) {
        $leaseStartFormatted = date('F d, Y', strtotime($leaseStart));
        $leaseEndFormatted = date('F d, Y', strtotime($leaseEnd));
        $leaseTermText = '';
        try {
            $startDate = new DateTime($leaseStart);
            $endDate = new DateTime($leaseEnd);
            $diff = $startDate->diff($endDate);
            $months = ($diff->y * 12) + $diff->m;
            $leaseTermText = $months > 0 ? "{$months} month(s)" : $diff->format('%a days');
        } catch (Exception $e) {
            $leaseTermText = '';
        }

        $contractNumber = 'XM-' . strtoupper(dechex(time())) . '-' . $tenantDetailId;
        $generatedDate = date('F d, Y g:i A');
        $monthlyRentFormatted = number_format($monthlyRent, 2);

        $templateData = [
            'contract_number' => $contractNumber,
            'generated_date' => $generatedDate,
            'lessor_name' => 'Xentro Mall Management',
            'mall_address' => getMallAddress(),
            'lessee_name' => $lesseeName,
            'company_name' => $companyName,
            'tenant_email' => $tenantEmail,
            'tenant_contact' => $tenantContact,
            'stall_number' => $tenant['stall_number'] ?? 'N/A',
            'stall_location' => $tenant['stall_description'] ?? 'Within Xentro Mall Premises',
            'stall_size' => $stallSize !== '' ? $stallSize : 'To be confirmed',
            'monthly_rent' => $monthlyRentFormatted,
            'lease_start' => $leaseStartFormatted,
            'lease_end' => $leaseEndFormatted,
            'lease_term_text' => $leaseTermText,
            'payment_due' => $paymentDue,
            'payment_mode' => $paymentMode,
            'late_penalty' => $latePenalty,
            'advance_payment' => $advancePayment,
            'security_deposit' => $securityDeposit,
            'utilities_note' => $utilitiesNote,
            'termination_note' => $terminationNote,
            'liability_note' => $liabilityNote,
            'rules' => $rules,
            'admin_signature_data' => $adminSignature['dataUri'] ?? '',
            'admin_name' => $adminName,
            'admin_title' => $adminTitle
        ];

        ob_start();
        $data = $templateData; // Template expects variable named $data
        include __DIR__ . '/templates/contract_template.php';
        $contractHtml = ob_get_clean();

        $tenantContractsDir = getTenantContractsDirectory($tenantDetailId);
        $fileName = 'contract_' . date('Ymd_His') . '_' . $status . '.html';
        $fullPath = $tenantContractsDir . $fileName;
        $publicPath = 'uploads/contracts/' . $tenantDetailId . '/' . $fileName;

        if (file_put_contents($fullPath, $contractHtml) === false) {
            $formErrors[] = 'Failed to write contract file to disk.';
        } else {
            $version = getNextContractVersion($pdo, $tenantDetailId);
            $insert = $pdo->prepare("
                INSERT INTO tenant_contracts (
                    tenant_detail_id,
                    tenant_user_id,
                    stall_id,
                    contract_status,
                    contract_path,
                    contract_html,
                    lease_start_date,
                    lease_end_date,
                    version,
                    generated_by,
                    notes,
                    send_email
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            try {
                $insertSuccess = $insert->execute([
                    $tenantDetailId,
                    $tenant['user_id'],
                    $tenant['stall_id'],
                    $status,
                    $publicPath,
                    $contractHtml,
                    $leaseStart,
                    $leaseEnd,
                    $version,
                    $adminUserId,
                    $notes,
                    $sendEmail ? 1 : 0
                ]);

                if (!$insertSuccess) {
                    $formErrors[] = 'Failed to save contract to database.';
                } else {
                    $contractId = $pdo->lastInsertId();

                    if ($status === 'final' && $sendEmail) {
                        try {
                            $mail = new PHPMailer(true);
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'mallxentro5@gmail.com';
                            $mail->Password = 'iwld cjlr kmcy bxab';
                            $mail->SMTPSecure = 'tls';
                            $mail->Port = 587;
                            $mail->SMTPOptions = [
                                'ssl' => [
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true
                                ]
                            ];

                            $mail->setFrom($adminEmail, 'XentroMall Admin');
                            $mail->addAddress($tenantEmail, $lesseeName);
                            $mail->isHTML(true);

                            $viewLink = BASE_URL . 'view_contract.php?id=' . $contractId;

                            $mail->Subject = 'New Stall Lease Agreement';
                            $mail->Body = "
                                <p>Good day {$lesseeName},</p>
                                <p>Your latest stall lease agreement with XentroMall is now available for review. Please click the link below to view the document:</p>
                                <p><a href=\"{$viewLink}\">View Lease Agreement</a></p>
                                <p>Kindly review and coordinate with mall management for signing. This is an automated notification.</p>
                                <p>Best regards,<br>XentroMall Management Team</p>
                            ";

                            $mail->send();
                        } catch (MailException $mailException) {
                            error_log('Contract email failed: ' . $mailException->getMessage());
                        }
                    }

                    $_SESSION['success_message'] = 'Contract generated successfully (' . strtoupper($status) . ').';
                    header('Location: view_contract.php?id=' . $contractId);
                    exit;
                }
            } catch (PDOException $e) {
                $formErrors[] = 'Database error: ' . $e->getMessage();
                error_log('Contract insert failed: ' . $e->getMessage());
            }
        }
    }
}

// Prefill form values
$prefill = [
    'lessee_name' => $_POST['lessee_name'] ?? ($tenant['contact_person'] ?: ($tenant['tenant_representative'] ?? $tenant['tradename'])),
    'company_name' => $_POST['company_name'] ?? ($tenant['company_name'] ?? $tenant['tradename']),
    'tenant_email' => $_POST['tenant_email'] ?? ($tenant['tenant_email'] ?? ''),
    'tenant_contact' => $_POST['tenant_contact'] ?? ($tenant['mobile'] ?? ''),
    'stall_size' => $_POST['stall_size'] ?? '',
    'lease_start_date' => $_POST['lease_start_date'] ?? $defaultLeaseStart,
    'lease_end_date' => $_POST['lease_end_date'] ?? $defaultLeaseEnd,
    'monthly_rent' => $_POST['monthly_rent'] ?? ($tenant['monthly_rate'] ?? ''),
    'payment_due' => $_POST['payment_due'] ?? '5th of the month',
    'payment_mode' => $_POST['payment_mode'] ?? 'Bank Deposit / GCash',
    'late_penalty' => $_POST['late_penalty'] ?? '5% per day of delay',
    'advance_payment' => $_POST['advance_payment'] ?? '1 month advance',
    'security_deposit' => $_POST['security_deposit'] ?? (getSecurityDepositMonths() . ' months security deposit'),
    'utilities_note' => $_POST['utilities_note'] ?? 'The tenant shall shoulder electricity and water consumption.',
    'termination_note' => $_POST['termination_note'] ?? 'Either party may terminate the contract by giving a 30-day written notice.',
    'liability_note' => $_POST['liability_note'] ?? 'The tenant shall be liable for any damages to mall property beyond normal wear and tear.',
    'rules' => $_POST['rules'] ?? "Bawal magbago ng layout ng stall nang walang written approval mula sa mall management.\nPanatilihing malinis at maayos ang stall sa lahat ng oras.\nSundin ang mall operating hours at security protocols.\nBawal magbenta ng ipinagbabawal o mapanganib na produkto.",
    'notes' => $_POST['notes'] ?? '',
    'contract_status' => $_POST['contract_status'] ?? 'draft',
    'send_email' => isset($_POST['send_email']) ? (bool) $_POST['send_email'] : true
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Stall Rental Contract</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-slate-100 min-h-screen p-6">
    <div class="max-w-5xl mx-auto">
        <div class="flex items-center gap-4 mb-6">
            <a href="admin_dashboard.php" class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow hover:bg-slate-50 transition">
                <i class="fas fa-arrow-left text-slate-700"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Generate Stall Lease Agreement</h1>
                <p class="text-slate-600">Prepare and finalize lease agreements with e-signature for tenants.</p>
            </div>
        </div>

        <?php if (!empty($formErrors)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl">
                <h2 class="font-semibold mb-2 flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> Please fix the following:</h2>
                <ul class="list-disc list-inside text-sm">
                    <?php foreach ($formErrors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$adminSignature): ?>
            <div class="mb-6 bg-amber-50 border border-amber-300 text-amber-800 px-5 py-4 rounded-xl">
                <strong>Reminder:</strong> No admin e-signature found yet. Upload one in <a href="admin_settings.php" class="underline font-semibold">Admin Settings &raquo; E-Signature</a>.
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-sky-500 to-emerald-500 text-white px-8 py-6 flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-widest opacity-80">Tenant</p>
                    <h2 class="text-2xl font-bold"><?= htmlspecialchars($tenant['tradename'] ?? $tenant['company_name'] ?? 'Tenant'); ?></h2>
                    <p class="text-sm opacity-90"><?= htmlspecialchars($tenant['company_name'] ?? ''); ?></p>
                </div>
                <div class="text-right text-sm opacity-90">
                    <p>Stall: <strong><?= htmlspecialchars($tenant['stall_number'] ?? 'N/A'); ?></strong></p>
                    <p>Location: <?= htmlspecialchars($tenant['stall_description'] ?? 'Not set'); ?></p>
                </div>
            </div>

            <form method="post" class="p-8 space-y-8">
                <input type="hidden" name="generate_contract" value="1">

                <section>
                    <h3 class="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <i class="fas fa-user-circle text-emerald-500"></i>
                        Tenant Profile
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Lessee / Contact Person</label>
                            <input type="text" name="lessee_name" required value="<?= htmlspecialchars($prefill['lessee_name']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 transition">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Company / Trade Name</label>
                            <input type="text" name="company_name" required value="<?= htmlspecialchars($prefill['company_name']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 transition">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Tenant Email</label>
                            <input type="email" name="tenant_email" required value="<?= htmlspecialchars($prefill['tenant_email']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 transition">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Contact Number</label>
                            <input type="text" name="tenant_contact" value="<?= htmlspecialchars($prefill['tenant_contact']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 transition">
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <i class="fas fa-store text-sky-500"></i>
                        Stall & Lease Details
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Stall Size / Area</label>
                            <input type="text" name="stall_size" required placeholder="e.g. 5m x 5m (25 sqm)" value="<?= htmlspecialchars($prefill['stall_size']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Monthly Rent (₱)</label>
                            <input type="number" step="0.01" min="0" name="monthly_rent" required value="<?= htmlspecialchars((string) $prefill['monthly_rent']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Lease Start</label>
                            <input type="date" name="lease_start_date" required value="<?= htmlspecialchars($prefill['lease_start_date']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Lease End</label>
                            <input type="date" name="lease_end_date" required value="<?= htmlspecialchars($prefill['lease_end_date']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition">
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <i class="fas fa-file-invoice-dollar text-indigo-500"></i>
                        Payment Terms
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Payment Due Date</label>
                            <input type="text" name="payment_due" value="<?= htmlspecialchars($prefill['payment_due']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Payment Mode</label>
                            <input type="text" name="payment_mode" value="<?= htmlspecialchars($prefill['payment_mode']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Late Payment Penalty</label>
                            <input type="text" name="late_penalty" value="<?= htmlspecialchars($prefill['late_penalty']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Advance Payment</label>
                            <input type="text" name="advance_payment" value="<?= htmlspecialchars($prefill['advance_payment']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Security Deposit</label>
                            <input type="text" name="security_deposit" value="<?= htmlspecialchars($prefill['security_deposit']); ?>" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <i class="fas fa-list-check text-purple-500"></i>
                        Rules & Notes
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Mall Rules & Regulations (one per line)</label>
                            <textarea name="rules" rows="4" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"><?= htmlspecialchars($prefill['rules']); ?></textarea>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Utilities & Maintenance Note</label>
                            <textarea name="utilities_note" rows="3" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"><?= htmlspecialchars($prefill['utilities_note']); ?></textarea>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Termination Note</label>
                            <textarea name="termination_note" rows="3" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"><?= htmlspecialchars($prefill['termination_note']); ?></textarea>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Liability Note</label>
                            <textarea name="liability_note" rows="3" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"><?= htmlspecialchars($prefill['liability_note']); ?></textarea>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Internal Notes (optional)</label>
                            <textarea name="notes" rows="3" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-slate-400 focus:border-slate-400 transition" placeholder="Internal notes for admin use only"><?= htmlspecialchars($prefill['notes']); ?></textarea>
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <i class="fas fa-shield-check text-emerald-500"></i>
                        Finalization
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-center">
                        <div>
                            <label class="text-sm font-semibold text-slate-600 mb-1 block">Contract Status</label>
                            <select name="contract_status" class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 transition">
                                <option value="draft" <?= $prefill['contract_status'] === 'draft' ? 'selected' : ''; ?>>Draft (For review)</option>
                                <option value="final" <?= $prefill['contract_status'] === 'final' ? 'selected' : ''; ?>>Final (With signature)</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="checkbox" id="send_email" name="send_email" value="1" class="w-5 h-5 border-slate-300" <?= $prefill['send_email'] ? 'checked' : ''; ?>>
                            <label for="send_email" class="text-sm text-slate-600">Email tenant when contract is finalized</label>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Draft contracts will be saved for portal viewing but will not include the admin signature.</p>
                </section>

                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 pt-6 border-t border-slate-200">
                    <div class="text-xs text-slate-500">
                        Generated by: <?= htmlspecialchars($adminName); ?> • <?= htmlspecialchars($adminEmail); ?>
                    </div>
                    <div class="flex gap-3">
                        <a href="admin_dashboard.php" class="px-5 py-3 rounded-xl border-2 border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-sky-500 text-white font-semibold shadow-lg hover:shadow-xl transition flex items-center gap-2">
                            <i class="fas fa-file-signature"></i>
                            Generate Contract
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
