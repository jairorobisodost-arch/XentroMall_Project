<?php
/**
 * Stall Lease Agreement Template - Word Compatible Format
 */

$rules = $data['rules'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stall Lease Agreement</title>
    <style>
        * {
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
            padding: 0.5in;
        }
        
        .contract-wrapper {
            max-width: 8.5in;
            margin: 0 auto;
            background: #fff;
        }
        
        .contract-header {
            text-align: center;
            margin-bottom: 0.3in;
            border-bottom: 2pt solid #000;
            padding-bottom: 0.2in;
        }
        
        .contract-header h1 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 0.1in;
            text-transform: uppercase;
        }
        
        .contract-meta {
            font-size: 10pt;
            margin-top: 0.1in;
        }
        
        .contract-body {
            margin-top: 0.3in;
        }
        
        h2.section-title {
            font-size: 11pt;
            font-weight: bold;
            margin-top: 0.2in;
            margin-bottom: 0.1in;
            text-transform: uppercase;
        }
        
        p {
            margin-bottom: 0.1in;
            text-align: justify;
            line-height: 1.5;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0.1in 0;
        }
        
        .info-table td {
            border: 1pt solid #000;
            padding: 0.1in;
            font-size: 10pt;
            width: 33.33%;
        }
        
        .info-label {
            font-weight: bold;
            font-size: 9pt;
            display: block;
            margin-bottom: 0.05in;
        }
        
        .info-value {
            font-weight: bold;
            font-size: 11pt;
        }
        
        ul.custom-list {
            margin: 0.1in 0 0.1in 0.3in;
            padding-left: 0.2in;
        }
        
        ul.custom-list li {
            margin-bottom: 0.05in;
            text-align: justify;
        }
        
        .blank-line {
            border-bottom: 1pt solid #000;
            display: inline-block;
            width: 60px;
            text-align: center;
        }
        
        .highlight-box {
            border: 1pt solid #000;
            padding: 0.1in;
            margin: 0.1in 0;
            background: #f9f9f9;
        }
        
        .page-break {
            page-break-before: always;
            height: 0;
            margin: 0;
            padding: 0;
            border: none;
        }
        
        @media print {
            .page-break {
                page-break-before: always !important;
            }
        }
        
        .parties-section {
            background: #f9f9f9;
            border: 1pt solid #000;
            padding: 0.15in;
            margin: 0.1in 0;
        }
        
        .parties-section p {
            margin: 0.05in 0;
        }
        
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.3in;
        }
        
        .signature-table td {
            width: 50%;
            text-align: center;
            padding: 0.1in;
            vertical-align: top;
        }
        
        .sig-header {
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 0.1in;
            text-transform: uppercase;
        }
        
        .sig-box {
            height: 1in;
            border: 1pt dashed #999;
            margin-bottom: 0.1in;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 9pt;
        }
        
        .sig-line {
            border-top: 1pt solid #000;
            padding-top: 0.05in;
            min-height: 0.5in;
        }
        
        .sig-name {
            font-weight: bold;
            font-size: 10pt;
            margin-top: 0.05in;
        }
        
        .sig-title {
            font-size: 9pt;
            margin-top: 0.02in;
        }
        
        .footer-note {
            margin-top: 0.2in;
            font-size: 9pt;
            text-align: center;
            border-top: 1pt solid #000;
            padding-top: 0.1in;
        }
        
        .date-line {
            margin-top: 0.2in;
            font-size: 11pt;
        }
        
        .blank-line {
            border-bottom: 1pt solid #000;
            display: inline-block;
            width: 1.2in;
            text-align: center;
            margin: 0 0.05in;
        }
    </style>
