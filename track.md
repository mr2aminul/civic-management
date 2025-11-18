# Inventory Module Cleanup Workflow

## Problem Statement
- Remove duplicate and meshed endpoints in `/xhr/manage_inventory`
- Remove `$pdo` usage and replace with existing `$db` (MysqliDb) and `$sqlConnect` (raw mysqli)
- Consolidate redundant code across multiple files
- Ensure all endpoints work with existing global database connections
- Must follow the database schema for all endpoints from `database/00_COMPLETE_SCHEMA.sql`
- Must be remove duplicate files or endpoints, keep the working endpoins version of duplicate endpoints.
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
**Status:** ✓ COMPLETED
**Tokens Used:** ~9k

**Changes Made:**
- ✓ Removed `$db = new Database()` and replaced with global `$db` (MysqliDb)
- ✓ Converted all `$db->query()->fetch()` to `$db->where()->getOne()`
- ✓ Converted all `$db->query()->fetchAll()` to `$db->where()->get()`
- ✓ Replaced raw SQL queries with MysqliDb chaining methods
- ✓ Fixed action detection to use `$_POST['s']` and `$_GET['s']`
- ✓ Removed PDO-specific error handling
- ✓ Added graceful handling for missing tables (crm_overpayment_distribution)
- ✓ Updated logAuditAction helper to use global $db and $wo

**Endpoints Fixed:**
1. `get_schedules_list` - Uses $db->where()->orderBy()->get()
2. `get_invoices_list` - Uses $db->where()->orderBy()->get()
3. `record_invoice_payment` - Uses $db->getOne(), $db->update(), $db->insert()
4. `get_pending_emails` - Uses $db->where()->get()
5. `send_bulk_emails` - Uses $db->getOne(), $db->update()
6. `get_audit_trail` - Uses $db->where()->orderBy()->limit()->get()

---

### Step 2.3: Fix `inventory_invoice_system.php`
**Status:** ✓ COMPLETED
**Tokens Used:** ~8k

**Changes Made:**
- ✓ Replaced all `$pdo` with `$db` (MysqliDb)
- ✓ Fixed action detection to use `$_POST['s']` and `$_GET['s']`
- ✓ Converted `$pdo->query()` to `$db->where()->getOne()` and `$db->get()`
- ✓ Converted `$pdo->prepare()->execute()` to `$db->insert()` and `$db->update()`
- ✓ Replaced `$pdo->beginTransaction()` with `mysqli_begin_transaction($sqlConnect)`
- ✓ Replaced `$pdo->commit()` with `mysqli_commit($sqlConnect)`
- ✓ Replaced `$pdo->rollBack()` with `mysqli_rollback($sqlConnect)`
- ✓ Updated `logAuditTrail()` helper to use `$db->insert()`
- ✓ Added graceful handling for missing overpayment_credits table
- ✓ Fixed all 5 endpoints to use MysqliDb methods

**Endpoints Fixed:**
1. `create_invoice` - Uses $db->where(), $db->insert(), $db->getValue()
2. `get_invoices` - Uses $db->where()->orderBy()->get()
3. `record_payment` - Uses mysqli transactions, $db->getOne(), $db->update(), $db->insert()
4. `get_credits` - Uses $db->where()->orderBy()->get()
5. `apply_credit` - Uses mysqli transactions, $db->getOne(), $db->update()

---

### Step 2.4: Fix `inventory_merge_purchase.php`
**Status:** ✓ COMPLETED
**Tokens Used:** ~8k

**Changes Made:**
- ✓ Replaced all `$pdo` with `$db` (MysqliDb)
- ✓ Fixed action detection to use `$_POST['s']` and `$_GET['s']`
- ✓ Converted `$pdo->query()` to `$db->where()->getOne()` and `$db->get()`
- ✓ Converted `$pdo->prepare()->execute()` to `$db->insert()` and `$db->update()`
- ✓ Replaced `$pdo->beginTransaction()` with `mysqli_begin_transaction($sqlConnect)`
- ✓ Replaced `$pdo->commit()` with `mysqli_commit($sqlConnect)`
- ✓ Replaced `$pdo->rollBack()` with `mysqli_rollback($sqlConnect)`
- ✓ Updated `logAuditTrail()` helper to use `$db->insert()`
- ✓ Added graceful handling for missing overpayment_credits table
- ✓ Fixed all 5 endpoints to use MysqliDb methods
- ✓ Used JOIN syntax for complex queries in getMergeRequests()

**Endpoints Fixed:**
1. `create_merge_request` - Uses $db->where(), $db->getOne(), $db->insert()
2. `get_merge_requests` - Uses $db->join(), $db->where()->orderBy()->get()
3. `approve_merge` - Uses mysqli transactions, $db->getOne(), $db->update()
4. `reject_merge` - Uses $db->getOne(), $db->update()
5. `execute_merge` - Uses mysqli transactions, $db->where(), $db->update(), $db->getValue(), $db->insert()

---

## PHASE 3: Remove Duplicates & Consolidate

### Step 3.1: Consolidate Invoice Endpoints
**Status:** ✓ COMPLETED
**Actual Tokens:** ~6k

