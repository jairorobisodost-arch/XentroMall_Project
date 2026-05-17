<?php
require 'config.php';

class IPROG_SMS {
    private $api_token = '94686991a7d945fca6808694a6ffe563fa6d2e81';
    private $api_url = 'https://www.iprogsms.com/api/v1/sms_messages';
    
    public function sendSMS($phone_number, $message) {
        $data = [
            'api_token' => $this->api_token,
            'message' => $message,
            'phone_number' => $phone_number
        ];
        
        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $http_code == 200,
            'response' => $response,
            'http_code' => $http_code
        ];
    }
    
    public function sendPaymentApprovalSMS($tenant_name, $phone_number, $status, $remarks = '') {
        if ($status === 'approved') {
            $message = "Hi {$tenant_name}, Your payment has been APPROVED. Thank you for your payment to Xentro Mall.";
        } else {
            $message = "Hi {$tenant_name}, Your payment has been DECLINED.";
            if (!empty($remarks)) {
                $message .= " Reason: {$remarks}";
            }
            $message .= " Please contact Xentro Mall admin for assistance.";
        }
        
        return $this->sendSMS($phone_number, $message);
    }
    
    public function sendRenewalReminderSMS($tenant_name, $phone_number, $days_remaining) {
        $message = "Hi {$tenant_name}, This is a reminder that your contract expires in {$days_remaining} days. Please renew your contract at Xentro Mall to avoid interruption.";
        return $this->sendSMS($phone_number, $message);
    }
    
    public function sendWorkPermitNotificationSMS($tenant_name, $phone_number, $permit_status) {
        if ($permit_status === 'approved') {
            $message = "Hi {$tenant_name}, Your work permit has been APPROVED. You may now proceed with your construction/maintenance work at Xentro Mall.";
        } elseif ($permit_status === 'rejected') {
            $message = "Hi {$tenant_name}, Your work permit has been REJECTED. Please contact Xentro Mall admin for details and resubmission requirements.";
        } else {
            $message = "Hi {$tenant_name}, Your work permit is under review. We will notify you once a decision has been made.";
        }
        
        // Add logging for debugging
        error_log("SMS Attempt: Sending to {$phone_number} - Message: {$message}");
        
        $result = $this->sendSMS($phone_number, $message);
        
        // Log the result
        if ($result['success']) {
            error_log("✅ SMS sent successfully to {$phone_number} for work permit {$permit_status}");
        } else {
            error_log("❌ SMS failed to {$phone_number}. HTTP Code: {$result['http_code']}. Response: {$result['response']}");
        }
        
        return $result;
    }
    
    public function sendBillingUpdateSMS($tenant_name, $phone_number, $billing_month, $total_amount = 0) {
        $message = "Hi {$tenant_name}, Your utility bills for {$billing_month} have been updated and are now available in your Xentro Mall dashboard.";
        if ($total_amount > 0) {
            $message .= " Total amount: ₱" . number_format($total_amount, 2);
        }
        $message .= " Please check your account for details.";
        
        return $this->sendSMS($phone_number, $message);
    }
    
    public function sendRenewalApprovalSMS($tenant_name, $phone_number, $status, $amount = 0, $remarks = '') {
        if ($status === 'approved') {
            $message = "Hi {$tenant_name}, Your contract renewal request has been APPROVED!";
            if ($amount > 0) {
                $message .= " Total amount: ₱" . number_format($amount, 2) . ". Please proceed with payment to activate your renewed contract.";
            } else {
                $message .= " Your contract has been successfully renewed.";
            }
            $message .= " Thank you for continuing with Xentro Mall!";
        } else {
            $message = "Hi {$tenant_name}, Your contract renewal request has been DECLINED.";
            if (!empty($remarks)) {
                $message .= " Reason: {$remarks}";
            }
            $message .= " Please address the issues mentioned and submit a new renewal request. Contact Xentro Mall admin for assistance.";
        }
        
        return $this->sendSMS($phone_number, $message);
    }
    
    public function sendPaymentReminderSMS($tenant_name, $phone_number, $due_date, $amount = 0, $custom_message = '') {
        $message = "Hi {$tenant_name}, This is a friendly reminder that your payment for Xentro Mall is due on {$due_date}.";
        if ($amount > 0) {
            $message .= " Amount due: ₱" . number_format($amount, 2) . ".";
        }
        if (!empty($custom_message)) {
            $message .= " Note: " . $custom_message;
        }
        $message .= " Please settle your balance to avoid late fees. Thank you!";
        
        return $this->sendSMS($phone_number, $message);
    }
    
    public function sendBIRNotificationSMS($tenant_name, $phone_number, $status, $expiry_date = '') {
        if ($status === 'expired') {
            $message = "Hi {$tenant_name}, This is an URGENT notice from Xentro Mall. Your BIR registration has EXPIRED on {$expiry_date}. Please update your records immediately to avoid penalties.";
        } else {
            $days = $status === 'expiring_soon' ? "soon" : "within 30 days";
            $message = "Hi {$tenant_name}, This is a reminder from Xentro Mall that your BIR registration will expire {$days} (" . ($expiry_date ? "on $expiry_date" : "") . "). Please process your renewal and submit the updated documents to the admin.";
        }
        
        return $this->sendSMS($phone_number, $message);
    }
}

// Usage examples:
/*
$sms = new IPROG_SMS();

// Send payment approval SMS
$result = $sms->sendPaymentApprovalSMS('Juan Dela Cruz', '639171071234', 'approved');
if ($result['success']) {
    echo "SMS sent successfully!";
} else {
    echo "Failed to send SMS: " . $result['response'];
}

// Send declined payment with remarks
$result = $sms->sendPaymentApprovalSMS('Juan Dela Cruz', '639171071234', 'declined', 'Incomplete documentation');

// Send renewal reminder
$result = $sms->sendRenewalReminderSMS('Juan Dela Cruz', '639171071234', 30);

// Send work permit notification
$result = $sms->sendWorkPermitNotificationSMS('Juan Dela Cruz', '639171071234', 'approved');
*/
?>
