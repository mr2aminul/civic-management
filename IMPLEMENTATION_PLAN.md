# Client Management Enhancement - Implementation Plan

## Project Overview
Enhance the client management system with payment schedule management, transfer capabilities, and refund processing while maintaining complete data integrity and audit trails.

## Requirements Summary
1. **Payment Schedule**: Move from serialized data to dedicated table
2. **Name Transfer**: Change client while keeping plot and history
3. **Plot Transfer**: Change plot with rate adjustments
4. **Refund Management**: Flexible refund scheduling with deductions
5. **Data Safety**: Never delete, only update status
6. **Automation**: Cron job updates, email notifications

## Technical Specifications
- **Database**: MySQL (existing)
- **Email**: PHPMailer
- **Cron**: Daily updates
- **Refund Deduction**: One-time at initiation (5-25%)
- **Transfer Approval**: Admin approval required
- **Audit Trail**: Complete history tracking

---

## Phase 1: Database Schema Design ✅ IN PROGRESS

### Step 1.1: Create Payment Schedule Table ✅ COMPLETED
**File**: `database/migrations/001_create_payment_schedule_table.sql`
**Purpose**: Dedicated table for installment tracking
**Status**: ✅ COMPLETED
**Details**: See `STEP_1_1_COMPLETE.md`

### Step 1.2: Create Refund Schedule Table
**File**: Migration for `crm_refund_schedule`
**Purpose**: Track refund installments with deductions

### Step 1.3: Create Transfer History Table
**File**: Migration for `crm_transfer_history`
**Purpose**: Track all plot/name transfers

### Step 1.4: Create Booking History Table
**File**: Migration for `crm_booking_history`
**Purpose**: Complete audit trail for all changes

### Step 1.5: Update Table Constants
**File**: `assets/includes/tabels.php`
**Purpose**: Add new table constants

---

## Phase 2: Backend Functions

### Step 2.1: Payment Schedule Functions
- Create/Read/Update schedule entries
- Migrate existing serialized data (optional helper)
- Calculate schedule based on plot changes

### Step 2.2: Transfer Functions
- Name transfer logic
- Plot transfer logic with rate recalculation
- Transfer approval workflow

### Step 2.3: Refund Functions
- Calculate refund with deduction
- Create refund schedule
- Track refund payments

### Step 2.4: Cron Job Integration
- Update payment statuses daily
- Send scheduled emails
- Generate reports

---

## Phase 3: XHR Endpoints

### Step 3.1: Payment Schedule Endpoints
- Get schedule for purchase
- Update payment status
- Recalculate schedule

### Step 3.2: Transfer Endpoints
- Initiate name transfer
- Initiate plot transfer
- Approve/reject transfer
- Get transfer history

### Step 3.3: Refund Endpoints
- Calculate refund
- Create refund schedule
- Add refund installment
- Update refund payment

---

## Phase 4: UI Components

### Step 4.1: Payment Schedule Modal
- View/edit schedule
- Mark payments as paid
- Send email option

### Step 4.2: Transfer Modals
- Name transfer form
- Plot transfer form
- Transfer approval interface
- Transfer history viewer

### Step 4.3: Refund Modal
- Refund calculator
- Schedule creator
- Refund payment tracker

### Step 4.4: Enhanced View Client
- Integrate all new features
- Show transfer history
- Display refund status

---

## Phase 5: Email & Automation

### Step 5.1: Email Templates
- Payment schedule email
- Transfer notification
- Refund notification

### Step 5.2: Cron Job Updates
- Daily payment status updates
- Auto-send schedules (if enabled)

---

## Current Status: Step 1.1 - Creating Payment Schedule Table

**Next Steps**:
1. Create migration for `crm_payment_schedule` table
2. Test table creation
3. Proceed to Step 1.2

---

## Notes
- All changes maintain backward compatibility
- No data deletion, only status updates
- Complete audit trail for all operations
- Admin approval required for transfers
