/*
  # Create Refund Schedule Table

  ## Purpose
  Dedicated table for managing refund installments with deduction tracking when clients request refunds.

  ## Tables Created
  - `crm_refund_schedule`
    - `id` (int, primary key, auto increment)
    - `purchase_id` (int, references wo_booking_helper.id)
    - `client_id` (int, references crm_customers.id)
    - `refund_initiation_date` (date, when refund was requested)
    - `total_paid_amount` (decimal, total amount client has paid)
    - `deduction_percentage` (decimal, 5-25% penalty)
    - `deduction_amount` (decimal, calculated penalty amount)
    - `refundable_amount` (decimal, amount to be refunded after deduction)
    - `installment_number` (int, sequence number for refund installments)
    - `installment_amount` (decimal, amount per refund installment)
    - `due_date` (date, when refund installment is due)
    - `paid_amount` (decimal, amount actually refunded)
    - `payment_date` (date, when refund was paid)
    - `payment_method` (varchar, Cash/Cheque/Bank Transfer/Online)
    - `money_receipt_no` (varchar, receipt number)
    - `remarks` (text, additional notes)
    - `status` (tinyint, 0=pending, 1=paid, 2=partial, 3=cancelled)
    - `created_at` (timestamp)
    - `updated_at` (timestamp)
    - `created_by` (int, user who created)
    - `updated_by` (int, user who last updated)

  ## Indexes
  - Primary key on id
  - Index on purchase_id
  - Index on client_id
  - Index on due_date
  - Index on status

  ## Notes
  - Deduction is applied ONE TIME at refund initiation (5-25%)
  - Supports multiple refund installments for flexible payment scheduling
  - Complete audit trail with created_by/updated_by
  - Status tracking for each refund installment
*/

-- Create refund schedule table
CREATE TABLE IF NOT EXISTS `crm_refund_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL COMMENT 'References wo_booking_helper.id',
  `client_id` int(11) NOT NULL COMMENT 'References crm_customers.id',
  `refund_initiation_date` date NOT NULL COMMENT 'Date when refund was requested',
  `total_paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount client has paid',
  `deduction_percentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Penalty percentage (5-25%)',
  `deduction_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Calculated penalty amount',
  `refundable_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount to refund after deduction',
  `installment_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Refund installment sequence (1, 2, 3...)',
  `installment_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount for this refund installment',
  `due_date` date DEFAULT NULL COMMENT 'When this refund installment is due',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount actually refunded',
  `payment_date` date DEFAULT NULL COMMENT 'When refund was paid',
  `payment_method` varchar(100) DEFAULT NULL COMMENT 'Cash/Cheque/Bank Transfer/Online',
  `money_receipt_no` varchar(100) DEFAULT NULL COMMENT 'Receipt number',
  `remarks` text DEFAULT NULL COMMENT 'Additional notes',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=paid, 2=partial, 3=cancelled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated',
  PRIMARY KEY (`id`),
  KEY `idx_booking_helper` (`purchase_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_status` (`status`),
  KEY `idx_installment_number` (`installment_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Refund schedule with deduction tracking';
