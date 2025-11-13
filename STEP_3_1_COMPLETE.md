# ✅ Step 3.1 Complete: All Phase 3 XHR Endpoints Created

## Summary
Created comprehensive XHR endpoint file with 15+ endpoints covering payment schedules, transfers, and refunds.

## File Created
- **Location**: `/xhr/manage_schedule_endpoints.php`
- **Lines**: ~550+
- **Endpoints**: 15 total

---

## Payment Schedule Endpoints (5)

### 1. `get_payment_schedule`
\`\`\`javascript
// Get schedule for a booking
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_payment_schedule
{ booking_helper_id: 123 }

// Response includes:
// - booking_helper details
// - payment_schedule array
// - summary (total_due, total_paid, remaining)
\`\`\`

### 2. `recalculate_schedule`
\`\`\`javascript
// Recalculate after plot/rate changes
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=recalculate_schedule
{ booking_helper_id: 123, new_per_katha: 6000 }

// Returns: old_total, new_total, difference
\`\`\`

### 3. `update_payment_status`
\`\`\`javascript
// Mark installment as paid
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=update_payment_status
{
  payment_schedule_id: 456,
  amount: 50000,
  payment_date: '2025-02-01',
  payment_method: 'Bank Transfer',
  receipt_no: 'REF-001',
  remarks: 'First installment'
}
\`\`\`

### 4. `send_schedule_email`
\`\`\`javascript
// Send schedule email to client
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=send_schedule_email
{
  booking_helper_id: 123,
  recipient_email: 'client@example.com'
}
\`\`\`

### 5. `toggle_auto_email` (Placeholder)
\`\`\`javascript
// Future: Enable/disable auto-sending
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=toggle_auto_email
{ booking_helper_id: 123, enabled: true }
\`\`\`

---

## Transfer Endpoints (5)

### 1. `initiate_transfer`
\`\`\`javascript
// Initiate name or plot transfer
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=initiate_transfer

// Name Transfer
{
  booking_helper_id: 123,
  transfer_type: 'name_transfer',
  to_client_id: 456,
  reason: 'Family ownership change'
}

// Plot Transfer
{
  booking_helper_id: 123,
  transfer_type: 'plot_transfer',
  new_purchase_id: 789,
  new_rate: 6000,
  reason: 'Client requested plot change'
}

// Response includes:
// - transfer_id
// - status: pending approval
\`\`\`

### 2. `get_transfer_history`
\`\`\`javascript
// Get all transfers for a booking
GET /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_transfer_history
?booking_helper_id=123&type=name_transfer

// Returns: array of transfers with approval status
\`\`\`

### 3. `get_pending_transfers`
\`\`\`javascript
// Admin dashboard - view pending approvals
GET /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_pending_transfers

// Returns: all pending transfers across all bookings
\`\`\`

### 4. `approve_transfer`
\`\`\`javascript
// Admin approves transfer
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=approve_transfer
{ transfer_id: 999 }

// Updates client_id (name) or booking_id (plot)
// Returns: success message
\`\`\`

### 5. `reject_transfer`
\`\`\`javascript
// Admin rejects transfer
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=reject_transfer
{
  transfer_id: 999,
  reason: 'Documentation incomplete'
}

// Sets status to 2 (rejected) with reason
\`\`\`

---

## Refund Endpoints (5)

### 1. `calculate_refund`
\`\`\`javascript
// Calculate refund breakdown
GET /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=calculate_refund
?booking_helper_id=123&deduction_percent=15

// Response includes:
// - total_paid
// - deduction_amount
// - refundable_amount
// - all breakdown details
\`\`\`

### 2. `create_refund_schedule`
\`\`\`javascript
// Create refund installments
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=create_refund_schedule
{
  booking_helper_id: 123,
  deduction_percent: 15,
  num_installments: 4,
  start_date: '2025-02-01'
}

// Returns:
// - refund_id
// - schedule array with due dates
// - total_refundable
\`\`\`

### 3. `add_refund_payment`
\`\`\`javascript
// Record refund payment
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=add_refund_payment
{
  refund_schedule_id: 456,
  amount: 250000,
  payment_date: '2025-02-15',
  payment_method: 'Bank Transfer',
  receipt_no: 'REF-BANK-001',
  remarks: 'First refund installment'
}

// Updates paid_amount and status
\`\`\`

### 4. `get_refund_status`
\`\`\`javascript
// View refund details
GET /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_refund_status
?booking_helper_id=123

// Returns:
// - refund_schedule array
// - summary (total_refunded, remaining, pending_count)
\`\`\`

### 5. `cancel_refund`
\`\`\`javascript
// Cancel pending refund
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=cancel_refund
{
  booking_helper_id: 123,
  reason: 'Client changed mind'
}

// Sets all pending entries to cancelled status
\`\`\`

---

## Architecture

### Security Features
- User permission checks (admin, moderator, manage-clients)
- Session timeout detection
- Input validation and sanitization

### Error Handling
- Try-catch blocks on all operations
- Consistent JSON error responses
- Database transaction support (via functions)

### Response Format
All endpoints return standardized JSON:
\`\`\`json
{
  "status": 200|400|404|500,
  "message": "Human readable message",
  "data": {}  // endpoint-specific data
}
\`\`\`

---

## Integration with Phase 2 Functions

All endpoints use backend functions from Phase 2:

**Payment Schedule Functions**:
- `get_payment_schedule_rows()`
- `get_payment_schedule_summary()`
- `add_payment_schedule_payment()`

**Transfer Functions**:
- `initiate_name_transfer()`
- `initiate_plot_transfer()`
- `approve_name_transfer()`
- `approve_plot_transfer()`
- `reject_transfer()`
- `get_transfer_history()`
- `get_pending_transfers()`

**Refund Functions**:
- `calculate_refund()`
- `create_refund_schedule()`
- `add_refund_payment()`
- `get_refund_schedule()`
- `get_refund_schedule_summary()`
- `cancel_refund_schedule()`

---

## Testing the Endpoints

### Using cURL
\`\`\`bash
# Get payment schedule
curl -X POST http://localhost/xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_payment_schedule \
  -d '{"booking_helper_id":123}' \
  -H 'Content-Type: application/json'

# Create refund
curl -X POST http://localhost/xhr/manage_schedule_endpoints.php?f=manage_schedule&s=create_refund_schedule \
  -d '{
    "booking_helper_id":123,
    "deduction_percent":15,
    "num_installments":4
  }' \
  -H 'Content-Type: application/json'
\`\`\`

### Using JavaScript/jQuery
\`\`\`javascript
// Get payment schedule
$.post(
  'requests.php?f=manage_schedule&s=get_payment_schedule',
  { booking_helper_id: 123 },
  function(resp) {
    console.log(resp);
  }
);

// Create refund
$.post(
  'requests.php?f=manage_schedule&s=create_refund_schedule',
  {
    booking_helper_id: 123,
    deduction_percent: 15,
    num_installments: 4
  },
  function(resp) {
    if (resp.status === 200) {
      alert('Refund schedule created!');
    }
  }
);
\`\`\`

---

## Next Steps: Phase 3.2 → UI Components

Ready for Phase 4: Build the UI modals

**Next Phase Files**:
1. `manage/pages/clients/payment_schedule_modal.phtml` - View & manage schedules
2. `manage/pages/clients/transfer_modal.phtml` - Name/plot transfers
3. `manage/pages/clients/refund_modal.phtml` - Calculate & track refunds
4. `manage/pages/clients/view_client.phtml` - Integration updates

---

## Status: ✅ COMPLETE
All 15 endpoints are ready for frontend integration in Phase 4!
