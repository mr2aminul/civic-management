/*
  # Create Payment Schedule Table

  ## Purpose
  Dedicated table for managing payment installments, replacing serialized data in wo_booking_helper.installment

  ## Tables Created
  - `crm_payment_schedule`
    - `id` (int, primary key, auto increment)
    - `booking_helper_id` (int, references wo_booking_helper.id)
    - `client_id` (int, references crm_customers.id)
    - `installment_number` (int, sequence number)
    - `particular` (varchar, description like "1st Installment")
    - `due_date` (date, when payment is due)
    - `amount` (decimal, installment amount)
    - `paid_amount` (decimal, amount paid)
    - `payment_date` (date, when paid)
    - `payment_method` (varchar, Cash/Cheque/Bank Transfer/Online)
    - `money_receipt_no` (varchar, receipt number)
    - `remarks` (text, additional notes)
    - `status` (tinyint, 0=pending, 1=paid, 2=partial, 3=overdue)
    - `is_adjustment` (tinyint, 0=regular, 1=adjustment)
    - `created_at` (timestamp)
    - `updated_at` (timestamp)
    - `created_by` (int, user who created)
    - `updated_by` (int, user who last updated)

  ## Indexes
  - Primary key on id
  - Index on booking_helper_id
  - Index on client_id
  - Index on due_date
  - Index on status

  ## Notes
  - This table replaces the serialized 'installment' field in wo_booking_helper
  - Each row represents one payment installment
  - Supports partial payments tracking
  - Includes audit fields (created_by, updated_by, timestamps)
  - Status 3 (overdue) will be auto-updated by cron job
*/

-- Create payment schedule table
CREATE TABLE IF NOT EXISTS `crm_payment_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_helper_id` int(11) NOT NULL COMMENT 'References wo_booking_helper.id',
  `client_id` int(11) NOT NULL COMMENT 'References crm_customers.id',
  `installment_number` int(11) NOT NULL COMMENT 'Sequence number (1, 2, 3...)',
  `particular` varchar(255) DEFAULT NULL COMMENT 'e.g., "1st Installment", "Booking Money"',
  `due_date` date NOT NULL COMMENT 'When payment is due',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Installment amount',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount actually paid',
  `payment_date` date DEFAULT NULL COMMENT 'When payment was made',
  `payment_method` varchar(100) DEFAULT NULL COMMENT 'Cash/Cheque/Bank Transfer/Online',
  `money_receipt_no` varchar(100) DEFAULT NULL COMMENT 'Receipt number',
  `remarks` text DEFAULT NULL COMMENT 'Additional notes',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=paid, 2=partial, 3=overdue',
  `is_adjustment` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=regular, 1=adjustment entry',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated',
  PRIMARY KEY (`id`),
  KEY `idx_booking_helper` (`booking_helper_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_status` (`status`),
  KEY `idx_installment_number` (`installment_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