**Actions Taken:**
- Created: `invoices.php` (consolidated single file)
- Merged functionality from:
  - `advanced.php` → `create_invoice`, `update_invoice_status`, money receipt generation
  - `inventory_invoices.php` → schedule sync, installment-based invoices
  - `inventory_invoice_system.php` → overpayment credit logic, credit application
  - `inventory_complete.php` → payment recording with overpayment handling
- Standardized endpoint names with aliases for backward compatibility
- Single source of truth for all invoice operations

**Consolidated Endpoints:**
1. `get_invoices` / `get_invoices_for_purchase` / `get_invoices_list` → unified with filters
2. `create_invoice` / `create_invoice_from_installments` → merged with type detection
3. `record_invoice_payment` / `record_payment` → unified with overpayment handling
4. `update_invoice_status` → single implementation
5. `generate_money_receipt` / `auto_generate_receipt` → unified
6. `get_credits` / `get_overpayment_credits` → merged with graceful table handling
7. `apply_credit` → single implementation with full tracking

**Result:** All invoice operations now in `/xhr/manage_inventory/invoices.php`

**Note:** Old files (`advanced.php`, `inventory_invoices.php`, `inventory_invoice_system.php`) still contain invoice code but will be cleaned in Step 3.1b or removed entirely if they become empty.

---

### Step 3.2: Consolidate Email Endpoints
**Status:** ✓ COMPLETED
**Actual Tokens:** ~5k

**Actions Taken:**
- Created: `emails.php` (consolidated single file)
- Merged functionality from:
  - `inventory_emails.php` → basic email sending and pending emails
  - `inventory_audit_email.php` → email queue system, template handling
  - `advanced.php` → direct email sending with logging
  - `inventory_complete.php` → bulk email sending
- Standardized endpoint names with backward compatibility
- Single source of truth for all email operations

**Consolidated Endpoints:**
1. `get_pending_emails` → unified with money receipt + schedule detection
2. `queue_email` → queue system for scheduled emails
3. `get_queued_emails` → retrieve queued emails with filters
4. `send_email` / `send_email_to_client` → unified direct and queue-based sending
5. `send_bulk_emails` → batch email processing
6. `get_email_logs` → email history with filters
7. `get_email_templates` → list available templates

**Result:** All email operations now in `/xhr/manage_inventory/emails.php`

**Note:** Old files (`inventory_emails.php`, `inventory_audit_email.php`, `advanced.php`) still contain email code but will be cleaned in subsequent steps or removed if they become empty.

---

### Step 3.3: Consolidate Audit Trail Endpoints
**Status:** ✓ COMPLETED
**Actual Tokens:** ~4k

**Actions Taken:**
- Created: `audit.php` (consolidated single file)
- Merged functionality from:
  - `inventory_audit.php` → audit retrieval, manual logging, summary
  - `advanced.php` → logAudit helper function
  - `inventory_complete.php` → logAuditAction helper
  - `inventory_merge_purchase.php` → logAuditTrail helper
- Standardized endpoint names with backward compatibility
- Single source of truth for all audit operations

**Consolidated Endpoints:**
1. `get_audit_trail` → unified with all filters (purchase, client, category, date range, user)
2. `log_audit_action` / `log_audit` → merged manual logging endpoints
3. `get_audit_summary` → category-based summary
4. `get_recent_audit` → quick recent activity view

**Helper Functions:**
- `logAuditAction()` → primary standardized function
- `logAudit()` → backward compatibility alias
- `logAuditTrail()` → backward compatibility alias

**Result:** All audit operations now in `/xhr/manage_inventory/audit.php`

**Note:** Old files (`inventory_audit.php`, `advanced.php`, `inventory_complete.php`) still contain audit code but will be cleaned in subsequent steps or removed if they become empty.

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
| 2 | 2.1 | 12k | ✓ COMPLETED |
| 2 | 2.2 | 9k | ✓ COMPLETED |
| 2 | 2.3 | 8k | ✓ COMPLETED |
| 2 | 2.4 | 8k | ✓ COMPLETED |
| 3 | 3.1 | 6k | ✓ COMPLETED |
| 3 | 3.2 | 5k | ✓ COMPLETED |
| 3 | 3.3 | 4k | ✓ COMPLETED |
| 3 | 3.4 | 10k | PENDING |
| 3 | 3.5 | 8k | PENDING |
| 4 | 4.1 | 5k | PENDING |
| 4 | 4.2 | 8k | PENDING |
| 4 | 4.3 | 10k | PENDING |
| 5 | 5.1 | 8k | PENDING |
| 5 | 5.2 | 8k | PENDING |
| **TOTAL** | | **158k** | |

---

## How to Use This Workflow

1. **Complete ONE step at a time**
2. **Request next step** by saying: "Complete Step X.X"
3. **Review changes** before moving to next step
4. **Test endpoints** after each phase completes
5. **Ask questions** if anything is unclear

---

## Current Status

**Phase 3 Steps 3.1, 3.2, and 3.3 COMPLETED!** Invoice, Email, and Audit Trail endpoints consolidated.

**Next Step:** Step 3.4 - Consolidate Payment Schedule Operations

**Command to proceed:**
```
Complete Step 3.4
```

---

## Notes

- Each step is designed to stay under 25k tokens
- Database connection changes are isolated per file
- Consolidation happens after all files use correct DB connection
- Testing happens incrementally
- Rollback possible at any step

