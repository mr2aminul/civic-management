# Inventory Module Cleanup Workflow

## Problem Statement
- Remove duplicate and meshed endpoints in `/xhr/manage_inventory`
- Remove `$pdo` usage and replace with existing `$db` (MysqliDb) and `$sqlConnect` (raw mysqli)
- Consolidate redundant code across multiple files
- Ensure all endpoints work with existing global database connections
- Must follow the database schema for all endpoints from `database/00_COMPLETE_SCHEMA.sql`
---

## PHASE 1: Analysis & Planning (COMPLETED ✓)

### Step 1.1: Audit All Files
**Status:** ✓ COMPLETED

**Files Found:**
1. `advanced.php` - Invoices, Email, Audit Trail
2. `cancellations.php` - Plot cancellation, bulk cancellation
3. `clients.php` - Client management, purchase details
4. `inventory_audit.php` - Audit trail operations
5. `inventory_audit_email.php` - Email queue & logs (uses `$pdo`)
6. `inventory_complete.php` - Schedules, Invoices, Email (uses `$pdo`)
7. `inventory_crud.php` - Basic CRUD operations
8. `inventory_emails.php` - Email management
9. `inventory_invoice_system.php` - Invoice system (uses `$pdo`)
10. `inventory_invoices.php` - Invoice operations
11. `inventory_merge_purchase.php` - Merge purchases (uses `$pdo`)
12. `pending_changes.php` - Approval workflow
13. `payment_schedules.php` - Schedule viewing & export
14. `plot_management.php` - Plot availability
15. `purchases.php` - Purchase registration
16. `reschedules.php` - Payment rescheduling
17. `transfers.php` - Plot & name transfers

**Duplicate Categories Found:**
- **Invoices:** `advanced.php`, `inventory_complete.php`, `inventory_invoice_system.php`, `inventory_invoices.php`
- **Emails:** `advanced.php`, `inventory_audit_email.php`, `inventory_complete.php`, `inventory_emails.php`
- **Audit Trail:** `advanced.php`, `inventory_audit.php`, `inventory_audit_email.php`, `inventory_complete.php`
- **Payment Schedules:** Multiple files handle schedule operations

---

## PHASE 2: Database Connection Replacement

### Step 2.1: Fix `inventory_audit_email.php`
**Status:** ✓ COMPLETED
**Tokens Used:** ~12k

**Changes Made:**
- ✓ Replaced all `$pdo` with `$db` (MysqliDb)
- ✓ Converted `$pdo->prepare()` to `$db->insert()`, `$db->update()`, `$db->get()`
- ✓ Converted `$pdo->query()` to `$db->where()->get()` with chaining
- ✓ Removed `PDO::FETCH_ASSOC` - MysqliDb returns assoc arrays by default
- ✓ Removed transaction calls (MysqliDb not needed for simple operations)
- ✓ All 6 endpoints fixed

**Endpoints Fixed:**
1. `get_audit_trail` - Uses $db->where()->orderBy()->limit()->get()
2. `queue_email` - Uses $db->insert() with data array
3. `get_queued_emails` - Uses $db->where()->orderBy()->limit()->get()
4. `send_email` - Uses $db->getOne(), $db->update(), $db->insert()
5. `get_email_logs` - Uses $db->where()->orderBy()->limit()->get()
6. `get_email_templates` - No DB changes needed (filesystem only)

---

### Step 2.2: Fix `inventory_complete.php`
**Status:** PENDING
**Estimated Tokens:** ~20k

**Tasks:**
- Replace all `$pdo` with `$db`
- Replace database class instantiation
- Fix all SQL queries to use MysqliDb syntax
- Replace `$stmt->fetchAll()` with `$db->get()`
- Remove PDO-specific error handling

**Endpoints to Fix:**
1. `get_schedules_list`
2. `get_invoices_list`
3. `record_invoice_payment`
4. `get_pending_emails`
5. `send_bulk_emails`
6. `get_audit_trail`

---

### Step 2.3: Fix `inventory_invoice_system.php`
**Status:** PENDING
**Estimated Tokens:** ~18k

**Tasks:**
- Replace `$pdo` with `$db`
- Fix transaction handling
- Replace prepared statements with MysqliDb methods
- Update audit logging helper function

**Endpoints to Fix:**
1. `create_invoice`
2. `get_invoices`
3. `record_payment` (complex overpayment logic)
4. `get_credits`
5. `apply_credit`

---

### Step 2.4: Fix `inventory_merge_purchase.php`
**Status:** PENDING
**Estimated Tokens:** ~16k

**Tasks:**
- Replace `$pdo` with `$db`
- Fix merge request creation
- Fix approval/rejection logic
- Fix merge execution with proper transaction handling

**Endpoints to Fix:**
1. `create_merge_request`
2. `get_merge_requests`
3. `approve_merge`
4. `reject_merge`
5. `execute_merge`

---

## PHASE 3: Remove Duplicates & Consolidate

### Step 3.1: Consolidate Invoice Endpoints
**Status:** PENDING
**Estimated Tokens:** ~12k

