# ✅ Step 1.1 Complete: Payment Schedule Table

## What Was Created

### Migration File
**Location**: `/database/migrations/001_create_payment_schedule_table.sql`

### Table: `crm_payment_schedule`

#### Purpose
Replaces the serialized `installment` field in `wo_booking_helper` with a proper relational table structure for better data management, querying, and maintenance.

#### Key Features
- ✅ Individual row per installment
- ✅ Tracks payment status (pending/paid/partial/overdue)
- ✅ Supports partial payments
- ✅ Audit trail (created_by, updated_by, timestamps)
- ✅ Adjustment entries support
- ✅ Indexed for optimal query performance

#### Schema Details
```sql
Fields:
- id: Primary key
- booking_helper_id: Links to wo_booking_helper.id
- client_id: Links to crm_customers.id
- installment_number: Sequence (1, 2, 3...)
- particular: Description ("1st Installment", etc.)
- due_date: When payment is due
- amount: Installment amount
- paid_amount: Amount paid (for partial payments)
- payment_date: When paid
- payment_method: Cash/Cheque/Bank Transfer/Online
- money_receipt_no: Receipt number
- remarks: Additional notes
- status: 0=pending, 1=paid, 2=partial, 3=overdue
- is_adjustment: 0=regular, 1=adjustment
- created_at, updated_at: Timestamps
- created_by, updated_by: User IDs
```

#### Indexes Created
- Primary key on `id`
- Index on `booking_helper_id` (fast lookup by booking)
- Index on `client_id` (fast lookup by client)
- Index on `due_date` (for cron job overdue checks)
- Index on `status` (filter by payment status)
- Index on `installment_number` (ordering)

## Next Steps Required

### To Apply This Migration:
You need to run this SQL in your MySQL database. You can:

**Option A - Direct SQL Execution:**
```bash
mysql -u civicbd_group -p civicbd_group < database/migrations/001_create_payment_schedule_table.sql
```

**Option B - Through phpMyAdmin:**
1. Open phpMyAdmin
2. Select database: `civicbd_group`
3. Go to SQL tab
4. Copy contents of `001_create_payment_schedule_table.sql`
5. Execute

**Option C - Via PHP:**
```php
// In your PHP code
$sql = file_get_contents('database/migrations/001_create_payment_schedule_table.sql');
$db->rawQuery($sql);
```

### After Migration:
1. Verify table exists: `SHOW TABLES LIKE 'crm_payment_schedule';`
2. Check structure: `DESCRIBE crm_payment_schedule;`
3. Proceed to **Step 1.2**

## Benefits of This Approach

### Before (Serialized Data)
```php
// Hard to query
$installment = unserialize($helper->installment);
// Hard to update single installment
// Hard to join with other tables
// Hard to create reports
```

### After (Relational Table)
```php
// Easy to query
$installments = $db->where('booking_helper_id', $id)->get('crm_payment_schedule');
// Easy to update
$db->where('id', $installment_id)->update('crm_payment_schedule', ['status' => 1]);
// Easy to join
// Easy to create reports
```

---

## Ready for Step 1.2?

Once you've applied this migration, let me know and I'll provide:

**Step 1.2: Create Refund Schedule Table**

This will include:
- `crm_refund_schedule` table
- Deduction tracking (5-25%)
- Multiple refund installments
- Balance calculation fields
