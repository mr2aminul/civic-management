# ✅ Step 1.2 Complete: Refund Schedule Table

## What Was Created

### Migration File
**Location**: `/database/migrations/002_create_refund_schedule_table.sql`

### Table: `crm_refund_schedule`

#### Purpose
Manages refund installments when clients request refunds, with one-time deduction (5-25%) applied at initiation and flexible installment scheduling.

#### Key Features
- ✅ One-time deduction at refund initiation (5-25%)
- ✅ Tracks total paid amount and calculates refundable amount
- ✅ Supports multiple refund installments
- ✅ Individual row per refund installment
- ✅ Payment status tracking (pending/paid/partial/cancelled)
- ✅ Supports partial payments
- ✅ Audit trail (created_by, updated_by, timestamps)
- ✅ Indexed for optimal query performance

#### Schema Details
```sql
Fields:
- id: Primary key
- booking_helper_id: Links to wo_booking_helper.id
- client_id: Links to crm_customers.id
- refund_initiation_date: When refund was requested
- total_paid_amount: Total amount client has paid
- deduction_percentage: Penalty % (5-25%)
- deduction_amount: Calculated penalty amount
- refundable_amount: Amount to refund after deduction
- installment_number: Sequence (1, 2, 3...)
- installment_amount: Amount for this refund installment
- due_date: When this refund installment is due
- paid_amount: Amount actually refunded
- payment_date: When refund was paid
- payment_method: Cash/Cheque/Bank Transfer/Online
- money_receipt_no: Receipt number
- remarks: Additional notes
- status: 0=pending, 1=paid, 2=partial, 3=cancelled
- created_at, updated_at: Timestamps
- created_by, updated_by: User IDs
```

#### Indexes Created
- Primary key on `id`
- Index on `booking_helper_id` (fast lookup by booking)
- Index on `client_id` (fast lookup by client)
- Index on `due_date` (for scheduling)
- Index on `status` (filter by payment status)
- Index on `installment_number` (ordering)

## How It Works

### Refund Calculation Flow
1. **Initiation**: Client requests refund
2. **Calculate Deduction**: Apply one-time penalty (5-25%)
   - Example: Paid ৳500,000, Deduction 10% = ৳50,000
   - Refundable: ৳450,000
3. **Schedule Installments**: Divide refundable amount into installments
   - Example: 3 installments of ৳150,000 each
4. **Track Payments**: Mark each installment as paid when completed

### Example Usage

#### Calculate Refund with 10% Deduction
```php
$totalPaid = 500000;
$deductionPercentage = 10.00;
$deductionAmount = $totalPaid * ($deductionPercentage / 100); // 50000
$refundableAmount = $totalPaid - $deductionAmount; // 450000
```

#### Create Refund Schedule (3 installments)
```php
$numberOfInstallments = 3;
$installmentAmount = $refundableAmount / $numberOfInstallments; // 150000

for ($i = 1; $i <= $numberOfInstallments; $i++) {
    $db->insert('crm_refund_schedule', [
        'booking_helper_id' => $bookingHelperId,
        'client_id' => $clientId,
        'refund_initiation_date' => date('Y-m-d'),
        'total_paid_amount' => $totalPaid,
        'deduction_percentage' => $deductionPercentage,
        'deduction_amount' => $deductionAmount,
        'refundable_amount' => $refundableAmount,
        'installment_number' => $i,
        'installment_amount' => $installmentAmount,
        'due_date' => date('Y-m-d', strtotime("+{$i} month")),
        'status' => 0, // pending
        'created_by' => $userId
    ]);
}
```

#### Update Refund Payment
```php
$db->where('id', $installmentId)->update('crm_refund_schedule', [
    'paid_amount' => $amount,
    'payment_date' => date('Y-m-d'),
    'payment_method' => 'Bank Transfer',
    'money_receipt_no' => 'REF-12345',
    'status' => 1, // paid
    'updated_by' => $userId
]);
```

## Next Steps to Apply Migration

### Option A - Direct SQL Execution:
```bash
mysql -u civicbd_group -p civicbd_group < database/migrations/002_create_refund_schedule_table.sql
```

### Option B - Through phpMyAdmin:
1. Open phpMyAdmin
2. Select database: `civicbd_group`
3. Go to SQL tab
4. Copy contents of `002_create_refund_schedule_table.sql`
5. Execute

### Option C - Via PHP:
```php
$sql = file_get_contents('database/migrations/002_create_refund_schedule_table.sql');
$db->rawQuery($sql);
```

## Verification Steps

After migration:
1. Verify table exists: `SHOW TABLES LIKE 'crm_refund_schedule';`
2. Check structure: `DESCRIBE crm_refund_schedule;`
3. Verify indexes: `SHOW INDEX FROM crm_refund_schedule;`

## Benefits

### Before (No Refund Tracking)
- No systematic refund process
- Manual calculation of deductions
- Hard to track refund installments
- No audit trail for refunds

### After (Dedicated Refund Table)
- Clear refund calculation with deduction tracking
- Flexible refund installment scheduling
- Complete payment status tracking
- Full audit trail for compliance
- Easy reporting on refunds

## Business Rules Enforced

1. **One-Time Deduction**: Penalty applied only at refund initiation
2. **Flexible Percentage**: Deduction between 5-25% based on policy
3. **Installment Support**: Refund can be paid in multiple installments
4. **Status Tracking**: Each installment tracked separately
5. **Audit Trail**: Complete history of who created/updated records

---

## Ready for Step 1.3?

Once you've applied this migration, proceed to:

**Step 1.3: Create Transfer History Table**

This will include:
- `crm_transfer_history` table
- Name transfer tracking
- Plot transfer tracking with rate adjustments
- Transfer approval workflow
- Complete audit trail for all transfers