**Action Plan:**
- Keep: `inventory_invoices.php` (most complete)
- Merge unique functions from:
  - `advanced.php` → `create_invoice`, `update_invoice_status`
  - `inventory_invoice_system.php` → overpayment credit logic
  - `inventory_complete.php` → invoice payment recording
- Remove duplicate code from other files
- Create single source of truth for invoice operations

**Functions to Consolidate:**
- `get_invoices` / `get_invoices_for_purchase` / `get_invoices_list`
- `create_invoice` / `create_invoice_from_installments`
- `record_payment` / `record_invoice_payment`
- `update_invoice_status`

---

### Step 3.2: Consolidate Email Endpoints
**Status:** PENDING
**Estimated Tokens:** ~10k

**Action Plan:**
- Keep: `inventory_emails.php` (cleaner structure)
- Merge unique functions from:
  - `inventory_audit_email.php` → email queue system
  - `advanced.php` → email sending logic
  - `inventory_complete.php` → bulk email sending
- Remove duplicate code

**Functions to Consolidate:**
- `get_pending_emails` (appears 3 times)
- `send_email` / `send_email_to_client` / `send_bulk_emails`
- `get_email_logs`
- `queue_email`

---

### Step 3.3: Consolidate Audit Trail Endpoints
**Status:** PENDING
**Estimated Tokens:** ~8k

**Action Plan:**
- Keep: `inventory_audit.php` (focused file)
- Merge helper functions from other files
- Standardize `logAudit()` / `logAuditTrail()` helper
- Remove duplicate implementations

**Functions to Consolidate:**
- `get_audit_trail` (appears 3 times)
- `log_audit_action` / `logAudit()` / `logAuditTrail()`
- `get_audit_summary`

---

### Step 3.4: Consolidate Payment Schedule Operations
**Status:** PENDING
**Estimated Tokens:** ~10k

**Action Plan:**
- Keep: `payment_schedules.php` as base
- Merge schedule modification logic from:
  - `inventory_complete.php`
  - `reschedules.php`
- Remove duplicate viewing functions

---

### Step 3.5: Remove Advanced.php Duplicates
**Status:** PENDING
**Estimated Tokens:** ~8k

**Action Plan:**
- Extract unique endpoints only
- Move duplicates to consolidated files
- Keep only:
  - `calculate_overdue_fees`
  - `exclude_late_fees`
  - `auto_generate_receipt`
  - `recalculate_purchase_totals`
- Remove all other functions (already in specialized files)

---

## PHASE 4: Final Cleanup & Testing

### Step 4.1: Remove Empty/Redundant Files
**Status:** PENDING
**Estimated Tokens:** ~5k

**Files to Remove:**
- Files that become empty after consolidation
- Backup any removed code to comments

---

### Step 4.2: Create Endpoint Documentation
**Status:** PENDING
**Estimated Tokens:** ~8k

**Tasks:**
- Document all available endpoints
- Document required parameters
- Document expected responses
- Create quick reference guide

---

### Step 4.3: Test All Endpoints
**Status:** PENDING
**Estimated Tokens:** ~10k

**Tasks:**
- Test each consolidated endpoint
- Verify database connections work
- Verify no $pdo references remain
- Check for broken functionality

---

## PHASE 5: Optimization (Optional)

### Step 5.1: Add Response Standardization
**Status:** PENDING
**Estimated Tokens:** ~8k

**Tasks:**
- Standardize all JSON responses
- Add consistent error handling
- Add request validation helpers

---

### Step 5.2: Add Security Checks
**Status:** PENDING
**Estimated Tokens:** ~8k

**Tasks:**
- Add admin checks where needed
- Add input sanitization
- Add SQL injection prevention

---

## Token Budget Tracking

| Phase | Step | Est. Tokens | Status |
|-------|------|-------------|--------|
| 1 | 1.1 | 5k | ✓ COMPLETED |
| 2 | 2.1 | 15k | PENDING |
| 2 | 2.2 | 20k | PENDING |
| 2 | 2.3 | 18k | PENDING |
| 2 | 2.4 | 16k | PENDING |
| 3 | 3.1 | 12k | PENDING |
| 3 | 3.2 | 10k | PENDING |
| 3 | 3.3 | 8k | PENDING |
| 3 | 3.4 | 10k | PENDING |
| 3 | 3.5 | 8k | PENDING |
| 4 | 4.1 | 5k | PENDING |
| 4 | 4.2 | 8k | PENDING |
| 4 | 4.3 | 10k | PENDING |
| 5 | 5.1 | 8k | PENDING |
| 5 | 5.2 | 8k | PENDING |
| **TOTAL** | | **166k** | |

---

## How to Use This Workflow

1. **Complete ONE step at a time**
2. **Request next step** by saying: "Complete Step X.X"
3. **Review changes** before moving to next step
4. **Test endpoints** after each phase completes
5. **Ask questions** if anything is unclear

---

## Current Status

**Next Step:** Step 2.1 - Fix `inventory_audit_email.php`

**Command to proceed:**
```
Complete Step 2.1
```

---

## Notes

- Each step is designed to stay under 25k tokens
- Database connection changes are isolated per file
- Consolidation happens after all files use correct DB connection
- Testing happens incrementally
- Rollback possible at any step

