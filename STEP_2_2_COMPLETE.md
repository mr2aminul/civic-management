# Step 2.2 Complete: Transfer Functions

## What Was Created

### Function File
**Location**: `/assets/includes/crm_transfer_functions.php`

### Core Features Implemented

#### 1. Name Transfer Functions
- **initiate_name_transfer()** - Creates transfer request to change client ownership
- **approve_name_transfer()** - Approves and executes name transfer
- Maintains plot and complete history
- Updates booking_helper.client_id on approval

#### 2. Plot Transfer Functions
- **initiate_plot_transfer()** - Creates request to change plot assignment
- **approve_plot_transfer()** - Approves and executes plot transfer
- Automatic rate adjustment calculation
- Old plot reset to available status
- New plot marked as sold

#### 3. Approval Workflow
- **reject_transfer()** - Rejects pending transfer with reason
- Prevents duplicate pending transfers
- Tracks approver and approval date
- Status: 0=pending, 1=approved, 2=rejected, 3=cancelled

#### 4. History & Reporting
- **get_transfer_history()** - Get all transfers for a booking
- **get_pending_transfers()** - Admin view of pending approvals
- Filterable by type and status

## Function Signatures

### Name Transfer
```php
initiate_name_transfer($booking_helper_id, $from_client_id, $to_client_id, $details = [], $created_by = null)
approve_name_transfer($transfer_id, $approved_by = null)
```

### Plot Transfer
```php
initiate_plot_transfer($booking_helper_id, $new_purchase_id, $new_rate = null, $rate_adjustment_reason = null, $details = [], $created_by = null)
approve_plot_transfer($transfer_id, $approved_by = null)
```

### Common
```php
reject_transfer($transfer_id, $rejection_reason = null, $approved_by = null)
get_transfer_history($booking_helper_id, $filters = [])
get_pending_transfers($filters = [])
```

## Usage Examples

### Example 1: Name Transfer
```php
// Initiate transfer
$result = initiate_name_transfer(
    123,           // booking_helper_id
    45,            // from_client_id
    67,            // to_client_id
    [
        'remarks' => 'Family transfer - daughter taking ownership',
        'original_name' => 'Ahmed Khan',
        'new_name' => 'Fatima Khan',
        'relationship' => 'Daughter'
    ],
    $current_user_id
);

// Approve transfer
if ($result['status'] === 200) {
    $approve_result = approve_name_transfer($result['transfer_id'], $admin_user_id);
}
```

### Example 2: Plot Transfer with Rate Adjustment
```php
// Initiate plot transfer
$result = initiate_plot_transfer(
    123,              // booking_helper_id
    456,              // new_purchase_id (new plot)
    6000.00,          // new rate per katha
    'Market rate appreciation',
    [
        'remarks' => 'Client requested plot change due to location preference'
    ],
    $current_user_id
);

// Check rate adjustment
if ($result['status'] === 200) {
    echo "Rate adjustment: ৳" . $result['rate_adjustment_amount'];

    // Admin approves
    $approve_result = approve_plot_transfer($result['transfer_id'], $admin_user_id);
}
```

### Example 3: Reject Transfer
```php
$result = reject_transfer(
    789,
    'Documentation incomplete. Required: property deed copy and ID proof',
    $admin_user_id
);
```

### Example 4: Get Transfer History
```php
// All transfers for a booking
$history = get_transfer_history(123);

// Only approved plot transfers
$history = get_transfer_history(123, [
    'transfer_type' => 'plot_transfer',
    'approval_status' => 1
]);

// Pending transfers (admin dashboard)
$pending = get_pending_transfers(['transfer_type' => 'name_transfer']);
```

## Key Features

### Data Safety
- All operations wrapped in transactions
- Rollback on any failure
- Validates existence of all entities
- Prevents duplicate pending transfers

### Rate Adjustment Calculation
```php
$old_total = $old_rate * $old_katha;
$new_total = $new_rate * $new_katha;
$adjustment = $new_total - $old_total;
```

### Plot Status Management
- Old plot: Reset to available (status=1, file_num=null)
- New plot: Marked as sold (status=2)
- Automatic on approval

### Audit Trail
- created_by, updated_by tracking
- approval_date and approved_by
- rejection_reason for denied requests
- JSON storage of transfer-specific details

## Business Rules Enforced

1. **Validation**: Cannot transfer to same client (name transfer)
2. **Availability Check**: New plot must be available
3. **Single Pending**: Only one pending transfer per booking
4. **Status Lock**: Already processed transfers cannot be reprocessed
5. **Client Matching**: from_client must match current booking owner

## Database Tables Used

### Primary Table
- `crm_transfer_history` - All transfer requests and approvals

### Updated Tables
- `wo_booking_helper` - client_id (name transfer), booking_id + per_katha (plot transfer)
- `wo_booking` - status and file_num (plot availability)

## Return Format

All functions return consistent format:
```php
[
    'status' => 200|400|404|500,
    'message' => 'Human readable message',
    'transfer_id' => 123,  // when applicable
    'rate_adjustment_amount' => 150000.00  // plot transfers only
]
```

## Integration Points

### Required for Next Steps
- XHR endpoints (Phase 3) will call these functions
- UI modals (Phase 4) will trigger via AJAX
- Email notifications can hook into approve/reject functions

### Dependencies
- Requires `crm_transfer_history` table (Step 1.3)
- Uses existing `T_BOOKING_HELPER` and `T_BOOKING` tables
- Uses existing `T_CUSTOMERS` table for validation

## Testing Checklist

- [ ] Name transfer: Initiate → Approve → Verify client_id changed
- [ ] Name transfer: Reject → Verify status=2 and reason stored
- [ ] Plot transfer: Initiate → Check rate_adjustment_amount calculation
- [ ] Plot transfer: Approve → Verify old plot available, new plot sold
- [ ] Duplicate prevention: Try creating second pending transfer (should fail)
- [ ] Invalid client: Try transferring non-existent client (should fail)
- [ ] Get history: Verify filtering by type and status
- [ ] Pending queue: Admin view shows all pending transfers

---

## Ready for Step 2.3?

Once testing is complete, proceed to:

**Step 2.3: Refund Functions**

This will include:
- Calculate refund with deduction (5-25%)
- Create refund schedule with installments
- Track refund payments
- Refund status management
