# Phase 3: XHR Endpoints for Payment Schedule, Transfers & Refunds

## Overview
Phase 3 converts the backend functions from Phase 2 into AJAX endpoints that the frontend UI will call.
These endpoints handle all data processing for payment schedules, transfers, and refunds.

## Current Status
- ✅ Phase 1: Database tables created (crm_payment_schedule, crm_refund_schedule, crm_transfer_history)
- ✅ Phase 2: Backend functions created (3 PHP files with 20+ functions)
- 🚀 Phase 3: XHR Endpoints (THIS PHASE)
- ⏳ Phase 4: UI Modals & Components
- ⏳ Phase 5: Email & Automation

---

## Phase 3 Architecture

### Endpoints File Structure
\`\`\`
xhr/
├── manage_clients.php          (existing - client list, view, create, delete)
├── manage_inventory.php        (existing - booking/plot management)
└── manage_schedule_endpoints.php (NEW - Phase 3 endpoints)
\`\`\`

### Endpoint Categories

#### Category 1: Payment Schedule Endpoints
**File**: `manage_schedule_endpoints.php`

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `get_payment_schedule` | GET/POST | Fetch schedule for a booking |
| `recalculate_schedule` | POST | Recalculate after plot changes |
| `update_payment_status` | POST | Mark installment as paid |
| `send_schedule_email` | POST | Send schedule to client |
| `toggle_auto_email` | POST | Enable/disable auto-sending |

#### Category 2: Transfer Endpoints
**File**: `manage_schedule_endpoints.php`

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `initiate_transfer` | POST | Start name or plot transfer |
| `get_transfer_history` | GET/POST | View all transfers for booking |
| `get_pending_transfers` | GET/POST | Admin dashboard - pending approvals |
| `approve_transfer` | POST | Admin approves transfer |
| `reject_transfer` | POST | Admin rejects with reason |

#### Category 3: Refund Endpoints
**File**: `manage_schedule_endpoints.php`

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `calculate_refund` | GET/POST | Get refund breakdown |
| `create_refund_schedule` | POST | Create refund installments |
| `add_refund_payment` | POST | Record refund payment |
| `get_refund_status` | GET/POST | View refund details |
| `cancel_refund` | POST | Cancel pending refund |

---

## Step 3.1: Payment Schedule Endpoints

### Endpoint: `get_payment_schedule`

**Request**:
\`\`\`
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_payment_schedule
{
  "booking_helper_id": 123
}
\`\`\`

**Response**:
\`\`\`json
{
  "status": 200,
  "booking_helper": { id, client_id, per_katha, booking_money, down_payment },
  "payment_schedule": [
    { id, installment_no, due_date, amount, status, payment_date, remarks }
  ],
  "summary": { total_due, total_paid, remaining, last_paid_date }
}
\`\`\`

**Next**: Implement in `manage_schedule_endpoints.php`

---

## Step 3.2: Transfer Endpoints

### Endpoint: `initiate_transfer`

**Request**:
\`\`\`
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=initiate_transfer
{
  "booking_helper_id": 123,
  "transfer_type": "name_transfer|plot_transfer",
  "to_client_id": 456,     // for name transfer
  "new_purchase_id": 789,  // for plot transfer
  "new_rate": 6000,        // for plot transfer (optional)
  "reason": "Family change"
}
\`\`\`

**Response**:
\`\`\`json
{
  "status": 200,
  "transfer_id": 999,
  "message": "Transfer initiated - pending admin approval",
  "transfer_details": { ...}
}
\`\`\`

**Next**: Implement in `manage_schedule_endpoints.php`

---

## Step 3.3: Refund Endpoints

### Endpoint: `create_refund_schedule`

**Request**:
\`\`\`
POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=create_refund_schedule
{
  "booking_helper_id": 123,
  "deduction_percentage": 15,
  "num_installments": 4,
  "start_date": "2025-02-01"
}
\`\`\`

**Response**:
\`\`\`json
{
  "status": 200,
  "refund_id": 456,
  "message": "Refund schedule created",
  "schedule": [
    { installment_no, due_date, amount, status }
  ],
  "total_refundable": 1000000
}
\`\`\`

**Next**: Implement in `manage_schedule_endpoints.php`

---

## Implementation Steps

### Step 3.1: Create New XHR Endpoint File
Create `xhr/manage_schedule_endpoints.php` with:
1. Security checks (permissions, user validation)
2. Error handling wrappers
3. All 15+ endpoint implementations

### Step 3.2: Payment Schedule Endpoints (5 endpoints)
- `get_payment_schedule` - fetch and display
- `recalculate_schedule` - handle plot/rate changes
- `update_payment_status` - mark payments
- `send_schedule_email` - email notification
- `toggle_auto_email` - auto-send configuration

### Step 3.3: Transfer Endpoints (5 endpoints)
- `initiate_transfer` - create transfer request
- `get_transfer_history` - view history
- `get_pending_transfers` - admin queue
- `approve_transfer` - admin action
- `reject_transfer` - admin action

### Step 3.4: Refund Endpoints (5 endpoints)
- `calculate_refund` - display breakdown
- `create_refund_schedule` - create installments
- `add_refund_payment` - record payment
- `get_refund_status` - view status
- `cancel_refund` - cancel request

---

## Next Phase: UI Components (Phase 4)

Once XHR endpoints are complete, we'll build:
- **Payment Schedule Modal** - View & update schedules
- **Transfer Request Modal** - Name/plot transfers
- **Refund Modal** - Calculate & track refunds
- **Enhanced view_client.phtml** - Integration of all modals

---

## Ready?

Proceed to **Step 3.1: Create Payment Schedule Endpoints**
