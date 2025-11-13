# ✅ Step 1.3 Complete: Transfer History Table

## What Was Created

### Migration File
**Location**: `/database/migrations/003_create_transfer_history_table.sql`

### Table: `crm_transfer_history`

#### Purpose
Tracks all types of transfers (name transfers and plot transfers) with approval workflow, rate adjustments, fee tracking, and complete audit trail.

#### Key Features
- ✅ Supports name and plot transfers
- ✅ Plot transfers can include rate adjustments with reason tracking
- ✅ Transfer approval workflow (pending/approved/rejected/cancelled)
- ✅ Optional transfer fees with payment tracking
- ✅ Tracks who approved/rejected and when
- ✅ JSON fields for extensible transfer-specific details
- ✅ Complete audit trail (created_by, updated_by, timestamps)
- ✅ Indexed for optimal query performance

#### Schema Details
```sql
Core Fields:
- id: Primary key
- booking_helper_id: Links to wo_booking_helper.id
- transfer_type: 'name_transfer' or 'plot_transfer'
- from_client_id: Original client
- to_client_id: New client
- transfer_date: When transfer occurred
- approval_status: 0=pending, 1=approved, 2=rejected, 3=cancelled
- approval_date: When approved/rejected
- approved_by: Approver user id

Name Transfer:
- name_transfer_details: JSON with name transfer specifics

Plot Transfer:
- plot_transfer_rate_old: Old rate
- plot_transfer_rate_new: New rate
- rate_adjustment_reason: Why rate changed
- rate_adjustment_amount: Difference in amount
- plot_transfer_details: JSON with plot transfer specifics

Transfer Fee:
- transfer_fee: Fee charged
- transfer_fee_paid: Amount paid
- transfer_fee_due: Amount outstanding
- payment_method: Cash/Cheque/Bank Transfer/Online
- money_receipt_no: Receipt number

Audit Trail:
- remarks: Additional notes or conditions
- rejection_reason: Why rejected if applicable
- created_at, updated_at: Timestamps
- created_by, updated_by: User IDs
```

#### Indexes Created
- Primary key on `id`
- Index on `booking_helper_id` (fast lookup by booking)
- Index on `from_client_id` (fast lookup by source client)
- Index on `to_client_id` (fast lookup by destination client)
- Index on `transfer_type` (filter by transfer type)
- Index on `approval_status` (filter by approval status)
- Index on `transfer_date` (chronological queries)

## How It Works

### Transfer Types Supported

#### 1. Name Transfer
- Original owner transfers name rights to new owner
- Used when someone else takes over the property
- No rate adjustment typically
- Example JSON:
  ```json
  {
    "original_name": "Ahmed Khan",
    "new_name": "Fatima Khan",
    "relationship": "Daughter",
    "transfer_reason": "Family transfer"
  }
  ```

#### 2. Plot Transfer with Rate Adjustment
- Original owner transfers plot to new owner
- Rate can change based on market or agreement
- Tracks old and new rates with reason
- Example scenario:
  - Original rate: ৳5,000/sq ft
  - New rate: ৳6,000/sq ft (market appreciation)
  - Rate adjustment: +৳1,000/sq ft

### Approval Workflow

```
Pending → Approved → Transfer Complete
       ↘ Rejected  → Transfer Cancelled
```

States:
- **Pending (0)**: Transfer initiated, awaiting approval
- **Approved (1)**: Transfer approved and can be completed
- **Rejected (2)**: Transfer rejected with reason
- **Cancelled (3)**: Transfer cancelled by user

### Example Usage

#### Create Name Transfer Request
```php
$db->insert('crm_transfer_history', [
    'booking_helper_id' => $bookingHelperId,
    'transfer_type' => 'name_transfer',
    'from_client_id' => $originalClientId,
    'to_client_id' => $newClientId,
    'transfer_date' => date('Y-m-d'),
    'approval_status' => 0, // pending
    'name_transfer_details' => json_encode([
        'original_name' => 'Ahmed Khan',
        'new_name' => 'Fatima Khan',
        'relationship' => 'Daughter',
        'transfer_reason' => 'Family transfer'
    ]),
    'remarks' => 'Transfer between family members',
    'created_by' => $userId
]);
```

