/*
  # Create Transfer History Table

  ## Purpose
  Tracks all types of transfers in the system: name transfers, plot transfers with rate adjustments,
  and transfer approvals with complete audit trail.

  ## Tables Created
  - `crm_transfer_history`
    - `id` (int, primary key, auto increment)
    - `purchase_id` (int, references wo_booking_helper.id)
    - `transfer_type` (varchar, 'name_transfer' or 'plot_transfer')
    - `from_client_id` (int, original client)
    - `to_client_id` (int, new client)
    - `transfer_date` (date, when transfer occurred)
    - `approval_status` (tinyint, 0=pending, 1=approved, 2=rejected, 3=cancelled)
    - `approval_date` (date, when approved/rejected)
    - `approved_by` (int, approver user id)
    - `name_transfer_details` (text JSON, details for name transfers)
    - `plot_transfer_rate_old` (decimal, old rate if plot transfer)
    - `plot_transfer_rate_new` (decimal, new rate if plot transfer)
    - `rate_adjustment_reason` (varchar, reason for rate change)
    - `rate_adjustment_amount` (decimal, difference in amount)
    - `plot_transfer_details` (text JSON, details for plot transfers)
    - `transfer_fee` (decimal, fee charged for transfer if applicable)
    - `transfer_fee_paid` (decimal, fee amount paid)
    - `transfer_fee_due` (decimal, fee amount outstanding)
    - `payment_method` (varchar, Cash/Cheque/Bank Transfer/Online)
    - `money_receipt_no` (varchar, receipt number)
    - `remarks` (text, additional notes or conditions)
    - `rejection_reason` (text, reason if rejected)
    - `created_at` (timestamp)
    - `updated_at` (timestamp)
    - `created_by` (int, user who initiated)
    - `updated_by` (int, user who last updated)

  ## Indexes
  - Primary key on id
  - Index on purchase_id
  - Index on from_client_id
  - Index on to_client_id
  - Index on transfer_type
  - Index on approval_status
  - Index on transfer_date

  ## Notes
  - Supports both name and plot transfers
  - Plot transfers can include rate adjustments with reason tracking
  - Transfer approval workflow with approval_date and approver tracking
  - Optional transfer fees with payment tracking
  - Complete audit trail with created_by/updated_by
  - JSON fields for extensible transfer-specific details
*/

-- Create transfer history table
CREATE TABLE IF NOT EXISTS `crm_transfer_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL COMMENT 'References wo_booking_helper.id',
  `transfer_type` varchar(50) NOT NULL COMMENT 'name_transfer or plot_transfer',
  `from_client_id` int(11) NOT NULL COMMENT 'Original client',
  `to_client_id` int(11) NOT NULL COMMENT 'New client',
  `transfer_date` date NOT NULL COMMENT 'When transfer occurred',
  `approval_status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=approved, 2=rejected, 3=cancelled',
  `approval_date` date DEFAULT NULL COMMENT 'When approved/rejected',
  `approved_by` int(11) DEFAULT NULL COMMENT 'Approver user id',
  `name_transfer_details` text DEFAULT NULL COMMENT 'JSON details for name transfers',
  `plot_transfer_rate_old` decimal(12,2) DEFAULT NULL COMMENT 'Old rate for plot transfer',
  `plot_transfer_rate_new` decimal(12,2) DEFAULT NULL COMMENT 'New rate for plot transfer',
  `rate_adjustment_reason` varchar(255) DEFAULT NULL COMMENT 'Reason for rate change',
  `rate_adjustment_amount` decimal(12,2) DEFAULT NULL COMMENT 'Difference in rate amount',
  `plot_transfer_details` text DEFAULT NULL COMMENT 'JSON details for plot transfers',
  `transfer_fee` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Fee charged for transfer',
  `transfer_fee_paid` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Fee amount paid',
  `transfer_fee_due` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Fee amount outstanding',
  `payment_method` varchar(100) DEFAULT NULL COMMENT 'Cash/Cheque/Bank Transfer/Online',
  `money_receipt_no` varchar(100) DEFAULT NULL COMMENT 'Receipt number',
  `remarks` text DEFAULT NULL COMMENT 'Additional notes or conditions',
  `rejection_reason` text DEFAULT NULL COMMENT 'Reason if rejected',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who initiated',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated',
  PRIMARY KEY (`id`),
  KEY `idx_booking_helper` (`purchase_id`),
  KEY `idx_from_client` (`from_client_id`),
  KEY `idx_to_client` (`to_client_id`),
  KEY `idx_transfer_type` (`transfer_type`),
  KEY `idx_approval_status` (`approval_status`),
  KEY `idx_transfer_date` (`transfer_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Transfer history with approval workflow and rate adjustments';
