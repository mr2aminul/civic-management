# Advanced Payment & Invoice Management System - Complete Documentation

**Version:** 2.0  
**Status:** Production Ready  
**Last Updated:** 2025-11-20  
**System:** Civic BD Group CRM - Complete Payment Management

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Architecture & Database Schema](#architecture--database-schema)
3. [Key Features](#key-features)
4. [Frontend Implementation](#frontend-implementation)
5. [Backend API Reference](#backend-api-reference)
6. [Data Flow & Examples](#data-flow--examples)
7. [Installation & Deployment](#installation--deployment)
8. [Security & Compliance](#security--compliance)
9. [Troubleshooting & Support](#troubleshooting--support)
10. [Testing Scenarios](#testing-scenarios)

---

## System Overview

This is a complete, production-ready payment management system for Civic BD Group handling:

- **Invoice Management** - Create, track, and manage invoices with multi-invoice support
- **Payment Processing** - Record payments with automatic overpayment handling
- **Money Receipts** - Auto-generated receipts with complete payment history
- **Payment Schedules** - Full payment schedule management with rescheduling
- **Refunds** - Complete refund process with deduction tracking
- **Email/SMS Queue** - Queue and send payment notifications
- **Audit Trail** - 100% action logging for compliance and accountability
- **Purchase Transfers** - Track ownership changes and transfers
- **Purchase Merges** - Consolidate multiple purchases with admin approval
- **Payment Credits** - Track and manage overpayment credits

### Core Principles

- **ZERO DATA LOSS** - All data changes tracked in audit trail
- **COMPLETE HISTORICAL TRACKING** - Can see full history of any transaction
- **AUTOMATIC OVERPAYMENT HANDLING** - Overpayments automatically become credits for future invoices
- **ADMIN APPROVAL WORKFLOW** - Critical changes require approval
- **MULTI-INVOICE SUPPORT** - Single installment can have multiple invoices
- **FLEXIBLE PAYMENT TRACKING** - Supports various payment methods and references

---

## Architecture & Database Schema

### Database Tables (13 Core Tables)

#### 1. **crm_customers** - Client Master Data
- Stores complete customer information
- Phone number is unique identifier
- Supports additional JSON data for extensibility
- Related to: nominees, purchases, schedules

#### 2. **crm_nominees** - Beneficiaries & Heirs
- Stores nominee information per customer
- Share percentage tracking
- Birthday for automated notifications
- Contact information for emails/SMS

#### 3. **wo_booking** - Plot/Property Master
- Master property/plot information
- Project, block, road, plot details
- Facing, file number, katha (land unit)
- Shared across multiple bookings

#### 4. **wo_booking_helper** - Purchase Records
- Links customer to specific plot
- Status: 0=available, 1=available, 2=sold, 3=complete, 4=cancelled
- Tracks booking_money, down_payment, installments
- Supports merge and transfer tracking

#### 5. **crm_payment_schedule** - Payment Installments
- Individual payment installment records
- Type: booking, down_payment, installment
- Tracks paid_amount, payment_date, method
- Late fees, overdue fees, overpayment tracking
- Status: 0=pending, 1=paid, 2=partial, 3=overdue, 4=cancelled

#### 6. **crm_invoices** - Invoice Management
- Invoice-level tracking (separate from schedules)
- Supports multiple invoices per installment
- Status: draft → issued → partial → paid → overdue
- Amount, paid_amount, remaining_amount tracking
- Links to payment_schedule items

#### 7. **crm_money_receipts** - Payment Receipts
- Auto-generated money receipt records
- Format: MR-YYYY-MM-00001
- Tracks which invoices this payment covered
- Payment method and transaction reference
- Sent email/phone tracking

#### 8. **crm_payment_credits** - Overpayment Credits
- Stores overpayment amounts from invoices
- Tracks credit_amount, applied_amount, remaining_amount
- Links to source invoice and destination installment
- Reason for credit creation

#### 9. **crm_payment_credit_applications** - Credit Usage
- Records when credits are applied to future invoices
- Tracks applied amount and date
- Who applied the credit (user attribution)
- Remarks for explanation

#### 10. **crm_refund_schedule & crm_refund_transactions**
- Refund schedule management
- Deduction tracking (% or fixed)
- Transaction-level refund tracking
- Status: pending, paid, partial, cancelled

#### 11. **crm_purchase_transfer_history** - Transfer Tracking
- Records all ownership transfers
- Type: name_transfer, plot_transfer, cancel_plot
- Before/after plot details and amounts
- Transfer fee tracking

#### 12. **crm_email_queue & crm_email_logs** - Email Management
- Queue system for pending emails
- Template variables support
- Scheduled send capability
- Retry mechanism with failure tracking
- Complete email history in logs

#### 13. **crm_audit_trail** - Complete Action Logging
- Every action logged with before/after values
- User attribution (who did what)
- IP address and timestamp
- Action category and type
- Affected tables and values

#### 14. **crm_pending_changes & crm_approval_notifications**
- Critical changes requiring admin approval
- Reschedule, transfer, cancel, rate_change types
- Admin approval workflow
- Notification system for approvals

#### 15. **crm_merge_requests & crm_purchase_merge_history**
- Purchase merge workflow
- Consolidate payment schedules
- Transfer paid amounts and credits
- Complete merge history

---

## Key Features

### 1. Overpayment Handling (Core Feature)

**Scenario:**
\`\`\`
Installment: ৳50,000 (due 2025-12-01)
Invoice 1:    ৳30,000
Invoice 2:    ৳25,000
Invoice 3:    ৳20,000
Total:        ৳75,000

Client Payment: ৳75,000
\`\`\`

**System Response:**
1. ✅ Creates 3 invoice records
2. ✅ Records ৳75,000 payment
3. ✅ Marks invoices as paid
4. ✅ Identifies ৳25,000 overpayment
5. ✅ **Automatically creates overpayment credit**
6. ✅ **Applies ৳25,000 to next pending installment**
7. ✅ Updates next schedule: 25,000 paid of 50,000
8. ✅ Logs all actions to audit trail

**Data Storage:**
- `crm_invoices`: 3 records with individual amounts
- `crm_payment_credits`: 1 credit record (৳25,000)
- `crm_payment_schedule`: Updated paid_amount for installment #1 (৳50,000)
- `crm_payment_schedule`: Updated paid_amount for installment #2 (৳25,000)
- `crm_audit_trail`: Complete action history

### 2. Multi-Invoice Support

- One installment can have multiple invoices
- Each invoice tracked independently
- Payment sums across all invoices
- Status updates when all invoices paid

### 3. Money Receipt Generation

- Auto-generated receipt number: MR-YYYY-MM-00001
- Links invoices to receipts
- Payment method and reference tracking
- Can be sent via email/SMS
- Complete receipt history

### 4. Email Queue System

**Email Types:**
- `payment_reminder` - Upcoming payment due
- `payment_received` - Confirmation of payment
- `schedule_report` - Full payment schedule
- `invoice_issued` - New invoice notification
- `birthday_wish` - Birthday greeting
- `refund_schedule` - Refund installment notification
- `nominee_notification` - Notify nominees

**Recipients:**
- Primary client
- Nominees (from crm_nominees)
- Co-buyers
- Custom email addresses

**Features:**
- Queue for later sending (scheduled delivery)
- Template variables support
- Retry mechanism on failure
- Complete email logs with delivery tracking
- SMS gateway integration ready

### 5. Complete Audit Trail

**Tracked Actions:**
- Payment recording (before/after amounts)
- Schedule changes (reason, previous value)
- Invoice creation/modification
- Transfer/merge operations
- Email sending
- Admin approvals/rejections
- Refund processing
- Credits applied

**Data Captured:**
- Action type & category
- Before value (JSON)
- After value (JSON)
- Affected tables
- User attribution
- IP address & timestamp
- Detailed description

### 6. Payment Schedule Management

**Schedule Types:**
- Booking Money
- Down Payment
- Regular Installments

**Status Tracking:**
- 0 = Pending
- 1 = Paid
- 2 = Partial
- 3 = Overdue
- 4 = Cancelled

**Features:**
- Automatic late fee calculation
- Overdue fee tracking
- Admin waiver capability
- Rescheduling with history
- Transfer impact tracking

### 7. Purchase Transfer

**Transfer Types:**
- Name Transfer (client changes)
- Plot Transfer (plot changes)
- Cancel Plot (cancellation)

**Tracked:**
- Previous client name & ID
- New client name & ID
- Plot details before/after
- Per-katha rate changes
- Total amount changes
- Transfer fees

### 8. Purchase Merge

**Merge Scenario:**
\`\`\`
Client owns: Purchase X (3 katha)
Client owns: Purchase Y (6 katha)
Result: Consolidated single file
\`\`\`

**Merge Options:**
- Consolidate payment schedules
- Transfer paid amounts
- Transfer overpayment credits
- Reschedule payments

**Approval Workflow:**
- Request → Pending
- Admin Review → Approve/Reject
- If Approved → Auto-Execute
- Complete audit trail

### 9. Refund Management

**Refund Process:**
1. Calculate total paid amount
2. Apply deduction (% or fixed)
3. Generate refund installments
4. Track refund payments
5. Complete audit trail

**Deduction Modes:**
- None (no deduction)
- Fixed amount
- Percentage (e.g., 10%)

---

## Frontend Implementation

### Tab Pages Location: `/manage/pages/clients/tabs/`

#### 1. **schedules.phtml** (formerly payment_schedules_complete.phtml)
- View all payment schedules / purchases
- Filter by: status, type, date range
- Quick actions: view details (opens payment_schedule_modal), create invoice
- Summary dashboard: total due, paid, pending, overdue

#### 2. **invoices.phtml** (formerly invoices_complete.phtml)
- Invoice listing with advanced filtering
- Multi-tab interface: Invoices | Receipts | Credits
- Invoice detail view with payment history
- Money receipt generation
- Overpayment credit tracking

#### 3. **pending_emails.phtml** (formerly pending_emails_complete.phtml)
- Email queue management
- Filter by: type, status, recipient type
- Preview before sending
- Scheduled send capability
- Bulk send functionality
- Retry failed emails

#### 4. **audit_trail.phtml** (formerly audit_trail_complete.phtml)
- Timeline-based audit log view
- Filter by: category, action type, date, user
- Color-coded by category
- Before/after value comparison
- Export to PDF/Excel
- Print functionality

### Modal Files Location: `/manage/pages/clients/modals/`

- **payment_schedule_modal.phtml** - Main schedule interaction modal.
  - **Integrated Features**:
    - **Schedule**: View/Edit payment schedule, generate installments.
    - **Reschedule**: Calculate and submit schedule changes.
    - **Invoices**: View invoices, create new ones.
    - **Emails**: Manage pending emails (receipts/schedules).
    - **Audit**: View audit trail for the specific purchase.
    - **Pending**: View pending approval requests.
- **create-invoice.phtml** - Modal for creating new invoices.
- **reschedule_modal.phtml** - Standalone reschedule modal (legacy/alternative).
- **transfer_modal.phtml** - Purchase transfer management.
- **merge_purchase_modal.phtml** - Purchase merge management.

### Email Templates Location: `/manage/pages/clients/emails/`

- **payment_reminder.php** - Upcoming payment notification
- **payment_received.php** - Payment confirmation
- **schedule_report.php** - Full payment schedule
- **invoice_email.php** - Invoice details
- **birthday_wish.php** - Birthday greeting
- **refund_schedule.php** - Refund schedule notification
- **nominee_notification.php** - Nominee notification

---

## Backend API Reference

### Base Path: `/xhr/manage_inventory/`

The system uses a modular API structure within the `xhr/manage_inventory/` directory.

#### 1. **purchases.php**
- `get_purchases_list`: List all purchases with summary (paid, due).
- `get_purchase_details`: Get full details of a purchase including schedule.
- `search_purchases`: Search for purchases by client/file/plot.

#### 2. **invoices.php**
- `get_invoices`: List invoices for a purchase/client.
- `create_invoice`: Generate a new invoice.
- `record_payment`: Record payment against an invoice (handles overpayments).

#### 3. **schedules.php**
- `get_payment_schedule`: Get schedule rows.
- `update_installment`: Save/Update payment schedule.
- `recalculate_schedule`: Recalculate dues/interest.

#### 4. **audit.php**
- `get_audit_trail`: Fetch audit logs.

#### 5. **emails.php**
- `get_pending_emails`: List queued emails.
- `send_email`: Execute email sending.

#### GET_SCHEDULES_LIST (via purchases.php)
```json
POST Parameters:
- purchase_id (integer)
- client_id (integer)
- status (0-4, or empty for all)
- type (booking|down|installment, or empty)
- from_date (YYYY-MM-DD)
- to_date (YYYY-MM-DD)

Response:
{
  "success": true,
  "schedules": [...],
  "summary": {
    "total_due": 500000,
    "total_paid": 250000,
    "total_pending": 250000,
    "total_overdue": 10000,
    "total_overpayment": 25000,
    "total_late_fees": 5000
  }
}
```

#### GET_INVOICES_LIST (via invoices.php)
```json
POST Parameters:
- purchase_id (integer)
- client_id (integer)
- status (draft|issued|partial|paid|overdue|cancelled, or empty)
- from_date (YYYY-MM-DD)
- to_date (YYYY-MM-DD)

Response:
{
  "success": true,
  "invoices": [
    {
      "id": 1,
      "invoice_number": "INV-2025-001",
      "amount": 50000,
      "paid_amount": 75000,
      "remaining_amount": 0,
      "status": "paid",
      "invoice_date": "2025-11-20",
      "due_date": "2025-12-01"
    }
  ],
  "overpayment_credits": [
    {
      "credit_id": 5,
      "amount": 25000,
      "source_invoice": "INV-2025-001",
      "applied_to_schedule": 2
    }
  ]
}
```

#### RECORD_INVOICE_PAYMENT (via invoices.php)
```json
POST Parameters:
- invoice_id (integer)
- payment_amount (decimal)
- payment_method (string)
- payment_date (YYYY-MM-DD)
- money_receipt_no (string, optional)
- notes (string, optional)

Response:
{
  "success": true,
  "message": "Payment recorded successfully",
  "receipt_number": "MR-2025-11-001",
  "overpayment_distributed": true,
  "overpayment_amount": 25000,
  "applied_to_schedule_id": 2,
  "audit_trail_id": 1234
}
```

#### CREATE_INVOICE (via invoices.php)
```json
POST Parameters:
- purchase_id (integer)
- client_id (integer)
- payment_schedule_id (integer)
- invoice_type (booking_money|down_payment|installment)
- amount (decimal)
- due_date (YYYY-MM-DD)
- description (string)

Response:
{
  "success": true,
  "invoice_id": 5,
  "invoice_number": "INV-2025-005",
  "message": "Invoice created successfully"
}
```

#### GET_PAYMENT_CREDITS (via invoices.php)
```json
POST Parameters:
- purchase_id (integer)
- client_id (integer)
- status (active|applied|all)

Response:
{
  "success": true,
  "credits": [
    {
      "id": 1,
      "amount": 25000,
      "applied": 0,
      "remaining": 25000,
      "source_invoice": "INV-2025-001",
      "created_at": "2025-11-20 14:30:00"
    }
  ]
}
```

#### GET_AUDIT_TRAIL (via audit.php)
```json
POST Parameters:
- purchase_id (integer, optional)
- client_id (integer)
- action_category (payment|schedule|invoice|purchase|email|approval|system)
- action_type (create|update|delete|approve|reject|send_email)
- from_date (YYYY-MM-DD)
- to_date (YYYY-MM-DD)
- limit (default 50)
- offset (for pagination)

Response:
{
  "success": true,
  "audit_logs": [
    {
      "id": 1,
      "client_id": 5,
      "purchase_id": 1,
      "action_type": "payment",
      "action_category": "payment",
      "description": "Payment recorded for invoice INV-2025-001",
      "before_values": {"paid_amount": 0},
      "after_values": {"paid_amount": 75000},
      "performed_at": "2025-11-20 14:30:00",
      "performed_by": "admin",
      "ip_address": "192.168.1.1"
    }
  ],
  "total_count": 125
}
```

#### GET_EMAIL_LOGS (via emails.php)
```json
POST Parameters:
- client_id (integer)
- purchase_id (integer, optional)
- email_type (payment_reminder|payment_received|etc)
- from_date (YYYY-MM-DD)
- to_date (YYYY-MM-DD)

Response:
{
  "success": true,
  "emails": [
    {
      "id": 1,
      "recipient_email": "client@example.com",
      "email_type": "payment_reminder",
      "subject": "Payment Reminder - ৳50,000 Due",
      "sent_at": "2025-11-20 14:30:00",
      "status": "sent",
      "delivery_status": "delivered"
    }
  ]
}
\`\`\`

#### QUEUE_EMAIL
\`\`\`json
POST Parameters:
- client_id (integer)
- purchase_id (integer)
- email_type (required)
- recipient_email (required)
- recipient_name (required)
- template_variables (JSON)
- scheduled_send_date (datetime, optional)

Response:
{
  "success": true,
  "queue_id": 10,
  "message": "Email queued successfully"
}
\`\`\`

#### SEND_EMAIL
\`\`\`json
POST Parameters:
- queue_id (integer)
- override_content (string, optional - for custom message)

Response:
{
  "success": true,
  "message": "Email sent successfully",
  "delivery_status": "sent",
  "sent_at": "2025-11-20 14:35:00",
  "email_log_id": 15
}
\`\`\`

---

## Data Flow & Examples

### Example 1: Complete Overpayment Scenario

**Setup:**
- Client: Ahmed Hassan (ID: 5)
- Purchase: Plot at Moon Hill (ID: 1)
- Installment #1: ৳50,000 due 2025-12-01

**Step 1: Create Invoices**
\`\`\`
POST /xhr/manage_inventory_invoice_system.php
{
  action: "create_invoice",
  purchase_id: 1,
  client_id: 5,
  payment_schedule_id: 1,
  invoice_type: "installment",
  amount: 50000,
  due_date: "2025-12-01",
  description: "1st Installment - Moon Hill Plot"
}
Response: Invoice #INV-2025-001 created
\`\`\`

**Step 2: Client Makes Overpayment**
\`\`\`
POST /xhr/manage_inventory_invoice_system.php
{
  action: "record_invoice_payment",
  invoice_id: 1,
  payment_amount: 75000,
  payment_method: "bank_transfer",
  payment_date: "2025-11-20",
  money_receipt_no: "MR-2025-11-001"
}

Response:
{
  "success": true,
  "receipt_number": "MR-2025-11-001",
  "overpayment_amount": 25000,
  "applied_to_schedule_id": 2,
  "message": "Payment recorded. ৳25,000 overpayment applied to next installment"
}
\`\`\`

**Step 3: System Actions**
1. ✅ Creates Money Receipt: MR-2025-11-001
2. ✅ Updates Invoice #INV-2025-001: status = "paid", paid_amount = 75000
3. ✅ Updates Payment Schedule #1: paid_amount = 50000, status = "paid"
4. ✅ Creates Payment Credit: amount = 25000, source = Invoice#1
5. ✅ Updates Payment Schedule #2: paid_amount = 25000, status = "partial", remaining = 25000
6. ✅ Logs to audit trail with complete before/after values

**Step 4: Audit Trail Record**
\`\`\`json
{
  "id": 1000,
  "client_id": 5,
  "purchase_id": 1,
  "action_type": "payment",
  "description": "Overpayment of ৳25,000 automatically applied to next installment",
  "before_values": {
    "schedule_1": {"paid_amount": 0, "status": 0},
    "schedule_2": {"paid_amount": 0, "status": 0}
  },
  "after_values": {
    "schedule_1": {"paid_amount": 50000, "status": 1},
    "schedule_2": {"paid_amount": 25000, "status": 2},
    "credit_created": 25000
  },
  "performed_by": "admin",
  "performed_at": "2025-11-20 14:30:00"
}
\`\`\`

### Example 2: Email Queue & Sending

**Step 1: Queue Payment Reminder**
\`\`\`
POST /xhr/manage_inventory_audit_email.php
{
  action: "queue_email",
  client_id: 5,
  purchase_id: 1,
  email_type: "payment_reminder",
  recipient_email: "ahmed@example.com",
  recipient_name: "Ahmed Hassan",
  template_variables: {
    "client_name": "Ahmed Hassan",
    "amount_due": "25000",
    "due_date": "2026-01-01",
    "schedule_id": 2
  },
  scheduled_send_date: "2025-12-20 08:00:00"
}

Response:
{
  "success": true,
  "queue_id": 50,
  "message": "Email queued successfully for scheduled send"
}
\`\`\`

**Step 2: Send Queued Email**
\`\`\`
POST /xhr/manage_inventory_audit_email.php
{
  action: "send_email",
  queue_id: 50
}

Response:
{
  "success": true,
  "delivery_status": "sent",
  "sent_at": "2025-12-20 08:00:15",
  "email_log_id": 100
}
\`\`\`

### Example 3: Audit Trail Query

**Query: Show all payment actions for customer Ahmed Hassan in Nov 2025**
\`\`\`
POST /xhr/manage_inventory_audit_email.php
{
  action: "get_audit_trail",
  client_id: 5,
  action_category: "payment",
  from_date: "2025-11-01",
  to_date: "2025-11-30"
}

Response:
{
  "success": true,
  "audit_logs": [
    {
      "id": 998,
      "action_type": "invoice_created",
      "description": "Invoice INV-2025-001 created for ৳50,000",
      "performed_at": "2025-11-15 10:00:00",
      "performed_by": "admin"
    },
    {
      "id": 1000,
      "action_type": "payment_recorded",
      "description": "Payment of ৳75,000 recorded. ৳25,000 overpayment applied",
      "before_values": {...},
      "after_values": {...},
      "performed_at": "2025-11-20 14:30:00",
      "performed_by": "admin"
    }
  ]
}
\`\`\`

---

## Installation & Deployment

### Prerequisites

- MySQL 5.7+
- PHP 7.4+
- PDO support
- File write permissions for `/xhr/` directory
- Email service (SMTP or PHP mail)
- Apache/Nginx with rewrite enabled

### Installation Steps

**Step 1: Database Migration**
\`\`\`bash
# Run all migrations in order
mysql -u user -p database < database/00_COMPLETE_SCHEMA.sql
\`\`\`

**Step 2: Verify Database**
\`\`\`sql
USE civicbd_group;
-- Check core tables exist
SHOW TABLES LIKE 'crm_%';
SHOW TABLES LIKE 'wo_%';
\`\`\`

**Step 3: Verify Schema**
\`\`\`sql
-- Check crm_invoices table exists
DESC crm_invoices;
-- Check crm_audit_trail table exists
DESC crm_audit_trail;
\`\`\`

**Step 4: Place API Files**
\`\`\`bash
# Copy API endpoint files to xhr directory
cp xhr/manage_inventory_invoice_system.php /path/to/project/xhr/
cp xhr/manage_inventory_audit_email.php /path/to/project/xhr/
# Set proper permissions
chmod 644 xhr/manage_inventory_*.php
\`\`\`

**Step 5: Place Tab Files**
\`\`\`bash
# Copy tab pages
cp manage/pages/clients/tabs/*.phtml /path/to/project/manage/pages/clients/tabs/
\`\`\`

**Step 6: Email Configuration**
\`\`\`php
// In config or bootstrap file
define('MAIL_DRIVER', 'smtp'); // or 'mail'
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@gmail.com');
define('MAIL_PASSWORD', 'app-password');
\`\`\`

**Step 7: Verify Installation**
- Test API endpoints
- Send test email
- Check audit trail logging
- Verify tab pages load

### Post-Deployment

- Create admin user account
- Configure email templates
- Set up backup schedule
- Configure audit log archival (optional)
- Train staff on system usage

---

## Security & Compliance

### Data Protection

- All sensitive data encrypted in transit (HTTPS)
- Database credentials in environment variables
- Parameterized queries prevent SQL injection
- User input validated on client & server

### Access Control

- Role-based access control (Admin, User, Viewer)
- Permission checks on all API endpoints
- Audit trail tracks all user actions
- IP logging for security monitoring

### Audit Trail Compliance

- Complete action history (ZERO DATA LOSS)
- Before/after value tracking
- User attribution on every action
- Timestamp and IP address logged
- Immutable audit records

### Backup & Recovery

\`\`\`bash
# Full backup
mysqldump -u user -p database > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup new tables only
mysqldump -u user -p database crm_* wo_* > backup_new_tables.sql

# Restore
mysql -u user -p database < backup_20251120_120000.sql
\`\`\`

---

## Troubleshooting & Support

### Common Issues

**Issue: Overpayment not applying to next schedule**
- Check: Payment amount > invoice amount
- Check: Next schedule is pending (status = 0)
- Check: API endpoint returning success
- Solution: Verify calculate_overpayment logic in API

**Issue: Email not sending**
- Check: MAIL_HOST and MAIL_PORT configured
- Check: MAIL_USERNAME and MAIL_PASSWORD valid
- Check: Email template exists and readable
- Check: Recipient email address valid
- Solution: Test with: `mail('test@example.com', 'Test', 'Test message')`

**Issue: Audit trail not recording**
- Check: User session has user_id set
- Check: Database write permissions
- Check: SQL errors in log
- Solution: Check `$_SESSION['user_id']` is populated

**Issue: Slow performance on invoice list**
- Check: Table indexes created (see schema)
- Check: Row count in crm_invoices table
- Solution: Add composite index: `ALTER TABLE crm_invoices ADD INDEX idx_composite (purchase_id, status, invoice_date);`

### Error Messages

| Error | Cause | Solution |
|-------|-------|----------|
| "Invalid invoice ID" | Invoice doesn't exist | Verify invoice exists in database |
| "Insufficient payment amount" | Amount < outstanding | Check calculation logic |
| "Email sending failed" | SMTP configuration | Review email config |
| "Audit trail insert failed" | Database error | Check permissions & integrity |

### Support Resources

- **Schema Documentation:** database/00_COMPLETE_SCHEMA.sql
- **API Documentation:** This document (Backend API Reference section)
- **Frontend Code:** manage/pages/clients/tabs/*.phtml
- **Email Templates:** manage/pages/clients/emails/*.php
- **Audit Logs:** Query crm_audit_trail table

---

## Testing Scenarios

### Test Checklist

- [ ] Create invoice for installment
- [ ] Record payment exactly matching invoice amount
- [ ] Record payment exceeding invoice (overpayment test)
- [ ] Verify overpayment credit created
- [ ] Verify credit applied to next schedule
- [ ] Check audit trail has complete records
- [ ] Send payment reminder email
- [ ] Verify email in logs
- [ ] Queue email for scheduled send
- [ ] Transfer purchase to new client
- [ ] Merge two purchases
- [ ] Apply manual adjustment to schedule
- [ ] Waive late fee with admin approval
- [ ] Generate money receipt
- [ ] Export audit trail to PDF

### Production Readiness

- [ ] All API endpoints tested
- [ ] Error handling implemented
- [ ] Database backups automated
- [ ] Email service tested
- [ ] Audit trail verified working
- [ ] Admin approval workflow tested
- [ ] User permissions configured
- [ ] Documentation complete
- [ ] Staff trained
- [ ] System load tested

---

## Deployment Checklist

- [ ] Database migration completed
- [ ] API files in place
- [ ] Tab files in place
- [ ] Email templates configured
- [ ] Permissions set correctly
- [ ] Email service configured
- [ ] Backup schedule created
- [ ] Monitoring configured
- [ ] Security audit passed
- [ ] Performance baseline established
- [ ] Staff trained
- [ ] Go-live approved

---

**End of Documentation**

Generated: 2025-11-20  
System Version: 2.0  
Status: Production Ready

---
