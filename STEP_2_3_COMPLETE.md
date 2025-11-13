# ✅ Step 2.3 Complete: Refund Functions

## What Was Created

### Function File
**Location**: `/assets/includes/crm_refund_functions.php`

### Core Features Implemented

#### 1. Refund Calculation
- **calculate_refund()** - Calculates refundable amount with deduction (5-25%)
- Automatically sums all paid amounts from payment schedule
- Includes booking money and down payment in total paid calculation
- Returns complete breakdown of refund calculation

#### 2. Refund Schedule Creation
- **create_refund_schedule()** - Creates refund installment schedule
- One-time deduction applied at initiation
- Flexible installment count (1 or more)
- Automatic due date calculation (monthly intervals)
- Handles rounding in last installment

#### 3. Refund Payment Tracking
- **add_refund_payment()** - Records refund payments
- Supports partial payments
- Automatic status updates (pending/partial/paid)
- Caps paid amount at installment amount

#### 4. Status Management
- **cancel_refund_schedule()** - Cancels pending refunds
- **get_refund_schedule()** - Retrieves refund schedule
- **get_refund_schedule_summary()** - Provides summary statistics
- **get_pending_refunds()** - Admin view of pending refunds
- **get_overdue_refunds()** - Finds overdue refund installments

## Function Signatures

### Calculate Refund
```php
calculate_refund($booking_helper_id, $deduction_percentage = 10.00)
```

### Create Schedule
```php
create_refund_schedule($booking_helper_id, $deduction_percentage, $num_installments = 1, $start_date = null, $created_by = null)
```

### Payment Management
```php
add_refund_payment($refund_schedule_id, $amount, $payment_date = null, $payment_method = null, $receipt_no = null, $remarks = null, $updated_by = null)
cancel_refund_schedule($booking_helper_id, $reason = null, $updated_by = null)
```

### Reporting
```php
get_refund_schedule($booking_helper_id, $filters = [])
get_refund_schedule_summary($booking_helper_id)
get_pending_refunds($filters = [])
get_overdue_refunds()
```

## Usage Examples

### Example 1: Calculate Refund
```php
// Calculate refund with 15% deduction
$result = calculate_refund(123, 15.00);

if ($result['status'] === 200) {
    echo "Total Paid: ৳" . number_format($result['total_paid']);
    echo "Deduction (15%): ৳" . number_format($result['deduction_amount']);
    echo "Refundable: ৳" . number_format($result['refundable_amount']);
}
```

### Example 2: Create Refund Schedule
```php
// Create refund schedule with 4 monthly installments, 10% deduction
$result = create_refund_schedule(
    123,              // booking_helper_id
    10.00,            // deduction_percentage
    4,                // num_installments
    '2025-02-01',     // start_date
    $current_user_id  // created_by
);

if ($result['status'] === 200) {
    echo "Created {$result['count']} refund installments";
    echo "Total refundable: ৳" . number_format($result['refundable_amount']);
    echo "Deduction: ৳" . number_format($result['deduction_amount']);
}
```

### Example 3: Record Refund Payment
```php
// Record partial refund payment
$result = add_refund_payment(
    456,                    // refund_schedule_id
    50000.00,               // amount
    date('Y-m-d'),          // payment_date
    'Bank Transfer',        // payment_method
    'REF-2025-001',         // receipt_no
    'First installment',    // remarks
    $current_user_id        // updated_by
);

if ($result['status'] === 200) {
    echo "Payment recorded: ৳" . number_format($result['new_paid_amount']);
    // payment_status: 0=pending, 1=paid, 2=partial
}
```

### Example 4: Get Refund Summary
```php
$summary = get_refund_schedule_summary(123);

if ($summary) {
    echo "Total Paid: ৳" . number_format($summary['total_paid_amount']);
    echo "Deduction: ৳" . number_format($summary['deduction_amount']);
    echo "Refundable: ৳" . number_format($summary['refundable_amount']);
    echo "Refunded So Far: ৳" . number_format($summary['total_refunded']);
    echo "Remaining: ৳" . number_format($summary['remaining']);
    echo "Pending Installments: " . $summary['pending_count'];
}
```

