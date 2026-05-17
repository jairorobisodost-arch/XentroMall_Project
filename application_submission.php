<?php
session_start();
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];

    // Move tenant info fetch to top for descriptive filenames
    $stmtTenant = $pdo->prepare("SELECT td.tradename, td.company_name, td.business_type, td.email, td.contact_person, td.mobile, td.status, td.admin_feedback, u.email as user_email, u.username 
                                  FROM tenant_details td 
                                  LEFT JOIN users u ON td.user_id = u.id 
                                  WHERE td.user_id = ?");
    $stmtTenant->execute([$userId]);
    $tenant = $stmtTenant->fetch();

    $tradename = $tenant['tradename'] ?? $tenant['username'] ?? 'Unknown';
    // Sanitize tradename for filesystem
    $safeTradename = preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '_', $tradename));
    $dateStr = date('Y_m_d');

    $uploadDir = 'uploads/applications/' . $userId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $files = [
        'letter_of_intent',
        'business_profile',
        'business_registration',
        'valid_id',
        'bir_registration',
        'extended_bir_registration',
        'financial_statement'
    ];

    $uploadedFiles = [];

    foreach ($files as $file) {
        if (isset($_FILES[$file]) && $_FILES[$file]['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES[$file]['tmp_name'];
            $ext = pathinfo($_FILES[$file]['name'], PATHINFO_EXTENSION);
            $docLabel = str_replace(' ', '', ucwords(str_replace('_', ' ', $file)));
            $fileName = $docLabel . "_" . $safeTradename . "_" . $dateStr . "_" . substr(uniqid(), -5) . "." . $ext;
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($tmpName, $targetFile)) {
                $uploadedFiles[$file] = $targetFile;
            } else {
                $uploadedFiles[$file] = null;
            }
        } else {
            $uploadedFiles[$file] = null;
        }
    }

    error_log("Uploaded files: " . print_r($uploadedFiles, true));

    if ($uploadedFiles['extended_bir_registration'] === null) {
        error_log("Warning: extended_bir_registration file upload failed or missing.");
    }

    // Check if this is a resubmission
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM application_submissions WHERE user_id = ?");
    $stmtCheck->execute([$userId]);
    $submissionCount = $stmtCheck->fetchColumn();
    $isResubmission = $submissionCount > 0;

    try {
        $stmt = $pdo->prepare("INSERT INTO application_submissions (user_id, letter_of_intent, business_profile, business_registration, valid_id, bir_registration, extended_bir_registration, financial_statement) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $uploadedFiles['letter_of_intent'],
            $uploadedFiles['business_profile'],
            $uploadedFiles['business_registration'],
            $uploadedFiles['valid_id'],
            $uploadedFiles['bir_registration'],
            $uploadedFiles['extended_bir_registration'],
            $uploadedFiles['financial_statement']
        ]);

        $application_id = $pdo->lastInsertId();

        $application_id = $pdo->lastInsertId();

        $company_name = $tenant['company_name'] ?? 'N/A';
        $business_type = $tenant['business_type'] ?? 'N/A';
        $tenant_email = $tenant['email'] ?? $tenant['user_email'] ?? 'N/A';
        $contact_person = $tenant['contact_person'] ?? 'N/A';
        $mobile = $tenant['mobile'] ?? 'N/A';
        $previous_status = $tenant['status'] ?? 'pending';
        $admin_feedback = $tenant['admin_feedback'] ?? '';

        // Count uploaded documents
        $uploadedCount = count(array_filter($uploadedFiles));

        // Send email notification to admin
        $submissionType = $isResubmission ? 'Resubmitted' : 'New';
        error_log("=== SENDING ADMIN NOTIFICATION === $submissionType Application Submission");
        error_log("Tenant: $tradename, Email: $tenant_email, Application ID: $application_id");
        if ($isResubmission) {
            error_log("Previous Status: $previous_status" . (!empty($admin_feedback) ? ", Feedback: $admin_feedback" : ""));
        }

        $adminEmail = 'mallxentro5@gmail.com';
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function ($str, $level) {
                error_log("SMTP Debug [$level]: $str");
            };

            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'mallxentro5@gmail.com';
            $mail->Password = 'iwld cjlr kmcy bxab';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->Timeout = 30;

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Recipients
            $mail->setFrom('mallxentro5@gmail.com', 'XentroMall System');
            $mail->addAddress($adminEmail, 'XentroMall Admin');

            // Content
            $mail->isHTML(true);
            $badgeColor = $isResubmission ? '#f59e0b' : '#3b82f6';
            $badgeText = $isResubmission ? '🔄 Resubmitted' : '🆕 New';
            $headerEmoji = $isResubmission ? '🔄' : '📝';

            $mail->Subject = "$headerEmoji $submissionType Application Documents - XentroMall";
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                    .header { background: linear-gradient(135deg, $badgeColor 0%, " . ($isResubmission ? '#d97706' : '#2563eb') . " 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                    .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                    .header p { margin: 10px 0 0 0; font-size: 14px; opacity: 0.9; }
                    .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                    .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid $badgeColor; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                    .info-box h3 { margin-top: 0; color: $badgeColor; font-size: 18px; }
                    .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
                    .info-row:last-child { border-bottom: none; }
                    .info-label { font-weight: bold; color: #374151; width: 180px; }
                    .info-value { color: #1f2937; flex: 1; }
                    .badge { display: inline-block; background: $badgeColor; color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; }
                    .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                    .action-button { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                    .doc-list { background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 15px 0; }
                    .doc-item { padding: 8px 0; color: #1f2937; }
                    .doc-item::before { content: '✓ '; color: #10b981; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>$headerEmoji $submissionType Application Documents</h1>
                        <p>XentroMall Management System</p>
                    </div>
                    <div class='content'>
                        </p>" .
                ($isResubmission && $previous_status === 'declined' && !empty($admin_feedback) ? "
                        <div style='background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 8px;'>
                            <p style='margin: 0; color: #92400e; font-size: 14px;'>
                                <strong>⚠️ Reason for Previous Decline:</strong><br>
                                <em style='color: #78350f;'>\"$admin_feedback\"</em>
                            </p>
                        </div>" : "") . "
                        
                        <div class='info-box'>
                            <h3>📋 Application Details</h3>
                            <div class='info-row'>
                                <div class='info-label'>Application ID:</div>
                                <div class='info-value'>#$application_id</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Status:</div>
                                <div class='info-value'><span class='badge'>$badgeText</span></div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Documents Uploaded:</div>
                                <div class='info-value'><strong>$uploadedCount of 7</strong></div>
                            </div>" .
                ($isResubmission ? "
                            <div class='info-row'>
                                <div class='info-label'>Previous Status:</div>
                                <div class='info-value'><strong style='color: " . ($previous_status === 'declined' ? '#ef4444' : '#f59e0b') . ";'>" . strtoupper($previous_status) . "</strong></div>
                            </div>" : "") .
                ($isResubmission && !empty($admin_feedback) ? "
                            <div class='info-row'>
                                <div class='info-label'>Previous Feedback:</div>
                                <div class='info-value' style='font-style: italic; color: #6b7280;'>\"$admin_feedback\"</div>
                            </div>" : "") . "
                        </div>
                        
                        <div class='info-box'>
                            <h3>🏢 Tenant Information</h3>
                            <div class='info-row'>
                                <div class='info-label'>Trade Name:</div>
                                <div class='info-value'><strong>$tradename</strong></div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Company Name:</div>
                                <div class='info-value'>$company_name</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Business Type:</div>
                                <div class='info-value'>$business_type</div>
                            </div>
                        </div>
                        
                        <div class='info-box'>
                            <h3>👤 Contact Information</h3>
                            <div class='info-row'>
                                <div class='info-label'>Contact Person:</div>
                                <div class='info-value'>$contact_person</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Email:</div>
                                <div class='info-value'>$tenant_email</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Mobile:</div>
                                <div class='info-value'>$mobile</div>
                            </div>
                        </div>
                        
                        <div class='info-box'>
                            <h3>📄 Submitted Documents</h3>
                            <div class='doc-list'>" .
                (isset($uploadedFiles['letter_of_intent']) && $uploadedFiles['letter_of_intent'] ? "<div class='doc-item'>Letter of Intent</div>" : "") .
                (isset($uploadedFiles['business_profile']) && $uploadedFiles['business_profile'] ? "<div class='doc-item'>Business Profile</div>" : "") .
                (isset($uploadedFiles['business_registration']) && $uploadedFiles['business_registration'] ? "<div class='doc-item'>Business Registration</div>" : "") .
                (isset($uploadedFiles['valid_id']) && $uploadedFiles['valid_id'] ? "<div class='doc-item'>Valid ID</div>" : "") .
                (isset($uploadedFiles['bir_registration']) && $uploadedFiles['bir_registration'] ? "<div class='doc-item'>BIR Registration</div>" : "") .
                (isset($uploadedFiles['extended_bir_registration']) && $uploadedFiles['extended_bir_registration'] ? "<div class='doc-item'>Extended BIR Registration</div>" : "") .
                (isset($uploadedFiles['financial_statement']) && $uploadedFiles['financial_statement'] ? "<div class='doc-item'>Financial Statement</div>" : "") .
                "</div>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <p style='font-size: 15px; color: #374151; margin-bottom: 15px;'>
                                Please review the " . ($isResubmission ? 'resubmitted' : 'submitted') . " documents and take appropriate action.
                            </p>
                            <a href='" . BASE_URL . "admin_dashboard.php' class='action-button'>
                                Review Documents →
                            </a>
                        </div>
                        
                        <div class='footer'>
                            <p style='margin: 5px 0;'>This is an automated notification from XentroMall Management System</p>
                            <p style='margin: 5px 0;'>© " . date('Y') . " XentroMall. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";

            if ($mail->send()) {
                error_log("✅✅✅ ADMIN EMAIL SENT SUCCESSFULLY for $submissionType application: $tradename (ID: $application_id)");
            } else {
                error_log("❌ Admin email failed: " . $mail->ErrorInfo);
            }
        } catch (Exception $e) {
            error_log("❌ Exception sending admin email: " . $e->getMessage());
        }

    } catch (PDOException $e) {
        error_log("Database insert error: " . $e->getMessage());
        error_log("SQLSTATE error code: " . $e->getCode());
        error_log("Error info: " . print_r($stmt->errorInfo(), true));
        $_SESSION['error_message'] = "There was an error submitting your application. Please try again later.";
        header("Location: application_submission.php");
        exit;
    }

    $_SESSION['success_message'] = "Application " . ($isResubmission ? 'resubmitted' : 'submitted') . " successfully.";
    header("Location: tenant_dashboard.php?page=application_submission");
    exit;
}
?>
<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$existingExtendedBir = null;

try {
    $stmt = $pdo->prepare("SELECT extended_bir_registration FROM application_submissions WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $existingExtendedBir = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching existing extended_bir_registration: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Application Submission</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7fafc;
            padding: 20px;
        }

        form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input[type="file"] {
            margin-top: 5px;
            width: 100%;
        }

        input[type="submit"] {
            margin-top: 20px;
            background-color: #1E73FF;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
        }

        input[type="submit"]:hover {
            background-color: #155db2;
        }

        .preview {
            margin-top: 10px;
            font-size: 14px;
        }

        .preview a {
            color: #1E73FF;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <h1>Application Submission</h1>
    <form action="application_submission.php" method="POST" enctype="multipart/form-data">
        <label for="letter_of_intent">Letter of Intent</label>
        <input type="file" name="letter_of_intent" id="letter_of_intent" required>

        <label for="business_profile">Business Profile</label>
        <input type="file" name="business_profile" id="business_profile" required>

        <label for="business_registration">Business Registration</label>
        <input type="file" name="business_registration" id="business_registration" required>

        <label for="valid_id">Valid ID (Image)</label>
        <input type="file" name="valid_id" id="valid_id" required>

        <label for="bir_registration">BIR Registration</label>
        <input type="file" name="bir_registration" id="bir_registration" required>

        <label for="extended_bir_registration">Extended BIR Registration</label>
        <input type="file" name="extended_bir_registration" id="extended_bir_registration" required>
        <?php if ($existingExtendedBir): ?>
            <div class="preview">
                Previous Extended BIR Registration: <a href="<?php echo htmlspecialchars($existingExtendedBir); ?>"
                    target="_blank"><?php echo htmlspecialchars(basename($existingExtendedBir)); ?></a>
            </div>
            <?php
        endif; ?>

        <label for="financial_statement">Financial Statement</label>
        <input type="file" name="financial_statement" id="financial_statement" required>

        <input type="submit" value="Submit Application">
    </form>
</body>

</html>