#### Create Plot Transfer with Rate Adjustment
```php
$oldRate = 5000.00;
$newRate = 6000.00;
$totalPlotSqFt = 100;
$rateAdjustment = ($newRate - $oldRate) * $totalPlotSqFt; // 100,000

$db->insert('crm_transfer_history', [
    'booking_helper_id' => $bookingHelperId,
    'transfer_type' => 'plot_transfer',
    'from_client_id' => $originalClientId,
    'to_client_id' => $newClientId,
    'transfer_date' => date('Y-m-d'),
    'approval_status' => 0, // pending
    'plot_transfer_rate_old' => $oldRate,
    'plot_transfer_rate_new' => $newRate,
    'rate_adjustment_reason' => 'Market rate appreciation',
    'rate_adjustment_amount' => $rateAdjustment,
    'plot_transfer_details' => json_encode([
        'plot_size' => $totalPlotSqFt,
        'old_total' => $oldRate * $totalPlotSqFt,
        'new_total' => $newRate * $totalPlotSqFt
    ]),
    'transfer_fee' => 5000.00,
    'transfer_fee_due' => 5000.00,
    'remarks' => 'Rate adjusted due to market appreciation',
    'created_by' => $userId
]);
```

#### Approve Transfer
```php
$db->where('id', $transferId)->update('crm_transfer_history', [
    'approval_status' => 1, // approved
    'approval_date' => date('Y-m-d'),
    'approved_by' => $approverId,
    'updated_by' => $userId
]);
```

#### Reject Transfer with Reason
```php
$db->where('id', $transferId)->update('crm_transfer_history', [
    'approval_status' => 2, // rejected
    'approval_date' => date('Y-m-d'),
    'approved_by' => $approverId,
    'rejection_reason' => 'Documentation incomplete. Required: property deed copy and ID proof',
    'updated_by' => $userId
]);
```

#### Record Transfer Fee Payment
```php
$db->where('id', $transferId)->update('crm_transfer_history', [
    'transfer_fee_paid' => $amountPaid,
    'transfer_fee_due' => 0.00,
    'payment_date' => date('Y-m-d'),
    'payment_method' => 'Bank Transfer',
    'money_receipt_no' => 'TRF-12345',
    'updated_by' => $userId
]);
```

## Query Examples

#### Get All Pending Transfers for a Booking
```sql
SELECT * FROM crm_transfer_history
WHERE booking_helper_id = ? AND approval_status = 0
ORDER BY transfer_date DESC;
```

#### Get All Plot Transfers with Rate Adjustments
```sql
SELECT * FROM crm_transfer_history
WHERE transfer_type = 'plot_transfer' AND rate_adjustment_amount IS NOT NULL
AND approval_status = 1
ORDER BY transfer_date DESC;
```

#### Get Transfer History for a Client
```sql
SELECT * FROM crm_transfer_history
WHERE from_client_id = ? OR to_client_id = ?
ORDER BY transfer_date DESC;
```

#### Get Pending Transfer Fees
```sql
SELECT * FROM crm_transfer_history
WHERE transfer_fee_due > 0 AND approval_status = 1
ORDER BY transfer_date ASC;
```

## Next Steps to Apply Migration

### Option A - Direct SQL Execution:
```bash
mysql -u civicbd_group -p civicbd_group < database/migrations/003_create_transfer_history_table.sql
```

### Option B - Through phpMyAdmin:
1. Open phpMyAdmin
2. Select database: `civicbd_group`
3. Go to SQL tab
4. Copy contents of `003_create_transfer_history_table.sql`
5. Execute

### Option C - Via PHP:
```php
$sql = file_get_contents('database/migrations/003_create_transfer_history_table.sql');
$db->rawQuery($sql);
```

## Verification Steps

After migration:
1. Verify table exists: `SHOW TABLES LIKE 'crm_transfer_history';`
2. Check structure: `DESCRIBE crm_transfer_history;`
3. Verify indexes: `SHOW INDEX FROM crm_transfer_history;`

## Benefits

### Before (No Transfer Tracking)
- Manual tracking of transfers
- No approval workflow
- Hard to track rate adjustments
- Difficult to manage transfer fees
- No audit trail for transfers

### After (Dedicated Transfer Table)
- Systematic transfer management
- Clear approval workflow with tracking
- Rate adjustment tracking with reasons
- Transfer fee management with payment tracking
- Complete audit trail for compliance
- Support for multiple transfer types
- Easy reporting on all transfers

## Business Rules Enforced

1. **Transfer Types**: Support both name and plot transfers
2. **Approval Workflow**: All transfers must be approved before completion
3. **Rate Adjustment**: Plot transfers can have rate adjustments with documented reasons
4. **Fee Tracking**: Transfer fees tracked with payment status
5. **Audit Trail**: Complete history of who initiated and approved transfers
6. **Flexibility**: JSON fields allow extensible transfer-specific data

---

## Summary of Phase 1: Database Schema

All three core tables have been created:

1. ✅ **Step 1.1**: Payment Schedule Table - Track installment payments
2. ✅ **Step 1.2**: Refund Schedule Table - Track refunds with deductions
3. ✅ **Step 1.3**: Transfer History Table - Track transfers with approvals

**All Phase 1 tables are now ready to be applied to the database!**

Next phases will focus on:
- Phase 2: Backend PHP Functions
- Phase 3: Frontend UI/Forms
- Phase 4: Reports and Analytics