</head>
<body>
    <div class="contract-wrapper">
        <div class="contract-header">
            <h1>STALL LEASE AGREEMENT</h1>
            <div class="contract-meta">
                <div>Contract No.: <strong><?= htmlspecialchars($data['contract_number'] ?? 'N/A'); ?></strong></div>
                <div>Generated on: <?= htmlspecialchars($data['generated_date'] ?? ''); ?></div>
            </div>
        </div>
        
        <div class="contract-body">
            <h2 class="section-title">PARTIES:</h2>
            <div class="parties-section">
                <p>
                    This Lease Agreement (the "Agreement") made this <span class="blank-line"><?= htmlspecialchars(date('j', strtotime($data['generated_date'] ?? 'now'))); ?></span> day of <span class="blank-line"><?= htmlspecialchars(date('F', strtotime($data['generated_date'] ?? 'now'))); ?></span>, 20<span class="blank-line"><?= htmlspecialchars(date('y', strtotime($data['generated_date'] ?? 'now'))); ?></span>, is made and entered into by and between
                    <strong><?= htmlspecialchars($data['lessor_name'] ?? 'Xentro Mall Management'); ?></strong>,
                    located at <?= htmlspecialchars($data['mall_address'] ?? 'XentroMall, City'); ?>
                    (hereinafter referred to as the "Landlord"), and
                    <strong><?= htmlspecialchars($data['lessee_name'] ?? ''); ?></strong>
                    of <strong><?= htmlspecialchars($data['company_name'] ?? ''); ?></strong>,
                    with contact email <?= htmlspecialchars($data['tenant_email'] ?? ''); ?> and phone <?= htmlspecialchars($data['tenant_contact'] ?? ''); ?>
                    (hereinafter referred to as the "Tenant").
                </p>
            </div>

            <h2 class="section-title">1. LEASED PREMISES:</h2>
            <p>
                The Landlord hereby leases to the Tenant, and the Tenant hereby leases from the Landlord, the following described stall/space located within the mall premises:
            </p>
            <table class="info-table">
                <tr>
                    <td>
                        <span class="info-label">Stall Number</span>
                        <span class="info-value"><?= htmlspecialchars($data['stall_number'] ?? 'N/A'); ?></span>
                    </td>
                    <td>
                        <span class="info-label">Location</span>
                        <span class="info-value"><?= htmlspecialchars($data['stall_location'] ?? 'N/A'); ?></span>
                    </td>
                    <td>
                        <span class="info-label">Floor Area</span>
                        <span class="info-value"><?= htmlspecialchars($data['stall_size'] ?? 'To be confirmed'); ?></span>
                    </td>
                </tr>
            </table>

            <h2 class="section-title">2. TERM OF LEASE:</h2>
            <div class="highlight-box">
                <strong>Lease Period:</strong> From <?= htmlspecialchars($data['lease_start'] ?? ''); ?> to <?= htmlspecialchars($data['lease_end'] ?? ''); ?> 
                (<?= htmlspecialchars($data['lease_term_text'] ?? ''); ?>).
            </div>

            <h2 class="section-title">3. RENT:</h2>
            <p>
                Tenant agrees to pay to Landlord the sum of <strong>₱<?= htmlspecialchars($data['monthly_rent'] ?? '0.00'); ?></strong> per month as rent for the leased premises. 
                The rent shall be payable in advance on or before the <?= htmlspecialchars($data['payment_due'] ?? '5th'); ?> day of each month during the term of this Agreement.
            </p>

            <h2 class="section-title">4. FORM OF PAYMENT:</h2>
            <p>
                Rent shall be paid in the following manner: <strong><?= htmlspecialchars($data['payment_mode'] ?? 'Bank Deposit / GCash'); ?></strong>. 
                Tenant shall provide proof of payment to the Landlord within 24 hours of transaction.
            </p>

            <h2 class="section-title">5. RENT DUE DATE:</h2>
            <p>
                Tenant hereby acknowledges that late payment will incur a penalty of <strong><?= htmlspecialchars($data['late_penalty'] ?? '5% per day of delay'); ?></strong>. 
                If rent is not received on or before the due date, Tenant shall be in default of this Agreement. 
                If the rent is not paid within five (5) days after the due date, the Landlord may, at its option, declare this lease terminated and proceed with eviction proceedings.
            </p>

            <h2 class="section-title">6. ADVANCE PAYMENT & SECURITY DEPOSIT:</h2>
            <p>
                Tenant shall pay <strong><?= htmlspecialchars($data['advance_payment'] ?? '1 month advance'); ?></strong> upon signing of this Agreement. 
                Additionally, Tenant shall pay <strong><?= htmlspecialchars($data['security_deposit'] ?? '2 months security deposit'); ?></strong> as security for the faithful performance of all terms and conditions of this lease. 
                This deposit shall be held by the Landlord and shall be returned to the Tenant within thirty (30) days after the termination of this lease, provided that the Tenant has complied with all the terms and conditions hereof and has not caused any damage to the premises.
            </p>

            <h2 class="section-title">7. UTILITIES & MAINTENANCE:</h2>
            <p>
                <?= htmlspecialchars($data['utilities_note'] ?? 'The Tenant shall be responsible for all utilities including electricity, water, and other services consumed within the leased premises. The Tenant shall pay these utilities directly to the service providers or reimburse the Landlord as per the mall\'s metering policies.'); ?>
            </p>

            <h2 class="section-title">8. CLEANING & MAINTENANCE:</h2>
            <p>
                Tenant agrees to keep the leased premises clean and in good condition at all times. The Tenant shall be responsible for regular cleaning and maintenance of the stall. 
                Any damage caused by the Tenant shall be repaired at the Tenant's expense. The Landlord reserves the right to conduct inspections of the premises.
            </p>

            <!-- Page break for 2-page layout -->
            <div class="page-break"></div>

            <h2 class="section-title">9. ALTERATIONS & IMPROVEMENTS:</h2>
            <p>
                Tenant shall not make any alterations, additions, or improvements to the leased premises without the prior written consent of the Landlord. 
                Any alterations made without permission shall be removed at the Tenant's expense, and the premises shall be restored to their original condition.
            </p>

            <h2 class="section-title">10. BUSINESS OPERATIONS:</h2>
            <p>
                Tenant agrees to operate the business in compliance with all applicable laws, regulations, and the mall's policies. 
                The Tenant shall not engage in any illegal activities or sell prohibited items. 
                The Tenant shall maintain proper business permits and licenses as required by law.
            </p>

            <h2 class="section-title">11. MALL RULES & REGULATIONS:</h2>
            <p>The Tenant agrees to abide by the following mall rules and regulations:</p>
            <ul class="custom-list">
                <?php foreach ($rules as $rule): ?>
                    <li><?= htmlspecialchars($rule); ?></li>
                <?php endforeach; ?>
            </ul>

            <h2 class="section-title">12. TERMINATION OF LEASE:</h2>
            <p>
                <?= htmlspecialchars($data['termination_note'] ?? 'Either party may terminate this Agreement by providing written notice of at least thirty (30) days prior to the intended termination date. Violations of the mall policies or non-payment of rent may result in immediate termination without notice.'); ?>
            </p>

            <h2 class="section-title">13. LIABILITY & DAMAGE:</h2>
            <p>
                <?= htmlspecialchars($data['liability_note'] ?? 'The Tenant shall be responsible for any damages to the stall, fixtures, and equipment beyond normal wear and tear. The Landlord shall not be liable for any loss or damage to the Tenant\'s merchandise or personal property. The Tenant is advised to obtain appropriate insurance coverage.'); ?>
            </p>

            <h2 class="section-title">14. DEFAULT & REMEDIES:</h2>
            <p>
                If the Tenant fails to pay rent when due or violates any material term of this Agreement, the Tenant shall be in default. 
                Upon default, the Landlord may pursue any legal remedies available, including but not limited to eviction proceedings, collection of unpaid rent, and recovery of damages.
            </p>

            <h2 class="section-title">15. FULL DISCLOSURE:</h2>
            <p>
                The Tenant signing this Lease Agreement hereby certifies that all questions about the Rental Agreement have been answered and the provisions of the agreement and the obligations of each party are fully understood and agreed to by all parties. 
                The Tenant further certifies that they agree to fulfill all obligations and responsibilities of each party as outlined in this Lease Agreement and acknowledge receipt of a signed copy of the Lease Agreement.
            </p>

            <div class="date-line">
                <p><strong>Accepted this <span class="blank-line"><?= htmlspecialchars(date('j', strtotime($data['generated_date'] ?? 'now'))); ?></span> day of <span class="blank-line"><?= htmlspecialchars(date('F', strtotime($data['generated_date'] ?? 'now'))); ?></span>, 20<span class="blank-line"><?= htmlspecialchars(date('y', strtotime($data['generated_date'] ?? 'now'))); ?></span></strong></p>
            </div>

            <div class="footer-note">
                This document was generated electronically by XentroMall Management System. Printed copies are considered uncontrolled unless signed and dated by authorized representatives.
            </div>
        </div>
    </div>
</body>
</html>
