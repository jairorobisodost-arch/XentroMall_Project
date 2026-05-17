-- =====================================================
-- Work Permits Table Creation Script
-- =====================================================
-- This SQL script creates the complete work_permits table
-- for managing work permits in the XentroMall system
-- =====================================================

-- Drop existing table if it exists (optional)
-- DROP TABLE IF EXISTS `work_permits_backup`;

-- Create the work_permits_backup table
CREATE TABLE `work_permits_backup` (
  `permit_no` varchar(50) NOT NULL COMMENT 'Unique Work Permit Number',
  `date_filed` date NOT NULL COMMENT 'Date when the permit was filed',
  `tenant_name` varchar(255) NOT NULL COMMENT 'Name of the tenant/requester',
  `stall_number` varchar(50) DEFAULT NULL COMMENT 'Stall or space number',
  `scope_of_work` text NOT NULL COMMENT 'Description of work to be performed',
  
  -- Permit Validity Period
  `permit_valid_from` date DEFAULT NULL COMMENT 'Start date of permit validity',
  `permit_valid_to` date DEFAULT NULL COMMENT 'End date of permit validity',
  `time_from` time DEFAULT NULL COMMENT 'Work start time',
  `time_to` time DEFAULT NULL COMMENT 'Work end time',
  
  -- Security Services
  `security_posting` tinyint(1) DEFAULT 0 COMMENT 'Whether security posting is required (1=yes, 0=no)',
  `rate_security` decimal(10,2) DEFAULT NULL COMMENT 'Rate for security services',
  `charge_security` enum('With Charge','No Charge') DEFAULT NULL COMMENT 'Whether security is chargeable',
  
  -- Janitorial Services
  `janitorial_deployment` tinyint(1) DEFAULT 0 COMMENT 'Whether janitorial service is deployed (1=yes, 0=no)',
  `rate_janitorial` decimal(10,2) DEFAULT NULL COMMENT 'Rate for janitorial services',
  `charge_janitorial` enum('With Charge','No Charge') DEFAULT NULL COMMENT 'Whether janitorial is chargeable',
  
  -- Maintenance Services
  `maintenance` tinyint(1) DEFAULT 0 COMMENT 'Whether maintenance is required (1=yes, 0=no)',
  `rate_maintenance` decimal(10,2) DEFAULT NULL COMMENT 'Rate for maintenance services',
  `charge_maintenance` enum('With Charge','No Charge') DEFAULT NULL COMMENT 'Whether maintenance is chargeable',
  
  -- Contractor Information
  `personnel` text NOT NULL COMMENT 'List of personnel performing the work',
  
  -- Signatures (base64 encoded or file paths)
  `tenant_signature` longtext DEFAULT NULL COMMENT 'Tenant signature (base64 encoded)',
  `admin_signature` longtext DEFAULT NULL COMMENT 'Admin/Approver signature (base64 encoded)',
  `guard_signature` longtext DEFAULT NULL COMMENT 'Guard signature (base64 encoded)',
  
  -- Uploaded Images/Documents
  `uploaded_images` longtext DEFAULT NULL COMMENT 'JSON array or comma-separated list of uploaded image paths/base64',
  
  -- Approval Information
  `status` varchar(50) DEFAULT 'pending' COMMENT 'Status: pending, approved, rejected, completed',
  `admin_signed_at` datetime DEFAULT NULL COMMENT 'Timestamp when admin signed/approved',
  `guard_signed_at` datetime DEFAULT NULL COMMENT 'Timestamp when guard signed',
  
  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last update timestamp',
  
  -- Primary Key
  PRIMARY KEY (`permit_no`),
  
  -- Indexes for better query performance
  KEY `idx_tenant_name` (`tenant_name`),
  KEY `idx_date_filed` (`date_filed`),
  KEY `idx_status` (`status`),
  KEY `idx_stall_number` (`stall_number`),
  KEY `idx_permit_valid_from` (`permit_valid_from`),
  KEY `idx_permit_valid_to` (`permit_valid_to`),
  KEY `idx_created_at` (`created_at`)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Work Permits Backup Table';

-- =====================================================
-- Optional: Create a view for active/current permits
-- =====================================================
CREATE OR REPLACE VIEW `active_work_permits_backup` AS
SELECT 
    permit_no,
    date_filed,
    tenant_name,
    stall_number,
    scope_of_work,
    permit_valid_from,
    permit_valid_to,
    time_from,
    time_to,
    status,
    created_at,
    updated_at
FROM work_permits_backup
WHERE status = 'approved' 
  AND permit_valid_from <= CURDATE() 
  AND permit_valid_to >= CURDATE();

-- =====================================================
-- Optional: Create a view for pending permits
-- =====================================================
CREATE OR REPLACE VIEW `pending_work_permits_backup` AS
SELECT 
    permit_no,
    date_filed,
    tenant_name,
    stall_number,
    scope_of_work,
    status,
    created_at
FROM work_permits_backup
WHERE status = 'pending'
ORDER BY date_filed DESC;

-- =====================================================
-- Optional: Index for contractor type (if needed)
-- =====================================================
-- If you need to track contractor types (inside/outside),
-- add this column:
-- ALTER TABLE `work_permits_backup` ADD COLUMN `contractor_type` enum('inside','outside') DEFAULT 'inside' COMMENT 'Contractor type: inside or outside';
-- ALTER TABLE `work_permits_backup` ADD KEY `idx_contractor_type` (`contractor_type`);