### Example 5: Cancel Refund
```php
$result = cancel_refund_schedule(
    123,
    'Client changed mind',
    $admin_user_id
);

if ($result['status'] === 200) {
    echo "Cancelled {$result['count']} refund installments";
}
```

### Example 6: Admin Dashboard - Pending Refunds
```php
// Get all pending refunds due this month
$pending = get_pending_refunds([
    'due_before' => date('Y-m-t') // end of current month
]);

foreach ($pending as $refund) {
    echo "Client: {$refund->client_id}, Due: {$refund->due_date}, Amount: ৳{$refund->installment_amount}";
}

// Get overdue refunds
$overdue = get_overdue_refunds();
echo "Found " . count($overdue) . " overdue refund payments";
```

## Key Features

### Data Safety
- All operations wrapped in transactions
- Rollback on any failure
- Validates existence of booking before processing
- Prevents duplicate refund schedules

### Automatic Calculations
- Sums all paid amounts from payment schedule
- Includes booking money and down payment
- Handles deduction calculation (5-25%)
- Manages rounding in last installment

### Status Management
- 0 = pending (not yet refunded)
- 1 = paid (fully refunded)
- 2 = partial (partially refunded)
- 3 = cancelled (refund cancelled)

### Audit Trail
- created_by, updated_by tracking
- Timestamps (created_at, updated_at)
- Remarks field for notes
- Complete payment history

## Business Rules Enforced

1. **Deduction Range**: Deduction must be between 5% and 25%
2. **One-Time Deduction**: Applied only at refund initiation
3. **No Duplicates**: One active refund schedule per booking
4. **Paid Amount Cap**: Cannot exceed installment amount
5. **Status Tracking**: Automatic status updates based on payments

## Database Tables Used

### Primary Table
- `crm_refund_schedule` - All refund entries and payments

### Referenced Tables
- `wo_booking_helper` - Source of booking and payment data
- `crm_payment_schedule` - To calculate total paid amount

## Return Format

All functions return consistent format:
```php
[
    'status' => 200|400|404|500,
    'message' => 'Human readable message',
    // Additional data based on function
]
```

## Integration Points

### Required for Next Steps
- XHR endpoints (Phase 3) will call these functions
- UI modals (Phase 4) will trigger via AJAX
- Admin dashboard can display pending/overdue refunds
- Email notifications can be added to refund approval flow

### Dependencies
- Requires `crm_refund_schedule` table (Step 1.2)
- Uses `crm_payment_schedule` table (Step 1.1)
- Uses existing `T_BOOKING_HELPER` table
- Uses existing `T_CUSTOMERS` table

## Testing Checklist

- [ ] Calculate refund: Verify deduction calculation (5%, 10%, 25%)
- [ ] Create schedule: 1 installment, 3 installments, 12 installments
- [ ] Record payment: Full payment, partial payment, overpayment (should cap)
- [ ] Get summary: Verify totals, pending count, remaining amount
- [ ] Cancel refund: Verify all pending entries marked as cancelled
- [ ] Duplicate prevention: Try creating second refund schedule (should fail)
- [ ] Overdue detection: Create refund with past due date, verify appears in overdue list
- [ ] Invalid data: Deduction 30% (should fail), negative amount (should fail)

---

## Summary: Phase 2 Backend Functions Complete!

All three function files have been created:

1. ✅ **Step 2.1**: Payment Schedule Functions - Track installment payments
2. ✅ **Step 2.2**: Transfer Functions - Handle name/plot transfers with approvals
3. ✅ **Step 2.3**: Refund Functions - Process refunds with deductions

**Phase 2 is now complete!**

Next phases will focus on:
- **Phase 3**: XHR Endpoints - Connect frontend to backend functions
- **Phase 4**: UI Components - Build user interfaces for all features
- **Phase 5**: Email & Automation - Add notifications and cron jobs

## Ready for Phase 3?

Once testing is complete, proceed to:

**Phase 3: XHR Endpoints**

This will include:
- Payment schedule endpoints (view, update, recalculate)
- Transfer endpoints (initiate, approve, reject, history)
- Refund endpoints (calculate, create, payment, cancel)
- All AJAX handlers for frontend integration
